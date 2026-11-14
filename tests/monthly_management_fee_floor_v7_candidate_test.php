<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/MonthlyManagementFeeFloorV7Candidate.php';
require_once __DIR__.'/../src/Domain/Commercial/MonthlyFeeReconciliationV6.php';
require_once __DIR__.'/../src/Domain/Commercial/MonthlyFeeReconciliation.php';

use Imagine\Performance\Commercial\MonthlyManagementFeeFloorV7Candidate as Candidate;
use Imagine\Performance\Commercial\MonthlyFeeReconciliationV6 as V6;
use Imagine\Performance\Commercial\MonthlyFeeReconciliation as V4;

function feeFloorMust(bool $ok,string $description):void{
    if(!$ok)throw new RuntimeException('Fee floor candidate: '.$description);
}
function feeFloorDeny(callable $callback,string $description):void{
    try {$callback();} catch(InvalidArgumentException) {return;}
    throw new RuntimeException('Accepted bad fee floor input: '.$description);
}

$meta=Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>35000],
]);
feeFloorMust($meta['estimated_management_fee_cents']===17000 &&
    $meta['unverified_earned_fee_cents']===17000,
    'Meta R700 plan and R350 spend both require the R170 management floor');
$google=Candidate::preview('2026-11',[
    'google_ads'=>['planned_cents'=>100000,'actual_cents'=>400000],
]);
feeFloorMust($google['estimated_management_fee_cents']===20000 &&
    $google['unverified_earned_fee_cents']===80000,
    'Google R4000 real spend yields R800 at 20 percent');
$chat=Candidate::preview('2026-11',[
    'chatgpt_ads'=>['planned_cents'=>120000,'actual_cents'=>0],
]);
feeFloorMust($chat['estimated_management_fee_cents']===24000 &&
    $chat['unverified_earned_fee_cents']===17000,
    'active ChatGPT channel with zero spend retains a candidate R170 monthly management fee');

$all=Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
    'chatgpt_ads'=>['planned_cents'=>120000,'actual_cents'=>100000],
    'google_ads'=>['planned_cents'=>100000,'actual_cents'=>125000],
]);
feeFloorMust($all['planned_media_cents']===290000 &&
    $all['unverified_actual_media_cents']===225000,
    'media totals at three channel floors');
feeFloorMust($all['estimated_management_fee_cents']===61000,
    'estimated R170 + R200 + R240 is R610, NOT V6 R580');
feeFloorMust($all['unverified_earned_fee_cents']===62000,
    'actual floor-aware R170 + R250 + R200 is R620');
feeFloorMust($all['review_only_adjustment_cents']===1000,
    'illustrative adjustment plus R10 pending review');
feeFloorMust(array_keys($all['channels'])===['chatgpt_ads','google_ads','meta_ads'],
    'canonical channel order');
feeFloorMust($all['active_contracted_channel_count']===3 &&
    $all['management_fee_basis_points']===2000,
    'three contracted channels at 20 percent');

foreach([
    'commercial_catalog_active','provider_spend_verified',
    'portal_agreement_verified','finance_approved','billable',
    'automatic_charge_or_credit_enabled','asaas_write_enabled',
    'ads_write_enabled','crm_write_enabled',
] as $flag)feeFloorMust(($all[$flag]??null)===false,
    'must remain disabled: '.$flag);

// Per-channel rounding can differ by R0.01 from portfolio rounding.
$round=Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>85003,'actual_cents'=>85003],
    'google_ads'=>['planned_cents'=>100000,'actual_cents'=>85003],
]);
feeFloorMust($round['unverified_earned_fee_cents']===34002,
    'R850.03 twice rounds to R170.01 each (not portfolio R340.01)');

// Historical contracts retain their own formula. Never silently change V4/V6.
$legacy=V4::calculate('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
]);
feeFloorMust($legacy['advance_fee_cents']===14000 &&
    $legacy['earned_fee_cents']===0,'V4 historic snapshot untouched');
$v6=V6::calculate('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
]);
feeFloorMust($v6['advance_fee_cents']===14000 &&
    $v6['earned_fee_cents']===0,'V6 HMAC preview behavior untouched');

feeFloorDeny(fn()=>Candidate::preview('2026-13',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
]),'invalid month');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[]),'empty contract');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'tiktok_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
]),'unsupported channel');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'google_ads'=>['planned_cents'=>99999,'actual_cents'=>0],
]),'Google media below minimum');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'chatgpt_ads'=>['planned_cents'=>119999,'actual_cents'=>0],
]),'ChatGPT media below minimum');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>69999,'actual_cents'=>0],
]),'Meta media below minimum');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>-1],
]),'negative actual');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>100000001],
]),'actual spend cap');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>'70000','actual_cents'=>0],
]),'string amounts forbidden');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000.0,'actual_cents'=>0],
]),'float money forbidden');
feeFloorDeny(fn()=>Candidate::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0,'can_charge'=>true],
]),'unknown billing field forbidden');

echo "OK: 20% fee with R170 per contracted channel, direct media, V4/V6 untouched, no billing or Ads writes\n";
