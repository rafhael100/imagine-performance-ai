<?php
declare(strict_types=1);

/**
 * F180: original Portal signer / F124 nonce -> independent F100 recheck
 * -> collision-safe JSON delivery for the already compatible F62/F63.
 * All four signatures come from the actual pinned Portal PHP issuer.
 */
$root=dirname(__DIR__,2);
require_once $root.'/vendor/autoload.php';
foreach([
 'app/Performance/Security/SyntheticCurrentAuthority.php',
 'app/Performance/Security/SignedPortalCapabilityGate.php',
 'contracts/lab/php/SyntheticPortalSqlWorkbenchBridge.php',
 'contracts/lab/php/SyntheticSignedPortalNonceFence.php',
 'contracts/lab/php/SyntheticDurableSignedSqlPreviewBridge.php',
 'contracts/lab/php/SyntheticPostReceiptF100Delivery.php'
] as $path)require_once $root.'/'.$path;

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\Security\SyntheticPostReceiptF100Delivery;
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F29_SIGNED_PORTAL_LAB',
 'PERF_F123_SIGNED_SQL_WORKBENCH_LAB',
 'PERF_F124_SIGNED_NONCE_LAB',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB',
 'PERF_F180_POST_RECEIPT_LAB',
 'PERF_F180_RACE_INJECTION_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F180 missing disposable lab gate '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('sodium')||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F180 must run under local PG16 testing');
}
$rootTemp=realpath((string)getenv('RUNNER_TEMP'));
$checkPaths=[];
for($i=1;$i<=4;$i++){
 $public=realpath((string)getenv('PERF_F180_PUBLIC_'.$i));
 $packet=realpath((string)getenv('PERF_F180_PACKET_'.$i));
 if($rootTemp===false ||$public===false ||$packet===false
    ||!str_starts_with($public,sys_get_temp_dir().'/')
    ||!str_starts_with($packet,sys_get_temp_dir().'/')
    ||is_link($public)||is_link($packet)
    ||filesize($public)!==64||filesize($packet)>8192){
     throw new RuntimeException('F180 original Portal test signer paths invalid');
 }
 $hex=trim((string)file_get_contents($public));
 if(!preg_match('/^[a-f0-9]{64}$/D',$hex))throw new RuntimeException('F180 bad test public key');
 $checkPaths[$i]=['public'=>$hex,'packet'=>trim((string)file_get_contents($packet))];
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $u,string $p):PDO=>new PDO($dsn,$u,$p,$opt);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$writer=$open('perf_f124_receipt_writer','ci-f124-receipt-write-only');
$n=0;
$check=static function(bool $condition,string $message)use(&$n):void{
 ++$n;
 if(!$condition)throw new RuntimeException('F180 FAILED: '.$message);
};
$make=static function(string $pub,?\Closure $testHook=null)use($writer):SyntheticPostReceiptF100Delivery{
 return new SyntheticPostReceiptF100Delivery(
    new SyntheticDurableSignedSqlPreviewBridge(
        new SyntheticPortalSqlWorkbenchBridge(new SignedPortalCapabilityGate($pub)),
        new SyntheticSignedPortalNonceFence($writer)
    ),
    new CredentialBoundDemoWorkbenchReader(),
    $testHook
 );
};
$authority=static fn():SyntheticCurrentAuthority=>
 SyntheticCurrentAuthority::forSyntheticLab(time());
$nonceCount=static fn():int=>(int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$baseCount=$nonceCount();
$check($baseCount===1,
 'F179 has already consumed one original issuer nonce in the same PG16 CI');

$target=$rootTemp.'/f180-valid.json';
$check(!file_exists($target),
 'new canonical signed delivery destination starts absent');
$time=time();
$valid=$make($checkPaths[1]['public'])->deliver(
 $a,$checkPaths[1]['packet'],$authority(),$time,$target);
$check($valid['status']==='post_receipt_readonly_snapshot_only'
 &&$valid['receipt_consumed_demo']===true
 &&$valid['artifact_path']===$target
 &&is_string($valid['sha256']),
 'valid F29/F124 signed Portal envelope writes a new file only after post-receipt F100 recheck');
$check($nonceCount()===$baseCount+1,
 'one new original-Portal nonce SQL receipt is recorded before delivery');
$check(hash_file('sha256',$target)===$valid['sha256'],
 'delivered F100 JSON exact bytes match post-receipt SHA256');
$actual=json_decode((string)file_get_contents($target),true,24,JSON_THROW_ON_ERROR);
$earlier=json_decode((string)file_get_contents('/tmp/perf-f100-unified.json'),true,24,JSON_THROW_ON_ERROR);
$check($actual===$earlier,
 'newly re-read SQL-backed payload equals F100 canonical demo fixture');
$check($actual['portal_identity_authenticated']===false
 &&$actual['real_human_approval_verified']===false
 &&$actual['authorization_eligible']===false
 &&$actual['dispatch_enabled']===false
 &&$actual['billing_enabled']===false
 &&$actual['ready_for_production']===false,
 'signed delivery remains strictly unprivileged even when successfully exported');

$retryTarget=$rootTemp.'/f180-replay.json';
$again=$make($checkPaths[1]['public'])->deliver(
 $a,$checkPaths[1]['packet'],$authority(),time(),$retryTarget);
$check($again['status']==='signed_or_nonce_gate_denied'
 &&$again['artifact_path']===null
 &&!file_exists($retryTarget),
 'fresh PHP verifier cannot publish again after F124 one-use receipt');
$check($nonceCount()===$baseCount+1,
 'replay attempt cannot duplicate the SQL nonce entry');

$binding='perf_f91_reader_a';
$revokedTarget=$rootTemp.'/f180-revoked.json';
$revokeHook=static function()use($admin,$binding):void{
 $stmt=$admin->prepare("UPDATE public.performance_db_principal_tenant_bindings
 SET revoked_at=clock_timestamp() WHERE db_login=?");
 $stmt->execute([$binding]);
 if($stmt->rowCount()!==1)throw new RuntimeException('F180 test principal revoke failed');
};
$revoked=$make($checkPaths[2]['public'],$revokeHook)->deliver(
 $a,$checkPaths[2]['packet'],$authority(),time(),$revokedTarget);
$check($revoked['status']==='post_receipt_sql_change_denied'
 &&$revoked['artifact_path']===null
 &&!file_exists($revokedTarget),
 'SQL login revocation AFTER signed receipt denies file publication');
$check($nonceCount()===$baseCount+2,
 'denied after-receipt delivery still irreversibly consumes its SQL nonce');
$admin->prepare("UPDATE public.performance_db_principal_tenant_bindings
 SET revoked_at=NULL WHERE db_login=?")->execute([$binding]);
$check((new CredentialBoundDemoWorkbenchReader())->read($a)['status']
 ==='credential_scoped_demo_workbench_only',
 'restored synthetic A login can resume new, unrelated legitimate reads');
$revokeReplay=$make($checkPaths[2]['public'])->deliver(
 $a,$checkPaths[2]['packet'],$authority(),time(),$revokedTarget);
$check($revokeReplay['status']==='signed_or_nonce_gate_denied'
 &&!file_exists($revokedTarget),
 'restoring SQL access does NOT permit replay of consumed original nonce');

$campaign='cam-demo-f89-a';
$query=$admin->prepare('SELECT lifecycle_state FROM performance_managed_campaigns WHERE campaign_ref=?');
$query->execute([$campaign]);
$originalState=$query->fetchColumn();
$check(in_array($originalState,['draft','paused','archived'],true),
 'existing real F100 fixture has a lifecycle state');
$changed=$originalState==='draft'?'paused':'draft';
$changeHook=static function()use($admin,$campaign,$changed):void{
 $stmt=$admin->prepare('UPDATE performance_managed_campaigns SET lifecycle_state=? WHERE campaign_ref=?');
 $stmt->execute([$changed,$campaign]);
 if($stmt->rowCount()!==1)throw new RuntimeException('F180 synthetic campaign update failed');
};
$changedTarget=$rootTemp.'/f180-changed.json';
$race=$make($checkPaths[3]['public'],$changeHook)->deliver(
 $a,$checkPaths[3]['packet'],$authority(),time(),$changedTarget);
$check($race['status']==='post_receipt_sql_change_denied'
 &&$race['artifact_path']===null&&!file_exists($changedTarget),
 'campaign lifecycle updated AFTER F124 receipt cannot publish stale pre-change F100');
$check($nonceCount()===$baseCount+3,
 'campaign change still consumes the one-use nonce before failing closed');
$admin->prepare('UPDATE performance_managed_campaigns SET lifecycle_state=? WHERE campaign_ref=?')
 ->execute([$originalState,$campaign]);
$check((new CredentialBoundDemoWorkbenchReader())->read($a)['campaigns']===$actual['campaigns'],
 'restoring synthetic campaign state restores exact A read model');
$changedReplay=$make($checkPaths[3]['public'])->deliver(
 $a,$checkPaths[3]['packet'],$authority(),time(),$changedTarget);
$check($changedReplay['status']==='signed_or_nonce_gate_denied'
 &&!file_exists($changedTarget),
 'stale snapshot remains undisclosed after campaign state is restored and replayed');

$occupied=$rootTemp.'/f180-occupied.json';
file_put_contents($occupied,'SENTINEL'."\n",LOCK_EX);
$blocked=$make($checkPaths[4]['public'])->deliver(
 $a,$checkPaths[4]['packet'],$authority(),time(),$occupied);
$check($blocked['status']==='unsafe_delivery_destination'
 &&file_get_contents($occupied)==="SENTINEL\n",
 'existing artifact path is never overwritten nor does it consume a nonce');
$check($nonceCount()===$baseCount+3,
 'unsafe destination is rejected before signature can consume receipt');
$wrongTenantTarget=$rootTemp.'/f180-other-tenant.json';
$wrongTenant=$make($checkPaths[4]['public'])->deliver(
 $b,$checkPaths[4]['packet'],$authority(),time(),$wrongTenantTarget);
$check($wrongTenant['status']==='signed_or_nonce_gate_denied'
 &&!file_exists($wrongTenantTarget),
 'Portal signed A cannot issue any file from SQL login B');
$check($nonceCount()===$baseCount+3,
 'cross-tenant signed denial never burns otherwise legitimate A nonce');
$packet=json_decode($checkPaths[4]['packet'],true,16,JSON_THROW_ON_ERROR);
$packet['payload']=rtrim(strtr(base64_encode('{"issuer":"attacker"}'),'+/','-_'),'=');
$altered=$make($checkPaths[4]['public'])->deliver(
 $a,json_encode($packet,JSON_THROW_ON_ERROR),$authority(),time(),$wrongTenantTarget);
$check($altered['status']==='signed_or_nonce_gate_denied'
 &&$nonceCount()===$baseCount+3,
 'tampered F29 signature denied before nonce commit or artifact delivery');

putenv('PERF_F180_POST_RECEIPT_LAB=0');
$noLab=$make($checkPaths[4]['public'])->deliver(
 $a,$checkPaths[4]['packet'],$authority(),time(),$wrongTenantTarget);
$check($noLab['status']==='lab_disabled_or_bad_clock'
 &&$nonceCount()===$baseCount+3,
 'disabling lab flag never publishes or burns nonce');
putenv('PERF_F180_POST_RECEIPT_LAB=1');

$last=$rootTemp.'/f180-final.json';
$final=$make($checkPaths[4]['public'])->deliver(
 $a,$checkPaths[4]['packet'],$authority(),time(),$last);
$check($final['status']==='post_receipt_readonly_snapshot_only'
 &&file_exists($last) && $final['receipt_consumed_demo']===true,
 'same packet 4 remains deliverable exactly once AFTER prior failed cross-tenant/tamper/path probes');
$check($nonceCount()===$baseCount+4,
 'all four original Portal signed packets consumed exactly once');
$check(json_decode((string)file_get_contents($last),true,24,JSON_THROW_ON_ERROR)===$actual,
 'final successful F180 output remains identical to canonical SQL F100 fixture');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'F180 and F100 leave no database read transactions open');
echo "F180 post-receipt F100 fresh-read and atomic delivery: {$n} PASS / 0 FAIL — NO-GO\n";
