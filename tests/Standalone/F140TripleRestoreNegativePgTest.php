<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F139LockedAnchorRegistration.php';
use Imagine\Performance\Lab\F139LockedAnchorRegistration;
if(getenv('APP_ENV')!=='testing' || getenv('PERF_F140_LAB')!=='1'
   || getenv('PERF_F139_LAB')!=='1'
   || getenv('PGDATABASE')!=='perf_f132_ci'
   || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
   || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci') {
 throw new RuntimeException('F140 lab guarded: three disposable PostgreSQL16 DBs');
}
function f140db(int $port,string $name,string $user,string $pwd):PDO {
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$name}",$user,$pwd,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false,
  PDO::ATTR_TIMEOUT=>4,
 ]);
}
$pAdmin=f140db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wAdmin=f140db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aAdmin=f140db(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$primary=f140db(5432,'perf_f132_ci','perf_f139_primary_bridge',(string)getenv('PERF_F139_PRIMARY_PASSWORD'));
$witness=f140db(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$anchor=f140db(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$bridge=new F139LockedAnchorRegistration($primary,$witness,$anchor);
$fingerprint=static fn(string $seed):string=>hash('sha256','F140-NEGATIVE-'.$seed);
$pc=static fn():int=>(int)$pAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc=static fn():int=>(int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$pe=static fn():int=>(int)$pAdmin->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wAdmin->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ae=static fn():int=>(int)$aAdmin->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F140 FAIL: '.$why);
};
$phase=$argv[1]??'';
if($phase==='seed'){
 $check($pe()===8 && $we()===8 && $ae()===8,'all three authorities start generation 8');
 $check($pc()===0 && $wc()===0 && $rc()===0,'empty journals on all new CI databases');
 $a=$bridge->register('operation_1401','tenant_demo_A',$fingerprint('A'));
 $check($a['status']==='registered_lab' && $a['registered'],'A accepted at epoch 8');
 $check(!$a['action_authorized'],'never approves Ads or billing');
 $check($pc()===1 && $wc()===1 && $rc()===1,'A persisted and redeemed once');
}elseif($phase==='after-snapshot'){
 $check($pc()===1 && $wc()===1 && $rc()===1,'pre-upgrade snapshots contain only A');
 $check($ae()===8 && $pe()===8 && $we()===8,'snapshots made at generation 8');
 $raised=$aAdmin->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $check(in_array($raised,[true,'t','1',1],true),'anchor floor advanced to 9');
 $wAdmin->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pAdmin->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($pe()===9 && $we()===9 && $ae()===9,'all three authorities now at generation 9');
 $b=$bridge->register('operation_1402','tenant_demo_B',$fingerprint('B'));
 $check($b['status']==='registered_lab' && $b['registered'],'B registered after old backups at epoch 9');
 $check(!$b['action_authorized'],'B registration is never media authorization');
 $check($pc()===2 && $wc()===2 && $rc()===2,'B persisted in both ledgers after all snapshots');
 $check($bridge->register('operation_1402','tenant_demo_B',$fingerprint('B'))['status']==='witness_denied',
  'witness rejects B replay while all authorities have current state');
 $check($ae()===9 && $pc()===2,'current anchor floor and primary journal still valid');
}elseif($phase==='after-triple-restore'){
 $check($pe()===8 && $we()===8 && $ae()===8,
  'EXPECTED GAP: all three epoch floors rolled back from 9 to 8');
 $check($pc()===1 && $wc()===1 && $rc()===1,
  'EXPECTED GAP: all three backups lost all evidence of B');
 $check((int)$pAdmin->query(
  "SELECT count(*) FROM perf_f129_lab.receipts WHERE operation_id='operation_1402'"
 )->fetchColumn()===0,'B is absent after triple restore');
 $replayed=$bridge->register('operation_1402','tenant_demo_B',$fingerprint('B'));
 $check($replayed['status']==='registered_lab' && $replayed['registered'],
  'EXPECTED VULNERABILITY: B is readmitted after triple rollback');
 $check(!$replayed['action_authorized'],
  'successful lab registration still cannot cause a real external action');
 $check($pc()===2 && $wc()===2 && $rc()===2,
  'replay creates a fresh B in both restored ledgers');
 $check($bridge->register('operation_1402','tenant_demo_B',$fingerprint('B'))['status']==='witness_denied',
  'replay denied only AFTER duplicate re-registration changed witness');
 $check($ae()===8 && $we()===8,
  'no remaining antirollback evidence outside all restored databases');
}else{
 throw new RuntimeException('phase must be seed|after-snapshot|after-triple-restore');
}
echo "F140 {$phase}: {$n} PASS / 0 FAIL — expected-negative replay, NO-GO\n";
