<?php
declare(strict_types=1);

/**
 * F116: real PostgreSQL16 ownership and SECURITY DEFINER risk evidence.
 * Fail closed on regressions, but NEVER conclude production AppSec approval.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F116_DEFINER_VIEW_RISK_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F116 only runs in disposable PostgreSQL16 CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/SqlObjectOwnershipRiskInventory.php';
use App\Performance\Security\SqlObjectOwnershipRiskInventory;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$reader=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$auditor=new SqlObjectOwnershipRiskInventory();
$n=0;
$check=static function(bool $condition,string $label)use(&$n):void{
    if(!$condition)throw new RuntimeException('FAIL F116 '.$label);
    ++$n;
};
$has=static function(array $report,string $needle,string $bucket):bool{
    return in_array($needle,$report[$bucket],true);
};
$regression=static function(string $label,string $item)use($auditor,$db,$check,$has):void{
    $result=$auditor->inspect($db);
    $check($result['status']==='sql_hardening_regression',$label.' flagged');
    $check($result['audit_complete']===true,$label.' scan completed');
    $check($has($result,$item,'hardening_regressions'),
        $label.' concrete SQL object identified');
    $check($result['release_allowed']===false
        && $result['media_execution_allowed']===false
        && $result['billing_allowed']===false
        && $result['independent_appsec_dba_review_approved']===false,
        $label.' cannot permit release');
    $blocked=false;
    try{$auditor->assertBaselineHardening($db);}catch(RuntimeException){$blocked=true;}
    $check($blocked,$label.' baseline gate fails closed');
};
$baseline=$auditor->assertBaselineHardening($db);
$check($baseline['schema_version']===SqlObjectOwnershipRiskInventory::SCHEMA,
    'versioned inventory schema');
$check($baseline['status']==='independent_owner_review_required',
    'AppSec and DBA review remains open');
$check(count($baseline['views'])===4,'four protected VIEWs in inventory');
$check(count($baseline['security_definers'])===2,'two security definer functions');
$check($baseline['hardening_regressions']===[],'no regression against lab baseline');
$check(count($baseline['unreviewed_privileged_owners'])>=5,
    'superuser ownership of tenant views/F91 remains an explicit risk');
foreach([
    'perf_f91_demo_account_read',
    'perf_f93_demo_campaign_account_read',
    'perf_f97_demo_review_read',
    'perf_f106_credential_budget_demo_read',
] as $view){
    $matches=array_values(array_filter($baseline['views'],
        static fn(array $v):bool=>$v['view']===$view));
    $check(count($matches)===1,'inventory has view '.$view);
    $check($matches[0]['owner_has_elevated_rights']===true,
        'privileged owner is not falsely cleared on '.$view);
    $check($matches[0]['security_barrier']===true,
        'view baseline security barrier true on '.$view);
    $check($has($baseline,'view:public.'.$view,'unreviewed_privileged_owners'),
        'view included in external review backlog '.$view);
}
$tenantDefiner=array_values(array_filter($baseline['security_definers'],
    static fn(array $f):bool=>$f['function']==='perf_f91_tenant_for_login'));
$journalDefiner=array_values(array_filter($baseline['security_definers'],
    static fn(array $f):bool=>$f['function']==='perf_f27_capture_review_transition'));
$check(count($tenantDefiner)===1&&count($journalDefiner)===1,
    'F91/F27 functions independently inventoried');
$check($tenantDefiner[0]['owner_has_elevated_rights']===true,
    'F91 tenant mapping remains superuser-owned review requirement');
$check($tenantDefiner[0]['security_definer']===true,
    'F91 helper is SECURITY DEFINER');
$check($tenantDefiner[0]['search_path_catalog_only']===true,
    'F91 definer has fixed catalog-only search path');
$check($journalDefiner[0]['owner']==='perf_f104_journal_writer'
    && $journalDefiner[0]['owner_has_elevated_rights']===false,
    'F27 journal owner is dedicated least-privileged F104 role');
$check($journalDefiner[0]['search_path_catalog_only']===true,
    'F27 search path F103 guard retained');
$check($baseline['independent_appsec_dba_review_approved']===false,
    'no fabricated independent security sign-off');

// Real hostile changes against disposable SQL catalog; restore immediately.
$db->exec(
    'ALTER VIEW public.perf_f106_credential_budget_demo_read '
    .'SET (security_barrier = false)'
);
$regression('remove F106 view security barrier',
    'view_hardening:public.perf_f106_credential_budget_demo_read');
$db->exec(
    'ALTER VIEW public.perf_f106_credential_budget_demo_read '
    .'SET (security_barrier = true)'
);
$check($auditor->assertBaselineHardening($db)===$baseline,
    'view security barrier restored');

$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() '
    .'SET search_path = public'
);
$regression('unsafe F91 definer lookup path',
    'definer_hardening:public.perf_f91_tenant_for_login');
$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() '
    .'SET search_path = pg_catalog'
);
$check($auditor->assertBaselineHardening($db)===$baseline,
    'F91 catalog-only search path restored');

$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() SECURITY INVOKER'
);
$regression('F91 function no longer audited SECURITY DEFINER',
    'definer_hardening:public.perf_f91_tenant_for_login');
$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() SECURITY DEFINER'
);
$check($auditor->assertBaselineHardening($db)===$baseline,
    'F91 expected behavior restored');

$db->exec(
    'ALTER FUNCTION public.perf_f27_capture_review_transition() OWNER TO postgres'
);
$regression('F27 journal function owner elevated to postgres',
    'definer_hardening:public.perf_f27_capture_review_transition');
$db->exec(
    'ALTER FUNCTION public.perf_f27_capture_review_transition() '
    .'OWNER TO perf_f104_journal_writer'
);
$check($auditor->assertBaselineHardening($db)===$baseline,
    'F27 least-privileged owner restored');

$denied=$auditor->inspect($reader);
$check($denied['status']==='untrusted_database_auditor'
    && $denied['audit_complete']===false,
    'tenant SQL reader cannot self-certify object owners');
putenv('PERF_F116_DEFINER_VIEW_RISK_LAB=0');
$off=$auditor->inspect($db);
$check($off['status']==='lab_disabled'
    && $off['release_allowed']===false,
    'audit disabled without exclusive CI flag');
putenv('PERF_F116_DEFINER_VIEW_RISK_LAB=1');
putenv('APP_ENV=production');
$prod=$auditor->inspect($db);
$check($prod['status']==='lab_disabled'
    && $prod['independent_appsec_dba_review_approved']===false,
    'no production ownership approval from CI rehearsal');
putenv('APP_ENV=testing');

$manifest=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($manifest['risk_state']==='open_no_go'
    && $manifest['release_allowed']===false
    && $manifest['direct_sql_client_access_allowed']===false,
    'production tenant/SQL role security remains open');

$artifact=getenv('PERF_F116_OWNERSHIP_JSON_FILE');
if(is_string($artifact)&&$artifact!==''){
    $check(str_starts_with($artifact,'/tmp/')&&!file_exists($artifact)
        &&!is_link($artifact),'new local CI risk artifact only');
    $check(file_put_contents($artifact,
        json_encode($baseline,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
        ."\n",LOCK_EX)!==false,'write synthetic owner risk inventory for DBA');
}
echo "PASS F116 $n assertions: privileged VIEW/F91 owner risk remains NO-GO; F27 least-privileged and definer regressions detected\n";
