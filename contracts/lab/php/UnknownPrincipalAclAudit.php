<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F1-114. Read-only PostgreSQL ACL catalog scan that complements F113's
 * effective-privilege checks for known roles by finding grants to *unknown*
 * SQL principals, including PUBLIC and column ACLs.
 *
 * Strictly a disposable CI auditor. This is not DBA approval or an
 * operational inventory of a real business database.
 */
final class UnknownPrincipalAclAudit
{
    private const SCHEMA='perf.security.unknown-sql-principals.ci.v1';
    private const BASE=[
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
    private const VIEWS=[
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ];

    private static function denied(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
            'audit_scope'=>'ephemeral_postgresql_only',
            'audit_complete'=>false,
            'audited_objects'=>0,
            'unexpected_acl_edges'=>[],
            'release_allowed'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
        ];
    }

    public function inspect(PDO $db):array
    {
        if(PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F114_UNKNOWN_ACL_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return self::denied('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'){
                return self::denied('invalid_driver');
            }
            $identity=$db->query(
                'SELECT current_database() AS dbname,session_user::text AS login'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                || $identity['dbname']!=='perf_test_campaign_registry'
                || $identity['login']!=='postgres'){
                return self::denied('untrusted_acl_auditor');
            }
            $objects=[...self::BASE,...self::VIEWS];
            $params=implode(',',array_fill(0,count($objects),'?'));
            // ACLs are catalog data, NOT requests to execute SQL operations.
            // Excluding owner entries avoids treating PostgreSQL implicit
            // ownership rights as grants to external actors.
            $tableAcl=$db->prepare(
                'SELECT c.relname AS object, '
                ."CASE WHEN acl.grantee=0 THEN 'PUBLIC' "
                .'ELSE pg_catalog.pg_get_userbyid(acl.grantee) END AS principal, '
                'acl.privilege_type AS privilege, '
                "'object' AS scope, NULL::text AS column_name "
                'FROM pg_catalog.pg_class c '
                'JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace '
                'CROSS JOIN LATERAL pg_catalog.aclexplode('
                "COALESCE(c.relacl,pg_catalog.acldefault('r',c.relowner))) acl "
                'WHERE n.nspname=? AND c.relname IN ('.$params.') '
                'AND acl.grantee<>c.relowner'
            );
            $tableAcl->execute(['public',...$objects]);
            $edges=$tableAcl->fetchAll(PDO::FETCH_ASSOC);
            $columnAcl=$db->prepare(
                'SELECT c.relname AS object, '
                ."CASE WHEN acl.grantee=0 THEN 'PUBLIC' "
                .'ELSE pg_catalog.pg_get_userbyid(acl.grantee) END AS principal, '
                'acl.privilege_type AS privilege, '
                "'column' AS scope, a.attname AS column_name "
                'FROM pg_catalog.pg_attribute a '
                'JOIN pg_catalog.pg_class c ON c.oid=a.attrelid '
                'JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace '
                'CROSS JOIN LATERAL pg_catalog.aclexplode(a.attacl) acl '
                'WHERE n.nspname=? AND c.relname IN ('.$params.') '
                'AND a.attnum>0 AND NOT a.attisdropped '
                'AND acl.grantee<>c.relowner'
            );
            $columnAcl->execute(['public',...$objects]);
            $edges=[...$edges,...$columnAcl->fetchAll(PDO::FETCH_ASSOC)];
            $exists=$db->prepare(
                'SELECT count(*) FROM pg_catalog.pg_class c '
                'JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace '
                'WHERE n.nspname=? AND c.relname IN ('.$params.')'
            );
            $exists->execute(['public',...$objects]);
            if((int)$exists->fetchColumn()!==count($objects)){
                return self::denied('incomplete_sql_object_inventory');
            }

            $unexpected=[];
            foreach($edges as $e){
                $object=$e['object'];
                $principal=$e['principal'];
                $privilege=$e['privilege'];
                $scope=$e['scope'];
                $allowed=false;

                if($scope==='object') {
                    if(in_array($object,self::VIEWS,true)) {
                        $allowed=(
                            in_array($principal,['perf_f91_reader_a','perf_f91_reader_b'],true)
                            && $privilege==='SELECT'
                        );
                    } else {
                        // F104 is a dedicated NOLOGIN/NOBYPASSRLS definer
                        // that may INSERT synthetic terminal-review journals.
                        $allowed=$object==='performance_intent_review_journal'
                            && $principal==='perf_f104_journal_writer'
                            && $privilege==='INSERT';
                    }
                }
                if(!$allowed){
                    $unexpected[]=[
                        'principal'=>$principal,
                        'object'=>'public.'.$object,
                        'privilege'=>$privilege,
                        'scope'=>$scope,
                        'column'=>$e['column_name'],
                    ];
                }
            }
            usort($unexpected,static fn(array $a,array $b):int=>
                strcmp(
                    $a['object'].':'.$a['principal'].':'.$a['scope'].':'.$a['privilege'],
                    $b['object'].':'.$b['principal'].':'.$b['scope'].':'.$b['privilege']
                )
            );
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>$unexpected===[]?'approved_acl_edges_in_ci':'unknown_acl_grant_detected',
                'audit_scope'=>'ephemeral_postgresql_only',
                'audit_complete'=>true,
                'audited_objects'=>count($objects),
                'unexpected_acl_edges'=>$unexpected,
                'release_allowed'=>false,
                'media_execution_allowed'=>false,
                'billing_allowed'=>false,
            ];
        } catch(Throwable) {
            return self::denied('catalog_read_unavailable');
        }
    }

    public function assertKnownAclOnly(PDO $db):array
    {
        $result=$this->inspect($db);
        if($result['status']!=='approved_acl_edges_in_ci'
            || $result['audit_complete']!==true
            || $result['unexpected_acl_edges']!==[]) {
            throw new \RuntimeException(
                'F114 refuses unsafe or incomplete ACL catalog, including unknown SQL principals'
            );
        }
        return $result;
    }
}
