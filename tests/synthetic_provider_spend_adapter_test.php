<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__ . '/../src/Domain/Commercial/SyntheticAdsAccountSpendContract.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';

use Imagine\Performance\Commercial\SyntheticProviderSpendAdapter as Adapter;
use Imagine\Performance\Commercial\SyntheticAdsAccountSpendContract as Accounts;
use Imagine\Performance\Commercial\PortalQuoteReconciliationPreview as Preview;

function sameAdapter(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException(
        $message . ': expected ' . var_export($expected, true) .
        ' received ' . var_export($actual, true)
    );
}
function denyAdapter(callable $f, string $message): void
{
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected fixture rejection: ' . $message);
}
$tenant = 'tenant_provider_lab1234';
$google = '1234567890';
$meta = 'act_987654321';
$period = '2026-11';
$timeZone = 'America/Sao_Paulo';

$gp = Adapter::googleRequestPlan($google, $period);
sameAdapter('/v26/customers/1234567890/googleAds:searchStream', $gp['path'], 'allowlisted Google path');
sameAdapter(false, $gp['transport_enabled'], 'Google plan cannot call network');
sameAdapter('POST', $gp['method'], 'Google read-only report request uses POST searchStream');
if (!str_contains($gp['body']['query'], "2026-11-30")) {
    throw new RuntimeException('Google monthly query missing last calendar day');
}
$mp = Adapter::metaRequestPlan($meta, $period);
sameAdapter('GET', $mp['method'], 'Meta read-only Insights GET');
sameAdapter(false, $mp['transport_enabled'], 'Meta plan cannot call network');
sameAdapter('/v27.0/act_987654321/insights', $mp['path'], 'allowlisted Meta path');
sameAdapter('1', $mp['query']['time_increment'], 'Meta report daily granularity');
sameAdapter(
    '{"since":"2026-11-01","until":"2026-11-30"}',
    $mp['query']['time_range'], 'Meta inclusive month boundaries'
);
$leap = Adapter::googleRequestPlan($google, '2028-02');
if (!str_contains($leap['body']['query'], "2028-02-29")) {
    throw new RuntimeException('Leap February last date not calculated');
}
denyAdapter(fn()=> Adapter::googleRequestPlan('1234567890/../evil', $period), 'path traversal');
denyAdapter(fn()=> Adapter::metaRequestPlan('act_12?token=x', $period), 'query injection');
denyAdapter(fn()=> Adapter::googleRequestPlan($google, '2026-13'), 'invalid month');

$gFixture = [
    'customer_id' => $google, 'time_zone' => $timeZone,
    'currency_code' => 'BRL', 'complete' => true,
    'batches' => [
        ['results'=>[
            ['segments'=>['date'=>'2026-11-01'], 'metrics'=>['costMicros'=>'5500']],
            ['segments'=>['date'=>'2026-11-03'], 'metrics'=>['costMicros'=>'700000000']],
        ]],
        ['results'=>[
            ['segments'=>['date'=>'2026-11-02'], 'metrics'=>['costMicros'=>'4500']],
        ]],
    ]
];
$googleResult = Adapter::googleMonthlyFixture($tenant, $period, $google, $timeZone, $gFixture);
sameAdapter(70001, $googleResult['synthetic_account_report']['spend_cents'],
    'sum micro-units before one-time cent rounding');
sameAdapter(3, $googleResult['reported_day_count'], 'three account-day report rows');
sameAdapter(false, $googleResult['provider_spend_verified'], 'GAQL shape is not provider proof');
sameAdapter(false, $googleResult['transport_enabled'], 'offline only');
$gReverse = $gFixture;
$gReverse['batches'] = array_reverse($gReverse['batches']);
sameAdapter(
    $googleResult['synthetic_account_report'],
    Adapter::googleMonthlyFixture($tenant, $period, $google, $timeZone, $gReverse)['synthetic_account_report'],
    'monthly Google aggregation is deterministic'
);

$metaFixture = [
    'account_id' => $meta, 'time_zone' => $timeZone,
    'currency' => 'BRL', 'complete' => true, 'next' => null,
    'data' => [
        ['account_id'=>'987654321','account_currency'=>'BRL','date_start'=>'2026-11-01','date_stop'=>'2026-11-01','spend'=>'100.50'],
        ['account_id'=>'987654321','account_currency'=>'BRL','date_start'=>'2026-11-02','date_stop'=>'2026-11-02','spend'=>'0.05'],
    ],
];
$metaResult = Adapter::metaMonthlyFixture($tenant, $period, $meta, $timeZone, $metaFixture);
sameAdapter(10055, $metaResult['synthetic_account_report']['spend_cents'],
    'decimal-to-cents exact conversion, no float');
sameAdapter(2, $metaResult['reported_day_count'], 'Meta daily account reports');
sameAdapter(false, $metaResult['provider_source_authenticated'], 'Meta source unverified');
sameAdapter(false, $metaResult['ads_write_enabled'], 'cannot mutate Meta ads');

$scopes = [
    'google_ads'=>[$google=>$timeZone],
    'meta_ads'=>[$meta=>$timeZone],
];
$summaries = Accounts::summarize($tenant, $period, $scopes, [
    $googleResult['synthetic_account_report'],
    $metaResult['synthetic_account_report'],
]);
sameAdapter(
    ['google_ads'=>70001,'meta_ads'=>10055],
    $summaries['unverified_spend_by_channel_cents'],
    'provider-shaped fixtures compose with strict account-scoped contract'
);
sameAdapter(false, $summaries['provider_spend_verified'], 'composed reports never gain official proof');

$quote=[
    'catalog_version'=>str_repeat('a', 64),
    'product'=>'performance','currency'=>'BRL','gateway'=>'asaas','real_charge_enabled'=>false,
    'media_by_channel_cents'=>['google_ads'=>70000,'meta_ads'=>70000],
    'media_paid_directly_to_platforms'=>true,
    'management_fee_basis_points'=>2000,
    'estimated_management_fee_cents'=>28000,
    'reference_period'=>'monthly','amount_now_cents'=>28000,
    'settlement_policy'=>'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment'
];
$preview=Preview::build($quote, $period, $summaries['unverified_spend_by_channel_cents']);
sameAdapter(16011, $preview['unverified_earned_fee_cents'],
    '20% of 80056 cents rounded once');
sameAdapter(-11989, $preview['review_only_adjustment_cents'],
    'only unverified adjustment - no refund');
sameAdapter(false, $preview['asaas_write_enabled'], 'Asaas writes remain disabled');

// Incomplete stream or partial Meta cursor must NEVER be interpreted as zero.
$bad=$gFixture; $bad['complete']=false;
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'partial Google query');
$bad=$metaFixture; $bad['next']='opaque-next-cursor';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'Meta pagination incomplete');

$bad=$gFixture; $bad['batches'][1]['results'][0]['segments']['date']='2026-11-01';
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'duplicate Google day');
$bad=$gFixture; $bad['batches'][1]['results'][0]['segments']['date']='2026-10-31';
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'Google wrong month');
$bad=$gFixture; $bad['batches'][1]['results'][0]['metrics']['costMicros']='0.2';
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'float Google micros');
$bad=$gFixture; $bad['customer_id']='0000000000';
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'mismatched Google customer');
$bad=$gFixture; $bad['currency_code']='USD';
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'non-BRL currency');
$bad=$gFixture; $bad['source_authenticated']=true;
denyAdapter(fn()=>Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$bad),'spoofed Google trust assertion');

$bad=$metaFixture; $bad['data'][1]['date_start']='2026-11-01';$bad['data'][1]['date_stop']='2026-11-01';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'duplicate Meta day');
$bad=$metaFixture; $bad['data'][0]['date_stop']='2026-11-03';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'Meta grouped multi-day report');
$bad=$metaFixture; $bad['data'][0]['spend']='12.345';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'fraction of cent');
$bad=$metaFixture; $bad['data'][0]['spend']='-1.00';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'negative spend');
$bad=$metaFixture; $bad['data'][0]['account_id']='other';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'Meta other account');
$bad=$metaFixture; $bad['data'][0]['account_currency']='EUR';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'Meta other currency');
$bad=$metaFixture; $bad['data'][0]['provider_verified']=true;
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'spoofed Meta provider verification');
$bad=$metaFixture; $bad['time_zone']='America/New_York';
denyAdapter(fn()=>Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$bad),'Meta timezone mismatch');

$zeroGoogle=$gFixture;$zeroGoogle['batches']=[];
$z=Adapter::googleMonthlyFixture($tenant,$period,$google,$timeZone,$zeroGoogle);
sameAdapter(0,$z['synthetic_account_report']['spend_cents'],'synthetic empty complete response becomes explicit zero');
sameAdapter(false,$z['provider_spend_verified'],'zero in fixture not certified zero spend');
$zeroMeta=$metaFixture;$zeroMeta['data']=[];
$z=Adapter::metaMonthlyFixture($tenant,$period,$meta,$timeZone,$zeroMeta);
sameAdapter(0,$z['synthetic_account_report']['spend_cents'],'synthetic empty complete Meta response');
sameAdapter(false,$z['provider_spend_verified'],'no official zero certification');

echo "OK: Google v26 GAQL and Meta Insights v27 offline fixture parsing, account scope, exact money, no network or attestation\n";
