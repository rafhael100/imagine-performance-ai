<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F1-113: read-only PostgreSQL catalog audit of the F112 CI privilege
 * cutover. A successful check is evidence of the *disposable lab* only.
 *
 * Even a zero-drift result must never authorize production or advertising.
 */
final class LegacySqlGrantDriftAudit
{
    private const SCHEMA='perf.security.legacy-sql-grants.ci.v1';

    private const LEGACY_ROLES=[
        'perf_campaign_test_user',
        'perf_f23_writer',
        'perf_f24_reader',
        'perf_f27_journal_reader',
        'perf_f28_app_reader',
        'perf_f88_registrar',
        'perf_f89_link_registrar',
        'perf_f90_projection_reader',
    ];

    private const CREDENTIAL_ROLES=[
        'perf_f91_reader_a',
        'perf_f91_reader_b',
    ];

    private const BASE_TABLES=[
        'performance_tenants',
        'portal_service_links',
        'operational_memberships',
        'service_eligibility_snapshots',
        'audit_events',
        'idempotency_receipts',
        'performance_managed_campaigns',
        'performance_campaign_intents',
        'performance_intent_review_journal',
        'performance_ad_account_candidates',
        'performance_campaign_account_links',
        'performance_db_principal_tenant_bindings',
        'performance_demo_budget_scenarios',
    ];

    private const READ_VIEWS=[
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ];

    private const TABLE_PRIVS=[
        'SELECT','INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER',
    ];

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'audit_scope'=>'disposable_ci_only',
            'audit_complete'=>false,
            'unsafe_privileges'=>[],
            'audited_base_tables'=>0,
            'audited_read_views'=>0,
            'release_allowed'=>false,
            'provider_operations_allowed'=>false,
            'billing_allowed'=>false,
        ];
    }

    public function inspect(PDO $db):array
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F113_PRIVILEGE_AUDIT_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }

        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
                return self::denied('invalid_driver');
            }
            $identity=$db->query(
                'SELECT current_database() AS dbname,session_user::text AS db_login'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                || $identity['dbname']!=='perf_test_campaign_registry'
                || $identity['db_login']!=='postgres') {
                return self::denied('untrusted_catalog_auditor');
            }

            // These SQL identifiers are intentionally static, not request data.
            $roles=[...self::LEGACY_ROLES,...self::CREDENTIAL_ROLES];
            $lookup=$db->prepare(
                'SELECT rolsuper,rolcreaterole,rolcreatedb,rolbypassrls '
                .'FROM pg_catalog.pg_roles WHERE rolname=?'
            );
            foreach($roles as $role) {
                $lookup->execute([$role]);
                $attrs=$lookup->fetch(PDO::FETCH_ASSOC);
                if(!is_array($attrs))return self::denied('missing_audit_principal');
                foreach(['rolsuper','rolcreaterole','rolcreatedb','rolbypassrls'] as $flag) {
                    if(in_array($attrs[$flag],[true,'t','1',1],true)){
                        return self::denied('unsafe_principal_attributes');
                    }
                }
            }
            $violations=[];
            $has=$db->prepare('SELECT has_table_privilege(?,?,?)');
            $columnGrant=$db->prepare('SELECT has_column_privilege(?,?,?,?)');
            $columnNames=$db->prepare(
                'SELECT attname FROM pg_catalog.pg_attribute '
                .'WHERE attrelid=?::regclass AND attnum>0 AND NOT attisdropped'
            );
            $can=static function(string $role,string $table,string $priv)use($has):bool{
                $has->execute([$role,'public.'.$table,$priv]);
                return in_array($has->fetchColumn(),[true,'t','1',1],true);
            };
            foreach(self::BASE_TABLES as $table){
                $columnNames->execute(['public.'.$table]);
                $columns=$columnNames->fetchAll(PDO::FETCH_COLUMN);
                if(!is_array($columns)||$columns===[]){
                    return self::denied('missing_base_table_or_columns');
                }
                foreach($roles as $role){
                    foreach(self::TABLE_PRIVS as $priv){
                        if($can($role,$table,$priv)){
                            $violations[]=[
                                'principal'=>$role,'object'=>'public.'.$table,
                                'privilege'=>$priv,'scope'=>'table'
                            ];
                        }
                    }
                    foreach($columns as $column){
                        foreach(['SELECT','UPDATE','REFERENCES'] as $priv){
                            $columnGrant->execute([
                                $role,'public.'.$table,$column,$priv
                            ]);
                            if(in_array($columnGrant->fetchColumn(),[true,'t','1',1],true)
                                // A duplicate table privilege need not be
                                // repeated for every column.
                                && !$can($role,$table,$priv)) {
                                $violations[]=[
                                    'principal'=>$role,'object'=>'public.'.$table,
                                    'column'=>$column,
                                    'privilege'=>$priv,'scope'=>'column'
                                ];
                            }
                        }
                    }
                }
            }

            // Protected PostgreSQL VIEWs must be SELECT-only for the
            // authenticated lab logins and inaccessible to all legacy roles.
            foreach(self::READ_VIEWS as $view){
                foreach(self::CREDENTIAL_ROLES as $role){
                    if(!$can($role,$view,'SELECT')){
                        $violations[]=[
                            'principal'=>$role,'object'=>'public.'.$view,
                            'privilege'=>'SELECT','scope'=>'view_required_missing'
                        ];
                    }
                    foreach(array_slice(self::TABLE_PRIVS,1) as $priv){
                        if($can($role,$view,$priv)){
                            $violations[]=[
                                'principal'=>$role,'object'=>'public.'.$view,
                                'privilege'=>$priv,'scope'=>'view_write_or_admin'
                            ];
                        }
                    }
                }
                foreach(self::LEGACY_ROLES as $role){
                    foreach(self::TABLE_PRIVS as $priv){
                        if($can($role,$view,$priv)){
                            $violations[]=[
                                'principal'=>$role,'object'=>'public.'.$view,
                                'privilege'=>$priv,'scope'=>'legacy_view_exposure'
                            ];
                        }
                    }
                }
            }
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>$violations===[]?'closed_in_disposable_ci':'privilege_drift_detected',
                'audit_scope'=>'disposable_ci_only',
                'audit_complete'=>true,
                'unsafe_privileges'=>$violations,
                'audited_base_tables'=>count(self::BASE_TABLES),
                'audited_read_views'=>count(self::READ_VIEWS),
                'release_allowed'=>false,
                'provider_operations_allowed'=>false,
                'billing_allowed'=>false,
            ];
        } catch (Throwable) {
            return self::denied('catalog_audit_unavailable');
        }
    }

    public function assertClosedInLab(PDO $db):array
    {
        $result=$this->inspect($db);
        if($result['status']!=='closed_in_disposable_ci'
            || $result['audit_complete']!==true
            || $result['unsafe_privileges']!==[]) {
            throw new \RuntimeException(
                'F113 denies incomplete or drifting legacy SQL privilege inventory'
            );
        }
        return $result;
    }
}
