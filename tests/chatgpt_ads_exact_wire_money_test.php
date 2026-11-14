<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsAccountPreflightLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsExactWireSpendLab.php';

use Imagine\Performance\Commercial\ChatgptAdsOfflineAdapter as Adapter;
use Imagine\Performance\Commercial\AdsProviderReadOnlyBoundary as Boundary;
use Imagine\Performance\Commercial\ChatgptAdsAccountPreflightLab as Preflight;
use Imagine\Performance\Commercial\ChatgptAdsExactWireSpendLab as Money;
use Imagine\Performance\Commercial\ExactJsonNumberLexicalParser as Lexical;

function moneyEqual(mixed $expected,mixed $actual,string $label):void
{
    if($expected!==$actual)throw new RuntimeException(
        'Exact Ads JSON money '.$label.': expected '.var_export($expected,true).
        ', got '.var_export($actual,true)
    );
}
function moneyDeny(callable $call,string $label):void
{
    try {$call();} catch(InvalidArgumentException|RuntimeException) {return;}
    throw new RuntimeException('Exact Ads JSON money accepted invalid: '.$label);
}

$tenant='tenant_numeric_wire_123456';
$agreement='agreement_numeric_wire_123456';
$account='adacct_12345678';
$period='2026-11';
$zone='America/Sao_Paulo';
$now=1794660000;
$binding=[
    'tenant_id'=>$tenant,
    'agreement_id'=>$agreement,
    'period'=>$period,
    'provider'=>'chatgpt_ads',
    'account_id'=>$account,
    'credential_reference'=>'ref_numeric_wire_12345678',
    'consent_reference'=>'consent_numeric_wire_12345678',
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-100,
    'consent_expires_at'=>$now+600,
    'account_currency'=>'BRL',
    'account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible'
];
$plan=Adapter::insightsRequestPlan($account,$period,$zone);
$scope=Boundary::inspect($tenant,$agreement,$period,'chatgpt_ads',$account,$zone,
    $binding,$plan,$now
);
$httpAccount=[
    'status'=>200,
    'content_type'=>'application/json',
    'redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'Synthetic Brand',
        'url'=>'https://example.invalid',
        'preview_url'=>null,
        'status'=>'active','timezone'=>$zone,'currency_code'=>'BRL',
        'review'=>['status'=>'approved']
    ],JSON_THROW_ON_ERROR)
];
$pre=Preflight::inspect($scope,$httpAccount);
moneyEqual(true,$pre['metadata_shape_matches_expected_scope'],'account preflight is synthetic');
moneyEqual(false,$pre['api_key_verified_by_provider'],'no provider authentication');

$zoneInfo=new DateTimeZone($zone);
$makeRow=static function(string $day,string $money,string $impressions='200',
    string $clicks='12',?string $tz=null) use($zoneInfo,$zone):string {
    $local=new DateTimeImmutable($day.' 00:00:00',$zoneInfo);
    $start=$local->getTimestamp();
    $end=$local->modify('+1 day')->getTimestamp();
    $id='start='.$start.':end='.$end.':entity_id=adacct_12345678';
    return '{"id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"start_time":'.$start.',"end_time":'.$end.
        ',"readable_time":'.json_encode($day,JSON_THROW_ON_ERROR).
        ',"timezone":'.json_encode($tz??$zone,JSON_THROW_ON_ERROR).
        ',"impressions":'.$impressions.',"clicks":'.$clicks.
        ',"spend":'.$money.'}';
};
$row1=$makeRow('2026-11-01','0.1');
$row2=$makeRow('2026-11-02','0.2');
$row3=$makeRow('2026-11-03','18.42','400','15');
$rows=[$row1,$row2,$row3];
$makeBody=static function(array $rows,bool $hasMore=false,?string $first=null,
    ?string $last=null,?string $countLiteral=null):string {
    $a=json_decode($rows[0]??'null',true);
    $z=json_decode($rows[count($rows)-1]??'null',true);
    $first??=$a['id']??null;
    $last??=$z['id']??null;
    return '{"object":"list","data":['.implode(',',$rows).
        '],"count":'.($countLiteral??(string)count($rows)).
        ',"first_id":'.json_encode($first,JSON_THROW_ON_ERROR).
        ',"last_id":'.json_encode($last,JSON_THROW_ON_ERROR).
        ',"has_more":'.($hasMore?'true':'false').'}';
};
$http=[
    'status'=>200,'content_type'=>'application/json',
    'redirected'=>false,'body'=>$makeBody($rows)
];
$parse=static fn(array $wire=$http,array $bound=$scope,array $accountPre=$pre):array=>
    Money::normalize($bound,$accountPre,$wire);
$out=$parse();
moneyEqual(1872,$out['synthetic_account_report']['spend_cents'],
    '0.1 plus 0.2 plus 18.42 = 18.72 BRL exactly');
moneyEqual(3,$out['reported_day_count'],'three account-day rows');
moneyEqual(800,$out['impressions'],'sum impressions');
moneyEqual(39,$out['clicks'],'sum clicks');
moneyEqual($agreement,$out['agreement_id'],'contract bound');
moneyEqual($account,$out['account_id'],'account bound');
moneyEqual(true,$out['numeric_money_parsed_without_binary_float'],'raw decimal never float');
moneyEqual('synthetic_readonly_report_fixture',
    $out['synthetic_account_report']['source'],'unverified provenance');
foreach(['network_enabled','api_key_verified_by_provider','portal_iam_authenticated',
         'account_ownership_verified','provider_spend_verified','finance_approved',
         'billable','asaas_write_enabled','ads_write_enabled'] as $name) {
    moneyEqual(false,$out[$name],$name.' always false');
}
moneyEqual(hash('sha256',$http['body']),$out['source_wire_json_sha256'],
    'raw JSON evidence fingerprint');
$serialized=json_encode($out,JSON_THROW_ON_ERROR);
if(str_contains($serialized,'Synthetic Brand')||
   str_contains($serialized,'https://example.invalid')||
   str_contains($serialized,'Authorization: Bearer')){
    throw new RuntimeException('Unexpected account PII or secret in normalized report.');
}

// Decimal representation and precise cent semantics.
foreach([
    ['0',0],['0.00',0],['0.01',1],['0.1',10],['1',100],
    ['1.00',100],['1000000.00',100000000],['18.42',1842]
] as [$lexeme,$cents]) {
    $single=$http;
    $single['body']=$makeBody([$makeRow('2026-11-01',$lexeme)]);
    moneyEqual($cents,$parse($single)['synthetic_account_report']['spend_cents'],
        'exact lexical cents '.$lexeme);
}
foreach([
    '0.001','1.234','18.4200','1e2','1E+2','-0.01','-1',
    '1000000.01','9999999999999','+1.00','01.00',
    '1.','NaN','Infinity','"18.42"','null'
] as $lexeme){
    $wire=$http;
    $wire['body']=$makeBody([$makeRow('2026-11-01',$lexeme)]);
    moneyDeny(fn()=> $parse($wire),'reject lossy/invalid monetary literal '.$lexeme);
}

// Currency/timezone/account/contract must originate from independent trusted
// preflight; a matching self-assertion from a report is not sufficient.
$spoof=$scope;$spoof['agreement_id']='agreement_not_signed_123456';
moneyDeny(fn()=> $parse($http,$spoof),'cross-agreement fake scope');
$spoof=$scope;$spoof['account_id']='adacct_999999';
moneyDeny(fn()=> $parse($http,$spoof),'different Ads API key account');
$spoof=$scope;$spoof['account_currency']='USD';
moneyDeny(fn()=> $parse($http,$spoof),'wrong account currency');
$spoof=$pre;$spoof['api_key_verified_by_provider']=true;
moneyDeny(fn()=> $parse($http,$scope,$spoof),'mock pretends real API auth');
$spoof=$pre;$spoof['metadata_shape_matches_expected_scope']=false;
moneyDeny(fn()=> $parse($http,$scope,$spoof),'preflight not approved');
$spoof=$scope;$spoof['network_enabled']=true;
moneyDeny(fn()=> $parse($http,$spoof),'test plan illegally activates network');

// Adversarial wire shape, pagination, JSON duplicate-key and row integrity.
$bad=$http;$bad['body']=$makeBody($rows,true);
moneyDeny(fn()=> $parse($bad),'has_more true must not be billed');
$bad=$http;$bad['body']=$makeBody($rows,false,null,null,'2');
moneyDeny(fn()=> $parse($bad),'wire count differs from row count');
$bad=$http;$bad['body']=$makeBody($rows,false,'bogus-first');
moneyDeny(fn()=> $parse($bad),'cursor first ID mismatch');
$bad=$http;$bad['body']=$makeBody([$row1,$row1]);
moneyDeny(fn()=> $parse($bad),'duplicate account-day ID');
$bad=$http;$bad['body']=$makeBody([$makeRow('2026-12-01','4.20')]);
moneyDeny(fn()=> $parse($bad),'report month differs');
$bad=$http;$bad['body']=$makeBody([$makeRow('2026-11-01','4.20','200','12','UTC')]);
moneyDeny(fn()=> $parse($bad),'report timezone changed');
$bad=$http;$bad['body']=$makeBody([$makeRow('2026-11-01','4.20','12','13')]);
moneyDeny(fn()=> $parse($bad),'clicks greater than impressions');
$bad=$http;$bad['body']=$makeBody([$makeRow('2026-11-01','4.20','2.0','1')]);
moneyDeny(fn()=> $parse($bad),'counter decimal float');
$bad=$http;$bad['body']=$makeBody([$makeRow('2026-11-01','4.20','1e3','1')]);
moneyDeny(fn()=> $parse($bad),'counter exponential numeric');
$bad=$http;$bad['body']=$makeBody([$row1]);
$bad['body']=str_replace('"spend":0.1','"spend":0.1,"spend":0.01',$bad['body']);
moneyDeny(fn()=> $parse($bad),'duplicate JSON spend key');
$bad=$http;$bad['body']=$makeBody([$row1]);
$bad['body']=str_replace('"spend":0.1','"spend":0.1,"secret":"Bearer X"',$bad['body']);
moneyDeny(fn()=> $parse($bad),'unexpected row credential');
$bad=$http;$bad['body']=$makeBody([$row1]);
$bad['body']=str_replace('"spend":0.1','"spend":0.1,"id":"duplicate"',$bad['body']);
moneyDeny(fn()=> $parse($bad),'duplicate JSON ID key');
$bad=$http;$bad['body']=str_replace('"has_more":false',
    '"has_more":false,"has_more":true',$bad['body']);
moneyDeny(fn()=> $parse($bad),'duplicate pagination key');
$bad=$http;$bad['body']=str_replace('"spend":0.1','"spend":null',$bad['body']);
moneyDeny(fn()=> $parse($bad),'null spend is unknown, not zero');
$bad=$http;$bad['body']=str_replace('"spend":0.1','"spend":true',$bad['body']);
moneyDeny(fn()=> $parse($bad),'boolean spend is not decimal');
$bad=$http;$bad['body']=str_replace('"spend":0.1','"spend":0.1000000000001',$bad['body']);
moneyDeny(fn()=> $parse($bad),'precision cannot be silently rounded');
$bad=$http;$bad['body']='{"object":"list","data":{}}';
moneyDeny(fn()=> $parse($bad),'JSON object cannot impersonate data list');
$bad=$http;$bad['body']='[]';
moneyDeny(fn()=> $parse($bad),'JSON root must be an object');
$bad=$http;$bad['body']=str_repeat(' ',65537);
moneyDeny(fn()=> $parse($bad),'JSON body too large');
$bad=$http;$bad['redirected']=true;
moneyDeny(fn()=> $parse($bad),'HTTP redirect forbidden');
$bad=$http;$bad['status']=429;
moneyDeny(fn()=> $parse($bad),'rate limit is not spend');
$bad=$http;$bad['status']=401;
moneyDeny(fn()=> $parse($bad),'auth failure is not spend');

// Lexical parser must not mistake number-shaped TEXT inside strings for
// adjacent fields, and must fail closed on malformed strings/duplicate keys.
$opaque=Lexical::decode('{"note":"the text \\"spend\\":999.99 is not a metric","value":0.29}');
moneyEqual('the text "spend":999.99 is not a metric',$opaque->note,
    'quoted text never changes money field location');
moneyEqual('0.29',$opaque->value->text,
    'raw JSON numeric token remains textual in parser');
moneyDeny(fn()=>Lexical::decode('{"a":1,"a":2}'),'duplicate JSON object member');
moneyDeny(fn()=>Lexical::decode('{"note":"unterminated}'),'truncated JSON string');
moneyDeny(fn()=>Lexical::decode('[0.1] garbage'),'trailing JSON garbage');
moneyDeny(fn()=>Lexical::decode('[01]'),'leading-zero invalid JSON');
moneyDeny(fn()=>Lexical::decode('{"x":[1,2,]}'),'trailing comma invalid JSON');
moneyDeny(fn()=>Lexical::decode('{"x":'+str_repeat('[',14)+'0'+str_repeat(']',14)+'}'),
    'excessive JSON nesting');

putenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB=0');
moneyDeny(fn()=> $parse(),'lab disabled');
putenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB=1');
putenv('APP_ENV=production');
moneyDeny(fn()=> $parse(),'production never allowed');
echo "OK: ChatGPT Ads JSON numeric spend exact cents, no binary float, HMAC contract/account preflight boundary, malformed/duplicate/partial reports rejected, no billing\n";
