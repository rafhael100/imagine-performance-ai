<?php
declare(strict_types=1);

/**
 * F174 pinned cross-repo security/readiness check.
 * The historical signer is executable (F173), but the newer PR #356
 * candidate does not carry it at the historically pinned path.
 *
 * This test MUST NOT turn absence into a permissive fallback.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F174_READINESS_LAB')!=='1') {
 throw new RuntimeException('F174 only runs under synthetic CI');
}
$root=dirname(__DIR__,2);
$manifest=json_decode(file_get_contents(
 $root.'/contracts/security/f174-portal-candidate-readiness.json'
),true,32,JSON_THROW_ON_ERROR);
$historic=$root.'/portal-f174-historical';
$candidate=$root.'/portal-f174-candidate';
$historicalSha='739465b5cc5022aae85385e360e1b4b19ab033cd';
$candidateSha='1427a486434f653e6873ea6f3b37294d781aa316';
$n=0;
$check=static function(bool $condition,string $reason)use(&$n):void{
 ++$n;
 if(!$condition)throw new RuntimeException('F174 FAILED: '.$reason);
};
$check($manifest['schema_version']==='perf.portal.integration-readiness.pinned-ci.v1',
 'manifest has an explicitly versioned integration schema');
$check($manifest['source_repository']==='rafhael100/imagine-portal',
 'only the actual Portal repository is authoritative for signer provenance');
$check($manifest['historical_issuer']['ref']===$historicalSha
 &&$manifest['candidate']['ref']===$candidateSha,
 'two independent source commits are fixed, not silently branch-floating');
$check((int)$manifest['candidate']['pr_number']===356,
 'integration candidate PR recorded explicitly');
$check($manifest['candidate']['source_is_pinned_snapshot_only']===true,
 'candidate is time-scoped evidence, never described as continuously current');
$check($manifest['target_status']==='NO_GO',
 'uncertified integration cannot appear production-ready');

$historicSigner=$historic.'/'.$manifest['historical_issuer']['file'];
$historicEmitter=$historic.'/'.$manifest['historical_issuer']['emitter'];
$candidateSigner=$candidate.'/'.$manifest['historical_issuer']['file'];
$known=$candidate.'/'.$manifest['candidate']['known_file'];
$dashboard=$candidate.'/'.$manifest['candidate']['dashboard_file'];
$check(is_file($historicSigner)&&is_file($historicEmitter),
 'historical original Portal issuer and emitter present in pinned checkout');
$check(!is_file($candidateSigner),
 'candidate snapshot cannot be mistaken for containing the historical issuer');
$check($manifest['candidate']['same_issuer_path_expected_exists']===false,
 'missing candidate issuer is a recorded open integration dependency');
$check(is_file($known)&&is_file($dashboard),
 'candidate checkout is genuine and includes other known Portal Performance classes');

$original=file_get_contents($historicSigner);
$f29=file_get_contents($root.'/app/Performance/Security/SignedPortalCapabilityGate.php');
$f172=file_get_contents($root.'/src/Domain/Performance/F172LegacyPortalPgReadBridge.php');
$f170=file_get_contents($root.'/src/Domain/Performance/F170NonceBoundPortalReadGateway.php');
$check(is_string($original)&&str_contains($original,"IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1"),
 'original issuer retains domain separation and is not a made-up fixture');
$check(str_contains($original,"portal-capability-envelope.v1")
 &&str_contains($f29,"portal-capability-envelope.v1"),
 'original Portal envelope version is still shared with F29 verifier');
$check(str_contains($f172,'SignedPortalCapabilityGate')
 &&str_contains($f172,'perf-demo-aurora'),
 'F172 explicitly delegates to original F29 verifier and only fixed A SQL mapping');
$check(str_contains($f172,'F166Pg16ReadOnlyDashboard')
 &&str_contains($f172,'f170_consume_signed_read_nonce'),
 'F172 retains RLS read and F170 signed nonce gate');
foreach(['membership_revision','service_revision','session_revision',
 'recovery_epoch','session_mfa_verified'] as $field){
 $check(str_contains($original,$field)
    &&str_contains($f29,$field),
    'canonical Portal issuer and F29 verifier retain '.$field);
}
$check(str_contains($f170,"'ready_for_production'=>false")
 &&str_contains($f170,"'can_enable_ads'=>false")
 &&str_contains($f172,"'portal_identity_authenticated'=>false"),
 'integration wrappers keep production identity, Ads and billing gates closed');
$check(str_contains($f172,"'perf-demo-a'")
 &&str_contains($f172,"'perf-demo-aurora'"),
 'legacy tenant mapping is explicit fixture, not a generalized IAM resolver');
$check(str_contains($f172,"'PERF_F172_LEGACY_BRIDGE_LAB'")
 &&str_contains($f170,"'PERF_F170_NONCE_LAB'"),
 'legacy and nonce bridges require explicit CLI lab flags');
$check(!str_contains($f172,'F169SignedPortalReadGateway'),
 'compatibility bridge does not downgrade to incompatible abbreviated F169 claims');

$expect=$manifest['expected_security_assertions'];
foreach([
 'preserve_f29_strict_envelope','preserve_f29_session_mfa',
 'preserve_f29_membership_service_session_revisions',
 'preserve_f29_recovery_epoch','no_fallback_to_f169_short_envelope',
 'require_pg16_rls_tenant','distinguish_f124_from_f170_nonce_ledgers',
 'signer_missing_at_candidate_path_is_open_integration_blocker'
] as $constraint){
 $check(($expect[$constraint]??null)===true,
  'non-negotiable canonical integration gate: '.$constraint);
}
foreach([
 'runtime_deployment_authorized','mfa_production_verified',
 'portal_iam_verified','portal_s2s_verified',
 'rollback_witness_verified','real_advertiser_consent_verified'
] as $gate){
 $check(($expect[$gate]??null)===false,
  'external production gate explicitly remains NO-GO: '.$gate);
}
$check(str_contains(file_get_contents($root.'/src/Domain/Performance/F169SignedPortalReadGateway.php'),
 'performance:demo:read')
 &&str_contains($f29,"'campaigns:read'"),
 'F169 and F29 still use different scope strings; do not infer equivalence');
echo "F174 Portal candidate readiness sentry: {$n} PASS / 0 FAIL — CANDIDATE GAP CONFIRMED / NO-GO\n";
