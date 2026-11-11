<?php
declare(strict_types=1);

/**
 * F194 CI-only: require service and epoch on EVERY retrieval of a fully
 * rendered, fictional Portal F63 file. No HTTP or real Portal IAM.
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
 'contracts/lab/php/SyntheticServiceCheckedHtmlRetrieval.php',
 'contracts/lab/php/SyntheticPrivateHtmlResponse.php',
] as $path)require_once $root.'/'.$path;

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\Security\SyntheticPostReceiptF100Delivery;
use App\Performance\Security\SyntheticF181BoundPortalHtml;
use App\Performance\Security\SyntheticServiceCheckedHtmlRetrieval;
use App\Performance\Security\SyntheticPrivateHtmlResponse;
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;
use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;

foreach([
 'PERF_F194_OFFLINE_HTML_FETCH_LAB','PERF_F193_F63_SERVICE_LAB',
 'PERF_F192_SIGNED_SERVICE_LAB','PERF_F191_SERVICE_EPOCH_LAB',
 'PERF_F189_SERVICE_STATUS_LAB','PERF_F181_BOUND_HTML_LAB',
 'PERF_F180_POST_RECEIPT_LAB','PERF_F124_SIGNED_NONCE_LAB',
 'PERF_F123_SIGNED_SQL_WORKBENCH_LAB','PERF_F29_SIGNED_PORTAL_LAB',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB','PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB','PERF_F98_CREDENTIAL_REVIEW_LAB',
 'PORTAL_F62_CREDENTIAL_WORKBENCH_LAB','PORTAL_F63_STATIC_HTML_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F194 missing disposable CI flag '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F194 only on disposable PG16');
}
$tmp=realpath((string)getenv('RUNNER_TEMP'));
$pubFile=realpath((string)getenv('PERF_F194_SIGNER_PUBLIC_FILE'));
$envelopeFile=realpath((string)getenv('PERF_F194_SIGNER_PACKET_FILE'));
if($tmp===false||$pubFile===false||$envelopeFile===false
 ||!str_starts_with($pubFile,sys_get_temp_dir().'/')
 ||!str_starts_with($envelopeFile,sys_get_temp_dir().'/')
 ||is_link($pubFile)||is_link($envelopeFile)
 ||filesize($pubFile)!==64||filesize($envelopeFile)>8192){
 throw new RuntimeException('F194 requires bounded original Portal signer data');
}
$pub=trim((string)file_get_contents($pubFile));
$envelope=trim((string)file_get_contents($envelopeFile));
if(!preg_match('/^[a-f0-9]{64}$/D',$pub)){
 throw new RuntimeException('F194 invalid ephemeral Portal public key');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $u,string $p):PDO=>new PDO($dsn,$u,$p,$opts);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$writer=$open('perf_f124_receipt_writer','ci-f124-receipt-write-only');
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F194 FAILED: '.$why);
};
$count=static fn():int=>(int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$prior=$count();
$tenant='88888888-8888-4888-8888-888888888888';
$check($fetcher->fetchInline($a,$delivery,$inline)['html']===null,
 'F196 inline render remains invalid after service ABA reactivation');
$check((new CredentialBoundDemoServiceStatusFence())->read($a)['status']
 ==='credential_scoped_demo_workbench_only',
 'A remains synthetically eligible before signed preview retrieval');
$check((new CredentialBoundDemoServiceStatusFence())->read($b)['status']
 ==='credential_scoped_demo_workbench_only',
 'B independently exposes only own synthetic status');

$signer=new SyntheticPostReceiptF100Delivery(
 new SyntheticDurableSignedSqlPreviewBridge(
  new SyntheticPortalSqlWorkbenchBridge(new SignedPortalCapabilityGate($pub)),
  new SyntheticSignedPortalNonceFence($writer)
 ),
 new CredentialBoundDemoWorkbenchReader()
);
$json=$tmp.'/f180-f194-valid.json';
$html=$tmp.'/f181-f194-valid.html';
$delivery=$signer->deliver(
 $a,$envelope,SyntheticCurrentAuthority::forSyntheticLab(time()),time(),$json
);
$check($delivery['status']==='post_receipt_readonly_snapshot_only'
 &&$delivery['receipt_consumed_demo']===true&&is_file($json),
 'original Portal F29/F124 signed delivery created current SQL F100 payload');
$check($count()===$prior+1,
 'exactly one PostgreSQL F124 nonce is consumed');
$rendered=(new SyntheticF181BoundPortalHtml())->render(
 $delivery,$json,$html,$a
);
$check($rendered['status']==='offline_html_bound_to_f180_ci_only'
 &&is_file($html),
 'F193 service-bound original Portal F63 HTML is generated first');

$fetcher=new SyntheticServiceCheckedHtmlRetrieval();
$first=$fetcher->fetch($a,$delivery,$rendered);
$check($first['status']==='offline_html_readonly_preview_only'
 &&$first['content_type']==='text/html; charset=utf-8'
 &&$first['html']===file_get_contents($html),
 'a new service checked retrieval returns exactly Portal F63 HTML bytes');
$check($first['synthetic_only']===true
 &&$first['portal_identity_authenticated']===false
 &&$first['authorization_eligible']===false
 &&$first['can_publish_ads']===false
 &&$first['can_bill']===false
 &&$first['ready_for_production']===false,
 'positive synthetic fetch cannot grant any real Portal/Ads/billing authority');
$check($fetcher->fetch($a,$delivery,$rendered)===$first
 &&$count()===$prior+1,
 'repeat safe retrieval needs no replay of original one-use signed nonce');

// F196: render the original Portal F63 again without ever creating an HTML file.
putenv('PERF_F196_INLINE_HTML_LAB=1');
$beforeHtmlFiles=glob($tmp.'/*.html');
$inline=(new SyntheticF181BoundPortalHtml())->render(
 $delivery,$json,':memory:',$a,true
);
$check($inline['status']==='in_memory_f63_ci_only'
 &&($inline['html_path']??'missing')===null
 &&($inline['non_persistent']??false)===true
 &&is_string($inline['html_bytes']??null)
 &&hash('sha256',$inline['html_bytes'])===$inline['html_sha256'],
 'real Portal F63 returns verifiable in-memory HTML only');
$check(glob($tmp.'/*.html')===$beforeHtmlFiles,
 'no additional HTML file is persisted for inline preview');
$inMem=$fetcher->fetchInline($a,$delivery,$inline);
$check($inMem['status']==='offline_html_readonly_preview_only'
 &&$inMem['html']===$inline['html_bytes'],
 'read-time SQL epoch and F100 checks authorize only the current demo A');
$inMemOther=$fetcher->fetchInline($b,$delivery,$inline);
$check($inMemOther['html']===null
 &&$inMemOther['status']==='service_revoked_or_sql_changed',
 'tenant B cannot read tenant A ephemeral HTML');
$response=(new SyntheticPrivateHtmlResponse())->serveInline($a,$delivery,$json);
$check($response['status_code']===200
 &&$response['headers']['Content-Type']==='text/html; charset=utf-8'
 &&str_contains($response['headers']['Cache-Control'],'no-store')
 &&str_contains($response['body'],'DEMONSTRAÇÃO NÃO HOMOLOGADA'),
 'no-disk F63 response returns non-cacheable private HTML only');
$check(glob($tmp.'/*.html')===$beforeHtmlFiles &&$count()===$prior+1,
 'inline requests neither create HTML artifacts nor consume extra nonces');
$mutatedInline=$inline;
$mutatedInline['html_bytes'].='<!-- malicious append -->';
$check($fetcher->fetchInline($a,$delivery,$mutatedInline)['html']===null,
 'inline HTML byte mutation is rejected despite original proof');

$other=$fetcher->fetch($b,$delivery,$rendered);
$check($other['status']==='service_revoked_or_sql_changed'
 &&$other['html']===null,
 'SQL tenant B cannot retrieve A service-bound F63 preview');
$badReceipt=$delivery;
$badReceipt['receipt_consumed_demo']=false;
$bad=$fetcher->fetch($a,$badReceipt,$rendered);
$check($bad['status']==='unsigned_or_untrusted_local_handoff'
 &&$bad['html']===null,
 'untrusted signed-receipt structure is denied before reading HTML');

$originalHtml=(string)file_get_contents($html);
$modified=str_replace('DEMONSTRAÇÃO NÃO HOMOLOGADA',
 'DEMONSTRAÇÃO LOCAL', $originalHtml);
$check($modified!==$originalHtml,
 'CI replaces a non-production warning with a benign alternate label');
file_put_contents($html,$modified,LOCK_EX);
$changed=$fetcher->fetch($a,$delivery,$rendered);
$check($changed['status']==='html_integrity_changed'
 &&$changed['html']===null,
 'altered offline HTML is refused under original F193 digest');
$modifiedProof=$rendered;
$modifiedProof['html_sha256']=hash('sha256',$modified);
$wrongWarning=$fetcher->fetch($a,$delivery,$modifiedProof);
$check($wrongWarning['status']==='unsafe_offline_html'
 &&$wrongWarning['html']===null,
 'new matching hash cannot bypass required demonstration warning');
file_put_contents($html,$originalHtml,LOCK_EX);
$check($fetcher->fetch($a,$delivery,$rendered)===$first,
 'restored original HTML remains viewable only while service is unchanged');

$admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$cancelled=$fetcher->fetch($a,$delivery,$rendered);
$check($cancelled['status']==='service_revoked_or_sql_changed'
 &&$cancelled['html']===null,
 'cancellation AFTER HTML generation refuses the next gated view');
$check(is_file($html)&&file_get_contents($html)===$originalHtml,
 'EXPECTED NEGATIVE: CI file remains directly readable outside per-fetch guard');
$check($count()===$prior+1,
 'denied retrieval does not mint or consume any new Portal nonce');
$check($fetcher->fetchInline($a,$delivery,$inline)['html']===null,
 'F196 old inline render is refused after cancellation');
$revokedResponse=(new SyntheticPrivateHtmlResponse())->serveInline($a,$delivery,$json);
$check($revokedResponse['status_code']===403
 &&$revokedResponse['body']==='Preview unavailable',
 'F196 no-disk response is denied after service cancellation');
$admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$reopened=$fetcher->fetch($a,$delivery,$rendered);
$check($reopened['status']==='service_revoked_or_sql_changed'
 &&$reopened['html']===null,
 're-enabled status cannot reuse prior F191 epoch for an old HTML artifact');
$check((new CredentialBoundDemoServiceStatusFence())->read($a)['status']
 ==='credential_scoped_demo_workbench_only',
 'a new synthetic status read may succeed, but the OLD signed artifact stays revoked');
putenv('PERF_F194_OFFLINE_HTML_FETCH_LAB=0');
$disabled=$fetcher->fetch($a,$delivery,$rendered);
$check($disabled['status']==='lab_disabled'&&$disabled['html']===null,
 'CI gate disabled never returns file bytes');
putenv('PERF_F194_OFFLINE_HTML_FETCH_LAB=1');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'every SQL service/workbench check leaves the connection clean');
echo "F194 service-checked offline HTML retrieval: {$n} PASS / 0 FAIL — STATIC-FILE GAP / NO-GO\n";
