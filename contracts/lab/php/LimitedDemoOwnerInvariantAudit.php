<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F1-118: CI-only guard for the post-F117 least-privilege SQL owner model.
 * Inventories role attributes/membership, ACLs, security-barrier VIEWs,
 * SECURITY DEFINER, and PERMISSIVE+RESTRICTIVE session_user tenant RLS.
 *
 * This is NOT external AppSec/DBA approval or a deployment admission.
 */
final class LimitedDemoOwnerInvariantAudit
{
    public const SCHEMA = 'perf.security.limited-sql-owner.ci.v1';
    private const VIEW_OWNER='perf_f117_demo_view_owner';
    private const BINDING_OWNER='perf_f117_binding_definer_owner';
    private const BINDINGS='performance_db_principal_tenant_bindings';
    private const SOURCES=[
        'performance_ad_account_candidates',
        'performance_managed_campaigns',
        'performance_campaign_account_links',
        'performance_campaign_intents',
        'performance_intent_review_journal',
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
            'audit_scope'=>'disposable_postgresql16_only',
            'audit_complete'=>false,
            'unsafe_findings'=>[],
            'source_table_count'=>0,
            'read_view_count'=>0,
            'release_allowed'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'independent_review_complete'=>false,
        ];
    }

    public function inspect(PDO $db):array
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F118_OWNER_INVARIANT_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return self::denied('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'){
                return self::denied('invalid_db_driver');
            }
            $id=$db->query(
                'SELECT session_user::text AS login,current_database() AS database,'
                .'current_setting('."'server_version_num'".')::integer AS version'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($id)||$id['login']!=='postgres'
                ||$id['database']!=='perf_test_campaign_registry'
                ||(int)$id['version']<160000||(int)$id['version']>=170000){
                return self::denied('untrusted_ci_auditor');
            }
            $bad=[];
            $isTrue=static fn(mixed $v):bool=>in_array($v,[true,'t','1',1],true);
            $roles=[self::VIEW_OWNER,self::BINDING_OWNER];
            $roleQuery=$db->prepare(
                'SELECT oid::text AS id,rolcanlogin,rolsuper,rolcreatedb,'
                .'rolcreaterole,rolinherit,rolbypassrls,rolreplication '
                .'FROM pg_catalog.pg_roles WHERE rolname=?'
            );
            $ids=[];
            foreach($roles as $role){
                $roleQuery->execute([$role]);
                $r=$roleQuery->fetch(PDO::FETCH_ASSOC);
                if(!is_array($r))return self::denied('dedicated_owner_missing');
                $ids[$role]=$r['id'];
                foreach([
                    'rolcanlogin','rolsuper','rolcreatedb','rolcreaterole',
                    'rolinherit','rolbypassrls','rolreplication'
                ] as $field){
                    if($isTrue($r[$field])){
                        $bad[]='role_attribute:'.$role.':'.$field;
                    }
                }
                $membership=$db->prepare(
                    'SELECT count(*) FROM pg_catalog.pg_auth_members '
                    .'WHERE roleid=?::regrole OR member=?::regrole'
                );
                $membership->execute([$role,$role]);
                if((int)$membership->fetchColumn()>0){
                    $bad[]='membership:'.$role;
                }
            }
            $has=$db->prepare('SELECT has_table_privilege(?,?,?)');
            $can=static function(string $role,string $object,string $priv)use($has,$isTrue):bool{
                $has->execute([$role,'public.'.$object,$priv]);
                return $isTrue($has->fetchColumn());
            };
            $all=[...self::SOURCES,self::BINDINGS];
            foreach($all as $table){
                foreach($roles as $role){
                    $expected=$role===self::VIEW_OWNER
                        ?$table!==self::BINDINGS
                        :$table===self::BINDINGS;
                    if($can($role,$table,'SELECT')!==$expected){
                        $bad[]='select_grant:'.$role.':'.$table;
                    }
                    foreach(['INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'] as $priv){
                        if($can($role,$table,$priv)){
                            $bad[]='nonread_grant:'.$role.':'.$table.':'.$priv;
                        }
                    }
                }
            }

            $fn=$db->query(
                "SELECT pg_catalog.pg_get_userbyid(proowner) AS owner,"
                ."prosecdef,proconfig FROM pg_catalog.pg_proc "
                ."WHERE oid='public.perf_f91_tenant_for_login()'::regprocedure"
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($fn))return self::denied('F91_function_missing');
            if($fn['owner']!==self::BINDING_OWNER||!$isTrue($fn['prosecdef'])
                ||!is_string($fn['proconfig'])
                ||!str_contains($fn['proconfig'],'search_path=pg_catalog')){
                $bad[]='F91_definer_owner_or_path';
            }
            $functionPrivilege=$db->prepare('SELECT has_function_privilege(?,?,?)');
            $functionPrivilege->execute([
                self::VIEW_OWNER,'public.perf_f91_tenant_for_login()','EXECUTE'
            ]);
            if(!$isTrue($functionPrivilege->fetchColumn())){
                $bad[]='F91_execute_missing_for_view_owner';
            }
            $functionPrivilege->execute([
                self::BINDING_OWNER,'public.perf_f27_capture_review_transition()','EXECUTE'
            ]);
            if($isTrue($functionPrivilege->fetchColumn())){
                $bad[]='F91_owner_unexpected_F27_execute';
            }

            $viewQuery=$db->prepare(
                'SELECT pg_catalog.pg_get_userbyid(relowner) AS owner,'
                .'relkind,reloptions FROM pg_catalog.pg_class '
                'WHERE oid=?::regclass'
            );
            foreach(self::VIEWS as $view){
                $viewQuery->execute(['public.'.$view]);
                $v=$viewQuery->fetch(PDO::FETCH_ASSOC);
                if(!is_array($v))return self::denied('protected_view_missing');
                if($v['owner']!==self::VIEW_OWNER||$v['relkind']!=='v'
                    ||!is_string($v['reloptions'])
                    ||!str_contains($v['reloptions'],'security_barrier=true')){
                    $bad[]='view_owner_or_barrier:'.$view;
                }
                if($can(self::BINDING_OWNER,$view,'SELECT')){
                    $bad[]='F91_owner_cannot_read_view:'.$view;
                }
            }

            $policy=$db->prepare(
                'SELECT polname,polpermissive,polcmd,polroles::text AS roles,'
                .'pg_catalog.pg_get_expr(polqual,polrelid) AS predicate '
                .'FROM pg_catalog.pg_policy WHERE polrelid=?::regclass '
                ."AND polname IN ('perf_f117_owner_login_select','perf_f117_owner_login_fence')"
            );
            foreach(self::SOURCES as $table){
                $policy->execute(['public.'.$table]);
                $rows=$policy->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows)!==2){
                    $bad[]='missing_owner_rls:'.$table;
                    continue;
                }
                $seen=[];
                foreach($rows as $p){
                    $name=$p['polname'];
                    $seen[$name]=true;
                    $permissive=$name==='perf_f117_owner_login_select';
                    $expression=(string)$p['predicate'];
                    if($isTrue($p['polpermissive'])!==$permissive
                        ||$p['polcmd']!=='r'
                        ||$p['roles']!=='{'.$ids[self::VIEW_OWNER].'}'
                        ||!str_contains($expression,'perf_f91_tenant_for_login')
                        ||!str_contains($expression,'tenant_id')
                        ||preg_match('/\bOR\b|\bTRUE\b|current_setting/i',$expression)){
                        $bad[]='owner_rls_policy:'.$table.':'.$name;
                    }
                }
                if(!isset($seen['perf_f117_owner_login_select'])
                    ||!isset($seen['perf_f117_owner_login_fence'])){
                    $bad[]='wrong_owner_rls_pair:'.$table;
                }
            }
            sort($bad);
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>$bad===[]?'limited_ci_owners_verified':'sql_owner_security_regression',
                'audit_scope'=>'disposable_postgresql16_only',
                'audit_complete'=>true,
                'unsafe_findings'=>$bad,
                'source_table_count'=>count(self::SOURCES),
                'read_view_count'=>count(self::VIEWS),
                'release_allowed'=>false,
                'media_execution_allowed'=>false,
                'billing_allowed'=>false,
                'independent_review_complete'=>false,
            ];
        }catch(Throwable){
            return self::denied('catalog_query_failed');
        }
    }

    public function assertLimitedOwnerBaseline(PDO $db):array
    {
        $report=$this->inspect($db);
        if($report['status']!=='limited_ci_owners_verified'
            ||$report['audit_complete']!==true
            ||$report['unsafe_findings']!==[]){
            throw new \RuntimeException('F118 rejects SQL owner drift and incomplete audit');
        }
        return $report;
    }
}
