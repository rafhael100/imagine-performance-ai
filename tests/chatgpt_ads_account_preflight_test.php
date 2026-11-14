<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsAccountPreflightLab.php';

use Imagine\Performance\Commercial\ChatgptAdsOfflineAdapter as Adapter;
use Imagine\Performance\Commercial\AdsProviderReadOnlyBoundary as Boundary;
use Imagine\Performance\Commercial\ChatgptAdsAccountPreflightLab as Preflight;

function preflightTrue(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('ChatGPT Ads account preflight: '.$message);
}
function preflightReject(callable $fn,string $case):void {
    try{$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('ChatGPT account preflight accepted invalid: '.$case);
}
$tenant='tenant_chatgpt_preflight_1234';
$agreement='agreement_chatgpt_pref_1234';
$month='2026-11';
$zone='America/Sao_Paulo';
$account='adacct_1234567';
$now=1794660000;
$binding=[
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$month,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>'ref_offline_chatgpt_12345678',
    'consent_reference'=>'consent_preflight_1234567',
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-100,'consent_expires_at'=>$now+1000,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible'
];
$plan=Adapter::insightsRequestPlan($account,$month,$zone);
$scope=Boundary::inspect(
    $tenant,$agreement,$month,'chatgpt_ads',$account,$zone,$binding,$plan,$now
);
preflightTrue($scope['network_enabled']===false,'read-only plan has no outbound network');
preflightTrue($scope['method']==='GET' && $scope['path']==='/v1/ad_account/insights',
    'report-only source');

$body=[
    'id'=>$account,
    'name'=>'Demo Synthetic Advertiser',
    'legal_name'=>'Demo Synthetic Advertiser',
    'account_name'=>'Demo API Test Account',
    'brand_name'=>'Demo Brand',
    'url'=>'https://example.invalid/demo',
    'preview_url'=>null,
    'status'=>'active',
    'timezone'=>$zone,
    'currency_code'=>'BRL',
    'review'=>['status'=>'approved']
];
$http=[
    'status'=>200,'content_type'=>'application/json',
    'redirected'=>false,
    'body'=>json_encode($body,JSON_THROW_ON_ERROR)
];
$result=Preflight::inspect($scope,$http);
preflightTrue($result['metadata_shape_matches_expected_scope']===true,
    'reported account identity/BRL/timezone matches link');
preflightTrue($result['synthetic_status_claimed_active']===true,
    'active status appears in fixture');
preflightTrue($result['synthetic_brand_review_claimed_approved']===true,
    'brand approved appears in fixture');
preflightTrue($result['api_key_verified_by_provider']===false,
    'offline mock not valid Ads API authentication');
preflightTrue($result['provider_account_owner_verified']===false,
    'cannot assert client ownership');
preflightTrue($result['portal_iam_authenticated']===false,
    'cannot assert Portal user');
foreach(['network_enabled','provider_spend_verified','finance_approved',
         'billable','asaas_write_enabled','ads_write_enabled'] as $field) {
    preflightTrue($result[$field]===false,'no operational privilege '.$field);
}
$asText=json_encode($result,JSON_THROW_ON_ERROR);
preflightTrue(!str_contains($asText,'Demo Synthetic Advertiser') &&
              !str_contains($asText,'example.invalid') &&
              !str_contains($asText,'Bearer') &&
              !str_contains($asText,'access_token'),
    'no raw account name, URL or secret leaked');

$check=static function(array $data)use($scope,$http):void {
    $h=$http;$h['body']=json_encode($data,JSON_THROW_ON_ERROR);
    Preflight::inspect($scope,$h);
};
$wrong=$body;$wrong['id']='adacct_other';
preflightReject(fn()=>$check($wrong),'API key for foreign ad account');
$wrong=$body;$wrong['currency_code']='USD';
preflightReject(fn()=>$check($wrong),'foreign currency');
$wrong=$body;$wrong['timezone']='UTC';
preflightReject(fn()=>$check($wrong),'wrong account timezone');
$wrong=$body;$wrong['status']='paused';
preflightReject(fn()=>$check($wrong),'paused account');
$wrong=$body;$wrong['review']['status']='in_review';
preflightReject(fn()=>$check($wrong),'active but still in brand review');
$wrong=$body;$wrong['review']['status']='rejected';
preflightReject(fn()=>$check($wrong),'rejected review');
$wrong=$body;$wrong['review']=null;
preflightReject(fn()=>$check($wrong),'no brand review');
$wrong=$body;$wrong['access_token']='unsafe_private_token';
preflightReject(fn()=>$check($wrong),'extra secret field');
$wrong=$body;$wrong['name']='';
preflightReject(fn()=>$check($wrong),'empty advertised name');
$wrong=$body;unset($wrong['preview_url']);
preflightReject(fn()=>$check($wrong),'missing declared account field');
$wrong=$body;$wrong['review']=['status'=>'approved','portal_authenticated'=>true];
preflightReject(fn()=>$check($wrong),'fake trust flag inside nested review');
$bad=$http;$bad['status']=401;
preflightReject(fn()=>Preflight::inspect($scope,$bad),'invalid API key');
$bad=$http;$bad['status']=403;
preflightReject(fn()=>Preflight::inspect($scope,$bad),'key lacks account access');
$bad=$http;$bad['status']=429;
preflightReject(fn()=>Preflight::inspect($scope,$bad),'API quota failure');
$bad=$http;$bad['redirected']=true;
preflightReject(fn()=>Preflight::inspect($scope,$bad),'no redirect');
$bad=$http;$bad['content_type']='text/html';
preflightReject(fn()=>Preflight::inspect($scope,$bad),'bad content type');
$bad=$http;$bad['body']='{broken';
preflightReject(fn()=>Preflight::inspect($scope,$bad),'truncated JSON');
$bad=$http;$bad['body']='[]';
preflightReject(fn()=>Preflight::inspect($scope,$bad),'JSON list instead of ad account');
$bad=$http;$bad['body']='{"review":{}}';
preflightReject(fn()=>Preflight::inspect($scope,$bad),'incomplete account object');
$bad=$http;$bad['body']=str_repeat('A',65537);
preflightReject(fn()=>Preflight::inspect($scope,$bad),'overlong JSON body');
$badScope=$scope;$badScope['account_id']='adacct_other';
preflightReject(fn()=>Preflight::inspect($badScope,$http),'scope mismatch with response');
$badScope=$scope;$badScope['path']='/v1/ads/ad_123/activate';
preflightReject(fn()=>Preflight::inspect($badScope,$http),'Ads mutation path');
$badScope=$scope;$badScope['network_enabled']=true;
preflightReject(fn()=>Preflight::inspect($badScope,$http),'activated network claim');
$badScope=$scope;$badScope['provider_authorization_verified']=true;
preflightReject(fn()=>Preflight::inspect($badScope,$http),'faked official provider authentication');

putenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB=0');
preflightReject(fn()=>Preflight::inspect($scope,$http),'disabled lab');
putenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB=1');
putenv('APP_ENV=production');
preflightReject(fn()=>Preflight::inspect($scope,$http),'production denied');

echo "OK: ChatGPT Ads GET /ad_account mock identity, BRL, IANA timezone, active+brand review approved; no provider auth/Ads billing\n";
