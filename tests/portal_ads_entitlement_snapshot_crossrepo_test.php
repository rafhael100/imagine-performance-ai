<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-ads-read-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceAdsReadGrantLabIssuer.php';
foreach ([
    'PortalAdsReadS2SLabVerifier', 'PortalAdsGrantedReadOnlySessionLab',
    'PortalAdsEntitlementSnapshotLab', 'PortalAdsEntitledReadOnlySessionLab',
    'SyntheticProviderSpendAdapter', 'ChatgptAdsOfflineAdapter',
    'AdsProviderReadOnlyBoundary', 'AdsReadOnlyHttpResponseReview',
    'ChatgptAdsAccountPreflightLab', 'ExactJsonNumberLexicalParser',
    'ChatgptAdsExactWireSpendLab', 'ChatgptAdsReadOnlySessionLab',
] as $name) {
    require_once __DIR__.'/../src/Domain/Commercial/'.$name.'.php';
}

use App\Homologation\Commerce\PortalPerformanceAdsReadGrantLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalAdsEntitledReadOnlySessionLab as Gate;

function entitlementAssert(bool $ok, string $why): void {
    if (!$ok) throw new RuntimeException('Entitlement gate test: '.$why);
}
function entitlementReject(callable $fn, string $why): void {
    try { $fn(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException('Entitlement gate accepted invalid state: '.$why);
}
foreach ([
    'APP_ENV'=>'testing',
    'PORTAL_PERF_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_PORTAL_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB'=>'1',
    'PERF_ADS_READONLY_BOUNDARY_LAB'=>'1',
    'PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB'=>'1',
    'PERF_CHATGPT_EXACT_WIRE_MONEY_LAB'=>'1',
    'PERF_CHATGPT_READONLY_SESSION_LAB'=>'1',
] as $key=>$required) {
    if (getenv($key)!==$required) throw new RuntimeException('Missing CI scope: '.$key);
}
$tenant='tenant_entitlement_demo_123456';
$agreement='agreement_entitlement_demo_123456';
$period='2026-11';
$account='adacct_12345678';
$credential='ref_entitlement_demo_1234567890';
$consent='consent_entitlement_demo_1234567890';
$zone='America/Sao_Paulo';
$now=1794660000;
$keyId='k_entitlement_demo_01';
$secret=str_repeat('Q',48);

$issue=static fn(string $nonce):array=>Issuer::issue(
    $tenant,$agreement,$period,$account,$credential,$consent,
    $keyId,$nonce,$now-1,$now+90,$secret
);
$binding=[
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$period,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>$credential,'consent_reference'=>$consent,
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-100,'consent_expires_at'=>$now+1000,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible',
];
$policyFixture=[
    'source'=>'synthetic_registry',
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$period,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>$credential,'consent_reference'=>$consent,
    'agreement_status'=>'synthetic_active',
    'agreement_valid_from'=>$now-300,
    'agreement_valid_until'=>$now+3600,
    'account_status'=>'synthetic_active',
    'owner_tenant_id'=>$tenant,
    'consent_status'=>'synthetic_granted',
    'consent_granted_at'=>$now-100,
    'consent_expires_at'=>$now+1000,
    'consent_revoked_at'=>null,
    'permission'=>'ads.insights.read',
    'observed_at'=>$now,
];
$lookups=0;
$resolvePolicy=static function(array $selector)use(&$lookups,&$policyFixture,$tenant,$agreement,$period,$account):array{
    $lookups++;
    entitlementAssert($selector===[
        'tenant_id'=>$tenant,'agreement_id'=>$agreement,
        'period'=>$period,'account_id'=>$account
    ],'registry selectors must be independent, not copied from signed input');
    return $policyFixture;
};
$resolveKey=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_entitlement_demo_01'
        ?str_repeat('Q',48):null;
$claims=0;$claimed=[];
$claim=static function(string $t,string $nonce,int $expires)use(&$claims,&$claimed):bool{
    $claims++;
    $key=$t."\0".$nonce;
    if(isset($claimed[$key]))return false;
    $claimed[$key]=$expires;
    return true;
};
$start=(new DateTimeImmutable('2026-11-01 00:00:00',new DateTimeZone($zone)))->getTimestamp();
$end=(new DateTimeImmutable('2026-11-02 00:00:00',new DateTimeZone($zone)))->getTimestamp();
$id="start=$start:end=$end:entity_id=$account";
$metadata=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'Imaginary Sandbox Business',
        'url'=>'https://example.invalid','preview_url'=>null,
        'status'=>'active','timezone'=>$zone,'currency_code'=>'BRL',
        'review'=>['status'=>'approved'],
    ],JSON_THROW_ON_ERROR),
];
$insights=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>'{"object":"list","data":[{"id":'.
        json_encode($id,JSON_THROW_ON_ERROR).',"start_time":'.$start.
        ',"end_time":'.$end.',"readable_time":"2026-11-01",'.
        '"timezone":'.json_encode($zone,JSON_THROW_ON_ERROR).
        ',"impressions":10,"clicks":2,"spend":18.42}],'.
        '"count":1,"first_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"last_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"has_more":false}',
];
$requests=[];
$transport=static function(array $descriptor)use(&$requests,$metadata,$insights):array{
    $requests[]=$descriptor;
    return $descriptor['operation']==='ad_account_metadata'?$metadata:$insights;
};
$run=static function(array $grant,?array $b=null,?callable $reader=null)
    use($tenant,$agreement,$period,$account,$credential,$consent,$zone,$now,
        $binding,$resolvePolicy,$resolveKey,$claim,$transport):array{
    return Gate::collect(
        $grant,$b??$binding,$tenant,$agreement,$period,$account,
        $credential,$consent,$zone,$now,$reader??$resolvePolicy,
        $resolveKey,$claim,$transport
    );
};
$validGrant=$issue('nonce_entitlement_valid_1234567');
$ok=$run($validGrant);
entitlementAssert($lookups===1 && $claims===1 && count($requests)===2,
    'policy evaluated before nonce and both provider mock GETs');
entitlementAssert($ok['entitlement_snapshot_verified_in_lab']===true &&
    preg_match('/\A[a-f0-9]{64}\z/D',$ok['entitlement_snapshot_sha256'])===1,
    'snapshot fingerprint available without provider identity assertion');
entitlementAssert($ok['synthetic_account_report']['spend_cents']===1842,
    'existing exact numeric money parser still applies');
entitlementAssert($requests[0]['credential_reference']===$credential &&
    $requests[1]['credential_reference']===$credential,'same credential reference');
foreach ([
    'portal_iam_authenticated','consent_verified_in_real_database',
    'provider_account_owner_verified','real_s2s_identity_authenticated',
    'real_api_request_authorized','actual_network_executed',
    'provider_spend_verified','finance_approved','billable',
    'asaas_write_enabled','ads_write_enabled',
] as $flag){
    entitlementAssert(($ok[$flag]??null)===false,'no operational authority '.$flag);
}
entitlementReject(fn()=> $run($validGrant),'nonce cannot be replayed');
entitlementAssert(count($requests)===2,'replay cannot issue another GET');

$cases=[
    ['agreement_status','terminated','terminated contract'],
    ['agreement_valid_until',$now,'contract expiry'],
    ['agreement_valid_from',$now,'contract not active when grant signed'],
    ['account_status','disconnected','advertiser disconnected'],
    ['owner_tenant_id','tenant_foreign_123456','account no longer owned by tenant'],
    ['consent_status','revoked','revoked consent'],
    ['consent_revoked_at',$now-1,'revocation timestamp'],
    ['consent_expires_at',$now,'expired consent'],
    ['consent_granted_at',$now,'consent after grant issuance'],
    ['credential_reference','ref_changed_1234567890','rotated credential'],
    ['consent_reference','consent_replaced_123456789','replaced opt-in'],
    ['permission','ads.campaigns.write','read-only scope lost'],
    ['observed_at',$now-6,'stale registry snapshot'],
    ['observed_at',$now+1,'future registry snapshot'],
    ['source','not_a_trusted_registry','unexpected provenance'],
];
$i=0;
foreach($cases as [$field,$bad,$why]){
    $old=$policyFixture;
    $policyFixture[$field]=$bad;
    $nonce='nonce_entitlement_negative_'.str_pad((string)$i++,8,'0',STR_PAD_LEFT);
    $beforeClaims=$claims;
    $beforeReq=count($requests);
    entitlementReject(fn()=> $run($issue($nonce)),$why);
    entitlementAssert($claims===$beforeClaims && count($requests)===$beforeReq,
        $why.' must be rejected BEFORE nonce claim or GET');
    $policyFixture=$old;
}
$old=$policyFixture;
$policyFixture['unexpected_token']='Bearer SENSITIVE';
entitlementReject(fn()=> $run($issue('nonce_entitlement_extra_123456')),'unknown policy field');
$policyFixture=$old;
unset($policyFixture['consent_status']);
entitlementReject(fn()=> $run($issue('nonce_entitlement_missing_12345')),'missing policy field');
$policyFixture=$old;

$bad=$issue('nonce_entitlement_signature_12345');
$bad['signature']=str_repeat('b',64);
$beforeClaims=$claims;$beforeReq=count($requests);
entitlementReject(fn()=> $run($bad),'forged grant signature');
entitlementAssert($claims===$beforeClaims && count($requests)===$beforeReq,
    'forged HMAC cannot consume nonce or issue GET');
$bad=$issue('nonce_entitlement_tenant_123456');
$bad['tenant_id']='tenant_fake_attacker_123456';
$beforeLookups=$lookups;
entitlementReject(fn()=> $run($bad),'untrusted tenant scope');
entitlementAssert($lookups===$beforeLookups,'scope checked before independent lookup');
$badBinding=$binding;
$badBinding['consent_reference']='consent_replaced_1234567890';
$beforeClaims=$claims;$beforeReq=count($requests);
entitlementReject(fn()=> $run($issue('nonce_entitlement_binding_12345'),$badBinding),
    'grant/binding mismatch after policy preflight');
entitlementAssert($claims===$beforeClaims && count($requests)===$beforeReq,
    'binding mismatch cannot consume nonce');

$rejectingResolver=static function(array $selector):array{
    throw new RuntimeException('Bearer NEVER_EXPOSE_THIS');
};
try {
    $run($issue('nonce_entitlement_resolver_12345'),null,$rejectingResolver);
    throw new RuntimeException('Resolver exception unexpectedly accepted');
} catch(RuntimeException $e) {
    entitlementAssert(!str_contains($e->getMessage(),'NEVER_EXPOSE_THIS'),
        'resolver exception must not leak details');
}
putenv('PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB=0');
entitlementReject(fn()=> $run($issue('nonce_entitlement_disabled_12345')),'gate disabled');
putenv('PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB=1');
putenv('APP_ENV=production');
entitlementReject(fn()=> $run($validGrant),'production denied');

echo "OK: independent simulated Portal account/contract/consent snapshot gating before HMAC nonce and two read-only GET fixtures; no real IAM, media, billing or ads writes\n";
