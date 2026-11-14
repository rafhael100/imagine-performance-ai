<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-v6-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__.'/../portal-v6-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceV6AgreementLabIssuer.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Catalog;
use App\Homologation\Commerce\PortalPerformanceV6AgreementLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalV6AgreementSignedGuard as Guard;
use Imagine\Performance\Commercial\PortalTriChannelV5CandidateGuard as GuardV5;

function v6Equals(mixed $expected,mixed $actual,string $label):void {
    if($expected!==$actual)throw new RuntimeException('V6 crossrepo mismatch: '.$label);
}
function v6Rejected(callable $f,string $label):void {
    try{$f();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('V6 crossrepo accepted invalid: '.$label);
}
if(getenv('APP_ENV')!=='testing' ||
   getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
   getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1'){
    throw new RuntimeException('V6 crossrepo CI-only guards required.');
}
$catalog=new Catalog();
$tenant='tenant_v6_portal_1234567';
$other='tenant_v6_other_7654321';
$keyId='k_v6_contract_test';
$key=str_repeat('G',48);
$now=1794660000;$period='2026-11';
$plan=['google_ads'=>100000,'meta_ads'=>70000,'chatgpt_ads'=>120000];
$unverified=['chatgpt_ads'=>0,'google_ads'=>140000,'meta_ads'=>50000];
$issue=static fn(string $agreement,string $nonce)=>
    Issuer::issue($catalog,$plan,$tenant,$agreement,$period,$keyId,
        $nonce,$now-1,$now+120,$key);
$one=$issue('agreement_brand_01_1234567','nonce_v6_agreement_one_123456');
$two=$issue('agreement_brand_02_1234567','nonce_v6_agreement_two_123456');
v6Equals(false,hash_equals($one['signature'],$two['signature']),'agreement changes HMAC');
$nonceClaims=[];$claims=0;
$claim=static function(string $t,string $k,string $n,int $expires)use(&$nonceClaims,&$claims):bool{
    $claims++;
    $id=$t."\0".$n;
    if(isset($nonceClaims[$id]))return false;
    $nonceClaims[$id]=$expires;
    return true;
};
$resolve=static fn(string $t,string $k):?string =>
    $t===$tenant && $k===$keyId ? str_repeat('G',48) : null;
$verify=static fn(array $envelope,string $ag='agreement_brand_01_1234567',
     string $who='tenant_v6_portal_1234567',string $month='2026-11',
     ?array $actual=null,?string $cat=null)=>
    Guard::verify($envelope,$who,$ag,$month,$cat??$catalog->version(),
        $catalog->minima(),$actual??$unverified,$now,$resolve,$claim);

v6Rejected(fn()=>$verify($one,'agreement_brand_02_1234567'),
    'cannot reuse a properly signed offer for another agreement');
v6Rejected(fn()=>$verify($one,'agreement_brand_01_1234567',$other),
    'cross tenant');
v6Rejected(fn()=>$verify($one,'agreement_brand_01_1234567',$tenant,'2026-12'),
    'cross month');
v6Rejected(fn()=>$verify($one,'agreement_brand_01_1234567',$tenant,$period,null,
    str_repeat('a',64)),'wrong trusted catalog version');
$bad=$one;$bad['agreement_id']='agreement_brand_02_1234567';
v6Rejected(fn()=>$verify($bad,'agreement_brand_02_1234567'),
    'agreement ID changed after signing');
$bad=$one;$bad['quote']['estimated_management_fee_cents']++;
v6Rejected(fn()=>$verify($bad),'fee tampering');
$bad=$one;$bad['quote']['media_by_channel_cents']['chatgpt_ads']-=1;
v6Rejected(fn()=>$verify($bad),'media floor changed');
$bad=$one;$bad['contract_version']='performance-trichannel-v5-candidate';
v6Rejected(fn()=>$verify($bad),'downgrade');
$bad=$one;$bad['audience']='imagine-performance-finance-v5-candidate-preview';
v6Rejected(fn()=>$verify($bad),'V5 audience');
$bad=$one;$bad['signature']=str_repeat('a',64);
v6Rejected(fn()=>$verify($bad),'bad HMAC');
$bad=$one;$bad['fake_approved']=true;
v6Rejected(fn()=>$verify($bad),'unknown signed field');
v6Rejected(fn()=>$verify($one,'agreement_brand_01_1234567',$tenant,$period,
    ['chatgpt_ads'=>0,'google_ads'=>140000]),'missing channel is not zero');
v6Rejected(fn()=>GuardV5::verify($one,$tenant,$period,$catalog->version(),
    $catalog->minima(),$unverified,$now,$resolve,$claim),
    'V5 cannot parse the new V6 agreement envelope');
v6Equals(0,$claims,'rejected claims never consume nonce');

$first=$verify($one);
v6Equals(true,$first['quote_claim_hmac_verified'],'real Portal signer validated');
v6Equals(true,$first['agreement_id_signed_in_envelope'],'agreement signature bound');
v6Equals('agreement_brand_01_1234567',$first['agreement_id'],'first agreement');
v6Equals(290000,$first['planned_media_cents'],'planned R2900');
v6Equals(190000,$first['unverified_actual_media_cents'],'synthetic R1900');
v6Equals(58000,$first['estimated_advance_fee_cents'],'20 percent estimated');
v6Equals(38000,$first['unverified_earned_fee_cents'],'20 percent simulated spent');
v6Equals(-20000,$first['review_only_adjustment_cents'],'negative delta review only');
foreach(['portal_catalog_active','portal_iam_authenticated','provider_spend_verified',
         'finance_approved','billable','asaas_write_enabled','ads_write_enabled'] as $flag) {
    v6Equals(false,$first[$flag],$flag.' must stay denied');
}
$second=$verify($two,'agreement_brand_02_1234567');
v6Equals('agreement_brand_02_1234567',$second['agreement_id'],'second agreement');
v6Equals(true,$second['agreement_id_signed_in_envelope'],'second agreement protected');
v6Equals(2,$claims,'two valid unique nonce claims');
v6Rejected(fn()=>$verify($one),'same signed agreement replay');
v6Equals(3,$claims,'replay checked by nonce claim');
putenv('PERF_PORTAL_V6_AGREEMENT_LAB=0');
v6Rejected(fn()=>$verify($two,'agreement_brand_02_1234567'),'disabled V6 guard');
putenv('PERF_PORTAL_V6_AGREEMENT_LAB=1');
putenv('APP_ENV=production');
v6Rejected(fn()=>$verify($two,'agreement_brand_02_1234567'),'deny production');
echo "OK: pinned real Portal HMAC V6 -> Performance expected tenant+agreement+month+catalog, 20% and replay; no IAM/PSP/Ads\n";
