<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticCampaignPortalProjection.php';

use Imagine\Protocol\Lab\SyntheticCampaignPortalProjection;

if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F24_READ_MODEL_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F24 requires disposable PG lab only.');
}
$connect=static fn (string $user,string $password):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$readDb=$connect('perf_f24_reader','ci-f24-read-only');
$admin=$connect('postgres','ci-f22-db-only');
$projection=new SyntheticCampaignPortalProjection($readDb);
$org='org-demo-a';$tenant='perf-demo-a';
$n=0;
$check=static function (string $description,mixed $got,mixed $expected) use(&$n):void {
    if($got!==$expected) {
        throw new RuntimeException('FAIL F24 '.$description.' got '.var_export($got,true)
            .' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F24 $n $description\n";
};
$d=$projection->build($org,$tenant);
if(!is_array($d)){
    throw new RuntimeException('F24 failed to read synthetic PG data.');
}
$check('01 correct contract version',
    $d['schema_version'],'perf.portal.storage.synthetic.v1');
$check('02 provenance explicitly PG synthetic only',
    $d['source'],'synthetic_postgresql_only');
$check('03 correct mock producer',
    $d['producer'],'imagine-performance-ai-lab');
$check('04 organization bound', $d['portal_organization_ref'],$org);
$check('05 tenant bound', $d['performance_tenant_ref'],$tenant);
$check('06 service bound', $d['portal_service_ref'],'svc-demo-a');
$check('07 always read-only', $d['read_only'],true);
$check('08 never contracted from test only', $d['contracted'],false);
$check('09 dispatch explicitly disabled', $d['dispatch_enabled'],false);
$check('10 price not homologated',
    $d['commercial']['price_state'],'not_homologated');
$check('11 campaign capacity not asserted',
    $d['commercial']['capacity_state'],'unknown_not_for_sale_demo');
$check('12 two campaigns belong to A',
    $d['metrics']['managed_campaigns'],2);
$check('13 paused managed campaign stays counted',
    $d['metrics']['paused_campaigns'],1);
$check('14 exactly one pending human approval',
    $d['metrics']['approvals_pending'],1);
$check('15 two internal approvals without dispatch',
    $d['metrics']['approvals_approved_internal'],2);
$check('16 rejected request not hidden',
    $d['metrics']['approvals_rejected'],1);
$check('17 no real click metrics', $d['metrics']['clicks'],null);
$check('18 no real media spending', $d['metrics']['media_spend'],null);
$check('19 correct first campaign ref',
    $d['campaigns'][0]['ref'],'demo-cam-a1');
$check('20 paused status is synthetic', $d['campaigns'][0]['state'],'paused_demo');
$check('21 first synthetic campaign has known channel',
    $d['campaigns'][0]['channel'],'google_ads');
$check('22 first campaign title explicitly illustrative',
    $d['campaigns'][0]['title'],'A One (exemplo)');
$check('23 second test fixture present',
    $d['campaigns'][1]['ref'],'demo-cam-f23-demo');
$check('24 second campaign still draft',
    $d['campaigns'][1]['state'],'draft_demo');
$check('25 total approvals from SQL',
    count($d['approvals']),4);
$check('26 one F22 request still pending',
    $d['approvals'][0]['ref'],'demo-req-demo-1');
$check('27 F22 pending status preserved',
    $d['approvals'][0]['status'],'pending_human_demo');
$check('28 internal review remains no-execution',
    $d['approvals'][1]['status'],'approved_demo_no_execution');
$check('29 revision manifest always illustrative',
    $d['approvals'][1]['manifest_version'],'demo-v1');
$check('30 request target uses safe display ref',
    $d['approvals'][1]['campaign_ref'],'demo-cam-f23-demo');
$json=$projection->exportJson($org,$tenant);
if(!is_string($json)){
    throw new RuntimeException('F24 expected serialized projection.');
}
$check('31 JSON roundtrip is exact',
    json_decode($json,true,32,JSON_THROW_ON_ERROR),$d);
$check('32 export is deterministic for same PG snapshot',
    $projection->exportJson($org,$tenant),$json);
$check('33 no email or actor IDs in exported contract',
    str_contains($json,'actor-demo-')
     || str_contains($json,'reviewer-demo-')
     || str_contains($json,'@'),false);
$check('34 no raw internal tenant UUID',
    str_contains($json,'11111111-1111-4111-8111-111111111111'),false);
$check('35 no access tokens or account IDs',
    str_contains($json,'access_token')
     || str_contains($json,'provider_account_id'),false);
$check('36 no operational active campaign or Ads spend',
    str_contains($json,'active_real')
     || str_contains($json,'"dispatch_enabled": true')
     || str_contains($json,'"media_spend": 0'),false);

$check('37 wrong organization fail-closed',
    $projection->build('org-demo-b',$tenant),null);
$check('38 wrong tenant fail-closed',
    $projection->build($org,'perf-demo-b'),null);
$check('39 empty tenant fail-closed',
    $projection->build($org,''),null);
$check('40 privileged admin not acceptable as read-only role',
    (new SyntheticCampaignPortalProjection($admin))->build($org,$tenant),null);
putenv('PERF_F24_READ_MODEL_LAB=0');
$check('41 disabling flag denies existing projector',
    $projection->build($org,$tenant),null);
putenv('PERF_F24_READ_MODEL_LAB=1');

$admin->exec('REVOKE SELECT ON performance_campaign_intents FROM perf_f24_reader');
$check('42 missing request table privilege refuses all output',
    $projection->build($org,$tenant),null);
$admin->exec('GRANT SELECT ON performance_campaign_intents TO perf_f24_reader');
$check('43 restored read-only privilege re-enables fictional projection',
    is_array($projection->build($org,$tenant)),true);
$again=$projection->build($org,$tenant);
$check('44 projection restored without stale state leak',
    $again['metrics']['approvals_pending'] ?? null,1);

$admin->exec("UPDATE performance_managed_campaigns
    SET managed=false WHERE campaign_ref='cam-f23-demo'
     AND tenant_id='11111111-1111-4111-8111-111111111111'");
$check('45 corrupted/unmanaged campaign row fails closed',
    $projection->build($org,$tenant),null);
$admin->exec("UPDATE performance_managed_campaigns
    SET managed=true WHERE campaign_ref='cam-f23-demo'
     AND tenant_id='11111111-1111-4111-8111-111111111111'");
$check('46 corrected fixture restores expected campaign count',
    $projection->build($org,$tenant)['metrics']['managed_campaigns'],2);

$check('47 read-only role cannot modify campaign data',
    (bool)$admin->query("SELECT has_table_privilege(
       'perf_f24_reader','performance_managed_campaigns','UPDATE')")->fetchColumn(),false);
$check('48 read-only role cannot insert approvals',
    (bool)$admin->query("SELECT has_table_privilege(
       'perf_f24_reader','performance_campaign_intents','INSERT')")->fetchColumn(),false);
$check('49 read-only role cannot read membership roles',
    (bool)$admin->query("SELECT has_table_privilege(
       'perf_f24_reader','operational_memberships','SELECT')")->fetchColumn(),false);
$check('50 role is non-superuser',
    $admin->query("SELECT rolsuper FROM pg_roles WHERE rolname='perf_f24_reader'")
        ->fetchColumn(),false);

echo "PASS F24 $n synthetic SQL-to-Portal offline projection checks\n";
