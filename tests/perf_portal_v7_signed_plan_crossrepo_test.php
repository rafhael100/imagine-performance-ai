<?php
declare(strict_types=1);

// Actual reviewed Portal F419 and Portal V7 plan issuer loaded at a PINNED SHA.
// This integration never authenticates real customers or touches media/PSPs.
$root=__DIR__.'/../portal-v7-plan-reference/apps/portal/app/Domain';
require_once $root.'/Performance/ActivationPostpaidPreview.php';
require_once $root.'/PaymentRouting/GatewayRoutingPreview.php';
require_once $root.'/PaymentRouting/GatewayPolicyReview.php';
require_once $root.'/PaymentRouting/PortalGatewayReviewExporter.php';
require_once $root.'/Performance/ActivationGatewayReviewLab.php';
require_once $root.'/Performance/PerformanceChannelReviewEnvelope.php';
require_once $root.'/Performance/PortalPerformanceV7AgreementPlanLabIssuer.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalF419SignedActivationReviewV7LabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV7SignedPlanAgreementLabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/MonthlyManagementFeeFloorV7Candidate.php';

use App\Domain\Performance\ActivationGatewayReviewLab as Gateway;
use App\Domain\Performance\PerformanceChannelReviewEnvelope as ActivationIssuer;
use App\Domain\Performance\PortalPerformanceV7AgreementPlanLabIssuer as PlanIssuer;
use Imagine\Performance\Commercial\PortalV7SignedPlanAgreementLabVerifier as PlanVerifier;
use Imagine\Performance\Commercial\MonthlyManagementFeeFloorV7Candidate as Fee;

function planCheck(bool $ok,string $case):void {
    if(!$ok)throw new RuntimeException('Crossrepo signed V7 plan: '.$case);
}
function planReject(callable $callback,string $case):void {
    try {$callback();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Crossrepo signed V7 plan accepted: '.$case);
}
foreach([
    'APP_ENV'=>'testing',
    'PORTAL_PERF_V7_PLAN_AGREEMENT_LAB'=>'1',
    'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
    'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
] as $key=>$expected) {
    if(getenv($key)!==$expected)throw new RuntimeException('Missing lab flag '.$key);
}
$reviewKey=str_repeat('A',48);
$planKey=str_repeat('G',48);
$nowUtc='2026-11-14T21:05:00Z';
$issuedUtc='2026-11-14T21:00:00Z';
$now=(new DateTimeImmutable($nowUtc))->getTimestamp();
$scope=[
    'tenant_id'=>'tenant_v7plan_12345678',
    'agreement_id'=>'agreement_v7plan_12345678',
    'period'=>'2026-11',
    'organization_ref'=>'org-v7-plan-001',
    'order_ref'=>'order-v7-plan-001',
    'policy_revision'=>3,
];
$eligibility=['meta_ads'=>true,'google_ads'=>true,'chatgpt_ads'=>false];
$psp=[
    'revision'=>3,'mode'=>'manual','currency'=>'BRL','preferred_gateway'=>'asaas',
    'providers'=>[
        'asaas'=>[
            'enabled'=>true,'approved'=>true,'healthy'=>true,'fee_version'=>'SIM-TEST',
            'methods'=>['pix'=>[
                'enabled'=>true,'fixed_cents'=>100,
                'variable_bps'=>100,'settlement_days'=>1,
            ]],
        ],
        'c6'=>[
            'enabled'=>false,'approved'=>false,'healthy'=>false,
            'fee_version'=>'SIM-C6','methods'=>[],
        ],
    ],
];
$review=Gateway::preview(['meta_ads','google_ads'],$eligibility,$psp,'pix',
    $scope['organization_ref'],$scope['order_ref'],3,$reviewKey);
$activation=ActivationIssuer::issue(
    $review,$eligibility,$scope['organization_ref'],$scope['order_ref'],
    3,$issuedUtc,$reviewKey
);
$plan=['google_ads'=>100000,'meta_ads'=>70000];
$mint=static function(array $activationWire,array $original,
    array $eligibilityMap,array $scopeData,array $media,
    string $nonce)use($nowUtc,$now,$reviewKey,$planKey):array{
    return PlanIssuer::issue(
        $activationWire,$original,$eligibilityMap,$scopeData,$media,
        $nowUtc,'k_v7_plan_demo_01',$nonce,
        $now-1,$now+90,$reviewKey,$planKey
    );
};
$wire=$mint($activation,$review,$eligibility,$scope,$plan,
    'nonce_perf_v7_positive_12345678');
$expected=[
    'tenant_id'=>$scope['tenant_id'],
    'agreement_id'=>$scope['agreement_id'],
    'period'=>$scope['period'],
    'organization_ref'=>$scope['organization_ref'],
    'order_ref'=>$scope['order_ref'],
    'policy_revision'=>3,
    'gateway'=>$review['gateway_quote']['gateway'],
    'method'=>$review['gateway_quote']['method'],
    'crm_review_mac'=>$review['crm_review']['mac'],
    'key_id'=>'k_v7_plan_demo_01',
];
$resolve=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_v7_plan_demo_01'
        ?str_repeat('G',48):null;
$claimed=[];$claimCount=0;
$claim=static function(string $tenant,string $agreement,string $nonce,int $expires)
    use(&$claimed,&$claimCount):bool{
    $claimCount++;
    $key=$tenant."\0".$agreement."\0".$nonce;
    if(isset($claimed[$key]))return false;
    $claimed[$key]=$expires;
    return true;
};
$inspect=static function(array $candidate,?array $scopeOverride=null,
    ?array $mediaOverride=null,?array $activationOverride=null,
    ?array $channelsOverride=null,?string $clockOverride=null,
    ?string $reviewSecretOverride=null,?callable $keyOverride=null
)use($expected,$plan,$activation,$nowUtc,$reviewKey,$resolve,$claim):array{
    return PlanVerifier::verify(
        $candidate,$activationOverride??$activation,
        $scopeOverride??$expected,
        $channelsOverride??['google_ads','meta_ads'],
        $mediaOverride??$plan,
        $clockOverride??$nowUtc,
        $reviewSecretOverride??$reviewKey,
        $keyOverride??$resolve,
        $claim
    );
};
$ok=$inspect($wire);
planCheck($ok['hmac_verified_in_lab']===true &&
    $ok['nonce_claimed_via_callback_in_lab']===true &&
    $ok['durable_nonce_verified']===false,
    'pinned issuer and independently implemented HMAC verifier match, no operational nonce');
planCheck($ok['planned_media_total_cents']===170000 &&
    $ok['estimated_management_fee_cents']===37000 &&
    $ok['activation_review_cents']===98000,
    '2 channels: R1700 media, R370 management estimate, R980 activation');
planCheck($ok['tenant_id']===$scope['tenant_id'] &&
    $ok['billing_period']==='2026-11' &&
    $ok['agreement_id']===$scope['agreement_id'],
    'contract and month bound by signed plan');
foreach([
    'real_customer_contract_authenticated','customer_acceptance_proven',
    'portal_iam_authenticated','provider_spend_verified','finance_approved',
    'billable','asaas_write_enabled','c6_write_enabled',
    'ads_write_enabled','crm_write_enabled',
] as $flag){
    planCheck(($ok[$flag]??null)===false,'NO-GO flag '.$flag);
}
planCheck($claimCount===1,'valid claim consumed once after HMAC');
planReject(fn()=> $inspect($wire),'nonce replay denied');
planCheck($claimCount===2,'duplicate claim is observed without re-authorizing');

$mutations=[
    ['tenant_id','tenant_attacker_999999','foreign tenant'],
    ['agreement_id','agreement_attacker_999999','different agreement'],
    ['billing_period','2026-12','wrong month'],
    ['activation_review_mac',str_repeat('c',64),'different activation HMAC reference'],
    ['postpaid_policy_version','performance-postpaid-v6','stale fee policy'],
    ['minimum_management_fee_per_channel_cents',0,'R170 waived'],
    ['estimated_management_fee_cents',20000,'fee floor tampered'],
    ['activation_cents',97000,'activation price tampered'],
    ['planned_media_total_cents',1,'wrong budget total'],
    ['management_basis_points',1000,'fee rate tampered'],
    ['billable',true,'fake billability'],
    ['can_charge',true,'fake PSP charge'],
    ['can_activate',true,'fake campaign activation'],
    ['provider_spend_verified',true,'fake provider evidence'],
    ['media_paid_directly_to_platforms',false,'media incorrectly charged by Imagine'],
];
$i=0;
foreach($mutations as [$field,$replacement,$reason]){
    $bad=$mint($activation,$review,$eligibility,$scope,$plan,
        'nonce_perf_v7_mutation_'.str_pad((string)$i++,8,'0',STR_PAD_LEFT));
    $bad['payload'][$field]=$replacement;
    $before=$claimCount;
    planReject(fn()=> $inspect($bad),$reason);
    planCheck($claimCount===$before,$reason.' rejected BEFORE consuming nonce');
}
$extra=$mint($activation,$review,$eligibility,$scope,$plan,
    'nonce_perf_v7_extra_12345678');
$extra['payload']['ads_token']='Bearer secret';
$before=$claimCount;
planReject(fn()=> $inspect($extra),'extra malicious payload field');
planCheck($claimCount===$before,'unknown field pre-claim');
$bad=$mint($activation,$review,$eligibility,$scope,$plan,
    'nonce_perf_v7_signature_12345');
$bad['mac']=str_repeat('0',64);
planReject(fn()=> $inspect($bad),'bad plan signature');
planCheck($claimCount===$before,'bad signature does not consume nonce');

$expectedDifferent=$expected;$expectedDifferent['period']='2026-12';
planReject(fn()=> $inspect($wire,$expectedDifferent),'expected period changed');
$otherPlan=['google_ads'=>100000,'meta_ads'=>70001];
planReject(fn()=> $inspect($wire,null,$otherPlan),'expected budget changed');
$otherExpected=$expected;$otherExpected['crm_review_mac']=str_repeat('a',64);
planReject(fn()=> $inspect($wire,$otherExpected),'independent CRM MAC changed');
$otherExpected=$expected;$otherExpected['gateway']='c6';
planReject(fn()=> $inspect($wire,$otherExpected),'independent PSP changed');
planReject(fn()=> $inspect($wire,null,null,null,['meta_ads']),
    'missing channel selection');
planReject(fn()=> $inspect($wire,null,null,null,null,'2026-11-14T21:08:00Z'),
    'plan expired at +90 seconds');
planReject(fn()=> $inspect($wire,null,null,null,null,null,str_repeat('X',48)),
    'wrong activation HMAC secret');
planReject(fn()=> $inspect($wire,null,null,null,null,null,null,
    static fn(string $issuer,string $kid):?string=>str_repeat('T',48)),
    'wrong V7 agreement signing key');

$threeEligibility=['chatgpt_ads'=>true,'google_ads'=>true,'meta_ads'=>true];
$threeOriginal=Gateway::preview(
    ['meta_ads','google_ads','chatgpt_ads'],$threeEligibility,$psp,
    'pix',$scope['organization_ref'],$scope['order_ref'],3,$reviewKey
);
$threeActivation=ActivationIssuer::issue(
    $threeOriginal,$threeEligibility,$scope['organization_ref'],
    $scope['order_ref'],3,$issuedUtc,$reviewKey
);
$threePlan=['chatgpt_ads'=>120000,'google_ads'=>100000,'meta_ads'=>70000];
$threeWire=$mint($threeActivation,$threeOriginal,$threeEligibility,
    $scope,$threePlan,'nonce_perf_v7_3channels_123456');
$threeExpected=$expected;$threeExpected['crm_review_mac']=$threeOriginal['crm_review']['mac'];
$three=PlanVerifier::verify(
    $threeWire,$threeActivation,$threeExpected,array_keys($threePlan),
    $threePlan,$nowUtc,$reviewKey,$resolve,$claim
);
$fee=Fee::preview('2026-11',[
    'chatgpt_ads'=>['planned_cents'=>120000,'actual_cents'=>0],
    'google_ads'=>['planned_cents'=>100000,'actual_cents'=>0],
    'meta_ads'=>['planned_cents'=>70000,'actual_cents'=>0],
]);
planCheck($three['activation_review_cents']===147000 &&
    $three['planned_media_total_cents']===290000 &&
    $three['estimated_management_fee_cents']===61000 &&
    $fee['estimated_management_fee_cents']===$three['estimated_management_fee_cents'],
    '3 synthetic channels: R1470 activation, R2900 planned media and R610 monthly estimate');
planCheck($three['billable']===false &&
    $three['customer_acceptance_proven']===false,
    'synthetic 3rd channel is NOT production authorization');

putenv('PERF_PORTAL_V7_SIGNED_PLAN_LAB=0');
planReject(fn()=> $inspect($wire),'lab disabled');
putenv('PERF_PORTAL_V7_SIGNED_PLAN_LAB=1');
putenv('APP_ENV=production');
planReject(fn()=> $inspect($wire),'production denied');

echo "OK: actual Portal V7 signed media plan HMAC independently verified; account/month/channel/fee binding and replay callback, NO REAL contract/Ads/billing\n";
