<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/ChannelMinimumBudgetProposal.php';
require_once __DIR__ . '/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__ . '/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__ . '/../src/Domain/Commercial/TrichannelSpendProposalReview.php';

use Imagine\Performance\Commercial\TrichannelSpendProposalReview as Review;

function sameTri(mixed $expected, mixed $actual, string $case): void {
    if ($expected !== $actual) throw new RuntimeException('Three-channel scenario: '.$case);
}
function rejectTri(callable $f, string $case): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid scenario accepted: '.$case);
}
$tenant='tenant_trichannel_lab1234';
$month='2026-11';
$zone='America/Sao_Paulo';
$planned=['chatgpt_ads'=>120000,'google_ads'=>100000,'meta_ads'=>70000];
$scopes=[
    'meta_ads'=>['act_987654321'=>$zone],
    'google_ads'=>['1234567890'=>$zone],
    'chatgpt_ads'=>['adacct_123456'=>$zone],
];
$fixtures=[
    'meta_ads'=>['act_987654321'=>[
        'account_id'=>'act_987654321','time_zone'=>$zone,
        'currency'=>'BRL','complete'=>true,'next'=>null,
        'data'=>[['account_id'=>'987654321','account_currency'=>'BRL',
            'date_start'=>'2026-11-01','date_stop'=>'2026-11-01','spend'=>'750.00']]
    ]],
    'google_ads'=>['1234567890'=>[
        'customer_id'=>'1234567890','time_zone'=>$zone,'currency_code'=>'BRL',
        'complete'=>true,'batches'=>[['results'=>[[
            'segments'=>['date'=>'2026-11-02'],
            'metrics'=>['costMicros'=>'1200000000']
        ]]]]
    ]],
    'chatgpt_ads'=>['adacct_123456'=>[
        'account_id'=>'adacct_123456','time_zone'=>$zone,'currency_code'=>'BRL',
        'complete'=>true,'has_more'=>false,
        'data'=>[['readable_time'=>'2026-11-03','spend'=>'500.00',
                  'impressions'=>1000,'clicks'=>20]]
    ]],
];
$r=Review::preview($tenant,$month,$planned,$scopes,$fixtures);
sameTri(['chatgpt_ads'=>50000,'google_ads'=>120000,'meta_ads'=>75000],$r['channels'],'all channel totals');
sameTri(290000,$r['proposed_planned_media_cents'],'proposed media floor budget');
sameTri(245000,$r['unverified_actual_media_cents'],'total mock account spend');
sameTri(58000,$r['illustrative_advance_management_fee_cents'],'20 percent planned advance');
sameTri(49000,$r['illustrative_earned_management_fee_cents'],'20 percent not verified');
sameTri(-9000,$r['illustrative_adjustment_cents'],'adjustment review only');
sameTri(3,$r['account_count'],'three account scopes');
sameTri(false,$r['portal_catalog_approved'],'not signed Portal quote');
sameTri(false,$r['quote_signed_by_portal'],'not a real quote');
sameTri(false,$r['provider_spend_verified'],'not provider proof');
sameTri(false,$r['finance_approved'],'not approved by finance');
sameTri(false,$r['asaas_write_enabled'],'not chargeable');
sameTri(false,$r['ads_write_enabled'],'cannot mutate ads');

$sorted=Review::preview($tenant,$month,
    ['meta_ads'=>70000,'google_ads'=>100000,'chatgpt_ads'=>120000],
    ['chatgpt_ads'=>$scopes['chatgpt_ads'],'google_ads'=>$scopes['google_ads'],'meta_ads'=>$scopes['meta_ads']],
    ['chatgpt_ads'=>$fixtures['chatgpt_ads'],'google_ads'=>$fixtures['google_ads'],'meta_ads'=>$fixtures['meta_ads']]);
sameTri($r,$sorted,'canonical order and deterministic provenance');

$redistributed=$fixtures;
$redistributed['google_ads']['1234567890']['batches'][0]['results'][0]['metrics']['costMicros']='1150000000';
$redistributed['meta_ads']['act_987654321']['data'][0]['spend']='800.00';
$r2=Review::preview($tenant,$month,$planned,$scopes,$redistributed);
sameTri($r['unverified_actual_media_cents'],$r2['unverified_actual_media_cents'],'equal total after redistribution');
if ($r['synthetic_provenance_sha256_not_provider_proof'] ===
    $r2['synthetic_provenance_sha256_not_provider_proof']) {
    throw new RuntimeException('Changed account records reused the old digest');
}
$missing=$fixtures;unset($missing['chatgpt_ads']);
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$missing),'ChatGPT missing fixture');
$missing=$fixtures;unset($missing['google_ads']['1234567890']);
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$missing),'Google missing account');
$foreign=$fixtures;$foreign['chatgpt_ads']['adacct_hijack']=$fixtures['chatgpt_ads']['adacct_123456'];
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$foreign),'uncontracted ChatGPT account');
$bad=$fixtures;$bad['chatgpt_ads']['adacct_123456']['has_more']=true;
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'ChatGPT incomplete pagination');
$bad=$fixtures;$bad['chatgpt_ads']['adacct_123456']['currency_code']='USD';
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'ChatGPT currency mismatch');
$bad=$fixtures;$bad['chatgpt_ads']['adacct_123456']['data'][0]['spend']=500.00;
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'float expense rejected');
$bad=$fixtures;$bad['chatgpt_ads']['adacct_123456']['provider_verified']=true;
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'forged trust field');
$bad=$fixtures;$bad['meta_ads']['act_987654321']['next']='another-page';
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'partial Meta pagination');
$bad=$fixtures;$bad['google_ads']['1234567890']['complete']=false;
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$bad),'partial Google batch stream');
rejectTri(fn()=>Review::preview('tenant_other_999999',$month,$planned,$scopes,$fixtures),'foreign tenant');
rejectTri(fn()=>Review::preview($tenant,'2026-12',$planned,$scopes,$fixtures),'period mismatch');
rejectTri(fn()=>Review::preview($tenant,$month,['google_ads'=>100000,'meta_ads'=>70000],$scopes,$fixtures),'quote vs channel scope mismatch');
rejectTri(fn()=>Review::preview($tenant,$month,['google_ads'=>99999,'meta_ads'=>70000,'chatgpt_ads'=>120000],$scopes,$fixtures),'below proposed Google floor');

putenv('PERF_TRICHANNEL_SPEND_LAB=0');
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$fixtures),'disabled lab');
putenv('PERF_TRICHANNEL_SPEND_LAB=1');
putenv('APP_ENV=production');
rejectTri(fn()=>Review::preview($tenant,$month,$planned,$scopes,$fixtures),'production denied');

echo "OK: Google+Meta+ChatGPT account-scoped synthetic spend reconciliation; 20% preview and immutable fingerprint with no billing/Portal signature\n";
