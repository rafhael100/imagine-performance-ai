<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticPortalEligibilityGate.php';
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticCampaignRegistry.php';

use Imagine\Protocol\Lab\SyntheticPortalEligibilityGate;
use Imagine\Protocol\Lab\SyntheticGuardedCampaignActions;
use Imagine\Performance\Domain\SyntheticCampaignRegistry;

$phase = getenv('PERF_F17_TEST_PHASE');
if (!in_array($phase,['before_reconcile','after_reconcile'],true)
    || getenv('PERF_F17_ELIGIBILITY_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab') {
    throw new RuntimeException('F17 fixture phase or disposable DB missing.');
}
$connect=static fn (string $database,string $user,string $password):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$database,
    $user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$source=$connect('portal_lab','portal_perf_source_reader','ci-only-portal-perf-reader');
$local=$connect('perf_test_relay_main','perf_test_wire_ingress','ci-only-direct-wire-ingress');
$sourceAdmin=$connect('portal_lab','postgres',(string)getenv('PGPASSWORD'));
$localAdmin=$connect('perf_test_relay_main','postgres',(string)getenv('PGPASSWORD'));
$gate=new SyntheticPortalEligibilityGate($source,$local);
$n=0;
$check=static function (string $label,mixed $actual,mixed $expected) use (&$n,$phase):void {
    if($actual!==$expected){
        throw new RuntimeException('FAIL F17 '.$phase.' '.$label.' got '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F17 $phase $n $label\n";
};
$expect=$phase==='before_reconcile'
    ? 'eligibility_revision_mismatch'
    : 'eligibility_source_revoked';
$sourceRevision=(int)$sourceAdmin->query(
    "SELECT min_revision FROM lab_portal_perf_authority WHERE service_ref='svc-demo-b'"
)->fetchColumn();
$localRevision=(int)$localAdmin->query(
    "SELECT grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b'"
)->fetchColumn();
$check('01 Portal revision remains 2', $sourceRevision, 2);
$check('02 local revision matches expected restore phase',
    $localRevision,$phase==='before_reconcile'?1:2);
$check('03 eligibility must reject restored/revoked service',
    $gate->check('org-demo-b','perf-demo-b','svc-demo-b'),$expect);
$check('04 other organization binding denied',
    $gate->check('org-demo-a','perf-demo-b','svc-demo-b'),
    'eligibility_binding_denied');
$check('05 other tenant binding denied',
    $gate->check('org-demo-b','perf-demo-a','svc-demo-b'),
    'eligibility_binding_denied');
$check('06 other service binding denied',
    $gate->check('org-demo-b','perf-demo-b','svc-demo-a'),
    'eligibility_binding_denied');

$registry=new SyntheticCampaignRegistry();
$registry->setDemoCapacity('perf-demo-b','google_ads',1);
$check('07 create in-memory fixture only',
    $registry->add('perf-demo-b','google_ads','campaign-demo-b1'),'accepted_demo');
$beforeAudit=count($registry->auditFor('perf-demo-b'));
$guarded=new SyntheticGuardedCampaignActions($gate,$registry);
$check('08 fake campaign pause forbidden',
    $guarded->pause('perf-demo-b','campaign-demo-b1'),
    'campaign_denied_by_eligibility_demo');
$check('09 fake campaign remains active',
    $registry->state('perf-demo-b','campaign-demo-b1'),'active_demo');
$check('10 fake campaign replacement forbidden',
    $guarded->replace('perf-demo-b','campaign-demo-b1','campaign-demo-b2'),
    'campaign_denied_by_eligibility_demo');
$check('11 no replacement appears',
    $registry->state('perf-demo-b','campaign-demo-b2'),'not_found');
$check('12 no extra audit record was created for rejected actions',
    count($registry->auditFor('perf-demo-b')),$beforeAudit);
$check('13 quota remains unchanged',
    $registry->capacity('perf-demo-b','google_ads')['used'],1);
$check('14 low-privilege local role cannot see other tenant A',
    (int)$local->query(
        "SELECT count(*) FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(),0);

$sourceAdmin->exec(
    'REVOKE SELECT ON lab_portal_perf_authority FROM portal_perf_source_reader'
);
$check('15 lost Portal authority denies all demo actions',
    $gate->check('org-demo-b','perf-demo-b','svc-demo-b'),
    'eligibility_source_unavailable');
$check('16 outage cannot pause fixture campaign',
    $guarded->pause('perf-demo-b','campaign-demo-b1'),
    'campaign_denied_by_eligibility_demo');
$sourceAdmin->exec(
    'GRANT SELECT ON lab_portal_perf_authority TO portal_perf_source_reader'
);
$check('17 restored authority returns to correct deny decision',
    $gate->check('org-demo-b','perf-demo-b','svc-demo-b'),$expect);

$wrongRole=new SyntheticPortalEligibilityGate($source,$localAdmin);
$check('18 privileged/incorrect destination role is rejected',
    $wrongRole->check('org-demo-b','perf-demo-b','svc-demo-b'),
    'eligibility_wrong_role_denied');
putenv('PERF_F17_ELIGIBILITY_LAB=0');
$check('19 disabled lab never returns allowed',
    $gate->check('org-demo-b','perf-demo-b','svc-demo-b'),
    'eligibility_lab_disabled');
putenv('PERF_F17_ELIGIBILITY_LAB=1');
$check('20 no fake campaigns were paused or replaced',
    $registry->state('perf-demo-b','campaign-demo-b1'),'active_demo');
echo "PASS F17 $n fail-closed campaign eligibility checks in $phase\n";
