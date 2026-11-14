<?php
declare(strict_types=1);

require_once __DIR__ . '/../crm-reference/app/Core/Leads/SyntheticCalculatorNurtureConsentInspector.php';

use App\Core\Leads\SyntheticCalculatorNurtureConsentInspector as Inspector;

function sameCrm(mixed $a,mixed $b,string $case): void
{
    if ($a !== $b) throw new RuntimeException('CRM contract mismatch: '.$case);
}
function deniedCrm(callable $f,string $case): void
{
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('CRM accepted invalid calculator draft: '.$case);
}
$draft=[
    'source'=>'imagine_comercial_calculadora_performance',
    'schema'=>'synthetic.calculator.lead-intent.v1',
    'email'=>'guest.synthetic@example.com',
    'name'=>'Visitante Sintetico',
    'channels'=>['meta_ads','google_ads','chatgpt_ads'],
    'report_requested'=>true,
    'marketing_opt_in'=>false,
    'notice_version'=>'lab-notice-v1',
    'budget_estimate_cents'=>430000,
];

$review=Inspector::review($draft);
sameCrm('synthetic.calculator.lead-intent-review.v1',$review['schema'],'schema');
sameCrm('imagine_comercial_calculadora_performance',$review['source'],'origin');
sameCrm(3,$review['channel_count'],'three channels recognized');
sameCrm(true,$review['report_request_claimed'],'distinct study request');
sameCrm(false,$review['marketing_opt_in_claimed'],'study does not opt in marketing');
foreach ([
 'tenant_authorized','consent_proven','crm_delivery_proven',
 'report_delivery_allowed','marketing_enrollment_allowed',
 'can_send_email','can_start_nurture_sequence','can_store_personal_data'
] as $gate) {
    sameCrm(false,$review[$gate],$gate.' must fail closed');
}
$json=json_encode($review,JSON_THROW_ON_ERROR);
if (str_contains($json,$draft['email']) || str_contains($json,$draft['name'])) {
    throw new RuntimeException('Synthetic review leaked test contact PII.');
}
$market=$draft;
$market['marketing_opt_in']=true;
$reviewMarketing=Inspector::review($market);
sameCrm(true,$reviewMarketing['marketing_opt_in_claimed'],'marketing claim captured separately');
sameCrm(false,$reviewMarketing['marketing_enrollment_allowed'],'a checkbox does not prove consent');
sameCrm(false,$reviewMarketing['can_start_nurture_sequence'],'no marketing execution');

$marketingOnly=$draft;
$marketingOnly['report_requested']=false;
$marketingOnly['marketing_opt_in']=true;
$marketingReview=Inspector::review($marketingOnly);
sameCrm(false,$marketingReview['report_request_claimed'],'newsletter opt-in not report request');
sameCrm(false,$marketingReview['can_send_email'],'offline no email even with opt-in');

$invalid=$draft;
$invalid['report_requested']=false;
$invalid['marketing_opt_in']=false;
deniedCrm(fn()=>Inspector::review($invalid),'no independent service request');
$invalid=$draft;$invalid['channels']=['chatgpt_ads','chatgpt_ads'];
deniedCrm(fn()=>Inspector::review($invalid),'duplicate channel');
$invalid=$draft;$invalid['channels'][]='tiktok_ads';
deniedCrm(fn()=>Inspector::review($invalid),'unknown provider');
$invalid=$draft;$invalid['schema']='unsigned-proposal';
deniedCrm(fn()=>Inspector::review($invalid),'unversioned schema');
$invalid=$draft;$invalid['notice_version']='unknown-privacy-notice';
deniedCrm(fn()=>Inspector::review($invalid),'unknown consent notice');
$invalid=$draft;$invalid['budget_estimate_cents']=-1;
deniedCrm(fn()=>Inspector::review($invalid),'negative media amount');
$invalid=$draft;$invalid['tenant_id']='tenant_from_browser';
deniedCrm(fn()=>Inspector::review($invalid),'browser-supplied tenant field');
$invalid=$draft;$invalid['can_send_email']=true;
deniedCrm(fn()=>Inspector::review($invalid),'browser-supplied permission flag');

echo "OK: pinned Imagine CRM consent inspector accepts three-channel candidate yet forbids PII persistence, emails and marketing without verified consent\n";
