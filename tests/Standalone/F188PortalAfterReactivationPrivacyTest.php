<?php
declare(strict_types=1);

/**
 * F188: real Portal F62/F63 must render only currently scoped F100 SQL
 * after F185 tombstone, F186 append-only audit, and F187 DBA red-team.
 * Offline, synthetic data; absolutely no Portal IAM/Ads authorization.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB',
 'PERF_F188_POST_REACTIVATION_PORTAL_LAB',
 'PORTAL_F62_CREDENTIAL_WORKBENCH_LAB',
 'PORTAL_F63_STATIC_HTML_LAB',
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F188 CI flag missing: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('dom')){
 throw new RuntimeException('F188 isolated PostgreSQL16 CI required');
}
$root=dirname(__DIR__,2);
$portal=$root.'/portal-f176-candidate/apps/portal';
$runner=realpath((string)getenv('RUNNER_TEMP'));
if($runner===false)throw new RuntimeException('F188 runner temp missing');
foreach([
 'app/Domain/Catalog/PerformanceChannel.php',
 'app/Domain/PerformanceDashboard/PerformanceCampaignObservationState.php',
 'app/Domain/PerformanceDashboard/PerformanceReviewObservationState.php',
 'app/Domain/PerformanceDashboard/CredentialBoundPerformanceWorkbenchPreview.php'
] as $file){
 if(!is_file($portal.'/'.$file))throw new RuntimeException('F188 pinned Portal missing '.$file);
 require_once $portal.'/'.$file;
}
$renderer=$portal.'/tools/f63-render-credential-workbench.php';
if(!is_file($renderer))throw new RuntimeException('F188 original Portal F63 missing');
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $login,string $pass):PDO=>new PDO($dsn,$login,$pass,$opts);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void {
 ++$n;if(!$pass)throw new RuntimeException('F188 FAILED: '.$why);
};
$originalB='cam-demo-f89-b';
$originalRequest=$admin->query(
 "SELECT i.request_ref FROM public.performance_campaign_intents i
  JOIN public.performance_managed_campaigns c ON c.id=i.campaign_id
  WHERE c.campaign_ref='cam-demo-f89-b' LIMIT 1"
)->fetchColumn();
$check(is_string($originalRequest)&&str_starts_with($originalRequest,'req-demo-'),
 'retired B review remains historically stored, not physically erased');
$check((int)$admin->query(
 "SELECT management_epoch FROM public.performance_managed_campaigns
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn()===2,
 'F187 negative test restored original B epoch to 2');
$check((int)$b->query(
 "SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn()===0
 &&(int)$b->query(
 "SELECT count(*) FROM public.perf_f97_demo_review_read
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn()===0,
 'both PostgreSQL read views hide the original reactivated B campaign/review');
$aDto=$reader->read($a);
$bDto=$reader->read($b);
$check($aDto['status']==='credential_scoped_demo_workbench_only'
 &&$aDto['counts']['linked_campaigns']===0
 &&$aDto['counts']['visible_reviews']===0,
 'previous F185 A retirement remains hidden from Portal read-model');
$check($bDto['status']==='credential_scoped_demo_workbench_only'
 &&$bDto['counts']['linked_campaigns']===1
 &&$bDto['counts']['visible_reviews']===1,
 'B current F186 newly created campaign remains visible after old tombstone');
$check($bDto['campaigns'][0]['campaign_ref']==='cam-demo-f186-new'
 &&$bDto['reviews'][0]['review_observation_state']==='pending_human_demo'
 &&$bDto['reviews'][0]['journaled']===false,
 'B F100 reports only NEW first-generation campaign and unapproved pending review');
$check(!in_array($originalB,array_column($bDto['campaigns'],'campaign_ref'),true)
 &&!in_array($originalRequest,array_column($bDto['reviews'],'request_ref'),true),
 'old B campaign and completed review never enter current F100 DTO');
foreach([$aDto,$bDto] as $dto){
 $check($dto['portal_identity_authenticated']===false
  &&$dto['real_human_approval_verified']===false
  &&$dto['provider_connected']===false
  &&$dto['authorization_eligible']===false
  &&$dto['ready_for_production']===false
  &&$dto['dispatch_enabled']===false
  &&$dto['billing_enabled']===false,
  'post-revocation tenant DTO never grants IAM, Ads or billing action');
}
$portalType=\App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview::class;
$portalA=$portalType::fromDto($aDto);
$portalB=$portalType::fromDto($bDto);
$check($portalA instanceof $portalType &&$portalB instanceof $portalType,
 'the pinned current Portal F62 consumer accepts both post-retirement scoped DTOs');
$check($portalB->counts===$bDto['counts']
 &&$portalB->campaigns===$bDto['campaigns']
 &&$portalB->reviews===$bDto['reviews'],
 'Portal F62 receives exact current B SQL rows without fixture conversions');
$check($portalB->hasPortalIdentity()===false
 &&$portalB->canPublishAds()===false
 &&$portalB->canBill()===false
 &&$portalB->isRealTime()===false,
 'real Portal F62 continues to reject operational privileges');

$input=$runner.'/f188-current-b.json';
$output=$runner.'/f188-current-b.html';
$bytes=json_encode(
 $bDto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
 |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT
 |JSON_THROW_ON_ERROR
)."\n";
$handle=fopen($input,'x');
if(!is_resource($handle))throw new RuntimeException('F188 cannot create exclusive CI input');
try{
 if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle)||!fsync($handle)){
  throw new RuntimeException('F188 input write/fsync failed');
 }
}finally{fclose($handle);}
$proc=proc_open(
 [PHP_BINARY,$renderer,$input],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,$root
);
if(!is_resource($proc))throw new RuntimeException('F188 original Portal F63 unavailable');
fclose($pipes[0]);
$html=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);
$stderr=(string)stream_get_contents($pipes[2]);fclose($pipes[2]);
$check(proc_close($proc)===0 &&strlen($html)>2000,
 'original current Portal F63 can render the post-retirement SQL dataset');
$check(file_put_contents($output,$html,LOCK_EX)!==false,
 'only an offline CI HTML file is produced for visual review');
$check(str_contains($html,'cam-demo-f186-new')
 &&str_contains($html,$bDto['reviews'][0]['request_ref']),
 'HTML shows newly created pending B campaign and review');
$check(!str_contains($html,$originalB)
 &&!str_contains($html,$originalRequest),
 'HTML does NOT resurrect previous B managed-era campaign or approved request');

$dom=new DOMDocument('1.0','UTF-8');
libxml_use_internal_errors(true);
$ok=$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,
 LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
libxml_clear_errors();
$check($ok===true,'offline document parses without a network request');
$xp=new DOMXPath($dom);
$check($xp->query('//section[@aria-label="Campanhas fictícias"]//tbody/tr')->length===1
 &&$xp->query('//section[@aria-label="Histórico de revisões fictícias"]//tbody/tr')->length===1,
 'visible Portal F63 row counts match the current B SQL epoch');
$check(str_contains($html,'DEMONSTRAÇÃO NÃO HOMOLOGADA')
 &&str_contains($html,"default-src 'none'")
 &&str_contains($html,"script-src 'none'")
 &&str_contains($html,"connect-src 'none'"),
 'current B HTML preserves security policy and demo-only disclaimer');
$check($xp->query('//script|//form|//iframe|//button|//a')->length===0,
 'static HTML includes no live forms, ads controls or outbound links');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'no externally managed PostgreSQL transaction left open');
echo "F188 current Portal F62/F63 after management epochs: {$n} PASS / 0 FAIL — NO-GO\n";
