<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-ads-read-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceAdsReadGrantLabIssuer.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalAdsReadS2SLabVerifier.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalAdsReadGrantNonceLab.php';

use App\Homologation\Commerce\PortalPerformanceAdsReadGrantLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalAdsReadS2SLabVerifier as Verifier;
use Imagine\Performance\Commercial\Lab\PgPortalAdsReadGrantNonceLab as NonceStore;

function pgGrantMust(bool $ok,string $label):void {
    if(!$ok) throw new RuntimeException('Durable Ads S2S nonce: '.$label);
}
function pgGrantDeny(callable $f,string $label):void {
    try {$f();} catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Durable Ads S2S nonce accepted: '.$label);
}
if(getenv('APP_ENV')!=='testing' ||
   getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
   getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB')!=='1' ||
   getenv('PERF_PG_ADS_S2S_NONCE_LAB')!=='1' ||
   getenv('PORTAL_PERF_ADS_S2S_READ_GRANT_LAB')!=='1'){
    throw new RuntimeException('PG18 Ads S2S durable CI flags missing.');
}
$dsn=getenv('PERF_LAB_PG_DSN');
$make=static fn(string $role,string $password):PDO=>new PDO($dsn,$role,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$alpha=$make('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$alphaNew=$make('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$make('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$a='tenant_v6multi_alpha_123456';
$b='tenant_v6multi_beta_567890';
$agreement='agreement_s2s_durable_123456';
$month='2026-11';
$key='k_perf_ads_read_01';
$nonce='nonce_durable_s2s_same_123456';
$now=1794660000;
$secret=str_repeat('Z',48);
$credAlpha='ref_pg_durable_alpha_123456';
$credBeta='ref_pg_durable_beta_1234567';
$consentAlpha='consent_pg_durable_alpha_123456';
$consentBeta='consent_pg_durable_beta_1234567';
$grantA=Issuer::issue($a,$agreement,$month,
    'adacct_12345678',$credAlpha,$consentAlpha,
    $key,$nonce,$now-2,$now+100,$secret);
$grantB=Issuer::issue($b,$agreement,$month,
    'adacct_87654321',$credBeta,$consentBeta,
    $key,$nonce,$now-2,$now+100,$secret);
$keys=static fn(string $issuer,string $keyId):?string=>
    $issuer==='imagine-portal-ci'&&$keyId==='k_perf_ads_read_01'?
        str_repeat('Z',48):null;

$sa=new NonceStore($alpha);
$saNew=new NonceStore($alphaNew);
$sb=new NonceStore($beta);
$verify=static fn(array $g,string $tenant,string $acct,string $ref,string $consent,
    NonceStore $store):array=>Verifier::verify(
        $g,$tenant,$agreement,$month,$acct,$ref,$consent,$now,$keys,
        static fn(string $scope,string $n,int $exp):bool=>
            $store->claim($scope,$n,$exp)
    );

foreach([
    [$alpha,$a,0],[$beta,$b,0]
] as [$db,$tenant,$count]) {
    pgGrantMust($db->query(
        'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
    )->fetchColumn()===$tenant,'session_user matches protected registry');
    pgGrantMust(!in_array($db->query(
        "SELECT has_table_privilege(session_user,
          'perf_fin_role_lab.ads_read_grant_nonces','UPDATE')"
    )->fetchColumn(),[true,1,'1','t'],true),'tenant cannot alter existing nonce claims');
}
$bad=$grantA;$bad['ad_account_id']='adacct_00000000';
pgGrantDeny(fn()=> $verify($bad,$a,'adacct_00000000',$credAlpha,
    $consentAlpha,$sa),'ad account ID tampering rejected by HMAC before SQL');
$bad=$grantA;$bad['permission']='ads.write';
pgGrantDeny(fn()=> $verify($bad,$a,'adacct_12345678',$credAlpha,
    $consentAlpha,$sa),'write scope rejected before SQL');
$bad=$grantA;$bad['signature']=str_repeat('a',64);
pgGrantDeny(fn()=> $verify($bad,$a,'adacct_12345678',$credAlpha,
    $consentAlpha,$sa),'wrong signature never consumes durable nonce');
pgGrantDeny(fn()=> $verify($grantA,$a,'adacct_12345678',$credAlpha,
    $consentAlpha,$sb),'valid signed ALPHA grant cannot be claimed by BETA SQL login');
pgGrantDeny(fn()=> $verify($grantB,$b,'adacct_87654321',$credBeta,
    $consentBeta,$sa),'valid signed BETA grant cannot be claimed by ALPHA SQL login');

// Deliberately forge old custom GUC for Beta in Alpha connection: real
// authenticated session_user mapping remains Alpha, so Alpha grant works.
$alpha->exec("SET app.tenant_id TO 'tenant_v6multi_beta_567890'");
pgGrantMust($alpha->query(
    'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
)->fetchColumn()===$a,'forged tenant GUC did not reassign SQL role');
$first=$verify($grantA,$a,'adacct_12345678',$credAlpha,$consentAlpha,$sa);
pgGrantMust($first['short_lived_grant_hmac_verified_in_lab']===true,
    'real Portal HMAC validated before persistent claim');
pgGrantMust($first['real_s2s_identity_authenticated']===false &&
            $first['real_api_request_authorized']===false &&
            $first['billable']===false,'PG18 does not grant real Ads or financial permission');

// Separate PDO process-equivalent connection must see the persisted claim.
pgGrantDeny(fn()=> $verify($grantA,$a,'adacct_12345678',$credAlpha,
    $consentAlpha,$saNew),'same signed grant replay refused from another connection');
pgGrantMust(!$saNew->claim($a,$nonce,$now+100),
    'raw reused nonce denied even without rechecking same grant');
pgGrantMust(!$sa->claim($b,'nonce_other_tenant_123456',$now+100),
    'ALPHA login cannot claim BETA nonce');
$second=$verify($grantB,$b,'adacct_87654321',$credBeta,$consentBeta,$sb);
pgGrantMust($second['short_lived_grant_hmac_verified_in_lab']===true,
    'BETA can independently use same nonce scoped to BETA');
pgGrantDeny(fn()=> $verify($grantB,$b,'adacct_87654321',$credBeta,
    $consentBeta,$sb),'BETA replay rejected');

foreach([
    [$alpha,$a,$b],[$beta,$b,$a]
] as [$db,$real,$fake]){
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:fake,true)")
        ->execute(['fake'=>$fake]);
    pgGrantMust($db->query(
        'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
    )->fetchColumn()===$real,'session identity withstands forged GUC');
    pgGrantMust((int)$db->query(
        'SELECT count(*) FROM perf_fin_role_lab.ads_read_grant_nonces'
    )->fetchColumn()===1,'RLS shows only own committed grant nonce');
    $db->rollBack();
}
putenv('PERF_PG_ADS_S2S_NONCE_LAB=0');
pgGrantMust(!$saNew->claim($a,'nonce_new_disabled_123456',$now+100),
    'disabled lab cannot write nonce');
putenv('PERF_PG_ADS_S2S_NONCE_LAB=1');
putenv('APP_ENV=production');
pgGrantMust(!$saNew->claim($a,'nonce_new_production_123456',$now+100),
    'production must not accept lab nonce claims');
echo "OK: separate PostgreSQL 18 logins persist Portal Ads S2S nonce by tenant, reject cross-session replay and forged app.tenant_id, no Ads writes or billing\n";
