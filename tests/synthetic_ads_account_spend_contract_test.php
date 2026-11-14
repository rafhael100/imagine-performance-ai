<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/SyntheticAdsAccountSpendContract.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';

use Imagine\Performance\Commercial\SyntheticAdsAccountSpendContract as Spend;
use Imagine\Performance\Commercial\PortalQuoteReconciliationPreview as Preview;

function sameAds(mixed $expected, mixed $actual, string $case): void {
    if ($expected !== $actual) throw new RuntimeException('Ads scope failure: '.$case);
}
function rejectsAds(callable $f, string $case): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected account-scope rejection: '.$case);
}
$tenant = 'tenant_adsdemo_12345678';
$scope = [
    'google_ads'=>['customer_12345'=>'America/Sao_Paulo','customer_67890'=>'America/New_York'],
    'meta_ads'=>['act_123456'=>'America/Sao_Paulo']
];
function rowAds(string $channel,string $account,string $report,int $spend,string $zone): array {
    return [
        'tenant_id'=>'tenant_adsdemo_12345678','channel'=>$channel,'account_id'=>$account,
        'report_id'=>$report,'period'=>'2026-11','time_zone'=>$zone,
        'currency'=>'BRL','spend_cents'=>$spend,'source'=>'synthetic_readonly_report_fixture',
    ];
}
$rows=[
    rowAds('google_ads','customer_12345','google-a',30000,'America/Sao_Paulo'),
    rowAds('google_ads','customer_12345','google-b',20000,'America/Sao_Paulo'),
    rowAds('google_ads','customer_67890','google-c',0,'America/New_York'),
    rowAds('meta_ads','act_123456','meta-a',85000,'America/Sao_Paulo'),
];
$report=Spend::summarize($tenant,'2026-11',$scope,$rows);
sameAds(['google_ads'=>50000,'meta_ads'=>85000],$report['unverified_spend_by_channel_cents'],'channel sums');
sameAds(0,$report['unverified_spend_by_account_cents']['google_ads']['customer_67890'],'explicit zero account');
sameAds(3,$report['account_count'],'all three account scopes present');
sameAds(4,$report['report_count'],'source report count');
sameAds(false,$report['provider_spend_verified'],'not official provider confirmation');
sameAds(false,$report['finance_approved'],'not a settlement approval');
sameAds(false,$report['ads_write_enabled'],'cannot change campaigns');
$reversed=Spend::summarize($tenant,'2026-11',$scope,array_reverse($rows));
sameAds($report['fingerprint_sha256_not_provider_proof'],$reversed['fingerprint_sha256_not_provider_proof'],'canonical fingerprint order');

// Strictly illustrative finance calculation: passing synthetic summaries does
// NOT certify spending or permit billing via Asaas.
$quote=[
 'catalog_version'=>str_repeat('a',64), 'product'=>'performance','currency'=>'BRL',
 'gateway'=>'asaas','real_charge_enabled'=>false,
 'media_by_channel_cents'=>['google_ads'=>70000,'meta_ads'=>70000],
 'media_paid_directly_to_platforms'=>true, 'management_fee_basis_points'=>2000,
 'estimated_management_fee_cents'=>28000,'reference_period'=>'monthly',
 'amount_now_cents'=>28000,
 'settlement_policy'=>'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment',
];
$preview=Preview::build($quote,'2026-11',$report['unverified_spend_by_channel_cents']);
sameAds(27000,$preview['unverified_earned_fee_cents'],'20 percent of synthetic spend');
sameAds(-1000,$preview['review_only_adjustment_cents'],'finance review only');
sameAds(false,$preview['asaas_write_enabled'],'no financial side effects');

rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,[$rows[0],$rows[1],$rows[3]]),'missing zero account');
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,[$rows[0],$rows[0],$rows[2],$rows[3]]),'duplicate account report');
$bad=$rows; $bad[3]['tenant_id']='tenant_other_99999999';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'cross tenant report');
$bad=$rows; $bad[3]['account_id']='act_not_contracted';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'uncontracted account');
$bad=$rows; $bad[2]['time_zone']='America/Sao_Paulo';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'mismatched account timezone');
$bad=$rows; $bad[2]['currency']='USD';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'wrong currency');
$bad=$rows; $bad[1]['period']='2026-10';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'wrong period');
$bad=$rows; $bad[3]['source']='verified_google_api';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'spoofed provider proof');
$bad=$rows; $bad[3]['spend_cents']=85000.5;
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'float money');
$bad=$rows; $bad[3]['spend_cents']=-1;
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'negative money');
$bad=$rows; $bad[1]['spend_cents']=99990000;
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'aggregate exceeds cap');
$bad=$rows; $bad[3]['provider_verified']=true;
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$scope,$bad),'unknown attestation field');
$badScope=$scope; $badScope['meta_ads']['act_123456']='Imaginary/Timezone';
rejectsAds(fn()=>Spend::summarize($tenant,'2026-11',$badScope,$rows),'unknown IANA zone');
echo "OK: synthetic read-only Ads reports restricted by tenant/account/channel/period/timezone/currency; no source verification\n";
