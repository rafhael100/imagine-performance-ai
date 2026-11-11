<?php
declare(strict_types=1);

/** F158 one-shot synthetic-only subprocess to test independent SQLite writers. */
require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;

if (PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F52_SCOPE_LAB')!=='1'
    ||getenv('PERF_F157_SQLITE_LAB')!=='1'
    ||getenv('PERF_F158_RACE_LAB')!=='1'
    ||count($argv)!==7) {
    fwrite(STDERR,"F158 worker guard denied\n");
    exit(2);
}
[$program,$path,$operation,$tenant,$reference,$version,$argument]=$argv;
if(!in_array($operation,['decide','revise'],true)
    ||!ctype_digit($version)||strlen($version)>3){
    fwrite(STDERR,"F158 unsupported synthetic operation\n");
    exit(2);
}
try {
    $scope=new SyntheticManagedScopeLedger();
    foreach(['perf-demo-aurora','perf-demo-boreal'] as $t){
        $scope->setDemoCapacity($t,'google_ads',2,2);
    }
    $scope->includeCampaign('perf-demo-aurora','google_ads','cam-f158-search');
    $scope->includeCampaign('perf-demo-boreal','google_ads','cam-f158-boreal');
    $worker=new F157SyntheticSqliteReviewStore($path,$scope);
    $result=$operation==='decide'
        ?$worker->decide($tenant,$reference,(int)$version,$argument,'reviewer-demo-worker')
        :$worker->revise($tenant,$reference,(int)$version,$argument);
    echo json_encode([
        'status'=>$result,
        'action_authorized'=>false,
        'billing_enabled'=>false,
        'real_human_approval'=>false
    ],JSON_THROW_ON_ERROR)."\n";
} catch(\Throwable $e) {
    // Do not report SQL/host details in an execution interface.
    echo '{"status":"unknown_fail_closed","action_authorized":false,"billing_enabled":false,"real_human_approval":false}'."\n";
}
