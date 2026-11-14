<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceTriChannelV5LabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Candidate;
use App\Homologation\Commerce\PortalPerformanceTriChannelV5LabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalTriChannelV5CandidateGuard as Guard;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard as OldGuard;

function v5Equal(mixed $want,mixed $actual,string $label): void {
    if ($want !== $actual) {
        throw new RuntimeException('V5 crossrepo: '.$label.' wanted '.var_export($want,true).' actual '.var_export($actual,true));
    }
}
function v5Reject(callable $call,string $label): void {
    try { $call(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('V5 crossrepo accepted invalid: '.$label);
}
$source='https://github.com/rafhael100/imagine-portal/pull/772';
$catalog=new Candidate();
$legacy=new ApprovedOfferCatalog();
$meta=$catalog->minima();
v5Equal(['chatgpt_ads'=>120000,'google_ads'=>100000,'meta_ads'=>70000],$meta,'pinned Portal commercial minima');
v5Equal(true,$catalog->version()!==$legacy->version(),'V5 distinct from current active V4');
$tenant='tenant_v5_perf_lab12345';
$otherTenant='tenant_v5_other_lab12345';
$period='2026-11';
$now=1794660000;
$keyId='k_perf_v5_test';
$secret=str_repeat('T',48);
$resolver=static fn(string $t,string $k):?string =>
    ($t===$tenant && $k===$keyId) ? $secret : null;
$claims=[];
$claimCount=0;
$claimNonce=static function(string $t,string $k,string $nonce,int $expires) use (&$claims,&$claimCount): bool {
    $claimCount++;
    $key=$t."\0".$k."\0".$nonce;
    if (isset($claims[$key])) return false;
    $claims[$key]=$expires;
    return true;
};
$plans=['meta_ads'=>70000,'google_ads'=>100000,'chatgpt_ads'=>120000];
$expenses=['chatgpt_ads'=>90000,'google_ads'=>150000,'meta_ads'=>50000];
$issue=static fn(array $budget,string $nonce='nonce_v5_perf_1234567890abcdef',string $month='2026-11')=>
    Issuer::issue($catalog,$budget,$tenant,$month,$keyId,$nonce,$now-2,$now+120,$secret);
$signed=$issue($plans);
v5Equal('performance-trichannel-v5-candidate',$signed['contract_version'],'contract version');
v5Equal(58000,$signed['quote']['estimated_management_fee_cents'],'owner-approved three-channel advance');
v5Equal(false,$signed['quote']['real_charge_enabled'],'Portal cannot charge candidate');
v5Equal($catalog->version(),$signed['quote']['catalog_version'],'catalog fingerprint');
v5Equal(false,hash_equals($catalog->version(),$legacy->version()),'not legacy checkout');

$guard=static fn(array $envelope, string $t=$tenant, string $p=$period,
    array $actual=$expenses, ?string $expectedCatalog=null, ?array $floors=null)=>
    Guard::verify($envelope,$t,$p,$expectedCatalog??$catalog->version(),
        $floors??$meta,$actual,$now,$resolver,$claimNonce);

$broken=$signed;
$broken['quote']['estimated_management_fee_cents']++;
v5Reject(fn()=> $guard($broken),'fee HMAC tamper');
$broken=$signed;
$broken['quote']['media_by_channel_cents']['chatgpt_ads']=119999;
v5Reject(fn()=> $guard($broken),'ChatGPT floor cannot be bypassed');
$broken=$signed;$broken['billing_period']='2026-12';
v5Reject(fn()=> $guard($broken),'changed month after signature');
v5Reject(fn()=> $guard($signed,$tenant,'2026-12'),'cross-month spend');
v5Reject(fn()=> $guard($signed,$otherTenant),'cross-company misuse');
v5Reject(fn()=> $guard($signed,$tenant,$period,$expenses,$legacy->version()),'old catalog version rejected');
v5Reject(fn()=> $guard($signed,$tenant,$period,['chatgpt_ads'=>90000,'google_ads'=>150000]),'missing actual channel is NOT zero');
$badFloors=$meta;$badFloors['chatgpt_ads']='120000';
v5Reject(fn()=> $guard($signed,$tenant,$period,$expenses,null,$badFloors),'noninteger trusted price policy');
$broken=$signed;$broken['audience']='imagine-performance-finance-preview';
v5Reject(fn()=> $guard($broken),'v2 audience forbidden');
$broken=$signed;$broken['contract_version']='performance-v4';
v5Reject(fn()=> $guard($broken),'contract downgrade');
$broken=$signed;$broken['extra_field']='unapproved';
v5Reject(fn()=> $guard($broken),'strict signed shape');
$broken=$signed;$broken['signature']=str_repeat('a',64);
v5Reject(fn()=> $guard($broken),'wrong key signature');
v5Reject(fn()=> OldGuard::signingBytes(array_diff_key($signed,['signature'=>true])),'v2 guard cannot parse V5');
v5Equal(0,$claimCount,'bad quotes must not consume nonce');

$verified=$guard($signed);
v5Equal(true,$verified['quote_claim_hmac_verified'],'Portal V5 signed envelope authenticated in CI');
v5Equal(true,$verified['billing_period_bound'],'competence bound into signature');
v5Equal(290000,$verified['planned_media_cents'],'planned media is NOT management fee');
v5Equal(290000,$verified['unverified_actual_media_cents'],'synthetic actual example 2900 BRL');
v5Equal(58000,$verified['estimated_advance_fee_cents'],'estimated advance 20 percent');
v5Equal(58000,$verified['unverified_earned_fee_cents'],'actual hypothetical 20 percent');
v5Equal(0,$verified['review_only_adjustment_cents'],'equal planned and hypothetical spend');
v5Equal(1,$claimCount,'one nonce claim');
v5Equal(false,$verified['finance_approved'],'not approved by finance');
v5Equal(false,$verified['billable'],'candidate cannot be invoiced');
v5Equal(false,$verified['asaas_write_enabled'],'no Asaas');
v5Equal(false,$verified['ads_write_enabled'],'no Ads');
v5Equal(false,$verified['portal_catalog_active'],'not active Portal catalogue');
v5Reject(fn()=> $guard($signed),'HMAC valid but nonce reused');
v5Equal(2,$claimCount,'replay checked in durable claim provider');

$second=$issue($plans,'nonce_v5_second_1234567890abcdef');
$zero=$guard($second,$tenant,$period,['chatgpt_ads'=>0,'google_ads'=>0,'meta_ads'=>0]);
v5Equal(0,$zero['unverified_earned_fee_cents'],'no actual media means zero earned fee');
v5Equal(-58000,$zero['review_only_adjustment_cents'],'unused advance is review-only adjustment');
v5Equal(false,$zero['asaas_write_enabled'],'zero spend cannot trigger a refund or invoice');
$single=$issue(['chatgpt_ads'=>120000],'nonce_v5_chatgptonly_12345678');
$singlePreview=$guard($single,$tenant,$period,['chatgpt_ads'=>0]);
v5Equal(24000,$singlePreview['estimated_advance_fee_cents'],'ChatGPT alone BRL1200 20percent');
v5Equal(0,$singlePreview['unverified_earned_fee_cents'],'no spend remains zero');

putenv('PERF_PORTAL_TRICHANNEL_V5_LAB=0');
v5Reject(fn()=> $guard($issue($plans,'nonce_v5_disabled_123456789')),'Performance lab off');
putenv('PERF_PORTAL_TRICHANNEL_V5_LAB=1');
putenv('APP_ENV=production');
v5Reject(fn()=> $guard($signed),'no production verifier');
echo "OK: pinned Portal PR #772 HMAC V5 trichannel -> Performance strict guard, month, owner floors, replay, 20 percent, no billing\n";
