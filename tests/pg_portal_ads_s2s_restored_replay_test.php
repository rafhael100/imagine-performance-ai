<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-ads-read-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceAdsReadGrantLabIssuer.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalAdsReadS2SLabVerifier.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalAdsReadGrantNonceLab.php';

use App\Homologation\Commerce\PortalPerformanceAdsReadGrantLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalAdsReadS2SLabVerifier as Verifier;
use Imagine\Performance\Commercial\Lab\PgPortalAdsReadGrantNonceLab as Store;

if(getenv('APP_ENV')!=='testing' ||
   getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB')!=='1' ||
   getenv('PERF_PG_ADS_S2S_NONCE_LAB')!=='1' ||
   getenv('PORTAL_PERF_ADS_S2S_READ_GRANT_LAB')!=='1' ||
   !getenv('PERF_LAB_RESTORE_DSN')) {
    throw new RuntimeException('Restored Ads nonce CI-only scope missing.');
}
$connect=static fn(string $role,string $password):PDO=>new PDO(
    getenv('PERF_LAB_RESTORE_DSN'),$role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$alpha=$connect('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$connect('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$a='tenant_v6multi_alpha_123456';
$b='tenant_v6multi_beta_567890';
$agreement='agreement_s2s_durable_123456';
$month='2026-11';
$nonce='nonce_durable_s2s_same_123456';
$now=1794660000;
$secret=str_repeat('Z',48);
$key='k_perf_ads_read_01';
$ks=static fn(string $issuer,string $id):?string=>
    $issuer==='imagine-portal-ci'&&$id===$key?str_repeat('Z',48):null;
$cases=[
    [$alpha,$a,'adacct_12345678','ref_pg_durable_alpha_123456',
        'consent_pg_durable_alpha_123456'],
    [$beta,$b,'adacct_87654321','ref_pg_durable_beta_1234567',
        'consent_pg_durable_beta_1234567'],
];
foreach($cases as [$db,$tenant,$account,$ref,$consent]){
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:fake,true)")
       ->execute(['fake'=>$tenant===$a?$b:$a]);
    $mapped=$db->query(
        'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
    )->fetchColumn();
    $count=(int)$db->query(
        'SELECT count(*) FROM perf_fin_role_lab.ads_read_grant_nonces'
    )->fetchColumn();
    $db->rollBack();
    if($mapped!==$tenant||$count!==1){
        throw new RuntimeException('PG18 restored S2S nonces or tenant RLS lost.');
    }
    $store=new Store($db);
    $grant=Issuer::issue($tenant,$agreement,$month,$account,$ref,$consent,
        $key,$nonce,$now-2,$now+100,$secret
    );
    $claim=static fn(string $t,string $n,int $exp):bool=>
        $store->claim($t,$n,$exp);
    $denied=false;
    try{
        Verifier::verify($grant,$tenant,$agreement,$month,
            $account,$ref,$consent,$now,$ks,$claim);
    } catch(InvalidArgumentException|RuntimeException) {
        $denied=true;
    }
    if(!$denied){
        throw new RuntimeException('Cross-process replay accepted AFTER PG18 dump/restore.');
    }
    // A NEW grant with a distinct nonce can still be consumed, proving
    // replay refusal is uniqueness rather than broken/restored permissions.
    $nonceNext='nonce_durable_restore_new_123456';
    $new=Issuer::issue($tenant,$agreement,$month,$account,$ref,$consent,
        $key,$nonceNext,$now-2,$now+100,$secret
    );
    $verified=Verifier::verify($new,$tenant,$agreement,$month,
        $account,$ref,$consent,$now,$ks,$claim
    );
    if(($verified['short_lived_grant_hmac_verified_in_lab']??null)!==true ||
       ($verified['billable']??null)!==false ||
       ($verified['ads_write_enabled']??null)!==false ||
       ($verified['real_s2s_identity_authenticated']??null)!==false) {
        throw new RuntimeException('Fresh restored grant unexpectedly elevated authority.');
    }
}
echo "OK: PG18 dump/restore retains alpha/beta tenant-bound Ads S2S nonce, replays denied across processes; distinct new grants allowed without billing/Ads writes\n";
