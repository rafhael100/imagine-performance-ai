<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F151SyntheticCreativeReviewQueue.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F152SyntheticCreativeReviewCsvExporter.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F151SyntheticCreativeReviewQueue;
use Imagine\Performance\Domain\F152SyntheticCreativeReviewCsvExporter;
use Imagine\Performance\Domain\F153OfflineReviewFixtureFactory;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F152_CSV_LAB')!=='1') {
 throw new RuntimeException('F152 fictional CSV: CLI synthetic only');
}
$tenant='perf-demo-aurora';
$scope=new SyntheticManagedScopeLedger();
$scope->setDemoCapacity($tenant,'google_ads',2,3);
$scope->setDemoCapacity($tenant,'meta',1,2);
$scope->includeCampaign($tenant,'google_ads','cam-search-demo');
$scope->includeCampaign($tenant,'meta','cam-social-demo');
if(getenv('PERF_F153_PARITY_LAB')==='1'){
 require_once dirname(__DIR__,2).'/src/Domain/Performance/F153OfflineReviewFixtureFactory.php';
 $projection=F153OfflineReviewFixtureFactory::build($scope,$tenant);
}else{
 $queue=new F151SyntheticCreativeReviewQueue($scope);
 if($queue->submit($tenant,'rev-search-demo','cam-search-demo','google_ads',
     'Revisar peça de pesquisa')!=='submitted_demo'
  ||$queue->submit($tenant,'rev-social-demo','cam-social-demo','meta',
     'Revisar campanha social')!=='submitted_demo'
  ||$queue->decide($tenant,'rev-social-demo',1,'request_changes',
     'reviewer-demo-maria')!=='changes_requested_demo') {
  throw new RuntimeException('F152 invalid synthetic queue fixture');
 }
 $projection=$queue->project($tenant);
 }
$csv=(new F152SyntheticCreativeReviewCsvExporter())->export($projection,$tenant);
if(!is_string($csv))throw new RuntimeException('F152 unsafe review export denied');
echo $csv;
