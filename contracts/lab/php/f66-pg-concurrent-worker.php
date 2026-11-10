<?php
declare(strict_types=1);

/**
 * F1-66: independent PostgreSQL writer for synthetic CI concurrency.
 * Uses tenant-A fixture login only. No real client or provider.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
  ||getenv('GITHUB_ACTIONS')!=='true'){
    throw new RuntimeException('F66 concurrency worker is GitHub CI lab only');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4',
    'perf_f66_tenant_a','ci-f66-role-a-only',[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
if($db->query('SELECT session_user')->fetchColumn()!=='perf_f66_tenant_a'){
    throw new RuntimeException('Unexpected F66 role');
}
$mode=$argv[1]??'';
if($mode==='init'&&count($argv)===2){
    $value=$db->query("SELECT perf_f66_scope.configure_demo('meta',1,0)")->fetchColumn();
    echo $value.PHP_EOL;
}elseif($mode==='claim'&&count($argv)===3){
    $ref=$argv[2];
    if(!preg_match('/^cam-race-[a-z0-9-]{1,40}$/D',$ref)){
        throw new RuntimeException('Unsafe race reference');
    }
    $q=$db->prepare('SELECT perf_f66_scope.reserve_campaign(?,?)');
    $q->execute(['meta',$ref]);
    echo $q->fetchColumn().PHP_EOL;
}elseif($mode==='count'&&count($argv)===2){
    echo json_encode([
        'used'=>(int)$db->query(
          "SELECT count(*) FROM perf_f66_scope.campaigns"
          ." WHERE channel='meta' AND managed"
        )->fetchColumn(),
        'limit'=>(int)$db->query(
          "SELECT campaign_limit FROM perf_f66_scope.capacities"
          ." WHERE channel='meta'"
        )->fetchColumn(),
        'session_user'=>$db->query('SELECT session_user')->fetchColumn(),
        'billing_enabled'=>false,
        'dispatch_enabled'=>false,
    ],JSON_THROW_ON_ERROR).PHP_EOL;
}elseif($mode==='state'&&count($argv)===3){
    $q=$db->prepare('SELECT state FROM perf_f66_scope.campaigns WHERE ref=?');
    $q->execute([$argv[2]]);
    $v=$q->fetchColumn();
    echo ($v?:'not_found').PHP_EOL;
}elseif($mode==='pause'&&count($argv)===3){
    $q=$db->prepare('SELECT perf_f66_scope.pause_campaign(?)');
    $q->execute([$argv[2]]);
    echo $q->fetchColumn().PHP_EOL;
}elseif($mode==='crash_uncommitted'&&count($argv)===3){
    $db->beginTransaction();
    $q=$db->prepare('SELECT perf_f66_scope.pause_campaign(?)');
    $q->execute([$argv[2]]);
    if($q->fetchColumn()!=='paused_synthetic_only'){
        throw new RuntimeException('Cannot stage synthetic uncommitted pause');
    }
    // Simulate worker crash before COMMIT: connection close rolls back.
    exit(0);
}else{
    throw new RuntimeException('Unsupported F66 test worker command');
}
