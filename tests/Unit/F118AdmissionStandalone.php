<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/src/Domain/Performance/ServiceActionAdmissionPolicy.php';

use Imagine\Performance\Domain\ServiceActionAdmissionPolicy;
use Imagine\Performance\Domain\ServiceActionRequest;
use Imagine\Performance\Domain\ServiceAdmissionEvidence;
use Imagine\Performance\Domain\ServiceActionApproval;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('F1-18 policy tests require offline CLI.');
}
$now=1800000000;
$req=[
    'organizationRef'=>'org-demo-b',
    'tenantRef'=>'perf-demo-b',
    'serviceRef'=>'svc-demo-b',
    'actorRef'=>'actor-demo-b',
    'resourceRef'=>'campaign-demo-b1',
    'operation'=>'campaign.pause',
    'manifestVersion'=>'manifest-demo-v1',
];
$evid=[
    'organizationRef'=>'org-demo-b',
    'tenantRef'=>'perf-demo-b',
    'serviceRef'=>'svc-demo-b',
    'resourceTenantRef'=>'perf-demo-b',
    'resourceServiceRef'=>'svc-demo-b',
    'actorRef'=>'actor-demo-b',
    'actorTenantRef'=>'perf-demo-b',
    'actorStatus'=>'active_mock',
    'actorScopes'=>['campaigns:write','proposals:approve'],
    'sourceState'=>'active_mock',
    'localState'=>'active_mock',
    'contractState'=>'eligible_mock',
    'sourceRevision'=>2,
    'localRevision'=>2,
    'sourceObservedAt'=>$now-3,
    'localObservedAt'=>$now-3,
    'externalRecoveryEpoch'=>8,
    'localRecoveryEpoch'=>8,
    'recoveryState'=>'ready_mock',
    'sourceAvailable'=>true,
    'localAvailable'=>true,
    'externalFenceAvailable'=>true,
    'workloadAttestedInLab'=>true,
];
$approved=[
    'approvalId'=>'approval-demo-001',
    'proposerActorRef'=>'actor-demo-b',
    'approverActorRef'=>'actor-reviewer-b',
    'approverStatus'=>'active_mock',
    'organizationRef'=>'org-demo-b',
    'tenantRef'=>'perf-demo-b',
    'serviceRef'=>'svc-demo-b',
    'resourceRef'=>'campaign-demo-b1',
    'operation'=>'campaign.replace',
    'manifestVersion'=>'manifest-demo-v1',
    'approvedRevision'=>2,
    'approvedAt'=>$now-5,
    'state'=>'approved_mock',
];
$n=0;
$check=static function(string $case, bool $allow,string $code,bool $wanted,string $expected) use (&$n):void {
    if ($allow!==$wanted || $code!==$expected) {
        throw new RuntimeException("FAIL F1-18 $case allow=".var_export($allow,true)
            ." code=$code expected=".var_export($wanted,true)." $expected");
    }
    ++$n;
    echo "PASS F1-18 $n $case\n";
};
$decide=static function (
    array $requestOverrides=[],
    array $evidenceOverrides=[],
    ?array $approval=null,
    ?ServiceActionAdmissionPolicy $policy=null
) use ($req,$evid,$now) {
    $policy??=ServiceActionAdmissionPolicy::forSyntheticLabOnly();
    return $policy->decide(
        new ServiceActionRequest(...array_replace($req,$requestOverrides)),
        new ServiceAdmissionEvidence(...array_replace($evid,$evidenceOverrides)),
        $approval===null?null:new ServiceActionApproval(...$approval),
        $now
    );
};
$probe=static function (
    string $label,
    array $request=[],
    array $evidence=[],
    ?array $approval=null,
    bool $allowed=false,
    string $reason='',
) use ($decide,$check):void {
    $got=$decide($request,$evidence,$approval);
    $check($label,$got->allowed,$got->code,$allowed,$reason);
};

// A default-constructed product policy ALWAYS denies, even if someone supplies
// perfectly self-consistent PHP objects and a lab flag.
putenv('PERF_F18_ADMISSION_LAB=1');
putenv('APP_ENV=testing');
$default=$decide(policy:new ServiceActionAdmissionPolicy());
$check('01 default deployment policy fails closed',
    $default->allowed,$default->code,false,'deployment_gate_closed');
putenv('PERF_F18_ADMISSION_LAB=0');
$v=$decide();
$check('02 no lab flag fails closed', $v->allowed,$v->code,false,'deployment_gate_closed');
putenv('PERF_F18_ADMISSION_LAB=1');
putenv('APP_ENV=production');
$v=$decide();
$check('03 non-testing env fails closed', $v->allowed,$v->code,false,'deployment_gate_closed');
putenv('APP_ENV=testing');

// This ALLOW is intentionally synthetic and carries no authoritative grants.
$probe('04 valid synthetic pause',allowed:true,reason:'admitted_synthetic_only');
$probe('05 valid synthetic replace + separate approval',
    request:['operation'=>'campaign.replace'],
    approval:$approved,allowed:true,reason:'admitted_synthetic_only');
$probe('06 no approval for campaign replacement',
    request:['operation'=>'campaign.replace'],reason:'independent_approval_required');
$probe('07 no approval for campaign creation',
    request:['operation'=>'campaign.create'],reason:'independent_approval_required');
$probe('08 no approval for campaign release',
    request:['operation'=>'campaign.release'],reason:'independent_approval_required');
$probe('09 unrecognized operation',
    request:['operation'=>'billing.charge'],reason:'unknown_or_invalid_action');
$probe('10 hidden UI route not treated as known operation',
    request:['operation'=>'/api/campaigns/run'],reason:'unknown_or_invalid_action');
$probe('11 invalid campaign resource identifier',
    request:['resourceRef'=>'../../campaign'],reason:'unknown_or_invalid_action');
$probe('12 invalid actor identifier',
    request:['actorRef'=>' actor-demo-b'],reason:'unknown_or_invalid_action');
$probe('13 invalid organization identifier',
    request:['organizationRef'=>'x'.str_repeat('z',110)],reason:'unknown_or_invalid_action');
$probe('14 cross-organization rejected',
    request:['organizationRef'=>'org-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('15 cross-tenant rejected',
    request:['tenantRef'=>'perf-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('16 cross-service rejected',
    request:['serviceRef'=>'svc-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('17 cross-actor rejected',
    request:['actorRef'=>'actor-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('18 foreign resource tenant rejected',
    evidence:['resourceTenantRef'=>'perf-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('19 foreign resource service rejected',
    evidence:['resourceServiceRef'=>'svc-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('20 foreign actor tenant rejected',
    evidence:['actorTenantRef'=>'perf-demo-a'],reason:'tenant_or_actor_binding_denied');
$probe('21 expired membership rejected',
    evidence:['actorStatus'=>'revoked_mock'],reason:'actor_scope_denied');
$probe('22 missing actor capability rejected',
    evidence:['actorScopes'=>['reports:read']],reason:'actor_scope_denied');
$probe('23 source authority unavailable',
    evidence:['sourceAvailable'=>false],reason:'identity_or_authority_unavailable');
$probe('24 local state unavailable',
    evidence:['localAvailable'=>false],reason:'identity_or_authority_unavailable');
$probe('25 external revocation fence unavailable',
    evidence:['externalFenceAvailable'=>false],reason:'identity_or_authority_unavailable');
$probe('26 synthetic workload not attested',
    evidence:['workloadAttestedInLab'=>false],reason:'identity_or_authority_unavailable');
$probe('27 restore old main revision',
    evidence:['localRevision'=>1],reason:'service_revision_mismatch');
$probe('28 restore wrong future revision',
    evidence:['localRevision'=>3],reason:'service_revision_mismatch');
$probe('29 invalid source revision 0',
    evidence:['sourceRevision'=>0,'localRevision'=>0],reason:'service_revision_mismatch');
$probe('30 old external recovery epoch',
    evidence:['localRecoveryEpoch'=>7],reason:'recovery_quarantine');
$probe('31 missing external restore generation',
    evidence:['externalRecoveryEpoch'=>0],reason:'recovery_quarantine');
$probe('32 old local recovery generation',
    evidence:['localRecoveryEpoch'=>0],reason:'recovery_quarantine');
$probe('33 restore in progress',
    evidence:['recoveryState'=>'restoring_mock'],reason:'recovery_quarantine');
$probe('34 post-restore verification incomplete',
    evidence:['recoveryState'=>'checking_mock'],reason:'recovery_quarantine');
$probe('35 stale source observation beyond 30s',
    evidence:['sourceObservedAt'=>$now-31],reason:'stale_or_future_evidence');
$probe('36 stale destination observation',
    evidence:['localObservedAt'=>$now-31],reason:'stale_or_future_evidence');
$probe('37 source evidence from future beyond 3s',
    evidence:['sourceObservedAt'=>$now+4],reason:'stale_or_future_evidence');
$probe('38 local observation from future beyond 3s',
    evidence:['localObservedAt'=>$now+4],reason:'stale_or_future_evidence');
$probe('39 revoked Portal source',
    evidence:['sourceState'=>'revoked_mock'],reason:'service_not_eligible');
$probe('40 revoked Performance local',
    evidence:['localState'=>'revoked_mock'],reason:'service_not_eligible');
$probe('41 unapproved service contract',
    evidence:['contractState'=>'pending_mock'],reason:'service_not_eligible');

$badApprovalCases=[
    ['42 actor approves own request',['approverActorRef'=>'actor-demo-b']],
    ['43 wrong proposer',['proposerActorRef'=>'actor-demo-a']],
    ['44 unapproved state',['state'=>'pending_mock']],
    ['45 approver revoked',['approverStatus'=>'revoked_mock']],
    ['46 wrong approval org',['organizationRef'=>'org-demo-a']],
    ['47 wrong approval tenant',['tenantRef'=>'perf-demo-a']],
    ['48 wrong approval service',['serviceRef'=>'svc-demo-a']],
    ['49 wrong approval campaign',['resourceRef'=>'campaign-demo-a1']],
    ['50 approval for wrong operation',['operation'=>'campaign.release']],
    ['51 stale manifest',['manifestVersion'=>'manifest-demo-v0']],
    ['52 stale revision',['approvedRevision'=>1]],
    ['53 expired approval',['approvedAt'=>$now-31]],
    ['54 invalid approving identity',['approverActorRef'=>'actor<bad>']],
    ['55 malformed approval identifier',['approvalId'=>'../../wrong']],
];
foreach ($badApprovalCases as [$label,$mutation]) {
    $probe($label,request:['operation'=>'campaign.replace'],
        approval:array_replace($approved,$mutation),
        reason:'independent_approval_required');
}
$probe('56 approval accepted for correctly scoped release',
    request:['operation'=>'campaign.release'],
    approval:array_replace($approved,['operation'=>'campaign.release']),
    allowed:true,reason:'admitted_synthetic_only');
$probe('57 approval accepted for correctly scoped create',
    request:['operation'=>'campaign.create'],
    approval:array_replace($approved,['operation'=>'campaign.create']),
    allowed:true,reason:'admitted_synthetic_only');
$probe('58 valid synthetic proposal decision requires separate reviewer',
    request:['operation'=>'approval.decide'],
    approval:array_replace($approved,['operation'=>'approval.decide']),
    allowed:true,reason:'admitted_synthetic_only');
$probe('59 wrong actor scope on approval',
    request:['operation'=>'approval.decide'],
    evidence:['actorScopes'=>['campaigns:write']],
    approval:array_replace($approved,['operation'=>'approval.decide']),
    reason:'actor_scope_denied');

$p=ServiceActionAdmissionPolicy::forSyntheticLabOnly();
putenv('PERF_F18_ADMISSION_LAB=0');
$d=$p->decide(
    new ServiceActionRequest(...$req),
    new ServiceAdmissionEvidence(...$evid),
    null,$now
);
$check('60 disabling lab flag also disables existing policy object',
    $d->allowed,$d->code,false,'deployment_gate_closed');
putenv('PERF_F18_ADMISSION_LAB=1');
$d=$p->decide(
    new ServiceActionRequest(...$req),null,null,$now
);
$check('61 missing evidence denied',
    $d->allowed,$d->code,false,'missing_evidence');
$d=$p->decide(
    new ServiceActionRequest(...$req),
    new ServiceAdmissionEvidence(...$evid),null,0
);
$check('62 invalid clock denied',
    $d->allowed,$d->code,false,'invalid_clock');
echo "PASS F1-18 {$n} central action-admission policy checks (all lab-only)\n";
