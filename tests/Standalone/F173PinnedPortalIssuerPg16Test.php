<?php
declare(strict_types=1);

/**
 * F173 consumes an envelope emitted by the ORIGINAL REAL Portal repo's
 * SyntheticCapabilityIssuer (pinned cross-repository checkout), not a
 * locally re-implemented test signer. No real user/session authentication.
 */
require dirname(__DIR__,2).'/src/Domain/Performance/F172LegacyPortalPgReadBridge.php';

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F172LegacyPortalPgReadBridge;

foreach([
 'PERF_F29_SIGNED_PORTAL_LAB','PERF_F162_PG_LAB',
 'PERF_F166_READ_DASHBOARD_LAB','PERF_F170_NONCE_LAB',
 'PERF_F172_LEGACY_BRIDGE_LAB','PERF_F173_REAL_PORTAL_ISSUER_LAB'
] as $flag) {
 if(getenv($flag)!=='1')throw new RuntimeException('F173 missing lab gate: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||!extension_loaded('sodium')||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F173 only runs against local CI PostgreSQL16');
}
$temp=realpath(sys_get_temp_dir());
$packetPath=realpath((string)getenv('PERF_F173_SIGNED_PACKET_FILE'));
$publicPath=realpath((string)getenv('PERF_F173_SIGNED_PUBLIC_FILE'));
if($temp===false||$packetPath===false||$publicPath===false
 ||!str_starts_with($packetPath,$temp.'/')
 ||!str_starts_with($publicPath,$temp.'/')
 ||!is_file($packetPath)||!is_file($publicPath)
 ||filesize($packetPath)>8192||filesize($publicPath)>128){
 throw new RuntimeException('F173 requires pinned Portal issuer files within runner temp');
}
$originalPacket=trim((string)file_get_contents($packetPath));
$publicKeyHex=trim((string)file_get_contents($publicPath));
if(!preg_match('/^[a-f0-9]{64}$/D',$publicKeyHex)){
 throw new RuntimeException('F173 invalid pinned Portal public key hex');
}
$wire=json_decode($originalPacket,true,16,JSON_THROW_ON_ERROR);
if(!is_array($wire)||array_keys($wire)!==[
 'version','key_id','payload','signature'
 ]||$wire['version']!=='portal-capability-envelope.v1'){
 throw new RuntimeException('F173 issuer must emit ORIGINAL Portal envelope format');
}
$claims=json_decode(base64_decode(strtr($wire['payload'],'-_','+/'),true),
 true,24,JSON_THROW_ON_ERROR);
if(!is_array($claims)
 ||($claims['issuer']??null)!=='imagine.portal'
 ||($claims['performance_tenant_ref']??null)!=='perf-demo-a'
 ||($claims['session_mfa_verified']??null)!==true
 ||($claims['permission']??null)!=='campaigns:read'
 ||!is_string($claims['nonce']??null)
 ||!preg_match('/^[a-f0-9]{32}$/D',$claims['nonce'])){
 throw new RuntimeException('F173 Portal fixture claims not expected canonical shape');
}
$now=time();
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $password):PDO=>
 new PDO($dsn,$role,$password,$options);
$admin=$open('postgres','postgres');
$readerA=new F166Pg16ReadOnlyDashboard(
 $open('perf_f162_a','ci-only-perf-f162-a'));
$readerB=new F166Pg16ReadOnlyDashboard(
 $open('perf_f162_b','ci-only-perf-f162-b'));
$nonceWriter=$open('perf_f170_gateway','ci-only-f170-gateway');
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F173 FAILED: '.$why);
};
$make=static fn(F166Pg16ReadOnlyDashboard $reader,PDO $nonce):F172LegacyPortalPgReadBridge=>
 new F172LegacyPortalPgReadBridge(
  new SignedPortalCapabilityGate($publicKeyHex),$reader,$nonce
 );
$check((new SignedPortalCapabilityGate($publicKeyHex))->check(
 $originalPacket,SyntheticCurrentAuthority::forSyntheticLab($now),$now
)==='verified_synthetic_only',
 'existing F29 verifier accepts signed bytes from actual pinned Portal PHP emitter');
$check((int)$claims['expires_at']>$now
 &&(int)$claims['expires_at']<=$now+30,
 'real Portal issuer emitted a currently valid short-lived signed envelope');
$before=$admin->prepare('SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE tenant=? AND request_id=?');
$before->execute(['perf-demo-aurora',$claims['nonce']]);
$check((int)$before->fetchColumn()===0,
 'real Portal nonce is fresh in F170 SQL-only gateway ledger');

$gatewayB=$make($readerB,$nonceWriter);
$wrongTenant=$gatewayB->read(
 $originalPacket,SyntheticCurrentAuthority::forSyntheticLab($now),$now
);
$check($wrongTenant['status']==='sql_tenant_or_report_denied'
 &&$wrongTenant['report']===null,
 'actual Portal-issued A capability cannot read SQL login B data');
$before->execute(['perf-demo-aurora',$claims['nonce']]);
$check((int)$before->fetchColumn()===0,
 'wrong-tenant signed request does not burn the valid A nonce');

$gatewayA=$make($readerA,$nonceWriter);
$accepted=$gatewayA->read(
 $originalPacket,SyntheticCurrentAuthority::forSyntheticLab($now),$now
);
$check($accepted['status']==='original_portal_f29_pg16_readonly_demo_only'
 &&$accepted['legacy_portal_signature_verified_in_lab']===true
 &&$accepted['nonce_consumed_demo']===true,
 'actual independently emitted Portal bytes drive PostgreSQL16 RLS read and nonce');
$check(is_array($accepted['report'])
 &&$accepted['report']['tenant_ref']==='perf-demo-aurora',
 'returned report matches fixed synthetic A mapping and SQL login');
$check($accepted['read_only']===true
 &&$accepted['portal_identity_authenticated']===false
 &&$accepted['dispatch_enabled']===false
 &&$accepted['billing_enabled']===false
 &&$accepted['ready_for_production']===false,
 'actual cross-repo signature still does not authenticate user or enable Ads');
$check($accepted['report']['iam_verified']===false
 &&$accepted['report']['s2s_verified']===false
 &&$accepted['report']['human_approval_verified']===false,
 'PG16 projection remains strictly mock-only even with Portal issuer code');

$replayBridge=$make($readerA,$open('perf_f170_gateway','ci-only-f170-gateway'));
$check($replayBridge->read(
 $originalPacket,SyntheticCurrentAuthority::forSyntheticLab($now),$now
)['status']==='replay_or_nonce_denied',
 'fresh verifier cannot replay actually Portal-issued signature after receipt');
$before->execute(['perf-demo-aurora',$claims['nonce']]);
$check((int)$before->fetchColumn()===1,
 'exactly one SQL receipt committed for actual Portal-issued nonce');

$malformed=json_decode($originalPacket,true,16,JSON_THROW_ON_ERROR);
$malformed['payload']=rtrim(strtr(base64_encode('{"version":1}'),'+/','-_'),'=');
$check($make($readerA,$nonceWriter)->read(
 json_encode($malformed,JSON_THROW_ON_ERROR),
 SyntheticCurrentAuthority::forSyntheticLab($now),$now
)['status']==='original_portal_signature_or_authority_denied',
 'altering actual Portal emitted payload without resigning cannot access PG');
$check($admin->query(
 "SELECT to_regclass('public.performance_signed_portal_demo_receipts')"
)->fetchColumn()===null,
 'this CI demonstration still does NOT unify older F124 nonce ledger with F170');
echo "F173 actual Portal issuer to Performance PG16: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
