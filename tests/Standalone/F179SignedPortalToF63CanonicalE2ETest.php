<?php
declare(strict_types=1);

/**
 * F179: pinned original Portal PHP issuer -> strict F29 verifier ->
 * F123 SQL-bound F100 projection -> F124 one-use receipt ->
 * current Portal F62/F63 compatible DTO. Only synthetic CI data.
 */
$root=dirname(__DIR__,2);
require_once $root.'/vendor/autoload.php';
require_once $root.'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once $root.'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once $root.'/contracts/lab/php/SyntheticPortalSqlWorkbenchBridge.php';
require_once $root.'/contracts/lab/php/SyntheticSignedPortalNonceFence.php';
require_once $root.'/contracts/lab/php/SyntheticDurableSignedSqlPreviewBridge.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F29_SIGNED_PORTAL_LAB','PERF_F123_SIGNED_SQL_WORKBENCH_LAB',
 'PERF_F124_SIGNED_NONCE_LAB','PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB','PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB','PERF_F179_CANONICAL_PORTAL_CHAIN_LAB',
 'PORTAL_F62_CREDENTIAL_WORKBENCH_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F179 needs gated CI: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F179 requires disposable, PHP CLI-only PostgreSQL16');
}
$runTemp=realpath((string)getenv('RUNNER_TEMP'));
$pub=realpath((string)getenv('PERF_F179_SIGNED_PUBLIC_FILE'));
$packet=realpath((string)getenv('PERF_F179_SIGNED_PACKET_FILE'));
$out=(string)getenv('PERF_F179_AUTHORIZED_DTO_FILE');
$f100=(string)getenv('PERF_F100_JSON_OUTPUT_FILE');
if($runTemp===false||$pub===false||$packet===false
 ||!str_starts_with($out,$runTemp.'/')
 ||!str_starts_with($pub,sys_get_temp_dir().'/')
 ||!str_starts_with($packet,sys_get_temp_dir().'/')
 ||is_link($pub)||is_link($packet)||is_link($out)
 ||file_exists($out)||!is_file($f100)
 ||filesize($pub)!==64||filesize($packet)>8192){
 throw new RuntimeException('F179 requires bounded pinned Portal signer files and a fresh output');
}
$public=trim((string)file_get_contents($pub));
$envelope=trim((string)file_get_contents($packet));
if(!preg_match('/^[a-f0-9]{64}$/D',$public))throw new RuntimeException('F179 invalid pubkey');
$now=time();
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $password):PDO=>new PDO($dsn,$role,$password,$options);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$writer=$open('perf_f124_receipt_writer','ci-f124-receipt-write-only');

$n=0;
$check=static function(bool $ok,string $reason)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F179 FAILED: '.$reason);
};
$make=static fn():SyntheticDurableSignedSqlPreviewBridge=>
 new SyntheticDurableSignedSqlPreviewBridge(
   new SyntheticPortalSqlWorkbenchBridge(
     new SignedPortalCapabilityGate($public)
   ),
   new SyntheticSignedPortalNonceFence($writer)
 );

$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn()===0,
 'local F124 nonce ledger is empty before first actual Portal envelope');
$canonicalSql=(new CredentialBoundDemoWorkbenchReader())->read($a);
$check($canonicalSql['status']==='credential_scoped_demo_workbench_only'
 &&$canonicalSql['credential_binding_confirmed']===true,
 'fresh F100 query still has the real credential-bound SQL identity');
$fixture=json_decode((string)file_get_contents($f100),true,24,JSON_THROW_ON_ERROR);
$check($fixture===$canonicalSql,
 'F100 JSON generated earlier in CI equals a new SQL-bound F100 read');
$check($canonicalSql['portal_identity_authenticated']===false
 &&$canonicalSql['dispatch_enabled']===false
 &&$canonicalSql['billing_enabled']===false,
 'independent F100 read is never a production entitlement');

$rawPacket=json_decode($envelope,true,16,JSON_THROW_ON_ERROR);
$check(array_keys($rawPacket)===['version','key_id','payload','signature']
 &&$rawPacket['version']==='portal-capability-envelope.v1',
 'the actual Portal emitter generated its original F29 wire envelope');
$rawClaim=base64_decode(strtr($rawPacket['payload'],'-_','+/'),true);
if(!is_string($rawClaim))throw new RuntimeException('F179 original Portal payload not base64url');
$claim=json_decode($rawClaim,true,24,JSON_THROW_ON_ERROR);
$check(($claim['issuer']??null)==='imagine.portal'
 &&($claim['performance_tenant_ref']??null)==='perf-demo-a'
 &&($claim['session_mfa_verified']??null)===true
 &&($claim['permission']??null)==='campaigns:read',
 'real Portal original claim still contains MFA/service-scoped read-only demo');
$check(is_int($claim['issued_at'])&&is_int($claim['expires_at'])
 &&$claim['issued_at']<=$now&&$claim['expires_at']>$now
 &&$claim['expires_at']-$claim['issued_at']<=30,
 'real Portal signature is currently valid and short-lived');
$check(is_string($claim['nonce'])&&preg_match('/^[a-f0-9]{32}$/D',$claim['nonce'])===1,
 'real signer issued its own new cryptographic nonce');

$authority=SyntheticCurrentAuthority::forSyntheticLab($now);
$failedB=$make()->preview($b,$envelope,$authority,$now);
$check($failedB['status']!=='signed_sql_demo_with_local_durable_nonce_only'
 &&$failedB['accounts']===[]&&$failedB['reviews']===[],
 'signed Portal fixture A cannot read unrelated PostgreSQL tenant B');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn()===0,
 'cross-tenant denied request does not consume a valid A nonce');

$valid=$make()->preview($a,$envelope,$authority,$now);
$check($valid['status']==='signed_sql_demo_with_local_durable_nonce_only'
 &&$valid['cryptographic_lab_proof_verified']===true
 &&$valid['replay_receipt_recorded']===true
 &&$valid['credential_binding_confirmed']===true,
 'real Portal issuer -> original F29 -> F123 SQL -> F124 receipt succeeded');
$check($valid['accounts']===$canonicalSql['accounts']
 &&$valid['campaigns']===$canonicalSql['campaigns']
 &&$valid['reviews']===$canonicalSql['reviews']
 &&$valid['counts']===$canonicalSql['counts'],
 'signed, nonce-bound preview data exactly matches F100 SQL canonical DTO');
$check($valid['portal_identity_authenticated']===false
 &&$valid['advertiser_approval_verified']===false
 &&$valid['provider_connected']===false
 &&$valid['authorization_eligible']===false
 &&$valid['dispatch_enabled']===false
 &&$valid['billing_enabled']===false
 &&$valid['ready_for_production']===false,
 'cryptographic lab signature cannot imply real Portal IAM, Ads or billing');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn()===1,
 'only one F124 SQL receipt was persisted for actual Portal signer nonce');

$replay=$make()->preview($a,$envelope,$authority,$now);
$check($replay['status']==='nonce_already_consumed_or_storage_unavailable'
 &&$replay['accounts']===[]&&$replay['reviews']===[]
 &&$replay['replay_receipt_recorded']===false,
 'fresh F29 verifier cannot reuse a once-consumed Portal original envelope');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn()===1,
 'replayed original envelope cannot create another F124 receipt');

$tampered=$rawPacket;
$tampered['payload']=rtrim(strtr(
 base64_encode('{"version":1,"issuer":"fake"}'),'+/','-_'),'=');
$denyTamper=$make()->preview(
 $a,json_encode($tampered,JSON_THROW_ON_ERROR),$authority,$now
);
$check($denyTamper['status']!=='signed_sql_demo_with_local_durable_nonce_only'
 &&$denyTamper['accounts']===[],
 'modified Portal packet without a matching signature is refused');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn()===1,
 'tampered packet does not burn or fabricate additional receipt');

$portal=dirname(__DIR__,2).'/portal-f176-candidate/apps/portal';
foreach([
 'app/Domain/Catalog/PerformanceChannel.php',
 'app/Domain/PerformanceDashboard/PerformanceCampaignObservationState.php',
 'app/Domain/PerformanceDashboard/PerformanceReviewObservationState.php',
 'app/Domain/PerformanceDashboard/CredentialBoundPerformanceWorkbenchPreview.php',
] as $path)require_once $portal.'/'.$path;
$preview=\App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview
 ::fromDto($canonicalSql);
$check($preview!==null
 &&$preview->hasPortalIdentity()===false
 &&$preview->canPublishAds()===false
 &&$preview->canBill()===false,
 'CURRENT pinned Portal F62 accepts F100 payload only after verified signed lab path');
$check($preview->counts===$valid['counts']
 &&$preview->accounts===$valid['accounts']
 &&$preview->campaigns===$valid['campaigns']
 &&$preview->reviews===$valid['reviews'],
 'actual F62 reader output is identical to F124 signed SQL materialization');

$canonicalJson=json_encode($canonicalSql,
 JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$check(file_put_contents($out,$canonicalJson."\n",LOCK_EX)!==false,
 'safe report JSON is exported only after first signed+nonce success');
$check(file_get_contents($out)===$canonicalJson."\n",
 'exported file is strictly the original, F62-compatible F100 DTO');
$check(!str_contains($canonicalJson,'ci-f91-only-a')
 &&!str_contains($canonicalJson,'access_token')
 &&!str_contains($canonicalJson,'refresh_token'),
 'signed-to-Portal artifact leaks neither database credential nor advertiser tokens');
echo "F179 original Portal Ed25519 -> F124 nonce -> F100 -> current F62: {$n} PASS / 0 FAIL — NO-GO\n";
