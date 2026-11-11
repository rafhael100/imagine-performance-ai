<?php
declare(strict_types=1);

/** F192 signed Portal F29/F124 -> local F189/F191 service fence -> F100. */
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
use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;

foreach([
 'PERF_F192_SIGNED_SERVICE_LAB','PERF_F191_SERVICE_EPOCH_LAB',
 'PERF_F189_SERVICE_STATUS_LAB','PERF_F180_POST_RECEIPT_LAB',
 'PERF_F180_RACE_INJECTION_LAB','PERF_F124_SIGNED_NONCE_LAB',
 'PERF_F123_SIGNED_SQL_WORKBENCH_LAB','PERF_F29_SIGNED_PORTAL_LAB',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB','PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB','PERF_F98_CREDENTIAL_REVIEW_LAB',
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F192 missing lab flag '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('sodium')||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F192 requires disposable PostgreSQL16');
}
$tmp=realpath((string)getenv('RUNNER_TEMP'));
if($tmp===false)throw new RuntimeException('F192 missing runner temp');
$packets=[];
for($i=1;$i<=5;$i++){
 $pubFile=realpath((string)getenv('PERF_F192_PUBLIC_'.$i));
 $packetFile=realpath((string)getenv('PERF_F192_PACKET_'.$i));
 if($pubFile===false||$packetFile===false
  ||!str_starts_with($pubFile,sys_get_temp_dir().'/')
  ||!str_starts_with($packetFile,sys_get_temp_dir().'/')
  ||is_link($pubFile)||is_link($packetFile)
  ||filesize($pubFile)!==64||filesize($packetFile)>8192){
  throw new RuntimeException('F192 needs bounded original Portal signer proof');
 }
 $pub=trim((string)file_get_contents($pubFile));
 if(!preg_match('/^[a-f0-9]{64}$/D',$pub))throw new RuntimeException('F192 bad public key');
 $packets[$i]=[$pub,trim((string)file_get_contents($packetFile))];
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
$tenant='88888888-8888-4888-8888-888888888888';
$nonceCount=static fn():int=>(int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$initial=$nonceCount();
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F192 FAILED: '.$why);
};
$make=static function(int $index,?Closure $hook=null)use($packets,$writer):SyntheticPostReceiptF100Delivery{
 return new SyntheticPostReceiptF100Delivery(
  new SyntheticDurableSignedSqlPreviewBridge(
   new SyntheticPortalSqlWorkbenchBridge(
    new SignedPortalCapabilityGate($packets[$index][0])
   ),
   new SyntheticSignedPortalNonceFence($writer)
  ),
  new CredentialBoundDemoWorkbenchReader(),
  $hook
 );
};
$authority=static fn():SyntheticCurrentAuthority=>SyntheticCurrentAuthority::forSyntheticLab(time());
$target=static fn(string $name):string=>$tmp.'/f180-f192-'.$name.'.json';

$check((new CredentialBoundDemoServiceStatusFence())->read($a)['status']
 !=='credential_scoped_demo_workbench_only',
 'original A tenant is inactive/pending despite valid original Portal fixture');
$blocked=$make(1)->deliver($a,$packets[1][1],$authority(),time(),$target('blocked'));
$check($blocked['status']==='service_ineligible_before_nonce'
 &&!file_exists($target('blocked')),
 'signed delivery refuses inactive A service before writing any F100 file');
$check($nonceCount()===$initial,
 'service preflight denial cannot consume valid one-use original Portal nonce');

$admin->exec("UPDATE public.performance_tenants
 SET status='active' WHERE id='{$tenant}'::uuid");
$admin->exec("UPDATE public.portal_service_links
 SET link_status='active' WHERE tenant_id='{$tenant}'::uuid
 AND portal_service_ref='svc-demo-f88'");
$admin->exec("INSERT INTO public.service_eligibility_snapshots (
 id,tenant_id,portal_service_ref,state,source,source_revision,
 observed_at,created_at,updated_at
) VALUES (
 'ac920000-0000-4000-8000-000000000001'::uuid,'{$tenant}'::uuid,
 'svc-demo-f88','eligible','synthetic_ci_only','demo-revision-f88',
 clock_timestamp(),clock_timestamp(),clock_timestamp()
)");
$preview=(new CredentialBoundDemoServiceStatusFence())->read($a);
$check($preview['status']==='credential_scoped_demo_workbench_only'
 &&$preview['counts']['account_candidates']===2
 &&$preview['counts']['linked_campaigns']===0
 &&$preview['counts']['visible_reviews']===0,
 'synthetically active A has only two demo accounts, with old approvals tombstoned');

$success=$make(1)->deliver($a,$packets[1][1],$authority(),time(),$target('valid'));
$check($success['status']==='post_receipt_readonly_snapshot_only'
 &&$success['receipt_consumed_demo']===true
 &&$success['artifact_path']===$target('valid')
 &&is_file($target('valid')),
 'reused original Portal signature succeeds ONCE after full synthetic service evidence');
$check($nonceCount()===$initial+1,'exactly one F124 SQL nonce consumed by successful F192 delivery');
$delivered=json_decode((string)file_get_contents($target('valid')),true,24,JSON_THROW_ON_ERROR);
$check($delivered===$preview
 &&hash_file('sha256',$target('valid'))===$success['sha256'],
 'signed and service-scoped JSON is actual F100 SQL DTO, hash-bound to F180');
$check($delivered['portal_identity_authenticated']===false
 &&$delivered['authorization_eligible']===false
 &&$delivered['dispatch_enabled']===false
 &&$delivered['billing_enabled']===false
 &&$delivered['ready_for_production']===false,
 'synthetic service success cannot imply customer IAM, Ads or billing entitlement');
$replay=$make(1)->deliver($a,$packets[1][1],$authority(),time(),$target('replay'));
$check($replay['status']==='signed_or_nonce_gate_denied'
 &&!file_exists($target('replay'))
 &&$nonceCount()===$initial+1,
 'original Portal envelope remains strictly one-use after F124');

$revoke=static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
};
$revoked=$make(2,$revoke)->deliver(
 $a,$packets[2][1],$authority(),time(),$target('revoked'));
$check($revoked['status']==='post_receipt_service_changed'
 &&!file_exists($target('revoked'))
 &&$nonceCount()===$initial+2,
 'cancel service AFTER F124 nonce but BEFORE delivery: no snapshot leaves process');
$admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$replay2=$make(2)->deliver(
 $a,$packets[2][1],$authority(),time(),$target('revoked-replay'));
$check($replay2['status']==='signed_or_nonce_gate_denied'
 &&!file_exists($target('revoked-replay')),
 'restoring local service status cannot reuse spent Portal nonce');

$aba=static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
 $admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
};
$priorEpoch=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$trick=$make(3,$aba)->deliver(
 $a,$packets[3][1],$authority(),time(),$target('aba'));
$afterEpoch=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$check((int)$afterEpoch['service_epoch']>=(int)$priorEpoch['service_epoch']+2
 &&(new CredentialBoundDemoServiceStatusFence())->read($a)===$preview,
 'ABA left same demo eligibility fields but changed monotonic service epoch');
$check($trick['status']==='post_receipt_service_changed'
 &&!file_exists($target('aba'))
 &&$nonceCount()===$initial+3,
 'service pending->active AFTER F124 cannot bypass signed delivery epoch fence');

$tenantAba=static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.performance_tenants SET status='inactive'
 WHERE id='{$tenant}'::uuid");
 $admin->exec("UPDATE public.performance_tenants SET status='active'
 WHERE id='{$tenant}'::uuid");
};
$beforeTenant=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$trickTenant=$make(4,$tenantAba)->deliver(
 $a,$packets[4][1],$authority(),time(),$target('tenant-aba'));
$afterTenant=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$check((int)$afterTenant['tenant_epoch']>=(int)$beforeTenant['tenant_epoch']+2
 &&$trickTenant['status']==='post_receipt_service_changed'
 &&!file_exists($target('tenant-aba'))
 &&$nonceCount()===$initial+4,
 'tenant inactive->active AFTER receipt is detected despite restoring original status');

$admin->exec("UPDATE public.portal_service_links
 SET source_revision='demo-revision-f192-unmatched'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$before=$nonceCount();
$unmatched=$make(5)->deliver(
 $a,$packets[5][1],$authority(),time(),$target('unmatched'));
$check($unmatched['status']==='service_ineligible_before_nonce'
 &&!file_exists($target('unmatched'))
 &&$nonceCount()===$before,
 'mismatched source revision fails BEFORE consuming an original Portal signature');
$admin->exec("UPDATE public.portal_service_links
 SET source_revision='demo-revision-f88'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$last=$make(5)->deliver(
 $a,$packets[5][1],$authority(),time(),$target('last'));
$check($last['status']==='post_receipt_readonly_snapshot_only'
 &&$nonceCount()===$initial+5
 &&is_file($target('last')),
 'same unspent original Portal signature can be used once AFTER synthetic revision restored');
$check($b->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_b'
 &&(new CredentialBoundDemoServiceStatusFence())->read($b)['status']
 ==='credential_scoped_demo_workbench_only',
 'B remains separately authenticated in SQL with only demo access');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'F189/F180/F191 leave no open SQL session transactions');
echo "F192 original Portal signed+nonce delivery service/epoch fence: {$n} PASS / 0 FAIL — SYNTHETIC NO-GO\n";
