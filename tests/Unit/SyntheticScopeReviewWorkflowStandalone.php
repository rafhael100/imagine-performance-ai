<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeReviewWorkflow.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeReviewWorkflow;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F57_REVIEW_LAB')!=='1'){
 throw new RuntimeException('F57 unit suite exclusively synthetic PHP CLI');
}
$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void {
 if($got!==$expected)throw new RuntimeException(
    "FAIL F57 $name actual=".var_export($got,true)
    ." expected=".var_export($expected,true)
 );
 echo "PASS F57 ".++$n." $name".PHP_EOL;
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(InvalidArgumentException){return true;}
};
$l=new SyntheticManagedScopeLedger();
$flow=new SyntheticScopeReviewWorkflow($l);
$a='perf-demo-a';$b='perf-demo-b';
$pa='actor-demo-proposer';$reviewer='actor-demo-reviewer';$operator='actor-demo-operator';
$first='req-demo-replace-one';$old='cam-old-one';$new='cam-new-one';
$hash=hash('sha256','first intentional scope change');
$otherHash=hash('sha256','changed manifest cannot sneak through');
$t=1800000000;
$check('01 A google cap configured',
 $l->setDemoCapacity($a,'google_ads',1,2),'capacity_configured_demo');
$check('02 B google cap configured',
 $l->setDemoCapacity($b,'google_ads',1,1),'capacity_configured_demo');
$check('03 old campaign starts in managed scope',
 $l->includeCampaign($a,'google_ads',$old),'included_synthetic_only');
$check('04 old group in managed scope',
 $l->includeGroup($a,$old,'grp-old'),'included_synthetic_only');
$check('05 second old group in managed scope',
 $l->includeGroup($a,$old,'grp-old-two'),'included_synthetic_only');
$check('06 B separate campaign',
 $l->includeCampaign($b,'google_ads','cam-b-1'),'included_synthetic_only');
$check('07 unknown proposal denied',
 $flow->propose($a,'req-demo-unknown',$pa,'cam-missing','cam-new',$hash,$t,$t+120),
 'managed_scope_mismatch_denied');
$check('08 foreign tenant cannot propose on A resource',
 $flow->propose($b,'req-demo-cross',$pa,$old,'cam-cross',$hash,$t,$t+120),
 'managed_scope_mismatch_denied');
$check('09 impossible window refused',
 $flow->propose($a,'req-demo-invalid-window',$pa,$old,'cam-other',$hash,$t,$t),
 'invalid_review_window_denied');
$check('10 too-long fake approval window refused',
 $flow->propose($a,'req-demo-long',$pa,$old,'cam-other',$hash,$t,$t+3601),
 'invalid_review_window_denied');
$check('11 malformed request id throws',
 $throws(static fn()=>$flow->propose($a,'evil/id',$pa,$old,$new,$hash,$t,$t+120)),true);
$check('12 missing manifest digest throws',
 $throws(static fn()=>$flow->propose($a,'req-demo-invalid',$pa,$old,$new,'bad',$t,$t+120)),true);
$check('13 malformed proposer throws',
 $throws(static fn()=>$flow->propose($a,'req-demo-invalid','evil',$old,$new,$hash,$t,$t+120)),true);
$check('14 new proposal accepted only as a review request',
 $flow->propose($a,$first,$pa,$old,$new,$hash,$t,$t+120),'proposed_synthetic_only');
$check('15 pending state never execution',
 $flow->statusFor($a,$first),'pending_review_synthetic');
$check('16 foreign tenant cannot read request state',
 $flow->statusFor($b,$first),'not_found');
$check('17 duplicate request rejected',
 $flow->propose($a,$first,$pa,$old,'cam-new-evil',$hash,$t,$t+120),
 'duplicate_or_out_of_scope');
$check('18 duplicate cross-tenant request ID denied',
 $flow->propose($b,$first,$pa,'cam-b-1','cam-new-b',$hash,$t,$t+120),
 'duplicate_or_out_of_scope');
$check('19 source scope still unchanged before approval',
 $l->campaignState($a,$old),'active_demo');
$check('20 destination not yet created before approval',
 $l->campaignState($a,$new),'not_found');
$check('21 approver without mock role denied',
 $flow->decide($a,$first,$reviewer,$hash,false,true,$t+1),
 'independent_reviewer_required');
$check('22 self-approval denied',
 $flow->decide($a,$first,$pa,$hash,true,true,$t+1),
 'independent_reviewer_required');
$check('23 foreign tenant decision denied',
 $flow->decide($b,$first,$reviewer,$hash,true,true,$t+1),'not_found');
$check('24 modified manifest denied',
 $flow->decide($a,$first,$reviewer,$otherHash,true,true,$t+1),
 'review_manifest_conflict');
$check('25 review before creation timestamp denied',
 $flow->decide($a,$first,$reviewer,$hash,true,true,$t-1),'review_expired_denied');
$check('26 review remains pending after denials',
 $flow->statusFor($a,$first),'pending_review_synthetic');
$check('27 cannot apply before approval',
 $flow->applyMock($a,$first,$operator,$hash,true,$t+2),
 'review_not_approved_denied');
$check('28 approved mock review creates NO provider effect',
 $flow->decide($a,$first,$reviewer,$hash,true,true,$t+2),
 'approved_scope_demo_not_executed');
$check('29 approved review state still not executed',
 $flow->statusFor($a,$first),'approved_scope_demo_not_executed');
$check('30 source is still managed until apply',
 $l->campaignState($a,$old),'active_demo');
$check('31 reviewer cannot approve twice',
 $flow->decide($a,$first,'actor-demo-second-reviewer',$hash,true,true,$t+3),
 'already_reviewed_denied');
$check('32 operator self-participating as proposer denied',
 $flow->applyMock($a,$first,$pa,$hash,true,$t+3),
 'independent_operator_required');
$check('33 reviewer cannot also execute',
 $flow->applyMock($a,$first,$reviewer,$hash,true,$t+3),
 'independent_operator_required');
$check('34 operator without mock authorization denied',
 $flow->applyMock($a,$first,$operator,$hash,false,$t+3),
 'independent_operator_required');
$check('35 operator altered manifest denied',
 $flow->applyMock($a,$first,$operator,$otherHash,true,$t+3),
 'review_manifest_conflict');
$check('36 operator from foreign tenant cannot apply',
 $flow->applyMock($b,$first,$operator,$hash,true,$t+3),'not_found');
$check('37 scope unchanged by denied operators',
 $l->campaignState($a,$old),'active_demo');
$check('38 valid in-memory reviewed swap applied',
 $flow->applyMock($a,$first,$operator,$hash,true,$t+3),
 'scope_applied_synthetic_only');
$check('39 status records one mock apply',
 $flow->statusFor($a,$first),'scope_applied_synthetic_only');
$check('40 old campaign formally exits scope',
 $l->campaignState($a,$old),'outside_scope_demo');
$check('41 new campaign exists only as draft',
 $l->campaignState($a,$new),'draft_demo');
$check('42 no capacity overlap during in-memory swap',
 $l->capacity($a,'google_ads')['campaigns']['used'],1);
$check('43 historical group removed formally',
 $l->groupState($a,'grp-old'),'outside_scope_demo');
$check('44 old second group removed formally',
 $l->groupState($a,'grp-old-two'),'outside_scope_demo');
$check('45 group slots free after formal change',
 $l->capacity($a,'google_ads')['groups']['used'],0);
$check('46 replay of approved request denied',
 $flow->applyMock($a,$first,$operator,$hash,true,$t+4),
 'review_not_approved_denied');
$check('47 previous campaign ID cannot be reused',
 $l->includeCampaign($a,'google_ads',$old),'already_exists_or_out_of_scope');
$check('48 B tenant capacity unaffected',
 $l->capacity($b,'google_ads')['campaigns']['used'],1);
$check('49 A audit recorded only synthetic billing effect',
 count(array_filter($flow->auditFor($a),
  static fn(array $row):bool=>$row['billing_effect']!=='none_synthetic')),0);
$check('50 A audit recorded no provider effect',
 count(array_filter($flow->auditFor($a),
  static fn(array $row):bool=>$row['provider_action']!=='none_synthetic')),0);
$check('51 B does not see A audit',
 count(array_filter($flow->auditFor($b),
  static fn(array $row):bool=>$row['request_ref']===$first)),0);
$check('52 old approved request not in pending',
 count($flow->pendingFor($a)),0);

$second='req-demo-rejected';
$check('53 second review proposal submitted',
 $flow->propose($a,$second,$pa,$new,'cam-rejected',$hash,$t+5,$t+120),
 'proposed_synthetic_only');
$check('54 reviewer can reject request',
 $flow->decide($a,$second,$reviewer,$hash,true,false,$t+6),
 'rejected_scope_demo');
$check('55 rejection is never permission',
 $flow->applyMock($a,$second,$operator,$hash,true,$t+7),
 'review_not_approved_denied');
$check('56 rejected target was never created',
 $l->campaignState($a,'cam-rejected'),'not_found');
$check('57 rejected request state final',
 $flow->statusFor($a,$second),'rejected_scope_demo');

$third='req-demo-expiry';
$check('58 expiry test proposal submitted',
 $flow->propose($a,$third,$pa,$new,'cam-expired',$hash,$t+8,$t+15),
 'proposed_synthetic_only');
$check('59 reviewer cannot approve at exclusive expiry time',
 $flow->decide($a,$third,$reviewer,$hash,true,true,$t+15),
 'review_expired_denied');
$check('60 late review cannot apply',
 $flow->applyMock($a,$third,$operator,$hash,true,$t+16),
 'review_not_approved_denied');
$check('61 expiry did not change the managed scope',
 $l->campaignState($a,$new),'draft_demo');

$fourth='req-demo-stale';
$check('62 stale proposal accepted',
 $flow->propose($a,$fourth,$pa,$new,'cam-stale-target',$hash,$t+20,$t+100),
 'proposed_synthetic_only');
$check('63 stale proposal initially approved mock',
 $flow->decide($a,$fourth,$reviewer,$hash,true,true,$t+21),
 'approved_scope_demo_not_executed');
$check('64 scope state changes before apply',
 $l->pauseCampaign($a,$new),'paused_synthetic_only');
$check('65 stale state at apply fails closed',
 $flow->applyMock($a,$fourth,$operator,$hash,true,$t+22),
 'stale_scope_review_denied');
$check('66 stale status is terminal',
 $flow->statusFor($a,$fourth),'stale_scope_review_denied');
$check('67 cannot replay stale approval after changed scope',
 $flow->applyMock($a,$fourth,$operator,$hash,true,$t+23),
 'review_not_approved_denied');
$check('68 stale target never included',
 $l->campaignState($a,'cam-stale-target'),'not_found');

$fifth='req-demo-apply-expired';
$check('69 approved but soon-expiring request proposal',
 $flow->propose($a,$fifth,$pa,$new,'cam-late-target',$hash,$t+24,$t+30),
 'proposed_synthetic_only');
$check('70 approved before expiry',
 $flow->decide($a,$fifth,$reviewer,$hash,true,true,$t+25),
 'approved_scope_demo_not_executed');
$check('71 apply at expiry denied',
 $flow->applyMock($a,$fifth,$operator,$hash,true,$t+30),
 'review_expired_denied');
$check('72 no change after expired apply',
 $l->campaignState($a,'cam-late-target'),'not_found');
$check('73 foreign tenant pending queue cannot access A request',
 count($flow->pendingFor($b)),0);
$check('74 no absent token or account IDs in audit',
 preg_match('/token|provider_account|media_spend|charge/i,
   json_encode($flow->auditFor($a),JSON_THROW_ON_ERROR)),0);
$check('75 approval alone is no real execution',
 $l->capacity($a,'google_ads')['dispatch_enabled'],false);
$check('76 no A request changes B campaign',
 $l->campaignState($b,'cam-b-1'),'active_demo');

putenv('PERF_F57_REVIEW_LAB=0');
$check('77 disabled lab mode refuses new proposals',
 $flow->propose($a,'req-demo-off',$pa,$new,'cam-off',$hash,$t,$t+30),
 'review_lab_disabled');
$check('78 disabled lab mode hides approvals',
 $flow->pendingFor($a),[]);
putenv('PERF_F57_REVIEW_LAB=1');
putenv('APP_ENV=production');
$check('79 production cannot decide internal reviews',
 $flow->decide($a,$fifth,$reviewer,$hash,true,true,$t+26),
 'review_lab_disabled');
$check('80 production cannot apply any reviewed request',
 $flow->applyMock($a,$fifth,$operator,$hash,true,$t+26),
 'review_lab_disabled');
putenv('APP_ENV=testing');
$check('81 historical decision remains stable after re-enable',
 $flow->statusFor($a,$first),'scope_applied_synthetic_only');

echo "PASS F57 $n isolated review/scope-action checks, zero providers and charges".PHP_EOL;
