<?php
declare(strict_types=1);

/** F114: all ACLs, including NEW roles never included in F112 allowlist. */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F114_UNKNOWN_ACL_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F114 requires disposable PostgreSQL16');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/UnknownPrincipalAclAudit.php';

use App\Performance\Security\UnknownPrincipalAclAudit;
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$auditor=new UnknownPrincipalAclAudit();
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F114 '.$label);
    ++$n;
};
$has=static function(array $report,string $role,string $object,
    string $privilege,string $scope):bool {
    foreach($report['unexpected_acl_edges'] as $edge) {
        if($edge['principal']===$role
            && $edge['object']==='public.'.$object
            && $edge['privilege']===$privilege
            && $edge['scope']===$scope)return true;
    }
    return false;
};
$mustDetect=static function(string $label)use($db,$auditor,$check):array{
    $r=$auditor->inspect($db);
    $check($r['audit_complete']===true,$label.' audit completed');
    $check($r['status']==='unknown_acl_grant_detected',$label.' drift detected');
    $check($r['unexpected_acl_edges']!==[],$label.' reports edges');
    $check($r['release_allowed']===false
        && $r['media_execution_allowed']===false
        && $r['billing_allowed']===false,$label.' never grants GO');
    $blocked=false;
    try{$auditor->assertKnownAclOnly($db);}catch(RuntimeException){$blocked=true;}
    $check($blocked,$label.' fails closed');
    return $r;
};
$initial=$auditor->inspect($db);
$check($initial['status']==='approved_acl_edges_in_ci',
    'baseline ACL closure must contain only approved grants: '
    .json_encode(array_slice($initial['unexpected_acl_edges'],0,10)));
$check($initial['audit_complete']===true,'catalog scan completed');
$check($initial['audited_objects']===17,'thirteen base tables and four VIEWs');
$check($initial['unexpected_acl_edges']===[],'no unauthorized current grant edges');
$check($initial['release_allowed']===false
    && $initial['billing_allowed']===false
    && $initial['media_execution_allowed']===false,
    'even clean catalog does not permit media or billing');

// F113 only knows about a fixed set of legacy roles. This unknown role is
// deliberately NOT in that allowlist; F114 must nevertheless catch it.
$db->exec('CREATE ROLE perf_f114_unknown_reader NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE');
$db->exec(
    'GRANT SELECT ON public.performance_campaign_intents TO perf_f114_unknown_reader'
);
$report=$mustDetect('new unknown reader');
$check($has($report,'perf_f114_unknown_reader','performance_campaign_intents',
    'SELECT','object'),'unknown principal recorded by ACL graph');
$db->exec(
    'REVOKE SELECT ON public.performance_campaign_intents FROM perf_f114_unknown_reader'
);
$check($auditor->assertKnownAclOnly($db)['unexpected_acl_edges']===[],
    'unknown role with no privileges not flagged');

$db->exec(
    'GRANT SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'TO perf_f114_unknown_reader'
);
$report=$mustDetect('unknown column-level SELECT');
$check($has($report,'perf_f114_unknown_reader',
    'performance_demo_budget_scenarios','SELECT','column'),
    'column ACL of non-allowlisted role detected');
$db->exec(
    'REVOKE SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'FROM perf_f114_unknown_reader'
);
$check($auditor->assertKnownAclOnly($db)['unexpected_acl_edges']===[],
    'unknown column grant cleaned');

$db->exec(
    'GRANT INSERT ON public.performance_intent_review_journal '
    .'TO perf_f114_unknown_reader'
);
$report=$mustDetect('unknown role can INSERT journal');
$check($has($report,'perf_f114_unknown_reader',
    'performance_intent_review_journal','INSERT','object'),
    'journal write reserved to F104 NOLOGIN definer');
$db->exec(
    'REVOKE INSERT ON public.performance_intent_review_journal '
    .'FROM perf_f114_unknown_reader'
);

$db->exec(
    'GRANT SELECT ON public.perf_f106_credential_budget_demo_read '
    .'TO perf_f114_unknown_reader'
);
$report=$mustDetect('unknown role added to protected VIEW');
$check($has($report,'perf_f114_unknown_reader',
    'perf_f106_credential_budget_demo_read','SELECT','object'),
    'unknown role VIEW grant detected');
$db->exec(
    'REVOKE SELECT ON public.perf_f106_credential_budget_demo_read '
    .'FROM perf_f114_unknown_reader'
);

$db->exec('GRANT SELECT ON public.perf_f106_credential_budget_demo_read TO PUBLIC');
$report=$mustDetect('PUBLIC VIEW ACL');
$check($has($report,'PUBLIC','perf_f106_credential_budget_demo_read',
    'SELECT','object'),'PUBLIC grant detected directly from ACL catalog');
$db->exec(
    'REVOKE SELECT ON public.perf_f106_credential_budget_demo_read FROM PUBLIC'
);

$db->exec(
    'GRANT UPDATE ON public.perf_f106_credential_budget_demo_read '
    .'TO perf_f91_reader_a'
);
$report=$mustDetect('protected reader gained unexpected UPDATE');
$check($has($report,'perf_f91_reader_a',
    'perf_f106_credential_budget_demo_read','UPDATE','object'),
    'only SELECT is allowed on protected VIEWs');
$db->exec(
    'REVOKE UPDATE ON public.perf_f106_credential_budget_demo_read '
    .'FROM perf_f91_reader_a'
);

$db->exec('DROP ROLE perf_f114_unknown_reader');
$final=$auditor->assertKnownAclOnly($db);
$check($final===$initial,'catalog restored after all hostile grants');
$check($final['release_allowed']===false,'lab success cannot authorize release');

$untrusted=$auditor->inspect($a);
$check($untrusted['status']==='untrusted_acl_auditor'
    && $untrusted['audit_complete']===false,
    'customer SQL login cannot self-certify grant inventory');

putenv('PERF_F114_UNKNOWN_ACL_LAB=0');
$off=$auditor->inspect($db);
$check($off['status']==='lab_disabled' && $off['release_allowed']===false,
    'lab flag required and disabled response remains NO-GO');
putenv('PERF_F114_UNKNOWN_ACL_LAB=1');
putenv('APP_ENV=production');
$prod=$auditor->inspect($db);
$check($prod['status']==='lab_disabled'
    && $prod['audit_complete']===false,
    'no production ACL certification from CI tool');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    && $risk['release_allowed']===false
    && $risk['direct_sql_client_access_allowed']===false,
    'real-world tenant GUC security and release risk still OPEN');

echo "PASS F114 $n assertions: unknown/PUBLIC/column SQL ACLs audited without production GO\n";
