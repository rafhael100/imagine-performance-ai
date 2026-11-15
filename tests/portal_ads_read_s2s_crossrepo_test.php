<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-ads-read-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceAdsReadGrantLabIssuer.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalAdsReadS2SLabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalAdsGrantedReadOnlySessionLab.php';
require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsReadOnlyHttpResponseReview.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsAccountPreflightLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsExactWireSpendLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsReadOnlySessionLab.php';

use App\Homologation\Commerce\PortalPerformanceAdsReadGrantLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalAdsReadS2SLabVerifier as Verifier;
use Imagine\Performance\Commercial\PortalAdsGrantedReadOnlySessionLab as GrantedSession;

function s2sAssert(bool $ok,string $msg):void {
    if(!$ok)throw new RuntimeException('Portal→Performance Ads read S2S: '.$msg);
}
function s2sDeny(callable $fn,string $msg):void {
    try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Portal→Performance Ads read S2S accepted: '.$msg);
}
foreach([
    'APP_ENV'=>'testing',
    'PORTAL_PERF_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_PORTAL_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_ADS_READONLY_BOUNDARY_LAB'=>'1',
    'PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB'=>'1',
    'PERF_CHATGPT_EXACT_WIRE_MONEY_LAB'=>'1',
    'PERF_CHATGPT_READONLY_SESSION_LAB'=>'1'
] as $name=>$expected){
    if(getenv($name)!==$expected)throw new RuntimeException('Missing lab scope: '.$name);
}
$tenant='tenant_crossrepo_grant_1234';
$agreement='agreement_crossrepo_grant_1234';
$month='2026-11';
$account='adacct_12345678';
$cred='ref_crossrepo_grant_12345678';
$consent='consent_crossrepo_grant_12345678';
$keyId='k_perf_ads_read_01';
$nonce='nonce_crossrepo_grant_12345678';
$now=1794660000;
$secret=str_repeat('Z',48);
$grant=Issuer::issue(
    $tenant,$agreement,$month,$account,$cred,$consent,
    $keyId,$nonce,$now-1,$now+90,$secret
);
$nonces=[];$claims=0;
$claim=static function(string $t,string $n,int $expires)use(&$nonces,&$claims):bool{
    $claims++;
    $id=$t."\0".$n;
    if(isset($nonces[$id]))return false;
    $nonces[$id]=$expires;
    return true;
};
$resolve=static fn(string $issuer,string $key):?string=>
    $issuer==='imagine-portal-ci'&&$key==='k_perf_ads_read_01'?
        str_repeat('Z',48):null;
$verify=static function(array $signed,?string $who=null,?string $contract=null,
    ?string $acct=null,?string $ref=null,?string $optIn=null,?int $clock=null)
    use($tenant,$agreement,$month,$account,$cred,$consent,$now,$resolve,$claim):array {
    return Verifier::verify(
        $signed,$who??$tenant,$contract??$agreement,$month,
        $acct??$account,$ref??$cred,$optIn??$consent,
        $clock??$now,$resolve,$claim
    );
};
$bad=$grant;$bad['ad_account_id']='adacct_attacker';
s2sDeny(fn()=> $verify($bad,null,null,'adacct_attacker'),
    'ad account tampering denied by HMAC');
$bad=$grant;$bad['can_mutate_ads']=true;
s2sDeny(fn()=> $verify($bad),'write authority flag denied');
$bad=$grant;$bad['permission']='ads.campaigns.write';
s2sDeny(fn()=> $verify($bad),'only ads.insights.read');
$bad=$grant;$bad['audience']='imagine-performance-ai.campaign-mutate';
s2sDeny(fn()=> $verify($bad),'wrong S2S audience');
$bad=$grant;$bad['signature']=str_repeat('a',64);
s2sDeny(fn()=> $verify($bad),'invalid HMAC signature');
$bad=$grant;$bad['Authorization']='Bearer private';
s2sDeny(fn()=> $verify($bad),'unexpected token field in grant');
s2sDeny(fn()=> $verify($grant,'tenant_other_123456'),'different tenant');
s2sDeny(fn()=> $verify($grant,null,'agreement_other_123456'),'different agreement');
s2sDeny(fn()=> $verify($grant,null,null,'adacct_foreign123'),'wrong Ads account');
s2sDeny(fn()=> $verify($grant,null,null,null,'ref_other_123456789012'),
    'credential reference mismatch');
s2sDeny(fn()=> $verify($grant,null,null,null,null,
    'consent_other_123456'),'different consent');
s2sDeny(fn()=> $verify($grant,null,null,null,null,null,$now+100),
    'expired grant');
s2sAssert($claims===0,'invalid grants cannot claim nonce');

$valid=$verify($grant);
s2sAssert($valid['short_lived_grant_hmac_verified_in_lab']===true,
    'real pinned Portal issuer signed correct agreement+account+ref');
s2sAssert($valid['signed_permission']==='ads.insights.read',
    'only read scope');
s2sAssert($valid['credential_reference']===$cred,'same opaque grant ref');
s2sAssert($valid['real_s2s_identity_authenticated']===false &&
          $valid['portal_iam_authenticated']===false &&
          $valid['provider_account_owner_verified']===false &&
          $valid['real_api_request_authorized']===false,
    'offline signed grant is NOT real IAM or provider proof');
s2sAssert($claims===1,'successful grant consumes CI nonce once');
s2sDeny(fn()=> $verify($grant),'replay denied');
s2sAssert($claims===2,'replay detected by nonce callback');

$zone='America/Sao_Paulo';
$binding=[
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$month,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>$cred,'consent_reference'=>$consent,
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-100,'consent_expires_at'=>$now+1000,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible'
];
$dt=new DateTimeImmutable('2026-11-01 00:00:00',new DateTimeZone($zone));
$start=$dt->getTimestamp();$end=$dt->modify('+1 day')->getTimestamp();
$id="start=$start:end=$end:entity_id=$account";
$metadata=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'Mock Advertiser',
        'url'=>'https://example.invalid','preview_url'=>null,
        'status'=>'active','timezone'=>$zone,'currency_code'=>'BRL',
        'review'=>['status'=>'approved']
    ],JSON_THROW_ON_ERROR)
];
$insights=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>'{"object":"list","data":[{"id":'.
        json_encode($id,JSON_THROW_ON_ERROR).
        ',"start_time":'.$start.',"end_time":'.$end.
        ',"readable_time":"2026-11-01","timezone":'.
        json_encode($zone,JSON_THROW_ON_ERROR).
        ',"impressions":12,"clicks":2,"spend":18.42}],'.
        '"count":1,"first_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"last_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"has_more":false}'
];
$seen=[];
$transport=static function(array $descriptor)use(&$seen,$metadata,$insights):array {
    $seen[]=$descriptor;
    return $descriptor['operation']==='ad_account_metadata'?$metadata:$insights;
};
$sessionGrant=Issuer::issue(
    $tenant,$agreement,$month,$account,$cred,$consent,
    $keyId,'nonce_crossrepo_session_123456789',$now-1,$now+90,$secret
);
$run=GrantedSession::collect(
    $sessionGrant,$binding,$tenant,$agreement,$month,$account,
    $cred,$consent,$zone,$now,$resolve,$claim,$transport
);
s2sAssert(count($seen)===2,'only two GETs after signed grant verification');
s2sAssert($seen[0]['credential_reference']===$cred &&
          $seen[1]['credential_reference']===$cred,
    'Portal grant and both read sessions use SAME credential ref');
s2sAssert($run['synthetic_account_report']['spend_cents']===1842,
    'read-only session still derives cents from numeric JSON');
s2sAssert($run['billable']===false &&
          $run['api_key_verified_by_provider']===false &&
          $run['ads_write_enabled']===false,
    'grant NEVER authorizes billing, Ads mutation or real API');
$badBinding=$binding;$badBinding['credential_reference']='ref_someone_else_123456';
$newGrant=Issuer::issue(
    $tenant,$agreement,$month,$account,$cred,$consent,
    $keyId,'nonce_crossrepo_refguard_12345678',$now-1,$now+90,$secret
);
$before=$claims;
s2sDeny(fn()=>GrantedSession::collect(
    $newGrant,$badBinding,$tenant,$agreement,$month,$account,
    $cred,$consent,$zone,$now,$resolve,$claim,$transport
),'same signed grant cannot be used with a different account-credential link');
s2sAssert($claims===$before,'wrong credential ref blocked before claiming nonce');
s2sAssert(count($seen)===2,'invalid binding never reached transport');
$badBinding=$binding;$badBinding['consent_reference']='consent_other_123456';
s2sDeny(fn()=>GrantedSession::collect(
    $newGrant,$badBinding,$tenant,$agreement,$month,$account,
    $cred,$consent,$zone,$now,$resolve,$claim,$transport
),'revoked/replaced consent ref cannot be used with signed grant');
s2sAssert($claims===$before && count($seen)===2,
    'wrong consent ref blocked without replay claim or transport');
s2sDeny(fn()=>GrantedSession::collect(
    $sessionGrant,$binding,$tenant,$agreement,$month,$account,
    $cred,$consent,$zone,$now,$resolve,$claim,$transport
),'the same signed S2S grant cannot be replayed after one session');
s2sAssert(count($seen)===2,'S2S replay cannot invoke HTTP fixture');

putenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB=0');
s2sDeny(fn()=> $verify($grant),'grant verifier disabled');
putenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB=1');
putenv('APP_ENV=production');
s2sDeny(fn()=> $verify($grant),'production not allowed');
echo "OK: real pinned Portal signed Ads-read grant verified by Performance across repos, scope/credential/ref/TTL/HMAC/replay, two GETs same ref, no network/finance/Ads writes\n";
