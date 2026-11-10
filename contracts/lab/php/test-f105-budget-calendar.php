<?php
declare(strict_types=1);

/**
 * F1-105 deterministic offline budget preview tests. No Ads API,
 * provider credentials, payment systems, database or HTTP calls.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F105_BUDGET_PREVIEW_LAB') !== '1') {
    throw new RuntimeException('F105 only runs in local/CI testing mode');
}
require_once dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Budget;

$n = 0;
$check = static function (bool $yes, string $label) use (&$n): void {
    if (!$yes) {
        throw new RuntimeException('FAIL F105 '.$label);
    }
    ++$n;
};
$input = [
    'schema_version' => Budget::SCHEMA,
    'scenario_ref' => 'scenario-demo-seasonal-1',
    'currency' => 'BRL',
    'start_date' => '2028-02-28',
    'end_date' => '2028-03-01',
    'total_centavos' => 10001,
    'channel_split_bp' => [
        'google_ads' => 5500,
        'meta_ads' => 4500,
    ],
];
$actual = Budget::calculate($input);
$check($actual['number_of_days'] === 3, 'leap February counted');
$check($actual['currency'] === 'BRL', 'BRL only');
$check($actual['channel_totals_centavos'] === [
    'google_ads' => 5500, 'meta_ads' => 4501,
], 'whole-period weighted amounts reconcile');
$check(array_column($actual['daily'], 'date') === [
    '2028-02-28','2028-02-29','2028-03-01',
], 'calendar includes leap day and both boundaries');
$check(array_column($actual['daily'], 'total_centavos') === [
    3334,3334,3333,
], 'daily remainder assigned deterministically');
$check(array_column($actual['daily'], 'google_ads_centavos') === [
    1833,1834,1833,
], 'cumulative Google budget allocation exact');
$check(array_column($actual['daily'], 'meta_ads_centavos') === [
    1501,1500,1500,
], 'cumulative Meta budget allocation exact');
$check(Budget::calculate($input) === $actual, 'byte-stable deterministic output');
$check($actual['simulation_only'] === true, 'preview only');
foreach ([
    'advertiser_approval_verified','provider_connected',
    'media_execution_allowed','billing_allowed','ready_for_production',
] as $flag) {
    $check($actual[$flag] === false, $flag.' permanently denied');
}
$check(!array_key_exists('customer_id', $actual), 'no customer data');
$check(!array_key_exists('provider_account_id', $actual), 'no real provider IDs');

$validate = static function (array $result, int $expectedTotal, int $expectedG,
    int $expectedDays) use ($check): void {
    $daily=$result['daily'];
    $check(count($daily) === $expectedDays, 'days count reconciles');
    $check($result['number_of_days'] === $expectedDays, 'days metadata reconciles');
    $check($result['total_centavos'] === $expectedTotal, 'overall input reconciles');
    $check($result['channel_totals_centavos']['google_ads'] === $expectedG,
        'Google exact cents reconcile');
    $check($result['channel_totals_centavos']['meta_ads'] === $expectedTotal - $expectedG,
        'Meta exact cents reconcile');
    $sum=0;$sumG=0;$sumM=0;
    $lastG=-1;$maxG=$expectedG;
    foreach ($daily as $d) {
        $check(is_int($d['total_centavos'])
            && is_int($d['google_ads_centavos'])
            && is_int($d['meta_ads_centavos']), 'all output amounts are integers');
        $check($d['total_centavos'] >= 0
            && $d['google_ads_centavos'] >= 0
            && $d['meta_ads_centavos'] >= 0, 'no negative budget day');
        $check($d['google_ads_centavos'] + $d['meta_ads_centavos']
            === $d['total_centavos'], 'each day exact reconciled');
        $sum += $d['total_centavos'];
        $sumG += $d['google_ads_centavos'];
        $sumM += $d['meta_ads_centavos'];
        $check($sumG >= $lastG && $sumG <= $maxG,
            'monotone cumulative Google allocation');
        $lastG=$sumG;
    }
    $check($sum === $expectedTotal && $sumG === $expectedG
        && $sumM === $expectedTotal - $expectedG,
        'sum of all days and channels exact');
};

$validate($actual, 10001, 5500, 3);
foreach ([0,1,9,500,2500,5000,5555,9900,9999,10000] as $g) {
    foreach ([1,2,3,4,7,13,31] as $days) {
        foreach ([1,2,3,7,11,99,100,101,10001,2000000000] as $amount) {
            $end=(new DateTimeImmutable(
                '2027-03-01',
                new DateTimeZone('UTC')
            ))->modify('+'.($days-1).' days')->format('Y-m-d');
            $scenario=$input;
            $scenario['start_date']='2027-03-01';
            $scenario['end_date']=$end;
            $scenario['total_centavos']=$amount;
            $scenario['channel_split_bp']=[
                'google_ads'=>$g,'meta_ads'=>10000-$g
            ];
            $result=Budget::calculate($scenario);
            $validate($result,$amount,intdiv($amount*$g,10000),$days);
            $check($result['media_execution_allowed']===false
                && $result['billing_allowed']===false
                && $result['ready_for_production']===false,
                'all scenarios forbidden from provider billing and dispatch');
        }
    }
}
$max=$input;
$max['start_date']='2028-01-01';
$max['end_date']='2028-12-31';
$max['total_centavos']=1;
$maxOutput=Budget::calculate($max);
$check($maxOutput['number_of_days']===366, '366 days inclusive supported');
$check($maxOutput['daily'][0]['total_centavos']===1
    && $maxOutput['daily'][365]['total_centavos']===0,
    'sparse one-cent allocation across leap year');
$check(Budget::calculate([
    'currency'=>'BRL',
    'scenario_ref'=>'scenario-demo-seasonal-1',
    'channel_split_bp'=>['meta_ads'=>4500,'google_ads'=>5500],
    'end_date'=>'2028-03-01','total_centavos'=>10001,
    'start_date'=>'2028-02-28','schema_version'=>Budget::SCHEMA,
])===$actual,'input key order does not change output');

$bad=[
    ['scenario_ref'=>'different'],
    ['scenario_ref'=>'scenario-demo-<script>'],
    ['schema_version'=>'perf.planning.offline-budget.production.v1'],
    ['currency'=>'USD'],
    ['currency'=>'brl'],
    ['start_date'=>'2028-02-30'],
    ['start_date'=>'2028-2-28'],
    ['start_date'=>'2028-03-02'],
    ['end_date'=>'2028-02-27'],
    ['start_date'=>'0000-01-01'],
    ['start_date'=>'2028-01-01','end_date'=>'2029-01-01'],
    ['total_centavos'=>0],
    ['total_centavos'=>-1],
    ['total_centavos'=>2_000_000_001],
    ['total_centavos'=>100.99],
    ['total_centavos'=>'10001'],
    ['total_centavos'=>true],
    ['channel_split_bp'=>['google_ads'=>5000,'meta_ads'=>4999]],
    ['channel_split_bp'=>['google_ads'=>-1,'meta_ads'=>10001]],
    ['channel_split_bp'=>['google_ads'=>5000.5,'meta_ads'=>4999.5]],
    ['channel_split_bp'=>['google_ads'=>'5500','meta_ads'=>4500]],
    ['channel_split_bp'=>['google_ads'=>true,'meta_ads'=>9999]],
    ['channel_split_bp'=>['google_ads'=>0,'meta_ads'=>10000,'other'=>0]],
    ['channel_split_bp'=>['google_ads'=>10000]],
    ['channel_split_bp'=>null],
    ['provider_connected'=>true],
];
foreach ($bad as $change) {
    $candidate=array_replace($input,$change);
    $denied=false;
    try {
        Budget::calculate($candidate);
    } catch (DomainException) {
        $denied=true;
    }
    $check($denied, 'reject invalid budget input '.json_encode($change));
}
$missing=$input;
unset($missing['scenario_ref']);
$denied=false;
try {
    Budget::calculate($missing);
} catch(DomainException) {
    $denied=true;
}
$check($denied,'missing input denied');

$out=getenv('PERF_F105_BUDGET_JSON_FILE');
if(is_string($out) && $out!==''){
    $check(str_starts_with($out,'/tmp/') && !file_exists($out) && !is_link($out),
        'fresh temporary JSON file required');
    $encoded=json_encode($actual,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    $check(file_put_contents($out,$encoded."\n",LOCK_EX)!==false,
        'save ONLY deterministic DEMO preview artifact');
}
echo "PASS F105 $n integer-centavo preview assertions, no provider execution or billing\n";
