<?php
declare(strict_types=1);

/**
 * FAST-PERF F171 — negative compatibility test.
 *
 * F29/F123 has the REAL earlier Portal lab wire format and authority fields.
 * F169/F170 is a separate downstream laboratory wire format.
 * Neither format is silently transformed into the other.
 *
 * This executes both existing verifiers with the SAME signing key, so a
 * rejection cannot be blamed on different keys or signature algorithms.
 */
require dirname(__DIR__,2).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require dirname(__DIR__,2).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F170NonceBoundPortalReadGateway.php';

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F169SignedPortalReadGateway;
use Imagine\Performance\Domain\F170NonceBoundPortalReadGateway;

foreach([
  'PERF_F29_SIGNED_PORTAL_LAB','PERF_F162_PG_LAB',
  'PERF_F166_READ_DASHBOARD_LAB','PERF_F169_SIGNED_READ_LAB',
  'PERF_F170_NONCE_LAB','PERF_F171_CROSSCHECK_LAB'
] as $flag){
    if(getenv($flag)!=='1')throw new RuntimeException('F171 missing CI gate: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_f162_ci'
    ||!extension_loaded('sodium')||!extension_loaded('pdo_pgsql')){
    throw new RuntimeException('F171 requires disposable PostgreSQL CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','postgres',$options);
$tenantDb=new PDO($dsn,'perf_f162_a','ci-only-perf-f162-a',$options);
$nonceDb=new PDO($dsn,'perf_f170_gateway','ci-only-f170-gateway',$options);
$pair=sodium_crypto_sign_keypair();
$private=sodium_crypto_sign_secretkey($pair);
$public=sodium_crypto_sign_publickey($pair);
$oldVerifier=new SignedPortalCapabilityGate(bin2hex($public));
$newVerifier=new F169SignedPortalReadGateway(
    new F166Pg16ReadOnlyDashboard($tenantDb),$public
);
$nonceBound=new F170NonceBoundPortalReadGateway($newVerifier,$nonceDb);

$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
    ++$n;
    if(!$ok)throw new RuntimeException('F171 FAILED: '.$why);
};
$toBase64url=static fn(string $bin):string=>
    rtrim(strtr(base64_encode($bin),'+/','-_'),'=');
$now=time();
$legacyClaims=[
    'version'=>1,
    'issuer'=>'imagine.portal',
    'audience'=>'imagine.performance-ai',
    'profile'=>'portal.capability.synthetic.v1',
    'organization_ref'=>'org-demo-a',
    'performance_tenant_ref'=>'perf-demo-a',
    'portal_service_ref'=>'svc-demo-a',
    'resource_ref'=>'demo-cam-a1',
    'actor_ref'=>'actor-demo-a',
    'session_ref'=>'session-demo-a',
    'permission'=>'campaigns:read',
    'session_mfa_verified'=>true,
    'membership_revision'=>2,
    'service_revision'=>3,
    'session_revision'=>4,
    'recovery_epoch'=>5,
    'service_state'=>'active_mock',
    'issued_at'=>$now,
    'expires_at'=>$now+20,
    'nonce'=>bin2hex(random_bytes(16)),
];
$oldPayload=json_encode($legacyClaims,
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$oldSignature=sodium_crypto_sign_detached(
    "IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1\n".$oldPayload,$private
);
$legacyEnvelope=json_encode([
    'version'=>'portal-capability-envelope.v1',
    'key_id'=>'ephemeral-lab-key-1',
    'payload'=>$toBase64url($oldPayload),
    'signature'=>$toBase64url($oldSignature),
],JSON_THROW_ON_ERROR);
$currentAuthority=SyntheticCurrentAuthority::forSyntheticLab($now);
$check($oldVerifier->check($legacyEnvelope,$currentAuthority,$now)
    ==='verified_synthetic_only',
    'actual F29 verifier accepts correctly signed older Portal lab envelope');
$check($newVerifier->read($legacyEnvelope)['status']
    ==='untrusted_request_denied',
    'same-key F169 validator refuses F29 Portal envelope rather than silently mapping it');
$check($nonceBound->read($legacyEnvelope)['status']
    ==='untrusted_request_denied',
    'F170 cannot consume a legacy envelope without explicit protocol migration');

$modernClaims=[
    'subject'=>'imagine-portal-synthetic-s2s',
    'audience'=>'imagine-performance-readonly-v1',
    'scope'=>'performance:demo:read',
    'tenant'=>'perf-demo-aurora',
    'issued_at'=>$now,
    'expires_at'=>$now+20,
    'request_id'=>'request_f171_crosscheck_01',
];
$modernPayload=json_encode($modernClaims,
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$modernEnvelope=json_encode([
    'claims'=>$modernClaims,
    'signature'=>base64_encode(
        sodium_crypto_sign_detached($modernPayload,$private)
    ),
],JSON_THROW_ON_ERROR);
$check($newVerifier->read($modernEnvelope)['status']
    ==='accepted_readonly_demo',
    'F169 accepts its own modern signed fixture using the SAME Ed25519 public key');
$check($oldVerifier->check($modernEnvelope,
    SyntheticCurrentAuthority::forSyntheticLab($now),$now)
    ==='malformed_envelope',
    'F29 never considers F169 packet an older Portal-approved capability');

$check($legacyClaims['performance_tenant_ref']!==
    $modernClaims['tenant'],
    'F29 tenant identifier and F169 SQL demo tenant are not identical strings');
$check($legacyClaims['permission']!==$modernClaims['scope'],
    'F29 permission campaigns:read is distinct from F169 performance:demo:read scope');
$check(isset($legacyClaims['membership_revision'])
    &&isset($legacyClaims['service_revision'])
    &&isset($legacyClaims['session_revision'])
    &&isset($legacyClaims['recovery_epoch'])
    &&isset($legacyClaims['portal_service_ref'])
    &&isset($legacyClaims['session_mfa_verified']),
    'original Portal proof carries service, membership, session and recovery safeguards');
$check(!array_key_exists('membership_revision',$modernClaims)
    &&!array_key_exists('service_revision',$modernClaims)
    &&!array_key_exists('session_revision',$modernClaims)
    &&!array_key_exists('recovery_epoch',$modernClaims)
    &&!array_key_exists('session_mfa_verified',$modernClaims),
    'F169 synthetic capability omits Portal F29 authorization and revocation fields');

$initial=(int)$admin->query(
    "SELECT count(*) FROM perf_f162.consumed_read_nonces
     WHERE request_id='request_f171_crosscheck_01'"
)->fetchColumn();
$check($initial===0,
    'legacy-format refusal did not consume F170 nonce for modern request');
$first=$nonceBound->read($modernEnvelope);
$check($first['status']==='accepted_readonly_demo'
    &&$first['nonce_consumed_demo']===true,
    'modern fixture still successfully uses its own one-shot SQL nonce table');
$check($nonceBound->read($modernEnvelope)['status']
    ==='replay_or_nonce_denied',
    'same modern request cannot be reused through F170');
$check((int)$admin->query(
    "SELECT count(*) FROM perf_f162.consumed_read_nonces
     WHERE request_id='request_f171_crosscheck_01'"
)->fetchColumn()===1,
    'the new F170 nonce namespace is consumed exactly once');

$oldNoMfa=$legacyClaims;
$oldNoMfa['session_mfa_verified']=false;
$payload=json_encode($oldNoMfa,
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$envelope=json_encode([
    'version'=>'portal-capability-envelope.v1',
    'key_id'=>'ephemeral-lab-key-1',
    'payload'=>$toBase64url($payload),
    'signature'=>$toBase64url(sodium_crypto_sign_detached(
        "IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1\n".$payload,$private
    )),
],JSON_THROW_ON_ERROR);
$check($oldVerifier->check($envelope,
    SyntheticCurrentAuthority::forSyntheticLab($now),$now)!==
    'verified_synthetic_only',
    'F29 correctly denies a cryptographically signed legacy request with MFA false');

$check($first['ready_for_production']===false
    &&$first['can_enable_ads']===false
    &&$first['can_bill']===false
    &&$first['report']['s2s_verified']===false
    &&$first['report']['iam_verified']===false,
    'accepted modern read cannot imply parity with verified Portal IAM');

echo "F171 F29/F124 vs F169/F170 wire-format compatibility: {$n} PASS / 0 FAIL — INCOMPATIBLE, NO-GO\n";
