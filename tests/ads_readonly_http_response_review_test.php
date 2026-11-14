<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Domain/Commercial/AdsReadOnlyHttpResponseReview.php';
use Imagine\Performance\Commercial\AdsReadOnlyHttpResponseReview as Review;

function requireHttp(bool $condition,string $case):void{
    if(!$condition)throw new RuntimeException('Provider HTTP classification: '.$case);
}
function rejectHttp(callable $func,string $case):void{
    try{$func();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Provider response accepted unsafe: '.$case);
}
$google=[
    'status'=>200,'content_type'=>'application/json',
    'redirected'=>false,
    'body'=>json_encode([
        ['requestId'=>'synthetic-id','results'=>[
            ['segments'=>['date'=>'2026-11-01'],'metrics'=>['costMicros'=>'100000000']]
        ]]
    ],JSON_THROW_ON_ERROR)
];
$meta=[
    'status'=>200,'content_type'=>'application/json',
    'redirected'=>false,
    'body'=>json_encode([
        'data'=>[['account_id'=>'987654321','spend'=>'10.00']],
        'paging'=>['cursors'=>['after'=>'synthetic_cursor']]
    ],JSON_THROW_ON_ERROR)
];
$chatgpt=[
    'status'=>200,'content_type'=>'application/json; charset=UTF-8',
    'redirected'=>false,
    'body'=>json_encode([
        'object'=>'list','count'=>1,
        'data'=>[[
            'id'=>'start=synthetic',
            'readable_time'=>'2026-11-01',
            'spend'=>40.75
        ]],
        'first_id'=>'synthetic_first',
        'last_id'=>'synthetic_last',
        'has_more'=>false
    ],JSON_THROW_ON_ERROR)
];
foreach([
    'google_ads'=>$google,'meta_ads'=>$meta,'chatgpt_ads'=>$chatgpt
] as $name=>$fixture) {
    $out=Review::assess($name,$fixture);
    requireHttp($out['synthetic_json_shape_ok']===true,$name.' JSON shape');
    requireHttp($out['synthetic_row_count']===1,$name.' row count');
    requireHttp($out['provider_identity_authenticated']===false,$name.' not authenticated');
    requireHttp($out['provider_spend_verified']===false,$name.' spend not verified');
    requireHttp($out['safe_to_bill']===false,$name.' no billing');
    requireHttp($out['automatic_retry_enabled']===false,$name.' no automatic retry');
    requireHttp($out['network_enabled']===false,$name.' no network');
    requireHttp($out['asaas_write_enabled']===false,$name.' no Asaas');
    requireHttp($out['ads_write_enabled']===false,$name.' no ad writes');
    $bad=$fixture;$bad['redirected']=true;
    rejectHttp(fn()=>Review::assess($name,$bad),$name.' HTTP redirect');
    $bad=$fixture;$bad['content_type']='text/html';
    rejectHttp(fn()=>Review::assess($name,$bad),$name.' MIME HTML');
    $bad=$fixture;$bad['body']='{"broken":';
    rejectHttp(fn()=>Review::assess($name,$bad),$name.' truncated JSON');
    $bad=$fixture;$bad['body']=str_repeat('a',524289);
    rejectHttp(fn()=>Review::assess($name,$bad),$name.' oversized body');
    $bad=$fixture;$bad['redirect_to']='https://evil.example';
    rejectHttp(fn()=>Review::assess($name,$bad),$name.' injected URL');
    foreach([400,401,403,404,408,409,429,500,502,503,504] as $status){
        $bad=$fixture;
        $bad['status']=$status;
        $bad['body']='{"error":"PRIVATE_BEARER_SUPERSECRET_123"}';
        $output=Review::assess($name,$bad);
        requireHttp($output['safe_to_bill']===false,$name.' HTTP '.$status.' no billing');
        requireHttp($output['provider_spend_verified']===false,$name.' HTTP '.$status.' unverified');
        requireHttp($output['synthetic_json_shape_ok']===false,$name.' HTTP '.$status.' no result');
        requireHttp($output['automatic_retry_enabled']===false,$name.' HTTP '.$status.' no retries');
        requireHttp(!str_contains(json_encode($output,JSON_THROW_ON_ERROR),'PRIVATE_BEARER'),
            $name.' HTTP '.$status.' body never leaked');
        $retrySuggested=in_array($status,[408,429,500,502,503,504],true);
        requireHttp($output['manual_retry_review_recommended']===$retrySuggested,
            $name.' HTTP '.$status.' retry requires manual review');
    }
}
$bad=$google;$bad['body']='{"results":[]}';
rejectHttp(fn()=>Review::assess('google_ads',$bad),'Google SearchStream object instead of batch list');
$bad=$google;$bad['body']='[{"error":{"message":"unauthorized"}}]';
rejectHttp(fn()=>Review::assess('google_ads',$bad),'Google error as batch');
$bad=$google;$bad['body']='[{"results":[],"authorization":"secret"}]';
rejectHttp(fn()=>Review::assess('google_ads',$bad),'Google unexpected auth metadata');
$bad=$google;$bad['body']='[{"results":"not a list"}]';
rejectHttp(fn()=>Review::assess('google_ads',$bad),'Google result wrong type');
$bad=$google;$bad['body']='[{"results":' .json_encode(array_fill(0,36,['date'=>'2026-11-01']),JSON_THROW_ON_ERROR).'}]';
rejectHttp(fn()=>Review::assess('google_ads',$bad),'Google oversized daily report');

$bad=$meta;$bad['body']=json_encode(['data'=>[], 'paging'=>['next'=>'https://evil.example/steal?token=1']],JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('meta_ads',$bad),'Meta next URL incomplete, no follow');
$bad=$meta;$bad['body']=json_encode(['data'=>[],'error'=>['code'=>190]],JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('meta_ads',$bad),'Meta error field forbidden');
$bad=$meta;$bad['body']='{"data":{}}';
rejectHttp(fn()=>Review::assess('meta_ads',$bad),'Meta object instead of list');

$bad=$chatgpt;
$payload=json_decode($chatgpt['body'],true,32,JSON_THROW_ON_ERROR);
$payload['has_more']=true;
$bad['body']=json_encode($payload,JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('chatgpt_ads',$bad),'ChatGPT has_more requires pagination');
$payload=json_decode($chatgpt['body'],true,32,JSON_THROW_ON_ERROR);
$payload['count']=2;
$bad['body']=json_encode($payload,JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('chatgpt_ads',$bad),'ChatGPT total/count mismatch');
$payload=json_decode($chatgpt['body'],true,32,JSON_THROW_ON_ERROR);
$payload['access_token']='exfiltrated';
$bad['body']=json_encode($payload,JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('chatgpt_ads',$bad),'ChatGPT secret field refused');
$payload=json_decode($chatgpt['body'],true,32,JSON_THROW_ON_ERROR);
$payload['object']='error';
$bad['body']=json_encode($payload,JSON_THROW_ON_ERROR);
rejectHttp(fn()=>Review::assess('chatgpt_ads',$bad),'ChatGPT API error object refused');
$bad=$chatgpt;$bad['status']=302;
rejectHttp(fn()=>Review::assess('chatgpt_ads',$bad),'ChatGPT redirects forbidden');

putenv('PERF_ADS_READONLY_BOUNDARY_LAB=0');
rejectHttp(fn()=>Review::assess('chatgpt_ads',$chatgpt),'lab flag off');
putenv('PERF_ADS_READONLY_BOUNDARY_LAB=1');
putenv('APP_ENV=production');
rejectHttp(fn()=>Review::assess('google_ads',$google),'production disabled');
echo "OK: provider HTTP 200 structure only; 401/403/429/5xx redaction, no automatic retry, pagination/redirect errors fail closed, no billing\n";
