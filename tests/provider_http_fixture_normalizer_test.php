<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/ProviderHttpFixtureNormalizer.php';
require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/SyntheticAdsAccountSpendContract.php';

use Imagine\Performance\Commercial\ProviderHttpFixtureNormalizer as Http;
use Imagine\Performance\Commercial\SyntheticProviderSpendAdapter as Spend;
use Imagine\Performance\Commercial\SyntheticAdsAccountSpendContract as Accounts;

function matchesHttp(mixed $a,mixed $b,string $name): void {
    if ($a !== $b) throw new RuntimeException('Raw HTTP fixture mismatch: '.$name);
}
function mustDenyHttp(callable $f,string $name): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Raw provider response accepted invalid input: '.$name);
}
$tenant='tenant_raw_http123456';
$period='2026-11';
$zone='America/Sao_Paulo';
$google='1234567890';
$meta='act_987654321';
$gJson=[
    ['requestId'=>'synthetic-request-1','fieldMask'=>'segments.date,metrics.cost_micros',
     'results'=>[
         ['segments'=>['date'=>'2026-11-01'],'metrics'=>['costMicros'=>'300000000']],
         ['segments'=>['date'=>'2026-11-02'],'metrics'=>['costMicros'=>'200000000']],
     ]],
    ['requestId'=>'synthetic-request-2','results'=>[]],
];
$mJson=['data'=>[
    ['account_id'=>'987654321','account_currency'=>'BRL','date_start'=>'2026-11-01','date_stop'=>'2026-11-01','spend'=>'100.25'],
    ['account_id'=>'987654321','account_currency'=>'BRL','date_start'=>'2026-11-02','date_stop'=>'2026-11-02','spend'=>'0.05']
], 'paging'=>['cursors'=>['after'=>'synthetic-after']]];
$gh=['status'=>200,'content_type'=>'application/json; charset=UTF-8','redirected'=>false,
     'body'=>json_encode($gJson,JSON_THROW_ON_ERROR)];
$mh=['status'=>200,'content_type'=>'application/json','redirected'=>false,
     'body'=>json_encode($mJson,JSON_THROW_ON_ERROR)];
$gf=Http::googleSearchStream($google,'BRL',$zone,$gh);
$mf=Http::metaInsights($meta,'BRL',$zone,$mh);
matchesHttp(2,count($gf['batches']),'Google SearchStream batches');
matchesHttp(null,$mf['next'],'Meta no next page');
$g=Spend::googleMonthlyFixture($tenant,$period,$google,$zone,$gf);
$m=Spend::metaMonthlyFixture($tenant,$period,$meta,$zone,$mf);
matchesHttp(50000,$g['synthetic_account_report']['spend_cents'],'Google micros to cents');
matchesHttp(10030,$m['synthetic_account_report']['spend_cents'],'Meta decimal to cents');
$totals=Accounts::summarize($tenant,$period,
    ['google_ads'=>[$google=>$zone],'meta_ads'=>[$meta=>$zone]],
    [$g['synthetic_account_report'],$m['synthetic_account_report']]);
matchesHttp(['google_ads'=>50000,'meta_ads'=>10030],
    $totals['unverified_spend_by_channel_cents'],'account-scoped report compatibility');
matchesHttp(false,$totals['provider_spend_verified'],'HTTP mock not provider attestation');

$bad=$gh;$bad['status']=401;
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'401 authorization');
$bad=$gh;$bad['status']=429;
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'quota failure');
$bad=$gh;$bad['redirected']=true;
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'redirect');
$bad=$gh;$bad['content_type']='text/html';
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'not JSON');
$bad=$gh;$bad['body']='[{"results":';
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'truncated stream');
$bad=$gh;$bad['body']=str_repeat('0',524289);
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'oversized provider body');
$bad=$gh;$bad['url']='https://untrusted.example';
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'client-controlled host');
$bad=$gh;$bad['body']='{"results":[]}';
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'Google requires JSON batch list');
$bad=$gh;$copy=$gJson;$copy[0]['error']=['message'=>'error'];$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'unknown error batch');
$bad=$gh;$copy=$gJson;$copy[0]['results'][0]['access_token']='secret';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'unexpected secret field');
$bad=$gh;$copy=$gJson;$copy[0]['results'][0]['metrics']['clicks']='100';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$bad),'extra Google metric');

$bad=$mh;$copy=$mJson;$copy['paging']['next']='https://untrusted.example/page';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$bad),'Meta pagination incomplete');
$bad=$mh;$copy=$mJson;$copy['error']='bad';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$bad),'Meta error response');
$bad=$mh;$copy=$mJson;$copy['data'][0]['access_token']='secret';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$bad),'unexpected Meta secret');
$bad=$mh;$bad['body']='{"data":{}}';
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$bad),'Meta data object instead of list');
$bad=$mh;$bad['body']='[]';
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$bad),'Meta bare list');

$bad=$mh;$copy=$mJson;$copy['data'][0]['spend']='12.345';$bad['body']=json_encode($copy,JSON_THROW_ON_ERROR);
$fixture=Http::metaInsights($meta,'BRL',$zone,$bad);
mustDenyHttp(fn()=>Spend::metaMonthlyFixture($tenant,$period,$meta,$zone,$fixture),
    'decimal with fractional cents denied by downstream provider adapter');

$zeroGoogle=$gh;$zeroGoogle['body']='[]';
$zero=Http::googleSearchStream($google,'BRL',$zone,$zeroGoogle);
matchesHttp(0,Spend::googleMonthlyFixture($tenant,$period,$google,$zone,$zero)['synthetic_account_report']['spend_cents'],
    'empty synthetic Google stream is not official evidence');
$zeroMeta=$mh;$zeroMeta['body']='{"data":[]}';
$zero=Http::metaInsights($meta,'BRL',$zone,$zeroMeta);
matchesHttp(0,Spend::metaMonthlyFixture($tenant,$period,$meta,$zone,$zero)['synthetic_account_report']['spend_cents'],
    'empty synthetic Meta response is not official evidence');

putenv('PERF_ADS_HTTP_NORMALIZER_LAB=0');
mustDenyHttp(fn()=>Http::googleSearchStream($google,'BRL',$zone,$gh),'gate off');
putenv('PERF_ADS_HTTP_NORMALIZER_LAB=1');
putenv('APP_ENV=production');
mustDenyHttp(fn()=>Http::metaInsights($meta,'BRL',$zone,$mh),'production forbidden');
echo "OK: Google SearchStream and Meta Insights raw HTTP-shaped fixture validation, fail closed, no external provider claims\n";
