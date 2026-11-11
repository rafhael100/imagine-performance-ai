<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F151SyntheticCreativeReviewQueue.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F152SyntheticCreativeReviewCsvExporter.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F151SyntheticCreativeReviewQueue;
use Imagine\Performance\Domain\F152SyntheticCreativeReviewCsvExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F152_CSV_LAB')!=='1'){
 throw new RuntimeException('F152 CSV tests restricted to fictional CI');
}
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F152 FAILED: '.$why);
};
$tenant='perf-demo-aurora';$other='perf-demo-boreal';
$scope=new SyntheticManagedScopeLedger();
$check($scope->setDemoCapacity($tenant,'google_ads',3,3)==='capacity_configured_demo',
 'fictional campaign channel capacity configured');
$check($scope->includeCampaign($tenant,'google_ads','cam-f152-test')==='included_synthetic_only',
 'managed campaign prepared in existing F52 scope ledger');
$queue=new F151SyntheticCreativeReviewQueue($scope);
$check($queue->submit($tenant,'rev-f152-formula','cam-f152-test','google_ads',
 '=2+2')==='submitted_demo','formula-looking title can enter fictional review queue');
$check($queue->submit($tenant,'rev-f152-normal','cam-f152-test','google_ads',
 'Planejamento de pesquisa')==='submitted_demo','normal fictional review submitted');
$check($queue->decide($tenant,'rev-f152-normal',1,'approve_mock',
 'reviewer-demo-ana')==='approved_mock_only','approved mock is terminal UI state');
$dto=$queue->project($tenant);
$exporter=new F152SyntheticCreativeReviewCsvExporter();
$csv=$exporter->export($dto,$tenant);
$check(is_string($csv)&&str_starts_with($csv,"\xEF\xBB\xBF"),
 'CSV has UTF-8 BOM for spreadsheet interoperability');
$check(is_string($csv)&&str_contains($csv,"'=2+2"),
 'formula-like title neutralized before CSV export');
$check(is_string($csv)&&!str_contains($csv,'https://')
 &&!str_contains($csv,'@gmail.com'),'CSV includes no external provider URLs');
$check(is_string($csv)&&str_contains($csv,'SEM ANUNCIOS')
 &&str_contains($csv,'SEM COBRANCA')
 &&str_contains($csv,'NAO E APROVACAO REAL'),
 'every record marks all operational effects disabled');
$check(substr_count((string)$csv,"\n")===3,
 'CSV contains header and exactly two fictional reviews');
$check(str_contains((string)$csv,'Aprovada somente em teste'),
 'mock approval status cannot be mistaken for real approval');
$check($exporter->export($dto,$other)===null,
 'expected tenant B cannot export tenant A reviews');
$bad=$dto;$bad['dispatch_enabled']=true;
$check($exporter->export($bad,$tenant)===null,
 'live dispatch flag refused');
$bad=$dto;$bad['authorization_eligible']=true;
$check($exporter->export($bad,$tenant)===null,
 'claimed authorization eligibility refused');
$bad=$dto;$bad['human_approval_verified']=true;
$check($exporter->export($bad,$tenant)===null,
 'claimed human approval refused');
$bad=$dto;$bad['billing_enabled']=true;
$check($exporter->export($bad,$tenant)===null,
 'live billing flag refused');
$bad=$dto;$bad['contracted']=true;
$check($exporter->export($bad,$tenant)===null,
 'real contract flag refused');
$bad=$dto;$bad['reviews'][0]['action_authorized']=true;
$check($exporter->export($bad,$tenant)===null,
 'authorized review row refused');
$bad=$dto;$bad['reviews'][1]['status']='approved_real';
$check($exporter->export($bad,$tenant)===null,
 'real approval status not accepted by exporter');
$bad=$dto;$bad['reviews'][0]['title']='<script>alert(1)</script>';
$check($exporter->export($bad,$tenant)===null,
 'unsafe markup refused by CSV exporter');
$bad=$dto;$bad['reviews'][1]['review_ref']=$bad['reviews'][0]['review_ref'];
$check($exporter->export($bad,$tenant)===null,
 'duplicate review ref refused');
$bad=$dto;$bad['summary']['pending_demo']=999;
$check($exporter->export($bad,$tenant)===null,
 'inconsistent summary counts refused');
$bad=$dto;$bad['review_count']=999;
$check($exporter->export($bad,$tenant)===null,
 'unbounded review count refused');
$bad=$dto;$bad['reviews'][1]['channel']='tiktok';
$check($exporter->export($bad,$tenant)===null,
 'unsupported channel cannot leak into launch CSV');
$bad=$dto;$bad['tenant_ref']='perf-demo-boreal';
$check($exporter->export($bad,$tenant)===null,
 'tampered tenant reference denied');

$generator=dirname(__DIR__,2).'/contracts/demo/f152-export-creative-reviews.php';
$proc=proc_open([PHP_BINARY,$generator],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),
 ['APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
  'PERF_F151_APPROVAL_LAB'=>'1','PERF_F152_CSV_LAB'=>'1']
);
if(!is_resource($proc))throw new RuntimeException('F152 cannot start exporter');
fclose($pipes[0]);
$stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
$status=proc_close($proc);
$check($status===0&&is_string($stdout)
 &&str_contains($stdout,'rev-search-demo')
 &&str_contains($stdout,'rev-social-demo'),
 'F152 CLI produces two scoped creative reviews: '.substr((string)$stderr,0,160));
$check(is_string($stdout)&&str_starts_with($stdout,"\xEF\xBB\xBF")
 &&substr_count($stdout,"\n")===3,
 'CLI CSV preserves BOM and exact row count');
echo "F152 creative review CSV: {$n} PASS / 0 FAIL — NO-GO\n";
