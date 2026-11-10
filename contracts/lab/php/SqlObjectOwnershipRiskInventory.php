<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F1-116: inspect ownership and hardening of actual PostgreSQL CI views
 * and SECURITY DEFINER functions. No unsafe "everything secure" result:
 * superuser-owned views and tenant mapping function remain OPEN review risks.
 */
final class SqlObjectOwnershipRiskInventory
{
    public const SCHEMA='perf.security.sql-definer-view-ownership.ci.v1';
    private const VIEWS=[
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ];

    private static function failClosed(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'audit_scope'=>'disposable_postgresql_ci_only',
            'audit_complete'=>false,
            'views'=>[],
            'security_definers'=>[],
            'unreviewed_privileged_owners'=>[],
            'hardening_regressions'=>[],
            'release_allowed'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'independent_appsec_dba_review_approved'=>false,
        ];
    }

    public function inspect(PDO $db):array
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F116_DEFINER_VIEW_RISK_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return self::failClosed('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'){
                return self::failClosed('unsupported_driver');
            }
            $identity=$db->query(
                'SELECT current_database() AS dbname,session_user::text AS login'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                ||$identity['dbname']!=='perf_test_campaign_registry'
                ||$identity['login']!=='postgres'){
                return self::failClosed('untrusted_database_auditor');
            }
            $active=static fn(mixed $v):bool=>
                in_array($v,[true,'t','1',1],true);
            $viewResults=[];
            $privileged=[];
            $regressions=[];
            $prepared=$db->prepare(
                'SELECT c.relname,c.relkind,c.reloptions, '
                .'o.rolname AS owner_name,o.rolsuper AS owner_super, '
                .'o.rolbypassrls AS owner_bypassrls '
                .'FROM pg_catalog.pg_class c '
                .'JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace '
                .'JOIN pg_catalog.pg_roles o ON o.oid=c.relowner '
                'WHERE n.nspname=? AND c.relname=?'
            );
            foreach(self::VIEWS as $name){
                $prepared->execute(['public',$name]);
                $v=$prepared->fetch(PDO::FETCH_ASSOC);
                if(!is_array($v)){
                    return self::failClosed('missing_protected_view');
                }
                $isBarrier=is_string($v['reloptions'])
                    &&str_contains($v['reloptions'],'security_barrier=true');
                $isPrivileged=$active($v['owner_super'])
                    ||$active($v['owner_bypassrls']);
                $viewResults[]=[
                    'view'=>$name,
                    'owner'=>$v['owner_name'],
                    'owner_has_elevated_rights'=>$isPrivileged,
                    'security_barrier'=>$isBarrier,
                    'view_kind'=>$v['relkind'],
                ];
                if($isPrivileged){
                    $privileged[]='view:public.'.$name;
                }
                if($v['relkind']!=='v'||!$isBarrier){
                    $regressions[]='view_hardening:public.'.$name;
                }
            }
            $functions=[
                ['name'=>'perf_f91_tenant_for_login',
                    'expected_owner'=>null,'expected_definer'=>true],
                ['name'=>'perf_f27_capture_review_transition',
                    'expected_owner'=>'perf_f104_journal_writer','expected_definer'=>true],
            ];
            $definers=[];
            $functionStmt=$db->prepare(
                'SELECT p.proname,p.prosecdef,p.proconfig, '
                .'o.rolname AS owner_name,o.rolsuper AS owner_super, '
                .'o.rolbypassrls AS owner_bypassrls '
                .'FROM pg_catalog.pg_proc p '
                .'JOIN pg_catalog.pg_namespace n ON n.oid=p.pronamespace '
                .'JOIN pg_catalog.pg_roles o ON o.oid=p.proowner '
                'WHERE n.nspname=? AND p.proname=?'
            );
            foreach($functions as $config){
                $functionStmt->execute(['public',$config['name']]);
                $f=$functionStmt->fetch(PDO::FETCH_ASSOC);
                if(!is_array($f)){
                    return self::failClosed('missing_security_definer');
                }
                $searchPath=is_string($f['proconfig'])
                    &&str_contains($f['proconfig'],'search_path=pg_catalog')
                    &&!str_contains($f['proconfig'],'public');
                $isPrivileged=$active($f['owner_super'])
                    ||$active($f['owner_bypassrls']);
                $isDefiner=$active($f['prosecdef']);
                $definers[]=[
                    'function'=>$config['name'],
                    'owner'=>$f['owner_name'],
                    'owner_has_elevated_rights'=>$isPrivileged,
                    'security_definer'=>$isDefiner,
                    'search_path_catalog_only'=>$searchPath,
                ];
                if($isPrivileged){
                    $privileged[]='function:public.'.$config['name'];
                }
                if(!$isDefiner||!$searchPath
                    ||($config['expected_owner']!==null
                        &&$f['owner_name']!==$config['expected_owner'])) {
                    $regressions[]='definer_hardening:public.'.$config['name'];
                }
            }
            sort($privileged);
            sort($regressions);
            // This is deliberately NOT a successful release gate: privileged
            // owners have not been reviewed by an independent AppSec/DBA.
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>$regressions!==[]
                    ? 'sql_hardening_regression'
                    : 'independent_owner_review_required',
                'audit_scope'=>'disposable_postgresql_ci_only',
                'audit_complete'=>true,
                'views'=>$viewResults,
                'security_definers'=>$definers,
                'unreviewed_privileged_owners'=>$privileged,
                'hardening_regressions'=>$regressions,
                'release_allowed'=>false,
                'media_execution_allowed'=>false,
                'billing_allowed'=>false,
                'independent_appsec_dba_review_approved'=>false,
            ];
        } catch(Throwable){
            return self::failClosed('catalog_scan_unavailable');
        }
    }

    public function assertBaselineHardening(PDO $db):array
    {
        $report=$this->inspect($db);
        if($report['status']!=='independent_owner_review_required'
            ||$report['audit_complete']!==true
            ||$report['hardening_regressions']!==[]
            ||$report['unreviewed_privileged_owners']===[]){
            throw new \RuntimeException(
                'F116 denies unsafe SQL object hardening or incomplete risk register'
            );
        }
        return $report;
    }
}
