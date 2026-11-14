<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsReadOnlyHttpResponseReview.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsAccountPreflightLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsExactWireSpendLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsReadOnlySessionLab.php';

use Imagine\Performance\Commercial\ChatgptAdsReadOnlySessionLab as Session;

function sAssert(bool $ok,string $label):void{
    if(!$ok)throw new RuntimeException('Read-only same-credential test: '.$label);
}
function sDeny(callable $fn,string $label):void{
    try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Read-only same-credential test accepted: '.$label);
}
if(getenv('APP_ENV')!=='testing'||
   getenv('PERF_ADS_READONLY_BOUNDARY_LAB')!=='1'||
   getenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB')!=='1'||
   getenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB')!=='1'||
   getenv('PERF_CHATGPT_READONLY_SESSION_LAB')!=='1'){
    throw new RuntimeException('Offline session gates missing.');
}
$tenant='tenant_readonly_session_1234';
$agreement='agreement_readonly_sess_1234';
$period='2026-11';
$account='adacct_12345678';
$zone='America/Sao_Paulo';
$now=1794660000;
$ref='ref_fake_vault_session_1234567890';
$binding=[
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$period,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>$ref,
    'consent_reference'=>'consent_session_1234567890123',
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-30,'consent_expires_at'=>$now+600,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible'
];
$accountHttp=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'Only CI Mock LLC','url'=>'https://example.invalid',
        'preview_url'=>null,'status'=>'active',
        'currency_code'=>'BRL','timezone'=>$zone,
        'review'=>['status'=>'approved']
    ],JSON_THROW_ON_ERROR)
];
$local=new DateTimeImmutable('2026-11-01 00:00:00',new DateTimeZone($zone));
$start=$local->getTimestamp();
$end=$local->modify('+1 day')->getTimestamp();
$id="start=$start:end=$end:entity_id=$account";
$insightsHttp=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>'{"object":"list","count":1,"data":[{"id":'.
        json_encode($id,JSON_THROW_ON_ERROR).',"start_time":'.$start.
        ',"end_time":'.$end.',"readable_time":"2026-11-01",'.
        '"timezone":'.json_encode($zone,JSON_THROW_ON_ERROR).
        ',"spend":18.42,"impressions":100,"clicks":12}],'.
        '"first_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"last_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"has_more":false}'
];
$trace=[];
$fixture=static function(array $request)use(&$trace,$accountHttp,$insightsHttp):array {
    $trace[]=$request;
    return $request['operation']==='ad_account_metadata' ? $accountHttp : $insightsHttp;
};
$run=static function(?array $bound=null,?callable $transport=null,
    ?string $who=null,?string $contract=null,?string $acct=null,?string $month=null)
    use($binding,$fixture,$tenant,$agreement,$account,$period,$zone,$now):array {
    return Session::collect(
        $who??$tenant,$contract??$agreement,$month??$period,$acct??$account,
        $zone,$bound??$binding,$now,$transport??$fixture
    );
};
$out=$run();
sAssert(count($trace)===2,'exactly two read operations');
sAssert($out['state']==='synthetic_same_credential_two_gets_no_network','only simulation');
sAssert($out['sequence']===['ad_account_metadata','ad_account_insights'],
    'identity check precedes Insights');
sAssert($trace[0]['path']==='/v1/ad_account','metadata endpoint');
sAssert($trace[1]['path']==='/v1/ad_account/insights','Insights endpoint');
sAssert($trace[0]['credential_reference']===$ref &&
        $trace[1]['credential_reference']===$ref,
    'SAME opaque credential reference for both GETs');
sAssert($trace[0]['query']===[],'GET /ad_account has NO query');
sAssert($trace[1]['query']['aggregation_level']==='ad_account',
    'only account-level Insights');
sAssert($trace[1]['query']['limit']===35,'daily report bounded page');
sAssert($trace[1]['query']['fields[]']===[
    'metadata.readable_time','ad_account.spend',
    'ad_account.impressions','ad_account.clicks'
],'exact allowlisted field selection');
foreach($trace as $request) {
    sAssert($request['scheme']==='https' && $request['host']==='api.ads.openai.com' &&
            $request['method']==='GET','never issue POST/DELETE/mutable endpoint');
    sAssert($request['network_enabled']===false &&
            $request['follow_redirects']===false &&
            $request['automatic_retry']===false &&
            $request['ads_write_enabled']===false,'egress and writes disabled');
    sAssert($request['headers_provided_by_caller']===false &&
            !isset($request['Authorization']) &&
            !isset($request['access_token']),'no token or client header available');
}
sAssert($out['synthetic_account_report']['spend_cents']===1842,
    'exact numeric spend 18.42 maps 1842 cents');
sAssert($out['numeric_money_parsed_without_binary_float']===true,
    'must remain lexical decimal');
foreach([
    'actual_network_executed','provider_account_authenticated',
    'api_key_verified_by_provider','provider_spend_verified',
    'finance_approved','billable','asaas_write_enabled','ads_write_enabled'
] as $flag){
    sAssert(($out[$flag]??null)===false,'no real authority: '.$flag);
}
$redacted=json_encode($out,JSON_THROW_ON_ERROR);
sAssert(!str_contains($redacted,'Only CI Mock LLC') &&
        !str_contains($redacted,'example.invalid') &&
        !str_contains($redacted,'Bearer ') &&
        !str_contains($redacted,'client_secret'),'PII and secret excluded');

// Metadata failure must halt BEFORE any request for Insights, while a 200
// pretending to belong to the wrong account must also fail closed.
$events=[];
$badMetadata=$accountHttp;
$badMetadata['body']=json_encode([
    'id'=>'adacct_foreign_999','name'=>'Wrong Account',
    'url'=>'https://example.invalid','preview_url'=>null,'status'=>'active',
    'timezone'=>$zone,'currency_code'=>'BRL','review'=>['status'=>'approved']
],JSON_THROW_ON_ERROR);
$failBeforeInsights=static function(array $bad)use(&$events,$insightsHttp):callable {
    return static function(array $request)use(&$events,$bad,$insightsHttp):array {
        $events[]=$request['operation'];
        return $request['operation']==='ad_account_metadata'?$bad:$insightsHttp;
    };
};
sDeny(fn()=> $run(null,$failBeforeInsights($badMetadata)),'wrong account metadata');
sAssert($events===['ad_account_metadata'],'foreign account must not issue Insights');
$events=[];
$badMetadata=$accountHttp;
$badMetadata['status']=401;
$badMetadata['body']='{"error":"Bearer SENSITIVE_TOKEN"}';
sDeny(fn()=> $run(null,$failBeforeInsights($badMetadata)),'invalid/revoked account API key');
sAssert($events===['ad_account_metadata'],'401 stops before Insights');
$events=[];
$badMetadata=$accountHttp;
$badMetadata['status']=403;
sDeny(fn()=> $run(null,$failBeforeInsights($badMetadata)),'key lacks permission');
sAssert($events===['ad_account_metadata'],'403 stops before Insights');
$events=[];
$badMetadata=$accountHttp;
$badMetadata['body']=str_replace('"status":"approved"','"status":"in_review"',
    $badMetadata['body']);
sDeny(fn()=> $run(null,$failBeforeInsights($badMetadata)),'review pending');
sAssert($events===['ad_account_metadata'],'pending review stops before Insights');
$events=[];
$badMetadata=$accountHttp;
$badMetadata['body']=str_replace('"currency_code":"BRL"','"currency_code":"USD"',
    $badMetadata['body']);
sDeny(fn()=> $run(null,$failBeforeInsights($badMetadata)),'currency mismatch');
sAssert($events===['ad_account_metadata'],'USD stops before Insights');

// Failed Insights must return NO report, with no automatic retries or
// serialization of the provider's error payload.
foreach([401,403,429,503] as $code) {
    $events=[];
    $bad=$insightsHttp;
    $bad['status']=$code;
    $bad['body']='{"error":"Bearer PRIVATE_KEY_DO_NOT_LEAK"}';
    $fn=static function(array $request)use(&$events,$accountHttp,$bad):array{
        $events[]=$request['operation'];
        return $request['operation']==='ad_account_metadata'?$accountHttp:$bad;
    };
    sDeny(fn()=> $run(null,$fn),'report HTTP error '.$code);
    sAssert($events===['ad_account_metadata','ad_account_insights'],
        'no retry after report failure '.$code);
}
$bad=$insightsHttp;
$bad['body']=str_replace('"has_more":false','"has_more":true',$bad['body']);
sDeny(fn()=> $run(null,static function(array $r)use($accountHttp,$bad):array{
    return $r['operation']==='ad_account_metadata'?$accountHttp:$bad;
}),'incomplete Insights pagination must not be accepted');
$bad=$insightsHttp;
$bad['body']=str_replace('"spend":18.42','"spend":18.4201',$bad['body']);
sDeny(fn()=> $run(null,static function(array $r)use($accountHttp,$bad):array{
    return $r['operation']==='ad_account_metadata'?$accountHttp:$bad;
}),'sub-cent JSON spend never silently rounded');

// Ref/consent/contract scope is verified before executing any transport.
$events=[];
$track=static function(array $r)use(&$events,$accountHttp,$insightsHttp):array{
    $events[]=$r['operation'];
    return $r['operation']==='ad_account_metadata'?$accountHttp:$insightsHttp;
};
$wrong=$binding;$wrong['credential_reference']='Bearer stolen';
sDeny(fn()=> $run($wrong,$track),'raw secret in place of vault reference');
sAssert($events===[],'reject credential injection before any request');
$wrong=$binding;$wrong['consent_state']='revoked';
sDeny(fn()=> $run($wrong,$track),'revoked consent');
sAssert($events===[],'reject revoked consent before any request');
$wrong=$binding;$wrong['consent_expires_at']=$now;
sDeny(fn()=> $run($wrong,$track),'expired consent');
sAssert($events===[],'reject expired consent before any request');
sDeny(fn()=> $run($binding,$track,'tenant_other_123456'),'cross-tenant spoof');
sAssert($events===[],'reject tenant spoof before transport');
sDeny(fn()=> $run($binding,$track,null,'agreement_other_123456'),'other agreement');
sAssert($events===[],'reject agreement mismatch before transport');
sDeny(fn()=> $run($binding,$track,null,null,'adacct_other_123'),'foreign account');
sAssert($events===[],'reject account mismatch before transport');
sDeny(fn()=> $run($binding,$track,null,null,null,'2026-12'),'different month');
sAssert($events===[],'reject month mismatch before transport');

$providerError=static function(array $request):array{
    throw new RuntimeException('Bearer SUPER_SECRET_TOKEN');
};
try { $run(null,$providerError); }
catch(RuntimeException $e){
    sAssert(!str_contains($e->getMessage(),'SUPER_SECRET'),
        'transport exception redacted');
}
putenv('PERF_CHATGPT_READONLY_SESSION_LAB=0');
sDeny(fn()=> $run(),'flag disabled');
putenv('PERF_CHATGPT_READONLY_SESSION_LAB=1');
putenv('APP_ENV=production');
sDeny(fn()=> $run(),'production disabled');

echo "OK: identical opaque credential ref for GET /ad_account and GET /insights, strict fail-closed validation, account ID/consent and numeric cents; no network/Ads writes/billing\n";
