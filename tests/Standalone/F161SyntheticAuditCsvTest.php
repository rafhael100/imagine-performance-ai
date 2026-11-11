<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F161SyntheticAuditCsvExporter.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;
use Imagine\Performance\Domain\F160SyntheticSqliteAuditReader;
use Imagine\Performance\Domain\F161SyntheticAuditCsvExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F157_SQLITE_LAB')!=='1'
 ||getenv('PERF_F160_AUDIT_LAB')!=='1'
 ||getenv('PERF_F161_AUDIT_CSV_LAB')!=='1'){
 throw new RuntimeException('F161 evidence CSV CI only');
}
$dir=(string)getenv('RUNNER_TEMP').'/f161-evidence';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('F161 temp unavailable');
$db=$dir.'/review-audit.sqlite';
if(file_exists($db))throw new RuntimeException('F161 database needs empty file');
$tenant='perf-demo-aurora';$other='perf-demo-boreal';
$scope=new SyntheticManagedScopeLedger();
foreach([$tenant,$other] as $t){
 $scope->setDemoCapacity($t,'google_ads',2,3);
}
$scope->includeCampaign($tenant,'google_ads','cam-f161-aurora');
$scope->includeCampaign($other,'google_ads','cam-f161-boreal');
$writer=new F157SyntheticSqliteReviewStore($db,$scope);
$n=0;
$check=static function(bool $success,string $why)use(&$n):void{
 ++$n;if(!$success)throw new RuntimeException('F161 FAIL: '.$why);
};
$check($writer->submit($tenant,'rev-f161-review','cam-f161-aurora',
 'google_ads','Pesquisa fictícia')==='submitted_demo','seed fictional A review');
$check($writer->decide($tenant,'rev-f161-review',1,'request_changes',
 'reviewer-demo-ana')==='changes_requested_demo','A change request persisted');
$check($writer->revise($tenant,'rev-f161-review',2,'Pesquisa revisada')
 ==='resubmitted_demo','A revision persisted');
$check($writer->decide($tenant,'rev-f161-review',3,'approve_mock',
 'reviewer-demo-beto')==='approved_mock_only','A final mock-only decision persisted');
$check($writer->submit($other,'rev-f161-review','cam-f161-boreal',
 'google_ads','Empresa B')==='submitted_demo','B separate fictional review');

$reader=new F160SyntheticSqliteAuditReader($db);
$exporter=new F161SyntheticAuditCsvExporter($reader);
$original=$exporter->export($tenant);
$check(is_string($original)&&str_starts_with($original,"\xEF\xBB\xBF"),
 'CSV has UTF-8 BOM to ease offline spreadsheet reading');
$check(substr_count((string)$original,"\n")===5,
 'CSV header plus exactly four A audit events');
$check(str_contains((string)$original,'NAO E APROVACAO REAL')
 &&str_contains((string)$original,'SEM ANUNCIOS')
 &&str_contains((string)$original,'SEM COBRANCA'),
 'all audit events carry unequivocal NO-GO labels');
$check(str_contains((string)$original,'digest_local_nao_autenticado')
 &&str_contains((string)$original,'reviewer-demo-beto'),
 'auditable digest labeled non-authenticated and only fictional reviewer included');
$check(!str_contains((string)$original,'access_token')
 &&!str_contains((string)$original,'refresh_token')
 &&!str_contains((string)$original,'google.com'),
 'CSV contains no provider credentials or links');
$check($exporter->export($tenant)===$original,
 'consistent persistent history yields byte-identical CSV on repeat read');
$bCsv=$exporter->export($other);
$check(is_string($bCsv)&&substr_count($bCsv,"\n")===2,
 'B export has header and only B event');
$check(!str_contains((string)$bCsv,'reviewer-demo-beto'),
 'B export does not include A reviewer events');
$check($exporter->export('perf-demo-invalid OR 1=1')===null,
 'SQL injection in requested tenant refused');
$check($exporter->export('perf-demo-nobody')!==null
 &&substr_count((string)$exporter->export('perf-demo-nobody'),"\n")===1,
 'valid empty tenant exports only fictional header');

$admin=new PDO('sqlite:'.$db);
$admin->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$admin->exec("UPDATE f157_reviews SET status='pending_demo'
 WHERE tenant='$tenant' AND reference='rev-f161-review'");
$check($exporter->export($tenant)===null,
 'CSV denied after mismatched stored status and signed-off mock audit');
$admin->exec("UPDATE f157_reviews SET status='approved_mock_only'
 WHERE tenant='$tenant' AND reference='rev-f161-review'");
$check($exporter->export($tenant)===$original,
 'CSV re-enabled after synthetic state inconsistency is corrected');

$admin->exec("DELETE FROM f157_events WHERE tenant='$tenant' AND version=2");
$check($exporter->export($tenant)===null,
 'CSV denies missing intermediate audit event');
$check($exporter->export($other)===$bCsv,
 'B export unaffected by A integrity incident');
// Expected-negative: same-domain administrator can delete both tables for A,
// after which an empty consistent CSV would be accepted by this local lab.
$admin->exec("DELETE FROM f157_events WHERE tenant='$tenant'");
$admin->exec("DELETE FROM f157_reviews WHERE tenant='$tenant'");
$emptyAfterDelete=$exporter->export($tenant);
$check(is_string($emptyAfterDelete)
 &&substr_count($emptyAfterDelete,"\n")===1,
 'EXPECTED ANTIROLLBACK GAP: dual deletion allows new empty mock report');
$check($reader->inspect($tenant)['audit_verified_for_production']===false,
 'even apparently consistent empty report is never production evidence');
echo "F161 safe SQLite audit CSV: {$n} PASS / 0 FAIL — NO-GO\n";
