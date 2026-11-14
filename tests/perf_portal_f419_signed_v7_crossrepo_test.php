<?php
declare(strict_types=1);

// Read-only cross-repository CI proof: actual pinned Portal F419 PHP issuer,
// independent Performance verifier, and management V7 arithmetic.
// No S2S identity, payments, provider connections, CRM writes or production.
$base=__DIR__.'/../portal-f419-reference/apps/portal/app/Domain';
require_once $base.'/Performance/ActivationPostpaidPreview.php';
require_once $base.'/PaymentRouting/GatewayRoutingPreview.php';
require_once $base.'/PaymentRouting/GatewayPolicyReview.php';
require_once $base.'/PaymentRouting/PortalGatewayReviewExporter.php';
require_once $base.'/Performance/ActivationGatewayReviewLab.php';
require_once $base.'/Performance/PerformanceChannelReviewEnvelope.php';
require_once __DIR__.'/../src/Domain/Commercial/MonthlyManagementFeeFloorV7Candidate.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalF419SignedActivationReviewV7LabVerifier.php';

use App\Domain\Performance\ActivationGatewayReviewLab as PortalGateway;
use App\Domain\Performance\ActivationPostpaidPreview as PortalPolicy;
use App\Domain\Performance\PerformanceChannelReviewEnvelope as PortalIssuer;
use Imagine\Performance\Commercial\MonthlyManagementFeeFloorV7Candidate as PerfFee;
use Imagine\Performance\Commercial\PortalF419SignedActivationReviewV7LabVerifier as PerfVerifier;

function v7Must(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('F419↔Performance V7 parity: '.$label);
}
function v7Deny(callable $check,string $label):void {
    try {$check();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('F419↔Performance V7 accepted unsafe case: '.$label);
}
foreach([
    'APP_ENV'=>'testing','PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
] as $key=>$value)if(getenv($key)!==$value)throw new RuntimeException('Missing scope '.$key);

$key=str_repeat('K',48);
$org='org-v7-parity-001';
$order='order-v7-parity-001';
$revision=3;
$issued='2026-11-14T21:00:00Z';
$now='2026-11-14T21:05:00Z';
$eligible=['meta_ads'=>true,'google_ads'=>true,'chatgpt_ads'=>false];
$policy=[
    'revision'=>3,'mode'=>'manual','currency'=>'BRL','preferred_gateway'=>'asaas',
    'providers'=>[
        'asaas'=>[
            'enabled'=>true,'approved'=>true,'healthy'=>true,'fee_version'=>'SIM-2026-11',
            'methods'=>['pix'=>['enabled'=>true,'fixed_cents'=>100,
                'variable_bps'=>100,'settlement_days'=>1]],
        ],
        'c6'=>[
            'enabled'=>false,'approved'=>false,'healthy'=>false,
            'fee_version'=>'SIM-C6-2026-11','methods'=>[],
        ],
    ],
];
$review=PortalGateway::preview(['meta_ads','google_ads'],$eligible,$policy,
    'pix',$org,$order,$revision,$key);
$wire=PortalIssuer::issue($review,$eligible,$org,$order,$revision,$issued,$key);
v7Must(PortalIssuer::verify($wire,$review,$eligible,$org,$order,$revision,$now,$key),
    'pinned Portal issuer verifies its own envelope');
v7Must($review['activation_cents']===98000,'2 channels activation R980');
v7Must($review['postpaid_policy_version']===PortalPolicy::POLICY_VERSION,
    'Portal gateway review uses F419 minimum version');

$verify=static function(array $candidate,?string $expectedOrg=null,
    ?string $expectedOrder=null,?array $expectedChannels=null,
    ?int $expectedRevision=null,?string $expectedCRM=null,
    ?string $clock=null,?string $expectedGateway=null,
    ?string $expectedMethod=null,?string $secret=null
)use($org,$order,$revision,$review,$key,$now):array{
    return PerfVerifier::verify(
        $candidate,$expectedChannels??['meta_ads','google_ads'],
        $expectedOrg??$org,$expectedOrder??$order,$expectedRevision??$revision,
        $expectedGateway??$review['gateway_quote']['gateway'],
        $expectedMethod??$review['gateway_quote']['method'],
        $expectedCRM??$review['crm_review']['mac'],$clock??$now,$secret??$key
    );
};
$checked=$verify($wire);
v7Must($checked['hmac_verified_in_lab']===true &&
    $checked['crm_review_mac_bound_to_expected_context_in_lab']===true,
    'independent verifier accepted authentic F419 bytes against expected CRM MAC');
v7Must($checked['channels']===['google_ads','meta_ads'] &&
    $checked['activation_review_cents']===98000,
    'exact canonical channels and R490 per channel');
v7Must($checked['minimum_management_fee_per_channel_cents']===17000 &&
    $checked['management_basis_points']===2000 &&
    $checked['management_postpaid_policy']===PortalPolicy::POLICY_VERSION,
    'per-channel R170 management minimum is cryptographically bound');
foreach([
    'portal_iam_authenticated','real_customer_contract_authenticated',
    'real_psp_approved','media_spend_verified','finance_approved','billable',
    'activation_enabled','asaas_write_enabled','c6_write_enabled',
    'ads_write_enabled','crm_write_enabled',
] as $flag) {
    v7Must(($checked[$flag]??null)===false,
        'offline review must NEVER grant real authority '.$flag);
}
$redacted=json_encode($checked,JSON_THROW_ON_ERROR);
v7Must(!str_contains($redacted,$key) && !str_contains($redacted,'Bearer'),
    'no HMAC secret or token in returned review');

$bad=$wire;$bad['payload']['minimum_management_fee_cents']=0;
v7Deny(fn()=> $verify($bad),'missing R170 per channel');
$bad=$wire;$bad['payload']['postpaid_policy_version']='performance-postpaid-v6';
v7Deny(fn()=> $verify($bad),'wrong signed policy version');
$bad=$wire;$bad['payload']['activation_cents']=97000;
v7Deny(fn()=> $verify($bad),'wrong R490/channel activation');
$bad=$wire;$bad['payload']['channels']=['meta_ads'];
v7Deny(fn()=> $verify($bad),'unapproved reduction of channel list');
$bad=$wire;$bad['payload']['can_charge']=true;
v7Deny(fn()=> $verify($bad),'fake PSP approval flag');
$bad=$wire;$bad['payload']['can_activate']=true;
v7Deny(fn()=> $verify($bad),'fake activation authority');
$bad=$wire;$bad['payload']['gateway']='c6';
v7Deny(fn()=> $verify($bad),'different gateway from independent PSP decision');
$bad=$wire;$bad['payload']['crm_review_mac']=str_repeat('a',64);
v7Deny(fn()=> $verify($bad),'CRM MAC changed');
$bad=$wire;$bad['payload']['extra']=['secret'=>'ignore'];
v7Deny(fn()=> $verify($bad),'unexpected signed field');
$bad=$wire;$bad['mac']=str_repeat('0',64);
v7Deny(fn()=> $verify($bad),'forged signature');
v7Deny(fn()=> $verify($wire,'org-wrong-001'),'another tenant organization');
v7Deny(fn()=> $verify($wire,null,'order-wrong-001'),'another order');
v7Deny(fn()=> $verify($wire,null,null,['google_ads']),'missing expected channel');
v7Deny(fn()=> $verify($wire,null,null,null,4),'different policy revision');
v7Deny(fn()=> $verify($wire,null,null,null,null,str_repeat('c',64)),'different CRM review');
v7Deny(fn()=> $verify($wire,null,null,null,null,null,'2026-11-14T21:15:01Z'),'expired review');
v7Deny(fn()=> $verify($wire,null,null,null,null,null,'2026-11-14T20:59:59Z'),'not-yet-valid review');
v7Deny(fn()=> $verify($wire,null,null,null,null,null,null,'c6'),'changed PSP');
v7Deny(fn()=> $verify($wire,null,null,null,null,null,null,null,'boleto'),'changed PSP method');
v7Deny(fn()=> $verify($wire,null,null,null,null,null,null,null,null,str_repeat('X',48)),
    'wrong HMAC key');
v7Deny(fn()=> $verify($wire,null,null,['meta_ads','meta_ads']),'duplicate independently expected channel');

// The same Portal reference also computes a full-month postpaid review. Its
// economics MUST equal the independent Performance V7 per-channel calculator.
$threeEligible=['meta_ads'=>true,'google_ads'=>true,'chatgpt_ads'=>true];
$threeReview=PortalGateway::preview(
    ['chatgpt_ads','google_ads','meta_ads'],$threeEligible,$policy,'pix',
    $org,$order,$revision,$key
);
$threeWire=PortalIssuer::issue($threeReview,$threeEligible,$org,$order,
    $revision,$issued,$key);
$threeChecked=$verify($threeWire,null,null,
    ['chatgpt_ads','google_ads','meta_ads'],null,$threeReview['crm_review']['mac']);
v7Must($threeChecked['activation_review_cents']===147000,
    'synthetic three-channel eligibility requires R1470 activation (not media)');

$actual=['chatgpt_ads'=>100000,'google_ads'=>125000,'meta_ads'=>0];
$portalMonthly=PortalPolicy::monthlyReview(
    ['meta_ads','google_ads','chatgpt_ads'],
    '2026-10-01','2026-11',$actual,'SIM-PERF-V7-PAIRED-01'
);
$performanceMonthly=PerfFee::preview('2026-11',[
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
    'google_ads'=>['planned_cents'=>100000,'actual_cents'=>125000],
    'chatgpt_ads'=>['planned_cents'=>120000,'actual_cents'=>100000],
]);
v7Must($portalMonthly['informational_full_month_management_fee_cents']===
    $performanceMonthly['unverified_earned_fee_cents'],
    'cross-repo Portal and Performance postpaid R170+20% figures must match exactly');
v7Must($performanceMonthly['unverified_earned_fee_cents']===62000 &&
    $performanceMonthly['estimated_management_fee_cents']===61000,
    'cross-repo V7 3 channels R620 earned, R610 estimated');
v7Must($portalMonthly['postpaid_policy_version']===
    $threeChecked['management_postpaid_policy'] &&
    $portalMonthly['minimum_management_fee_cents']===17000 &&
    $portalMonthly['real_invoice_authorized']===false &&
    $portalMonthly['first_month_partial_contract_review_required']===false,
    'same policy and no invoice after full-month simulation');

$partial=PortalPolicy::monthlyReview(
    ['meta_ads'],'2026-11-16','2026-11',['meta_ads'=>0],
    'SIM-PERF-V7-PARTIAL-01'
);
v7Must($partial['first_month_partial_contract_review_required']===true &&
    $partial['real_invoice_authorized']===false,
    'first partial month must be reviewed, not silently charged at R170');

putenv('PERF_PORTAL_F419_V7_REVIEW_LAB=0');
v7Deny(fn()=> $verify($wire),'feature gate disabled');
putenv('PERF_PORTAL_F419_V7_REVIEW_LAB=1');
putenv('APP_ENV=production');
v7Deny(fn()=> $verify($wire),'production explicitly blocked');

echo "OK: actual pinned Portal F419 HMAC review verified independently; R490 activation and R170-per-channel 20pct postpaid V7 parity; no real API or charges\n";
