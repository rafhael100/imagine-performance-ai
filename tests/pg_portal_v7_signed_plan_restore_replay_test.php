<?php
declare(strict_types=1);

require_once __DIR__.'/../labs/finance-pg/PgPortalV7SignedPlanNonceLab.php';
use Imagine\Performance\Commercial\Lab\PgPortalV7SignedPlanNonceLab as Nonce;

function restorePlanMust(bool $value,string $why):void{
    if(!$value)throw new RuntimeException('V7 restore replay: '.$why);
}
foreach([
    'APP_ENV'=>'testing','PERF_PG_V6_ROLE_LAB'=>'1',
    'PERF_PG_V7_PLAN_NONCE_LAB'=>'1',
] as $key=>$value){
    if(getenv($key)!==$value)throw new RuntimeException('Missing restore test flag '.$key);
}
$dsn=getenv('PERF_LAB_RESTORE_DSN');
$pdo=static fn(string $user,string $password):PDO=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$alpha=$pdo('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$pdo('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$a='tenant_v6multi_alpha_123456';
$b='tenant_v6multi_beta_567890';
$agreementA='agreement_v7_pg18_alpha_123456';
$agreementB='agreement_v7_pg18_beta_1234567';
$agreementA2='agreement_v7_pg18_alpha_other1234';
$nonce='nonce_v7_pg18_same_123456789';
$storeA=new Nonce($alpha);
$storeB=new Nonce($beta);

restorePlanMust((int)$alpha->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn()===2,'alpha must see two preserved agreement-scoped claims');
restorePlanMust((int)$beta->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn()===1,'beta must see its own preserved claim');
restorePlanMust(!$storeA->claim($a,$agreementA,$nonce,1794661600),
    'alpha old contract grant remains used after restore');
restorePlanMust(!$storeA->claim($a,$agreementA2,$nonce,1794661600),
    'alpha other agreement grant remains used after restore');
restorePlanMust(!$storeB->claim($b,$agreementB,$nonce,1794661600),
    'beta own old grant remains used after restore');
restorePlanMust(!$storeB->claim($a,$agreementA,
    'nonce_v7_restore_cross_123456',1794661600),
    'beta cannot claim an alpha tenant V7 grant');
restorePlanMust($storeA->claim($a,$agreementA,
    'nonce_v7_restore_fresh_123456',1794661600),
    'fresh signed-plan nonce can still be claimed in restored test DB');
restorePlanMust(!$storeA->claim($a,$agreementA,
    'nonce_v7_restore_fresh_123456',1794661600),
    'new nonce cannot be consumed twice after restore');
restorePlanMust((int)$alpha->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn()===3,'new fresh nonce committed in restore database');

echo "OK: PG18 V7 nonce survives dump/restore by tenant+agreement, cross-tenant blocked and fresh nonce still works; no real billing\n";
