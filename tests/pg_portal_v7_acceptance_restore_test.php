<?php
declare(strict_types=1);

require_once __DIR__.'/../labs/finance-pg/PgPortalV7AcceptedPlanNonceLab.php';
use Imagine\Performance\Commercial\Lab\PgPortalV7AcceptedPlanNonceLab as Store;

function restoredAcceptMust(bool $yes,string $label):void {
    if(!$yes)throw new RuntimeException('Restored V7 acceptance PG18: '.$label);
}
if(getenv('APP_ENV')!=='testing' ||
   getenv('PERF_PG_V7_ACCEPTANCE_LAB')!=='1' ||
   getenv('PERF_PG_V7_PLAN_NONCE_LAB')!=='1' ||
   getenv('PERF_PG_V6_ROLE_LAB')!=='1'){
    throw new RuntimeException('Restore CI gating disabled');
}
$create=static fn(string $user,string $pass):PDO=>
    new PDO(getenv('PERF_LAB_RESTORE_DSN'),$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
$a=$create('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$b=$create('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$alpha='tenant_v6multi_alpha_123456';
$beta='tenant_v6multi_beta_567890';
$agreement='agreement_acceptance_alpha_123456';
$period='2026-11';
$eventsA=(int)$a->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_acceptance_events'
)->fetchColumn();
$eventsB=(int)$b->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_acceptance_events'
)->fetchColumn();
restoredAcceptMust($eventsA===5 && $eventsB===1,
    'append-only acceptance and revocation events survive dump/restore with RLS');
$state=$a->query(
    "SELECT state,event_revision FROM perf_fin_role_lab.portal_v7_acceptance_events
    WHERE tenant_id='tenant_v6multi_alpha_123456'
      AND agreement_id='agreement_acceptance_alpha_123456'
      AND period='2026-11' ORDER BY event_revision DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
restoredAcceptMust($state['state']==='synthetic_accepted' &&
    (int)$state['event_revision']===5,
    'latest event version persists, even when future-dated and not yet valid');
$nonceA=(int)$a->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn();
$nonceB=(int)$b->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn();
restoredAcceptMust($nonceA===2 && $nonceB===1,
    'used plan nonces persist per tenant after restore');
$store=new Store($a);
$nonce='nonce_v7_accept_alpha_initial_1234';
restoredAcceptMust(!$store->claim(
    $alpha,$agreement,$period,str_repeat('a',64),str_repeat('b',64),
    $nonce,1794700000,1794690300
),'replay rejected after restore even with forged approval fingerprint');
restoredAcceptMust(!$store->claim(
    $beta,'agreement_acceptance_beta_1234567',$period,
    str_repeat('a',64),str_repeat('b',64),
    'nonce_v7_accept_foreign_12345',1794700000,1794690300
),'authenticated ALPHA SQL role cannot claim BETA approval');
echo "OK: append-only acceptance and nonce states persisted after restore, RLS and replay still fail closed; no real client consent\n";
