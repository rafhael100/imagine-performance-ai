<?php
declare(strict_types=1);

/**
 * F181: fresh original pinned Portal Ed25519 signature -> F29/F123/F124
 * -> F180 after-receipt F100 JSON -> pinned REAL Portal F63 HTML renderer.
 *
 * Proof checksum stays in PHP memory (not a separately editable JSON
 * attestation file). Synthetic-only, no session/IAM production trust.
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
] as $source)require_once $root.'/'.$source;

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\Security\SyntheticPostReceiptF100Delivery;
use App\Performance\Security\SyntheticF181BoundPortalHtml;
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F29_SIGNED_PORTAL_LAB','PERF_F123_SIGNED_SQL_WORKBENCH_LAB',
 'PERF_F124_SIGNED_NONCE_LAB','PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB','PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB','PERF_F180_POST_RECEIPT_LAB',
 'PERF_F181_BOUND_HTML_LAB','PERF_F181_RACE_INJECTION_LAB',
 'PORTAL_F62_CREDENTIAL_WORKBENCH_LAB','PORTAL_F63_STATIC_HTML_LAB'
] as $key){
 if(getenv($key)!=='1')throw new RuntimeException('F181 missing lab gate '.$key);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F181 only supports synthetic PG16 CI');
}
$rootTemp=realpath((string)getenv('RUNNER_TEMP'));
$pub=realpath((string)getenv('PERF_F181_SIGNER_PUBLIC_FILE'));
$packet=realpath((string)getenv('PERF_F181_SIGNER_PACKET_FILE'));
if($rootTemp===false||$pub===false||$packet===false
 ||!str_starts_with($pub,sys_get_temp_dir().'/')
 ||!str_starts_with($packet,sys_get_temp_dir().'/')
 ||is_link($pub)||is_link($packet)
 ||filesize($pub)!==64||filesize($packet)>8192){
 throw new RuntimeException('F181 original Portal signer files missing or unsafe');
}
$pubHex=trim((string)file_get_contents($pub));
$rawPacket=trim((string)file_get_contents($packet));
if(!preg_match('/^[a-f0-9]{64}$/D',$pubHex))throw new RuntimeException('F181 bad test public key');
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $u,string $pwd):PDO=>new PDO($dsn,$u,$pwd,$opt);
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$writer=$open('perf_f124_receipt_writer','ci-f124-receipt-write-only');
$admin=$open('postgres','ci-f22-db-only');
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F181 FAILED: '.$msg);
};
$receiptCount=static fn():int=>(int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$prior=$receiptCount();
$check($prior===5,
 'previous F179/F180 integrated steps consumed exactly 5 Portal lab nonces');
$source=$rootTemp.'/f180-f181.json';
$makeDelivery=static fn():SyntheticPostReceiptF100Delivery=>
 new SyntheticPostReceiptF100Delivery(
    new SyntheticDurableSignedSqlPreviewBridge(
        new SyntheticPortalSqlWorkbenchBridge(
            new SignedPortalCapabilityGate($pubHex)
        ),
        new SyntheticSignedPortalNonceFence($writer)
    ),
    new CredentialBoundDemoWorkbenchReader()
 );

$delivery=$makeDelivery()->deliver(
 $a,$rawPacket,SyntheticCurrentAuthority::forSyntheticLab(time()),
 time(),$source
);
$check($delivery['status']==='post_receipt_readonly_snapshot_only'
 &&$delivery['receipt_consumed_demo']===true
 &&$delivery['artifact_path']===$source
 &&is_file($source),
 'original Portal F29 nonce consumed and F180 produces only fresh F100 SQL bytes');
$check($receiptCount()===$prior+1,
 'full chain has added exactly one PostgreSQL F124 receipt');
$check(hash_file('sha256',$source)===$delivery['sha256'],
 'F180 supplied trustworthy in-process checksum for subsequent F63 rendering');

$target=$rootTemp.'/f181-bound-preview.html';
$render=new SyntheticF181BoundPortalHtml();
$result=$render->render($delivery,$source,$target);
$check($result['status']==='offline_html_bound_to_f180_ci_only'
 &&$result['html_path']===$target &&is_file($target),
 'original Portal F63 renderer consumes a staged checksum-bound F180 report');
$check($result['html_sha256']===hash_file('sha256',$target),
 'returned HTML digest exactly matches actual published preview');
$check(file_get_contents($target)===
 file_get_contents($rootTemp.'/f180-portal-f63.html'),
 'F181 bound preview is byte-identical to earlier approved Portal F63 output');
$check($result['read_only']===true
 &&$result['synthetic_only']===true
 &&$result['portal_identity_authenticated']===false
 &&$result['can_publish_ads']===false
 &&$result['can_bill']===false
 &&$result['ready_for_production']===false,
 'integrity handoff does not assert authenticated Portal sessions or live Ads');

$original=(string)file_get_contents($source);
$dto=json_decode($original,true,24,JSON_THROW_ON_ERROR);
$check($dto['schema_version']==='perf.portal.credential-bound-workbench.synthetic.v1'
 &&$dto['counts']['linked_campaigns']===1,
 'input is real SQL F100 read model, not manually constructed HTML fixture');
$dto['campaigns'][0]['campaign_title']='F89 modified campaign A';
$modified=json_encode($dto,JSON_THROW_ON_ERROR)."\n";
$check($modified!==$original,
 'mutated source is a valid-looking alternate DEMO campaign title');
$changedFile=$rootTemp.'/f181-tampered.html';
$hook=static function()use($source,$modified):void{
 file_put_contents($source,$modified,LOCK_EX);
};
$denied=(new SyntheticF181BoundPortalHtml($hook))
 ->render($delivery,$source,$changedFile);
$check($denied['status']==='json_changed_before_render'
 &&$denied['html_path']===null
 &&!file_exists($changedFile),
 'valid-looking SQL DTO edit after first hash cannot be rendered');
$check(file_get_contents($source)===$modified,
 'racing edit actually occurred rather than an inert negative fixture');

$alreadyAltered=(new SyntheticF181BoundPortalHtml())
 ->render($delivery,$source,$changedFile);
$check($alreadyAltered['status']==='json_digest_mismatch'
 &&!file_exists($changedFile),
 'pre-existing altered JSON fails before invoking original Portal F63');
file_put_contents($source,$original,LOCK_EX);
$check(hash_file('sha256',$source)===$delivery['sha256'],
 'restored lab fixture returns to original F180 proof checksum');

$forged=$delivery;
$forged['receipt_consumed_demo']=false;
$denyForged=(new SyntheticF181BoundPortalHtml())
 ->render($forged,$source,$rootTemp.'/f181-forged.html');
$check($denyForged['status']==='untrusted_f180_delivery_proof'
 &&!file_exists($rootTemp.'/f181-forged.html'),
 'caller cannot mark F180 nonce unconsumed and still render');
$forged=$delivery;
$forged['can_publish_ads']=true;
$denyAds=(new SyntheticF181BoundPortalHtml())
 ->render($forged,$source,$rootTemp.'/f181-ads.html');
$check($denyAds['status']==='untrusted_f180_delivery_proof',
 'a forged F180 advertising entitlement is refused');
$forged=$delivery;
$forged['sha256']=str_repeat('0',64);
$denyHash=(new SyntheticF181BoundPortalHtml())
 ->render($forged,$source,$rootTemp.'/f181-hash.html');
$check($denyHash['status']==='json_digest_mismatch'
 &&!file_exists($rootTemp.'/f181-hash.html'),
 'wrong expected signed-handoff checksum cannot render a correct-looking file');

$occupied=$rootTemp.'/f181-occupied.html';
file_put_contents($occupied,'SENTINEL'."\n",LOCK_EX);
$collision=(new SyntheticF181BoundPortalHtml())
 ->render($delivery,$source,$occupied);
$check($collision['status']==='unsafe_input_or_output_path'
 &&file_get_contents($occupied)==="SENTINEL\n",
 'existing HTML preview cannot be overwritten');

putenv('APP_ENV=production');
$prod=(new SyntheticF181BoundPortalHtml())
 ->render($delivery,$source,$rootTemp.'/f181-production.html');
$check($prod['status']==='lab_disabled'
 &&!file_exists($rootTemp.'/f181-production.html'),
 'production environment cannot render laboratory Portal HTML');
putenv('APP_ENV=testing');
putenv('PORTAL_F63_STATIC_HTML_LAB=0');
$disabled=(new SyntheticF181BoundPortalHtml())
 ->render($delivery,$source,$rootTemp.'/f181-disabled.html');
$check($disabled['status']==='lab_disabled'
 &&!file_exists($rootTemp.'/f181-disabled.html'),
 'disabling original Portal F63 gate prevents output');
putenv('PORTAL_F63_STATIC_HTML_LAB=1');

$again=(new SyntheticF181BoundPortalHtml())
 ->render($delivery,$source,$rootTemp.'/f181-second.html');
$check($again['status']==='offline_html_bound_to_f180_ci_only'
 &&hash_file('sha256',$target)===hash_file('sha256',$rootTemp.'/f181-second.html'),
 'two same-process offline copies can render but remain byte-identical and read-only');
$check($receiptCount()===$prior+1,
 'F181 read-only HTML renders never consume additional F124 nonces');
$check(!$a->inTransaction(),
 'no SQL connection remains in an externally managed transaction');

echo "F181 F180-bound current Portal F63 HTML integrity: {$n} PASS / 0 FAIL — NO-GO\n";
