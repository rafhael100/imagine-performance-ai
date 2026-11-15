<?php
declare(strict_types=1);

// Cross-repository CI-only proof: the REAL Portal F826 PHP authority preflight
// feeds a signed V7 plan into the INDEPENDENT Performance V7 verifier.
// Every identity and grant here is a synthetic in-memory test fixture.
$domain=__DIR__.'/../portal-f826-reference/apps/portal/app/Domain';
foreach([
 '/Identity/PortalUserId.php',
 '/Organizations/OrganizationId.php',
 '/Organizations/OrganizationRole.php',
 '/Organizations/MembershipStatus.php',
 '/Authorization/Permission.php',
 '/Organizations/OrganizationMembership.php',
 '/Authorization/AuthorizationContext.php',
 '/Authorization/AuthorizationDecision.php',
 '/Authorization/OrganizationAuthorizationService.php',
 '/PortalAccess/Authority/PortalSessionPurpose.php',
 '/PortalAccess/Authority/PortalSessionSnapshot.php',
 '/PortalAccess/Authority/PortalMembershipAuthority.php',
 '/PortalAccess/Authority/PortalSessionAuthority.php',
 '/PortalAccess/Authority/PortalResourceAuthority.php',
 '/PortalAccess/Authority/PortalOrganizationAuthority.php',
 '/PortalAccess/Authority/PortalOrganizationSnapshot.php',
 '/PortalAccess/Authority/PortalBoundaryVerdict.php',
 '/PortalAccess/Authority/PortalAuthorityBoundary.php',
 '/PortalAccess/Authority/DenyingPortalAuthorities.php',
 '/Performance/ActivationPostpaidPreview.php',
 '/PaymentRouting/GatewayRoutingPreview.php',
 '/PaymentRouting/GatewayPolicyReview.php',
 '/PaymentRouting/PortalGatewayReviewExporter.php',
 '/Performance/ActivationGatewayReviewLab.php',
 '/Performance/PerformanceChannelReviewEnvelope.php',
 '/Performance/PortalPerformanceV7AgreementPlanLabIssuer.php',
 '/Performance/PortalPerformanceV7AuthorityPreflightLab.php',
] as $path)require_once $domain.$path;
require_once __DIR__.'/../src/Domain/Commercial/PortalF419SignedActivationReviewV7LabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV7SignedPlanAgreementLabVerifier.php';

use App\Domain\Identity\PortalUserId;
use App\Domain\Organizations\OrganizationId;
use App\Domain\Organizations\OrganizationRole;
use App\Domain\Organizations\MembershipStatus;
use App\Domain\Organizations\OrganizationMembership;
use App\Domain\Authorization\Permission;
use App\Domain\PortalAccess\Authority\PortalSessionAuthority;
use App\Domain\PortalAccess\Authority\PortalMembershipAuthority;
use App\Domain\PortalAccess\Authority\PortalResourceAuthority;
use App\Domain\PortalAccess\Authority\PortalOrganizationAuthority;
use App\Domain\PortalAccess\Authority\PortalOrganizationSnapshot;
use App\Domain\PortalAccess\Authority\PortalSessionSnapshot;
use App\Domain\PortalAccess\Authority\PortalSessionPurpose;
use App\Domain\PortalAccess\Authority\PortalAuthorityBoundary;
use App\Domain\PortalAccess\Authority\DenyingPortalAuthorities;
use App\Domain\Performance\ActivationGatewayReviewLab as PortalGateway;
use App\Domain\Performance\PerformanceChannelReviewEnvelope as PortalActivation;
use App\Domain\Performance\PortalPerformanceV7AuthorityPreflightLab as PortalPreflight;
use Imagine\Performance\Commercial\PortalV7SignedPlanAgreementLabVerifier as PerfPlan;

function f826Require(bool $condition,string $reason):void{
 if(!$condition)throw new RuntimeException('Portal F826/Performance V7: '.$reason);
}
function f826Reject(callable $callback,string $reason):void{
 try{$callback();}catch(InvalidArgumentException|RuntimeException){return;}
 throw new RuntimeException('Portal F826/Performance V7 unsafe allow: '.$reason);
}
foreach([
 'APP_ENV'=>'testing',
 'PORTAL_PERF_V7_PLAN_AGREEMENT_LAB'=>'1',
 'PORTAL_PERF_V7_AUTHORITY_PREFLIGHT_LAB'=>'1',
 'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
 'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
] as $k=>$v)if(getenv($k)!==$v)throw new RuntimeException('CI-only flag missing: '.$k);
$instant='2026-11-14T21:05:00Z';
$issued='2026-11-14T21:00:00Z';
$now=(new DateTimeImmutable($instant,new DateTimeZone('UTC')))->getTimestamp();
$org=new OrganizationId('org-v7-ci-crossrepo');
$actor=new PortalUserId('actor-v7-ci-crossrepo');
$tenant='tenant_f826_perf_12345678';
$agreement='agreement_f826_perf_12345678';
$scope=[
 'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>'2026-11',
 'organization_ref'=>$org->value,'order_ref'=>'order-v7-f826-001','policy_revision'=>3,
];
$planned=['google_ads'=>100000,'meta_ads'=>70000];
$eligible=['google_ads'=>true,'meta_ads'=>true,'chatgpt_ads'=>false];
$activationSecret=str_repeat('X',48);
$planSecret=str_repeat('Y',48);
$policy=[
 'revision'=>3,'mode'=>'manual','currency'=>'BRL','preferred_gateway'=>'asaas',
 'providers'=>[
  'asaas'=>['enabled'=>true,'approved'=>true,'healthy'=>true,'fee_version'=>'SIM-TEST',
   'methods'=>['pix'=>['enabled'=>true,'fixed_cents'=>100,'variable_bps'=>100,
    'settlement_days'=>1]]],
  'c6'=>['enabled'=>false,'approved'=>false,'healthy'=>false,
   'fee_version'=>'SIM-C6','methods'=>[]],
 ],
];
$original=PortalGateway::preview(['google_ads','meta_ads'],$eligible,$policy,
 'pix',$org->value,$scope['order_ref'],3,$activationSecret);
$activation=PortalActivation::issue($original,$eligible,$org->value,
 $scope['order_ref'],3,$issued,$activationSecret);

$session=static fn(bool $mfa=true,bool $active=true,
 PortalSessionPurpose $purpose=PortalSessionPurpose::Business):PortalSessionSnapshot=>
 new PortalSessionSnapshot($actor,$active,$mfa,$now-600,$now+600,3,3,
 $org,2,3,$purpose);
$member=static fn(
 MembershipStatus $status=MembershipStatus::Active,
 array $grants=[Permission::ManageServices]
):OrganizationMembership=>new OrganizationMembership(
 $actor,$org,OrganizationRole::Member,$status,$grants,2
);
$boundary=static function(
 ?PortalSessionSnapshot $authenticated,
 ?OrganizationMembership $membership,
 ?OrganizationId $resourceOwner,
 bool $orgActive=true
)use($org,$agreement):PortalAuthorityBoundary{
 $sessions=new class($authenticated) implements PortalSessionAuthority{
  public function __construct(private ?PortalSessionSnapshot $snapshot){}
  public function find(string $id):?PortalSessionSnapshot{
   return $id==='ci-only-portal-session-ref'?$this->snapshot:null;
  }
 };
 $members=new class($membership) implements PortalMembershipAuthority{
  public function __construct(private ?OrganizationMembership $member){}
  public function findFor(PortalUserId $actor,OrganizationId $organization):?OrganizationMembership{
   return $this->member;
  }
 };
 $resources=new class($resourceOwner,$agreement) implements PortalResourceAuthority{
  public function __construct(private ?OrganizationId $owner,private string $agreement){}
  public function ownerOf(OrganizationId $org,string $ref):?OrganizationId{
   return $ref===$this->agreement?$this->owner:null;
  }
 };
 $organizations=new class($org,$orgActive) implements PortalOrganizationAuthority{
  public function __construct(private OrganizationId $organization,private bool $active){}
  public function lookup(OrganizationId $org):?PortalOrganizationSnapshot{
   return new PortalOrganizationSnapshot($this->organization,$this->active,3);
  }
 };
 return new PortalAuthorityBoundary($sessions,$members,$resources,$organizations);
};
$review=static function(PortalAuthorityBoundary $authority,
 ?string $handle='ci-only-portal-session-ref',
 ?array $media=null
)use($org,$tenant,$agreement,$activation,$original,$eligible,$scope,
 $planned,$instant,$now,$activationSecret,$planSecret):array {
 return PortalPreflight::review(
  $authority,$handle,$org,$tenant,$agreement,
  $activation,$original,$eligible,$scope,$media??$planned,$instant,
  'k_f826_perf_v7_01','nonce_f826_crossrepo_12345678',
  $now-1,$now+90,$activationSecret,$planSecret
 );
};
$good=$boundary($session(),$member(),$org);
$source=$review($good);
$wire=$source['signed_plan_review'];
f826Require($source['synthetic_business_session_and_membership_checked']===true,
 'real Portal F826 class checked the synthetic business session');
f826Require($source['real_iam_authenticated']===false &&
 $source['real_customer_acceptance_proven']===false && $source['can_charge']===false,
 'positive synthetic result cannot authorize a customer or PSP');

$expected=$scope+[
 'gateway'=>$original['gateway_quote']['gateway'],
 'method'=>$original['gateway_quote']['method'],
 'crm_review_mac'=>$original['crm_review']['mac'],
 'key_id'=>'k_f826_perf_v7_01',
];
$claimCount=0;
$claim=static function(string $t,string $a,string $nonce,int $expires)
 use(&$claimCount,$tenant,$agreement):bool{
 $claimCount++;
 return $claimCount===1 && $t===$tenant && $a===$agreement &&
   $nonce==='nonce_f826_crossrepo_12345678' && $expires>0;
};
$keyResolver=static fn(string $issuer,string $kid):?string=>
 $issuer==='imagine-portal-ci'&&$kid==='k_f826_perf_v7_01'
 ?str_repeat('Y',48):null;
$verified=PerfPlan::verify(
 $wire,$activation,$expected,array_keys($planned),$planned,
 $instant,$activationSecret,$keyResolver,$claim
);
f826Require($verified['hmac_verified_in_lab']===true &&
 $verified['channels']===['google_ads','meta_ads'] &&
 $verified['planned_media_total_cents']===170000 &&
 $verified['estimated_management_fee_cents']===37000 &&
 $verified['activation_review_cents']===98000,
 'real F826 Portal preflight -> signed F419/V7 -> independent Performance cents parity');
f826Require($claimCount===1,'valid signed V7 review claims one synthetic nonce');
foreach([
 'real_customer_contract_authenticated','customer_acceptance_proven',
 'portal_iam_authenticated','finance_approved','provider_spend_verified',
 'billable','ads_write_enabled','asaas_write_enabled','crm_write_enabled',
] as $flag)f826Require(($verified[$flag]??null)===false,
 'crossrepo HMAC does NOT elevate operational authority: '.$flag);

// Production audience must deny all spoofed, stale or missing authority
// BEFORE producing a signed plan. A pass does not prove authentic SQL IAM.
f826Reject(fn()=> $review($boundary($session(mfa:false),$member(),$org)),
 'session lacks MFA');
f826Reject(fn()=> $review($boundary($session(active:false),$member(),$org)),
 'revoked session');
f826Reject(fn()=> $review($boundary($session(),$member(MembershipStatus::Revoked),$org)),
 'revoked membership');
f826Reject(fn()=> $review($boundary($session(),$member(MembershipStatus::Active,
 [Permission::ViewServices]),$org)),
 'missing services.manage');
f826Reject(fn()=> $review($boundary($session(),$member(),new OrganizationId('org-foreign'))),
 'foreign agreement owner');
f826Reject(fn()=> $review($boundary($session(),$member(),$org,false)),
 'suspended organization');
f826Reject(fn()=> $review($boundary($session(
 purpose:PortalSessionPurpose::InvitationOnly),$member(),$org)),
 'invitation-only session');
f826Reject(fn()=> $review($good,'crm-session-not-portal-session'),
 'CRM bearer is not a Portal session');
f826Reject(fn()=> $review($good,null,['google_ads'=>99999,'meta_ads'=>70000]),
 'Google budget below minimum');
f826Reject(fn()=> $review($good,null,['google_ads'=>100000,'meta_ads'=>69999]),
 'Meta budget below minimum');
f826Require($claimCount===1,'denied preflight cases never claim another nonce');
f826Reject(fn()=> PerfPlan::verify(
 $wire,$activation,$expected,array_keys($planned),$planned,
 $instant,$activationSecret,$keyResolver,$claim
), 'cannot replay signed plan into Performance');

$deny=new DenyingPortalAuthorities();
$defaultDeny=new PortalAuthorityBoundary($deny,$deny,$deny,$deny);
f826Reject(fn()=> $review($defaultDeny),'missing real Portal authority provider');

echo "PASS F826↔Performance V7: actual Portal MFA/services.manage preflight emitted signed plan accepted by independent verifier; all permissions are SYNTHETIC and no live work is allowed\n";
