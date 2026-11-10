<?php
declare(strict_types=1);

/**
 * F1-118: deliberately tamper with the new F117 SQL owner privileges/RLS
 * inside disposable PostgreSQL16 and prove each regression fails closed.
 * No customer environment or provider API is touched.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F118_OWNER_INVARIANT_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F118 restricted to disposable PostgreSQL16');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/LimitedDemoOwnerInvariantAudit.php';
use App\Performance\Security\LimitedDemoOwnerInvariantAudit;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$db=new PDO($dsn,'postgres','ci-f22-db-only',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$client=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$viewOwner='perf_f117_demo_view_owner';
$bindOwner='perf_f117_binding_definer_owner';
$tenantA='88888888-8888-4888-8888-888888888888';
$audit=new LimitedDemoOwnerInvariantAudit();
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F118 '.$label);
    ++$n;
};
$has=static function(array $report,string $needle):bool{
    foreach($report['unsafe_findings'] as $msg){
        if(str_contains($msg,$needle))return true;
    }
    return false;
};
$reject=static function(string $label,string $needle)use($audit,$db,$check,$has):void {
    $data=$audit->inspect($db);
    $check($data['status']==='sql_owner_security_regression',
        $label.' detected as explicit owner-policy regression: '
        .json_encode($data['unsafe_findings']));
    $check($data['audit_complete']===true,$label.' catalog inventory complete');
    $check($has($data,$needle),$label.' pinpoints '.$needle);
    $check($data['release_allowed']===false
        &&$data['media_execution_allowed']===false
        &&$data['billing_allowed']===false
        &&$data['independent_review_complete']===false,
        $label.' cannot authorize commercial deployment');
    $blocked=false;
    try{$audit->assertLimitedOwnerBaseline($db);}catch(RuntimeException){$blocked=true;}
    $check($blocked,$label.' blocks strict SQL admission');
};
$underRole=static function(PDO $db,string $tenant):int {
    $db->exec('BEGIN');
    try {
        $db->exec('SET LOCAL ROLE perf_f117_demo_view_owner');
        $stmt=$db->prepare(
            "SELECT set_config('app.performance_tenant_id',?,true)"
        );
        $stmt->execute([$tenant]);
        return (int)$db->query(
            'SELECT count(*) FROM public.performance_ad_account_candidates'
        )->fetchColumn();
    } finally {
        $db->exec('ROLLBACK');
    }
};

$baseline=$audit->assertLimitedOwnerBaseline($db);
$check($baseline['schema_version']===LimitedDemoOwnerInvariantAudit::SCHEMA,
    'versioned owner invariant report');
$check($baseline['status']==='limited_ci_owners_verified',
    'new F117 least privilege owner baseline verified in disposable CI');
$check($baseline['source_table_count']===6,
    'six source tables audited for exact privileges');
$check($baseline['read_view_count']===4,
    'four tenant-bound views audited');
$check($baseline['unsafe_findings']===[],'zero unexpected owner privileges');
$check($baseline['release_allowed']===false
    &&$baseline['media_execution_allowed']===false
    &&$baseline['billing_allowed']===false
    &&$baseline['independent_review_complete']===false,
    'even clean catalog cannot clear independent review gate');
$check($underRole($db,$tenantA)===0,
    'NOLOGIN VIEW owner with session_user postgres cannot see any synthetic tenant, even spoofing GUC');

$db->exec(
    'GRANT SELECT ON public.performance_db_principal_tenant_bindings '
    .'TO '.$viewOwner
);
$reject('VIEW owner given binding registry SELECT',
    'select_grant:'.$viewOwner.':performance_db_principal_tenant_bindings');
$db->exec(
    'REVOKE SELECT ON public.performance_db_principal_tenant_bindings '
    .'FROM '.$viewOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'VIEW owner cannot directly read binding registry after repair');

$db->exec(
    'GRANT SELECT ON public.performance_campaign_intents TO '.$bindOwner
);
$reject('binding definer owner given campaign data SELECT',
    'select_grant:'.$bindOwner.':performance_campaign_intents');
$db->exec(
    'REVOKE SELECT ON public.performance_campaign_intents FROM '.$bindOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'F91 owner cannot read campaign data after repair');

$db->exec(
    'GRANT INSERT ON public.performance_demo_budget_scenarios TO '.$viewOwner
);
$reject('VIEW owner gains budget INSERT',
    'nonread_grant:'.$viewOwner.':performance_demo_budget_scenarios:INSERT');
$db->exec(
    'REVOKE INSERT ON public.performance_demo_budget_scenarios FROM '.$viewOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'VIEW owner budget write grant removed');

$db->exec(
    'REVOKE SELECT ON public.performance_ad_account_candidates FROM '.$viewOwner
);
$reject('required source SELECT accidentally revoked',
    'select_grant:'.$viewOwner.':performance_ad_account_candidates');
$db->exec(
    'GRANT SELECT ON public.performance_ad_account_candidates TO '.$viewOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'minimal source SELECT restored');

$db->exec('ALTER ROLE '.$viewOwner.' BYPASSRLS');
$reject('VIEW owner gained BYPASSRLS',
    'role_attribute:'.$viewOwner.':rolbypassrls');
$db->exec('ALTER ROLE '.$viewOwner.' NOBYPASSRLS');
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'VIEW owner cannot bypass RLS after repair');

$db->exec('ALTER ROLE '.$bindOwner.' LOGIN');
$reject('F91 definer owner gained LOGIN',
    'role_attribute:'.$bindOwner.':rolcanlogin');
$db->exec('ALTER ROLE '.$bindOwner.' NOLOGIN');
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'binding owner returned to NOLOGIN');

$db->exec(
    'GRANT '.$viewOwner.' TO perf_f91_reader_a WITH INHERIT TRUE, SET TRUE'
);
$reject('authenticated reader inherits internal VIEW owner',
    'membership:'.$viewOwner);
$db->exec('REVOKE '.$viewOwner.' FROM perf_f91_reader_a');
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'F91 reader can no longer SET ROLE into internal owner');

$db->exec(
    'ALTER VIEW public.perf_f106_credential_budget_demo_read '
    .'SET (security_barrier = false)'
);
$reject('budget VIEW security_barrier disabled',
    'view_owner_or_barrier:perf_f106_credential_budget_demo_read');
$db->exec(
    'ALTER VIEW public.perf_f106_credential_budget_demo_read '
    .'SET (security_barrier = true)'
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'view security barrier restored');

$db->exec(
    'REVOKE EXECUTE ON FUNCTION public.perf_f91_tenant_for_login() '
    .'FROM '.$viewOwner
);
$reject('VIEW owner cannot invoke tenant binding helper',
    'F91_execute_missing_for_view_owner');
$db->exec(
    'GRANT EXECUTE ON FUNCTION public.perf_f91_tenant_for_login() '
    .'TO '.$viewOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'tenant helper EXECUTE restored only to VIEW owner');

$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() OWNER TO postgres'
);
$reject('tenant helper owner escalated to superuser','F91_definer_owner_or_path');
$db->exec(
    'ALTER FUNCTION public.perf_f91_tenant_for_login() '
    .'OWNER TO '.$bindOwner
);
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'tenant binding helper re-owned by non-superuser');

// Actual isolated SQL abuse when a RESTRICTIVE policy is removed:
// legacy permissive GUC rule can now expose tenant A to a session whose
// session_user is postgres, even though there is no F91 tenant mapping.
// Demonstrate the threat, detect policy drift, restore BEFORE any output.
$db->exec(
    'ALTER POLICY perf_f117_owner_login_fence '
    .'ON public.performance_ad_account_candidates USING (true)'
);
$reject('restrictive login-bound RLS predicate replaced by TRUE',
    'owner_rls_policy:performance_ad_account_candidates:perf_f117_owner_login_fence');
$observed=$underRole($db,$tenantA);
$check($observed>0,
    'unsafe policy actually exposes DEMO A rows to unmapped session_user via legacy GUC');
$db->exec(
    'ALTER POLICY perf_f117_owner_login_fence '
    .'ON public.performance_ad_account_candidates '
    .'USING (tenant_id = public.perf_f91_tenant_for_login())'
);
$check($underRole($db,$tenantA)===0,
    'restored restrictive policy denies all DEMO rows under unbound SQL login');
$check($audit->assertLimitedOwnerBaseline($db)===$baseline,
    'policy and catalog returned to exact secure F117 lab baseline');

$still=$client->query(
    'SELECT count(*) FROM public.perf_f91_demo_account_read'
)->fetchColumn();
$check((int)$still===2,'authentic tenant A reader still sees its own two DEMO accounts');

$off=$audit->inspect($client);
$check($off['status']==='untrusted_ci_auditor'
    &&$off['audit_complete']===false,
    'customer SQL login cannot certify owner configuration');
putenv('PERF_F118_OWNER_INVARIANT_LAB=0');
$off=$audit->inspect($db);
$check($off['status']==='lab_disabled'
    &&$off['release_allowed']===false,'lab flag required');
putenv('PERF_F118_OWNER_INVARIANT_LAB=1');
putenv('APP_ENV=production');
$off=$audit->inspect($db);
$check($off['status']==='lab_disabled'
    &&$off['independent_review_complete']===false,
    'CI-only audit cannot run as production certificate');
putenv('APP_ENV=testing');

$manifest=json_decode(
    (string)file_get_contents(
        dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
    ),true,16,JSON_THROW_ON_ERROR
);
$check($manifest['risk_state']==='open_no_go'
    &&$manifest['release_allowed']===false
    &&$manifest['direct_sql_client_access_allowed']===false,
    'legacy GUC risk remains open for external SQL access');
echo "PASS F118 $n assertions: NOLOGIN owner privileges, RLS restrictive fence and actual GUC leak rehearsal in CI, NO-GO\n";
