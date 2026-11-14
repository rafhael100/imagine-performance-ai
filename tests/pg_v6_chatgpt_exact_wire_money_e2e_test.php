<?php
declare(strict_types=1);

require_once __DIR__.'/pg_v6_signed_agreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsAccountPreflightLab.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsExactWireSpendLab.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgV6AgreementAtomicCloseLab.php';
require_once __DIR__.'/../labs/finance-pg/ChatgptAdsSyntheticWireToV6FinanceLab.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Catalog;
use App\Homologation\Commerce\PortalPerformanceV6AgreementLabIssuer as Issuer;
use Imagine\Performance\Commercial\ChatgptAdsOfflineAdapter as Adapter;
use Imagine\Performance\Commercial\AdsProviderReadOnlyBoundary as Boundary;
use Imagine\Performance\Commercial\ChatgptAdsAccountPreflightLab as Preflight;
use Imagine\Performance\Commercial\ChatgptAdsExactWireSpendLab as Money;
use Imagine\Performance\Commercial\Lab\ChatgptAdsSyntheticWireToV6FinanceLab as Bridge;
use Imagine\Performance\Commercial\Lab\PgV6AgreementAtomicCloseLab as Close;

function wireE2eEq(mixed $expected,mixed $actual,string $label):void {
    if($expected!==$actual) throw new RuntimeException(
        'ChatGPT Ads wire→PG18: '.$label.' expected '.var_export($expected,true).
        ' got '.var_export($actual,true)
    );
}
function wireE2eDeny(callable $call,string $label):void {
    try {$call();} catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('ChatGPT Ads wire→PG18 accepted: '.$label);
}

foreach([
    'APP_ENV'=>'testing',
    'PERF_CHATGPT_WIRE_V6_FINANCE_E2E_LAB'=>'1',
    'PORTAL_PERF_TRICHANNEL_V5_LAB'=>'1',
    'PORTAL_PERF_V6_AGREEMENT_LAB'=>'1',
    'PERF_PORTAL_V6_AGREEMENT_LAB'=>'1',
    'PERF_PG_V6_AGREEMENT_LAB'=>'1',
    'PERF_ADS_READONLY_BOUNDARY_LAB'=>'1',
    'PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB'=>'1',
    'PERF_CHATGPT_EXACT_WIRE_MONEY_LAB'=>'1'
] as $name=>$expected) {
    if(getenv($name)!==$expected)throw new RuntimeException('CI-only flag missing: '.$name);
}
$fx=V6SignedAgreementCases::prepare();
$catalog=new Catalog();
$tenant=V6SignedAgreementCases::ALPHA;
$agreement='agreement_chatgpt_exactwire_123456';
$month='2026-11';
$account='adacct_12345678';
$zone='America/Sao_Paulo';
$now=V6SignedAgreementCases::NOW;
$secret=V6SignedAgreementCases::resolveKey($tenant,V6SignedAgreementCases::KEY_ID);
if(!is_string($secret))throw new RuntimeException('Missing synthetic Portal v6 signing secret.');
$signed=Issuer::issue(
    $catalog,['chatgpt_ads'=>120000],$tenant,$agreement,
    $month,V6SignedAgreementCases::KEY_ID,
    'nonce_chatgpt_wire_v6_1234567890',
    $now-1,$now+120,$secret
);
wireE2eEq('performance-trichannel-v6-agreement-candidate',
    $signed['contract_version'],'actual pinned Portal V6 issuer version');
wireE2eEq($catalog->version(),$signed['quote']['catalog_version'],
    'owner-approved commercial price pin');
wireE2eEq(24000,$signed['quote']['estimated_management_fee_cents'],
    'ChatGPT R1200 planned, 20 percent estimated');
$binding=[
    'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$month,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>'ref_chatgpt_wire_12345678',
    'consent_reference'=>'consent_chatgpt_wire_12345678',
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-100,
    'consent_expires_at'=>$now+200,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible',
];
$scope=Boundary::inspect(
    $tenant,$agreement,$month,'chatgpt_ads',$account,$zone,
    $binding,Adapter::insightsRequestPlan($account,$month,$zone),$now
);
$preflight=Preflight::inspect($scope,[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'CI-only synthetic company',
        'url'=>'https://example.invalid','preview_url'=>null,
        'status'=>'active','timezone'=>$zone,
        'currency_code'=>'BRL','review'=>['status'=>'approved']
    ],JSON_THROW_ON_ERROR)
]);
$make=static function(string $date,string $amount)use($zone):array {
    $dt=new DateTimeImmutable($date.' 00:00:00',new DateTimeZone($zone));
    $start=$dt->getTimestamp();
    $end=$dt->modify('+1 day')->getTimestamp();
    $id='start='.$start.':end='.$end.':entity_id=adacct_12345678';
    return [
        'id'=>$id,'start'=>$start,'end'=>$end,'date'=>$date,'amount'=>$amount
    ];
};
$a=$make('2026-11-01','0.1');
$b=$make('2026-11-02','0.2');
$c=$make('2026-11-03','18.42');
$encodeRow=static fn(array $p):string =>
    '{"id":'.json_encode($p['id'],JSON_THROW_ON_ERROR).
    ',"start_time":'.$p['start'].',"end_time":'.$p['end'].
    ',"readable_time":'.json_encode($p['date'],JSON_THROW_ON_ERROR).
    ',"timezone":'.json_encode($zone,JSON_THROW_ON_ERROR).
    ',"impressions":200,"clicks":12,"spend":'.$p['amount'].'}';
$body='{"object":"list","data":['.
    implode(',',array_map($encodeRow,[$a,$b,$c])).'],"count":3,"first_id":'.
    json_encode($a['id'],JSON_THROW_ON_ERROR).',"last_id":'.
    json_encode($c['id'],JSON_THROW_ON_ERROR).',"has_more":false}';
$normalized=Money::normalize($scope,$preflight,[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>$body
]);
wireE2eEq(1872,$normalized['synthetic_account_report']['spend_cents'],
    'numerical JSON 0.1+0.2+18.42 exact 1872 cents');
wireE2eEq(false,$normalized['provider_spend_verified'],
    'unverified mock provider report');
$bridge=Bridge::prepare(
    $normalized,$signed,$tenant,$agreement,$month,$account,$zone
);
wireE2eEq(false,$bridge['billable'],'bridge is never billable');
wireE2eEq(false,$bridge['provider_spend_verified'],
    'synthetic bridge is not official provider proof');
wireE2eEq(1,count($bridge['reports']),'one account report');
wireE2eEq('synthetic_fixture',$bridge['reports'][0]['source'],
    'SQL closed fixture must not claim official provenance');
wireE2eEq(1872,$bridge['reports'][0]['spend_cents'],
    'bridge keeps exact cents');

$bad=$normalized;$bad['provider_spend_verified']=true;
wireE2eDeny(fn()=>Bridge::prepare($bad,$signed,$tenant,$agreement,$month,$account,$zone),
    'cannot forge official provider proof');
$bad=$normalized;$bad['synthetic_account_report']['source']='official_openai';
wireE2eDeny(fn()=>Bridge::prepare($bad,$signed,$tenant,$agreement,$month,$account,$zone),
    'cannot relabel synthetic source as official');
$bad=$normalized;$bad['synthetic_account_report']['spend_cents']=1873;
$bad['source_wire_json_sha256']=str_repeat('a',64);
wireE2eDeny(fn()=>Bridge::prepare($bad,$signed,$tenant,$agreement,$month,$account,$zone.'-Other'),
    'bad scope cannot launder doctored data into another timezone');
$bad=$signed;$bad['agreement_id']='agreement_another_contract_1234';
wireE2eDeny(fn()=>Bridge::prepare($normalized,$bad,$tenant,$agreement,$month,$account,$zone),
    'foreign signed contract blocked');
wireE2eDeny(fn()=>Bridge::prepare(
    $normalized,$signed,$tenant,'agreement_another_contract_1234',
    $month,$account,$zone
),'mismatched agreement and account proof');
wireE2eDeny(fn()=>Bridge::prepare(
    $normalized,$signed,$tenant,$agreement,'2026-12',$account,$zone
),'cross-month contract');

$db=new PDO(getenv('PERF_LAB_PG_DSN'),getenv('PERF_LAB_PG_USER'),
    getenv('PERF_LAB_PG_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
wireE2eEq('perf_fin_lab_app',$db->query('SELECT current_user')->fetchColumn(),
    'existing CI-only PostgreSQL finance user');
$finance=new Close($db);
$call=static function(?array $report=null,?array $quote=null)
    use($bridge,$signed,$finance,$tenant,$agreement,$month,$fx,$now):array {
    return $finance->stage(
        $quote??$signed,$tenant,$agreement,$month,$fx['catalog_sha256'],
        $fx['minima'],$report??$bridge['reports'],$now,
        [V6SignedAgreementCases::class,'resolveKey']
    );
};
$result=$call();
wireE2eEq(true,$result['created'],'real Portal HMAC V6 -> PG18 atomically closed');
wireE2eEq(true,$result['agreement_id_signed_in_v6_envelope'],
    'agreement is cryptographically signed');
wireE2eEq(24000,$result['signed_verification']['estimated_advance_fee_cents'],
    'R240 candidate advance not actually charged');
wireE2eEq(1872,$result['signed_verification']['unverified_actual_media_cents'],
    'R18.72 hypothetical spend from numeric wire');
wireE2eEq(374,$result['signed_verification']['unverified_earned_fee_cents'],
    '20 percent of R18.72 = R3.74 after integer rounding');
wireE2eEq(-23626,$result['signed_verification']['review_only_adjustment_cents'],
    'unearned advance adjustment only, no refund');
foreach(['billable','finance_approved','provider_spend_verified',
         'asaas_write_enabled','ads_write_enabled'] as $flag) {
    wireE2eEq(false,$result[$flag],'SQL finance '.$flag.' denied');
}
wireE2eDeny(fn()=> $call(),'same signed V6 nonce replay denied');
$badRecord=$bridge['reports'];$badRecord[0]['source']='verified_openai_ads';
wireE2eDeny(fn()=> $call($badRecord),'official provider label spoof rejected by SQL');
$badRecord=$bridge['reports'];$badRecord[0]['spend_cents']=100000;
wireE2eDeny(fn()=> $call($badRecord),'cannot replace existing immutable finance draft');

$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id',:t,true)")
    ->execute(['t'=>$tenant]);
$query=$db->prepare(
    'SELECT planned_media_cents,unverified_actual_media_cents,
        advance_fee_cents,unverified_earned_fee_cents,adjustment_cents,
        source_verified,finance_approved,billable
       FROM perf_fin_v6_lab.agreement_close_drafts
      WHERE tenant_id=:tenant AND agreement_id=:agreement AND period=:period'
);
$query->execute(['tenant'=>$tenant,'agreement'=>$agreement,'period'=>$month]);
$row=$query->fetch(PDO::FETCH_ASSOC);
if(!is_array($row)) throw new RuntimeException('Signed V6 wire financial draft missing.');
wireE2eEq(1872,(int)$row['unverified_actual_media_cents'],'PG18 exact cents persisted');
wireE2eEq(374,(int)$row['unverified_earned_fee_cents'],'PG18 20 percent persisted');
wireE2eEq(-23626,(int)$row['adjustment_cents'],'PG18 review adjustment persisted');
foreach(['source_verified','finance_approved','billable'] as $flag) {
    if(in_array($row[$flag],[true,1,'1','t'],true)) {
        throw new RuntimeException('Laboratory draft cannot be marked approved or billable.');
    }
}
$db->rollBack();

putenv('PERF_CHATGPT_WIRE_V6_FINANCE_E2E_LAB=0');
wireE2eDeny(fn()=>Bridge::prepare(
    $normalized,$signed,$tenant,$agreement,$month,$account,$zone
),'disable bridge');
putenv('PERF_CHATGPT_WIRE_V6_FINANCE_E2E_LAB=1');
putenv('APP_ENV=production');
wireE2eDeny(fn()=>Bridge::prepare(
    $normalized,$signed,$tenant,$agreement,$month,$account,$zone
),'production bridge disabled');
echo "OK: pinned Portal HMAC V6 -> ChatGPT Ads synthetic account preflight -> exact numeric JSON 1872 cents -> PG18 atomic draft fee 374 cents, no billing/Ads writes\n";
