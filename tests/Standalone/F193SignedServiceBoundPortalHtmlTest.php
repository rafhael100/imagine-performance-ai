<?php
declare(strict_types=1);

/**
 * F193: ORIGINAL Portal F29 -> F123 -> F124 -> F192 signed delivery
 * -> F181 ORIGINAL pinned Portal F63. Deny stale service, ABA and even
 * caller-rehashed but forged F100 JSON at the FINAL render boundary.
 */
$root=dirname(__DIR__,2);
require_once $root.'/vendor/autoload.php';
foreach([
 'app/Performance/Security/SyntheticCurrentAuthority.php',
 'app/Performance/Security/SignedPortalCapabilityGate.php',
 'contracts/lab/php/SyntheticPortalSqlWorkbenchBridge.php',
 'contracts/lab/php/SyntheticSignedPortalNonceFence.php',
 'contracts/lab/php/SyntheticDurableSignedSqlPreviewBridge.php',
 'contracts/lab/php/SyntheticPostReceiptF100Delivery.php',
 'contracts/lab/php/SyntheticF181BoundPortalHtml.php',
] as $file)require_once $root.'/'.$file;

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\Security\SyntheticPostReceiptF100Delivery;
use App\Performance\Security\SyntheticF181BoundPortalHtml;
use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;

foreach([
 'PERF_F193_F63_SERVICE_LAB','PERF_F192_SIGNED_SERVICE_LAB',
 'PERF_F191_SERVICE_EPOCH_LAB','PERF_F189_SERVICE_STATUS_LAB',
 'PERF_F181_BOUND_HTML_LAB','PERF_F181_RACE_INJECTION_LAB',
 'PERF_F180_POST_RECEIPT_LAB','PERF_F180_RACE_INJECTION_LAB',
 'PERF_F124_SIGNED_NONCE_LAB','PERF_F123_SIGNED_SQL_WORKBENCH_LAB',
 'PERF_F29_SIGNED_PORTAL_LAB','PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB','PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB',
 'PORTAL_F62_CREDENTIAL_WORKBENCH_LAB','PORTAL_F63_STATIC_HTML_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F193 missing disposable CI gate '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F193 requires isolated PostgreSQL16 PHP CLI');
}
$tmp=realpath((string)getenv('RUNNER_TEMP'));
if($tmp===false)throw new RuntimeException('F193 runner temp not found');
$packets=[];
for($i=1;$i<=4;$i++){
 $pubPath=realpath((string)getenv('PERF_F193_PUBLIC_'.$i));
 $packetPath=realpath((string)getenv('PERF_F193_PACKET_'.$i));
 if($pubPath===false||$packetPath===false
  ||!str_starts_with($pubPath,sys_get_temp_dir().'/')
  ||!str_starts_with($packetPath,sys_get_temp_dir().'/')
  ||is_link($pubPath)||is_link($packetPath)
  ||filesize($pubPath)!==64||filesize($packetPath)>8192){
  throw new RuntimeException('F193 signer files must come from original pinned Portal CI emitter');
 }
 $pub=trim((string)file_get_contents($pubPath));
 if(!preg_match('/^[a-f0-9]{64}$/D',$pub))throw new RuntimeException('F193 invalid pubkey');
 $packets[$i]=[$pub,trim((string)file_get_contents($packetPath))];
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
$nonceCount=static fn():int=>(int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$initial=$nonceCount();
$n=0;
$check=static function(bool $pass,string $msg)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F193 FAILED: '.$msg);
};
$make=static function(int $i)use($packets,$writer):SyntheticPostReceiptF100Delivery{
 return new SyntheticPostReceiptF100Delivery(
  new SyntheticDurableSignedSqlPreviewBridge(
   new SyntheticPortalSqlWorkbenchBridge(
    new SignedPortalCapabilityGate($packets[$i][0])
   ),
   new SyntheticSignedPortalNonceFence($writer)
  ),
  new \App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader()
 );
};
$authority=static fn():SyntheticCurrentAuthority=>SyntheticCurrentAuthority::forSyntheticLab(time());
$jsonPath=static fn(string $suffix):string=>$tmp.'/f180-f193-'.$suffix.'.json';
$htmlPath=static fn(string $suffix):string=>$tmp.'/f181-f193-'.$suffix.'.html';
$tenant='88888888-8888-4888-8888-888888888888';
$service=(new CredentialBoundDemoServiceStatusFence())->read($a);
$check($service['status']==='credential_scoped_demo_workbench_only'
 &&$service['counts']['account_candidates']===2
 &&$service['counts']['linked_campaigns']===0,
 'F192 synthetic A service remains eligible while stale F185 campaign is hidden');
$check((new CredentialBoundDemoServiceStatusFence())->read($b)['status']
 ==='credential_scoped_demo_workbench_only',
 'B remains independently scoped and eligible for synthetic workbench');

$deliver=static function(int $i,string $suffix)use($make,$a,$packets,$authority,$jsonPath):array{
 return $make($i)->deliver($a,$packets[$i][1],$authority(),time(),$jsonPath($suffix));
};
$proof1=$deliver(1,'valid');
$check($proof1['status']==='post_receipt_readonly_snapshot_only'
 &&$proof1['receipt_consumed_demo']===true
 &&$proof1['artifact_path']===$jsonPath('valid')
 &&is_file($jsonPath('valid'))
 &&array_keys($proof1['service_epoch_at_delivery'])===
   ['tenant_epoch','service_epoch'],
 'signed F192 delivery contains exactly an F100 payload and in-process F191 service epochs');

$render=new SyntheticF181BoundPortalHtml();
$positive=$render->render($proof1,$jsonPath('valid'),$htmlPath('valid'),$a);
$check($positive['status']==='offline_html_bound_to_f180_ci_only'
 &&$positive['html_path']===$htmlPath('valid')
 &&is_file($htmlPath('valid')),
 'original Portal F63 HTML created only after validated F192 SQL+epoch handoff');
$check($positive['html_sha256']===hash_file('sha256',$htmlPath('valid')),
 'final Portal HTML digest matches real content');
$expected=(string)file_get_contents($tmp.'/f192-f63-eligible-demo.html');
$check($expected!==''
 &&$expected===file_get_contents($htmlPath('valid')),
 'F193 original F63 HTML matches independently rendered F192 genuine SQL fixture byte-for-byte');
$check(!str_contains($expected,'cam-demo-f89-a')
 &&str_contains($expected,'DEMONSTRAÇÃO NÃO HOMOLOGADA')
 &&str_contains($expected,"script-src 'none'"),
 'real F63 HTML hides retired campaign, forbids scripts, visibly warns non-production');

$proof2=$deliver(2,'cancelled');
$check($proof2['status']==='post_receipt_readonly_snapshot_only',
 'separate signed envelope 2 already consumed its nonce before cancellation');
$admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$blocked=$render->render($proof2,$jsonPath('cancelled'),$htmlPath('cancelled'),$a);
$check($blocked['status']==='service_or_sql_changed_before_html'
 &&!file_exists($htmlPath('cancelled')),
 'service cancelled AFTER signed F192 but BEFORE F63 cannot create HTML');
$admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$blockedRestored=$render->render(
 $proof2,$jsonPath('cancelled'),$htmlPath('cancelled-again'),$a);
$check($blockedRestored['status']==='service_or_sql_changed_before_html'
 &&!file_exists($htmlPath('cancelled-again')),
 'reactivating the service does NOT reuse old F191 generation or HTML handoff');

$proof3=$deliver(3,'aba');
$check($proof3['status']==='post_receipt_readonly_snapshot_only'
 &&$proof3['receipt_consumed_demo']===true,
 'fresh original Portal envelope 3 creates a new F192 same-process signed proof');
$epochBefore=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$aba=static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
 $admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
};
$abaDenied=(new SyntheticF181BoundPortalHtml($aba))->render(
 $proof3,$jsonPath('aba'),$htmlPath('aba'),$a);
$epochAfter=$a->query(
 'SELECT * FROM public.perf_f191_service_epoch_for_login()'
)->fetch(PDO::FETCH_ASSOC);
$check((int)$epochAfter['service_epoch']>=(int)$epochBefore['service_epoch']+2
 &&(new CredentialBoundDemoServiceStatusFence())->read($a)===$service,
 'F193 injected ABA restored exactly the same F100 fields but incremented local service epoch');
$check($abaDenied['status']==='service_or_sql_changed_during_html'
 &&!file_exists($htmlPath('aba')),
 'ABA inside F63 integrity window prevents publishing any HTML');

$proof4=$deliver(4,'forgery');
$check($proof4['status']==='post_receipt_readonly_snapshot_only'
 &&is_file($jsonPath('forgery')),
 'fourth independent original Portal nonce is signed and delivered to F180');
$source=(string)file_get_contents($jsonPath('forgery'));
$dto=json_decode($source,true,24,JSON_THROW_ON_ERROR);
$check(count($dto['accounts'])===2,
 'forged fixture begins with real two F100 accounts A');
$dto['accounts'][0]['account_ref']='demo_f193_caller_forged';
$forgedJson=json_encode($dto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
 |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)."\n";
file_put_contents($jsonPath('forgery'),$forgedJson,LOCK_EX);
$forgedProof=$proof4;
$forgedProof['sha256']=hash('sha256',$forgedJson);
$rejectForged=$render->render(
 $forgedProof,$jsonPath('forgery'),$htmlPath('forgery'),$a);
$check($rejectForged['status']==='service_or_sql_changed_before_html'
 &&!file_exists($htmlPath('forgery')),
 'caller-rehashed but locally forged F100 JSON fails fresh PostgreSQL service+data comparison');
file_put_contents($jsonPath('forgery'),$source,LOCK_EX);
$check(hash_file('sha256',$jsonPath('forgery'))===$proof4['sha256'],
 'lab-only input restored to exact original signed-delivery checksum');
$deniedNoDb=$render->render(
 $proof4,$jsonPath('forgery'),$htmlPath('missing-db'));
$check($deniedNoDb['status']==='service_or_sql_changed_before_html'
 &&!file_exists($htmlPath('missing-db')),
 'F193 refuses F63 rendering without independent SQL connection');
$final=$render->render($proof4,$jsonPath('forgery'),$htmlPath('final'),$a);
$check($final['status']==='offline_html_bound_to_f180_ci_only'
 &&hash_file('sha256',$htmlPath('final'))===$positive['html_sha256'],
 'untampered freshly signed F100 renders byte-identical safe F63 HTML');
$check($nonceCount()===$initial+4,
 'F181 HTML render attempts consume ZERO additional original F124 nonces');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'no SQL F100 or service status read transaction remains open');
echo "F193 F192 service-bound final F63 output/revocation/ABA: {$n} PASS / 0 FAIL — NO-GO\n";
