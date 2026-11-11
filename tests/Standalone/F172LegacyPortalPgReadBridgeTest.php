<?php
declare(strict_types=1);

/**
 * F172 cross-contract proof: original Portal-F29 Ed25519 envelope can drive
 * a F166 PostgreSQL16 mock preview and a F170 one-shot nonce receipt.
 * ALL tenant/session/service authorities are synthetic and test-only.
 */
require dirname(__DIR__,2).'/src/Domain/Performance/F172LegacyPortalPgReadBridge.php';

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F172LegacyPortalPgReadBridge;

foreach(['PERF_F29_SIGNED_PORTAL_LAB','PERF_F162_PG_LAB',
 'PERF_F166_READ_DASHBOARD_LAB','PERF_F170_NONCE_LAB',
 'PERF_F172_LEGACY_BRIDGE_LAB'] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F172 CI flag missing: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F172 needs disposable PostgreSQL16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$pdoOpts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$connect=static fn(string $u,string $pw):PDO=>new PDO($dsn,$u,$pw,$pdoOpts);
$admin=$connect('postgres','postgres');
$pdoA=$connect('perf_f162_a','ci-only-perf-f162-a');
$pdoB=$connect('perf_f162_b','ci-only-perf-f162-b');
$nonce=$connect('perf_f170_gateway','ci-only-f170-gateway');

$keypair=sodium_crypto_sign_keypair();
$private=sodium_crypto_sign_secretkey($keypair);
$public=sodium_crypto_sign_publickey($keypair);
$hexPub=bin2hex($public);
$n=0;
$check=static function(bool $yes,string $why)use(&$n):void {
 ++$n;
 if(!$yes)throw new RuntimeException('F172 FAILED: '.$why);
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(RuntimeException $e){return true;}
};
$base64url=static fn(string $x):string=>
 rtrim(strtr(base64_encode($x),'+/','-_'),'=');
$time=time();
$claim=[
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
 'issued_at'=>$time,
 'expires_at'=>$time+25,
 'nonce'=>bin2hex(random_bytes(16)),
];
$signOriginal=static function(array $claims,string $secret)use($base64url):string{
 $raw=json_encode($claims,
  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $sig=sodium_crypto_sign_detached(
  "IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1\n".$raw,$secret);
 return json_encode([
  'version'=>'portal-capability-envelope.v1',
  'key_id'=>'ephemeral-lab-key-1',
  'payload'=>$base64url($raw),
  'signature'=>$base64url($sig),
 ],JSON_THROW_ON_ERROR);
};
$make=static fn(PDO $sql,PDO $nonceDb,string $key):F172LegacyPortalPgReadBridge=>
 new F172LegacyPortalPgReadBridge(
  new SignedPortalCapabilityGate($key),
  new F166Pg16ReadOnlyDashboard($sql),
  $nonceDb
 );
$envelope=$signOriginal($claim,$private);
$authority=SyntheticCurrentAuthority::forSyntheticLab($time);
$check((new SignedPortalCapabilityGate($hexPub))->check(
 $envelope,SyntheticCurrentAuthority::forSyntheticLab($time),$time
)==='verified_synthetic_only',
 'the real preexisting F29 signature validator accepts the envelope');

$check($throws(static fn()=>$make($pdoA,$pdoA,$hexPub)),
 'nonce connection must use separate F170 gateway role, not tenant');
$check($throws(static fn()=>$make($pdoA,$admin,$hexPub)),
 'superuser database login is not an approved synthetic gateway role');

$bridge=$make($pdoA,$nonce,$hexPub);
$first=$bridge->read($envelope,$authority,$time);
$check($first['status']==='original_portal_f29_pg16_readonly_demo_only'
 &&$first['nonce_consumed_demo']===true
 &&$first['legacy_portal_signature_verified_in_lab']===true,
 'ORIGINAL F29 envelope reaches PG16 read and consumes F170 nonce');
$check(is_array($first['report'])
 &&$first['report']['tenant_ref']==='perf-demo-aurora',
 'the only permitted synthetic fixed mapping is F29 Portal A to SQL A');
$check($first['report']['read_only']===true
 &&$first['report']['iam_verified']===false
 &&$first['report']['s2s_verified']===false,
 'original F29 signature does not imply real Portal IAM or S2S authentication');
$check($first['synthetic_only']===true
 &&$first['portal_identity_authenticated']===false
 &&$first['dispatch_enabled']===false
 &&$first['billing_enabled']===false
 &&$first['ready_for_production']===false,
 'accepted bridge never authorizes advertising or real business operation');
$check($bridge->read($envelope,$authority,$time)['status']
 ==='original_portal_signature_or_authority_denied',
 'F29 itself blocks reuse within the same PHP verifier instance');

$another=$make($pdoA,$connect('perf_f170_gateway','ci-only-f170-gateway'),$hexPub);
$check($another->read($envelope,$authority,$time)['status']
 ==='replay_or_nonce_denied',
 'fresh F29 verifier on new PHP object cannot bypass F170 durable nonce');
$statement=$admin->prepare('SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE tenant=? AND request_id=?');
$statement->execute(['perf-demo-aurora',$claim['nonce']]);
$check((int)$statement->fetchColumn()===1,
 'canonical Portal F29 nonce written exactly once by separate gateway SQL role');

$otherRole=$make($pdoB,$connect('perf_f170_gateway','ci-only-f170-gateway'),$hexPub);
$additional=$claim;
$additional['nonce']=bin2hex(random_bytes(16));
$otherSigned=$signOriginal($additional,$private);
$deniedB=$otherRole->read($otherSigned,$authority,$time);
$check($deniedB['status']==='sql_tenant_or_report_denied'
 &&$deniedB['report']===null,
 'valid original Portal A envelope cannot read SQL tenant B');
$crossTenantStmt=$admin->prepare(
 'SELECT count(*) FROM perf_f162.consumed_read_nonces
  WHERE tenant=? AND request_id=?');
$crossTenantStmt->execute(['perf-demo-boreal',$additional['nonce']]);
$check((int)$crossTenantStmt->fetchColumn()===0,
 'cross-tenant rejection does not burn the specific B nonce from denied envelope');

$mutated=json_decode($otherSigned,true,16,JSON_THROW_ON_ERROR);
$mutated['payload']=$base64url('{"tenant":"perf-demo-boreal"}');
$check($make($pdoA,$nonce,$hexPub)->read(
 json_encode($mutated,JSON_THROW_ON_ERROR),$authority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'tampering Portal envelope claims without signing blocks reading');
$wrongKeyPair=sodium_crypto_sign_keypair();
$wrongPublic=bin2hex(sodium_crypto_sign_publickey($wrongKeyPair));
$check($make($pdoA,$nonce,$wrongPublic)->read(
 $otherSigned,$authority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'mismatched pinned Portal verification key blocks reading');

$revoked=get_object_vars($authority);
$revoked['mfaVerified']=false;
$revokedAuthority=new SyntheticCurrentAuthority(...$revoked);
$check($make($pdoA,$nonce,$hexPub)->read(
 $otherSigned,$revokedAuthority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'original F29 revocation guard refuses unavailable MFA before SQL nonce');
$revoked=get_object_vars($authority);
$revoked['serviceActive']=false;
$revokedAuthority=new SyntheticCurrentAuthority(...$revoked);
$check($make($pdoA,$nonce,$hexPub)->read(
 $otherSigned,$revokedAuthority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'original F29 service entitlement revoked denies before SQL read');

$revoked=get_object_vars($authority);
$revoked['serviceRevision']++;
$revokedAuthority=new SyntheticCurrentAuthority(...$revoked);
$check($make($pdoA,$nonce,$hexPub)->read(
 $otherSigned,$revokedAuthority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'original F29 service revision mismatch is not bypassed by new adapter');
$revoked=get_object_vars($authority);
$revoked['recoveryEpoch']++;
$revokedAuthority=new SyntheticCurrentAuthority(...$revoked);
$check($make($pdoA,$nonce,$hexPub)->read(
 $otherSigned,$revokedAuthority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'original F29 recovery epoch mismatch is not bypassed');
$check($make($pdoA,$nonce,$hexPub)->read(
 $otherSigned,$authority,$time+30
)['status']==='original_portal_signature_or_authority_denied',
 'expired F29 envelope cannot reach SQL even with once-valid fixture authority');

$statement->execute(['perf-demo-aurora',$additional['nonce']]);
$check((int)$statement->fetchColumn()===0,
 'all denied signatures, authorities, cross-tenant reads and expiry consume no nonce');
$check($admin->query(
 "SELECT to_regclass('public.performance_signed_portal_demo_receipts')"
)->fetchColumn()===null,
 'F124 older receipt table is NOT shared with F170; migration to one ledger still required');

$invalidNew=json_encode([
 'claims'=>['tenant'=>'perf-demo-aurora'],
 'signature'=>base64_encode(random_bytes(64)),
],JSON_THROW_ON_ERROR);
$check($make($pdoA,$nonce,$hexPub)->read(
 $invalidNew,$authority,$time
)['status']==='original_portal_signature_or_authority_denied',
 'F172 refuses the newer F169 wire format rather than downgrading F29 security');

echo "F172 canonical Portal F29 to PG16 signed read: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
