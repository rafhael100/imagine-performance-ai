<?php
declare(strict_types=1);

/** F64 process worker used ONLY for concurrency and crash CI on /tmp SQLite. */
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticSqliteManagedScopeStore.php';
use Imagine\Performance\Domain\SyntheticSqliteManagedScopeStore;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F64_SQLITE_SCOPE_LAB')!=='1'){
    throw new RuntimeException('F64 concurrency worker only in testing PHP CLI');
}
$path=getenv('F64_DB_PATH');
if(!is_string($path)||!preg_match('~/perf-f64-[a-z0-9-]{1,60}\.sqlite$~D',$path)){
    throw new RuntimeException('F64 CI requires isolated temp sqlite path');
}
$mode=$argv[1]??'';
$db=new PDO('sqlite:'.$path);
$store=new SyntheticSqliteManagedScopeStore($db);
$t='perf-demo-race';$ch='google_ads';
if($mode==='init'&&count($argv)===2){
    $status=$store->configure($t,$ch,1,0);
    if($status!=='capacity_configured_demo')throw new RuntimeException($status);
    echo $status.PHP_EOL;
}elseif($mode==='claim'&&count($argv)===3){
    $id=$argv[2];
    if(!preg_match('/^cam-race-[a-z0-9-]{1,35}$/D',$id)){
        throw new RuntimeException('Unsafe mock campaign ref');
    }
    echo $store->addCampaign($t,$ch,$id).PHP_EOL;
}elseif($mode==='capacity'&&count($argv)===2){
    echo json_encode($store->capacity($t,$ch),JSON_THROW_ON_ERROR).PHP_EOL;
}elseif($mode==='pause'&&count($argv)===3){
    echo $store->pauseCampaign($t,$argv[2]).PHP_EOL;
}elseif($mode==='state'&&count($argv)===3){
    echo $store->campaignState($t,$argv[2]).PHP_EOL;
}elseif($mode==='crash_uncommitted'&&count($argv)===3){
    $db->exec('BEGIN IMMEDIATE');
    $db->prepare(
        "UPDATE f64_campaigns SET state='paused_demo' WHERE tenant=? AND ref=?"
    )->execute([$t,$argv[2]]);
    // Simulates a process exiting before COMMIT; SQLite rolls back.
    exit(0);
}else{
    throw new RuntimeException('Unsupported F64 race worker operation');
}
