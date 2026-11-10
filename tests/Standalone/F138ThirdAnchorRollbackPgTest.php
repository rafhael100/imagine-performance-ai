<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F138ThirdAnchorRegistration.php';

use Imagine\Performance\Lab\F138ThirdAnchorRegistration;
if(getenv('APP_ENV')!=='testing'||getenv('PERF_F138_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f132_ci'
 ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci') {
    throw new RuntimeException('F138 CI-only access guard');
}
function f138db(int $port,string $db,string $role,string $password):PDO {
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$role,$password,[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
   PDO::ATTR_EMULATE_PREPARES=>false,
   PDO::ATTR_TIMEOUT=>4,
 ]);
}
$pAdmin=f138db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wAdmin=f138db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aAdmin=f138db(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$primary=f138db(5432,'perf_f132_ci','perf_f138_primary_bridge',(string)getenv('PERF_F138_PRIMARY_PASSWORD'));
$witness=f138db(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$anchor=f138db(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$legacy=f138db(5432,'perf_f132_ci','perf_f136_primary_bridge',(string)getenv('PERF_F136_PRIMARY_PASSWORD'));
$sut=new F138ThirdAnchorRegistration($primary,$witness,$anchor);
$fp=static fn(string $key):string=>hash('sha256','F138-'.$key);
$pc=static fn():int=>(int)$pAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc=static fn():int=>(int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$pe=static fn():int=>(int)$pAdmin->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wAdmin->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ae=static fn():int=>(int)$aAdmin->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$n=0;
$check=static function(bool $passed,string $label)use(&$n):void {
 ++$n;if(!$passed)throw new RuntimeException('F138 FAILURE: '.$label);
};
$denied=static function(callable $fn):bool{
 try{$fn();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$permitPath=(getenv('RUNNER_TEMP') ?: '').'/f138-c-permit.json';
$phase=$argv[1]??'';
if($phase==='seed'){
 $check($pe()===8 && $we()===8 && $ae()===8,'three generations start at 8');
 $check($pc()===0 && $wc()===0,'ledgers empty');
 $check($denied(static fn()=>$legacy->query(
   "SELECT perf_f136_primary.redeem_and_record('operation_9801','tenant_demo_A','"
   .$fp('x')."',8,1,'".str_repeat('0',64)."')")),
   'legacy F136 DB credential cannot bypass third anchor');
 $result=$sut->register('operation_9801','tenant_demo_A',$fp('A'));
 $check($result['status']==='registered_lab' && $result['registered'],'A registered through third anchor');
 $check(!$result['action_authorized'],'no real-world Ads authorization');
 $check($pc()===1 && $wc()===1 && $rc()===1,'A persisted both journals');
 // C is an unredeemed valid permit at epoch 8; it persists in both old snapshots.
 $q=$witness->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,8)');
 $q->execute(['operation_9803','tenant_demo_A',$fp('C')]);
 $permit=$q->fetch(PDO::FETCH_ASSOC);
 $check(is_array($permit),'C permit minted at old generation 8');
 $check(file_put_contents($permitPath,json_encode($permit,JSON_THROW_ON_ERROR))!==false,
   'C saved as synthetic CI test fixture');
 $check($pc()===1 && $wc()===2 && $rc()===1,'C is issued but not redeemed');
}elseif($phase==='advance'){
 $check($pc()===1 && $wc()===2 && $rc()===1,'backup baseline A/C');
 $aAdmin->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $check($ae()===9 && $pe()===8 && $we()===8,'third anchor advanced first');
 $blocked=$sut->register('operation_9802','tenant_demo_B',$fp('B'));
 $check($blocked['status']==='anchor_epoch_mismatch','third anchor blocks stale primary epoch');
 $check($pc()===1 && $wc()===2,'anchor mismatch does not spend witness permit');
 $wAdmin->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pAdmin->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($ae()===9 && $pe()===9 && $we()===9,'all three match generation 9');
 $r=$sut->register('operation_9802','tenant_demo_B',$fp('B'));
 $check($r['status']==='registered_lab' && !$r['action_authorized'],'B accepted only with matching floor');
 $check($pc()===2 && $wc()===3 && $rc()===2,'B journaled and redeemed at epoch 9');
}elseif($phase==='after-double-restore'){
 $check($pe()===8 && $we()===8 && $ae()===9,
  'both ledger epochs rolled back but third anchor remains at 9');
 $check($pc()===1 && $wc()===2 && $rc()===1,'B erased from both restored ledgers');
 $b=$sut->register('operation_9802','tenant_demo_B',$fp('B'));
 $check($b['status']==='anchor_epoch_mismatch' && !$b['registered'],'B replay blocked');
 $d=$sut->register('operation_9804','tenant_demo_B',$fp('D'));
 $check($d['status']==='anchor_epoch_mismatch','previously unseen D blocked after dual rollback');
 $check($pc()===1 && $wc()===2 && $rc()===1,'epoch mismatches consume no receipts');
 $permit=json_decode((string)file_get_contents($permitPath),true,512,JSON_THROW_ON_ERROR);
 $q=$primary->prepare(
  'SELECT CASE WHEN perf_f138_primary.record_if_anchor_current(?,?,?,?,?,?) THEN 1 ELSE 0 END'
 );
 $q->execute(['operation_9803','tenant_demo_A',$fp('C'),8,
  (int)$permit['issued_at'],(string)$permit['signature']]);
 $check((int)$q->fetchColumn()===0,'direct SQL with valid old permit denied at third anchor');
 $check($pc()===1 && $rc()===1,'direct SQL denial leaves all receipts intact');
 $aAdmin->exec('REVOKE SELECT ON perf_f138_anchor.epoch_floor FROM perf_f138_anchor_reader');
 $unavailable=$sut->register('operation_9805','tenant_demo_A',$fp('E'));
 $check($unavailable['status']==='anchor_or_primary_unavailable',
  'missing anchor read permission fails closed');
 $check($pc()===1 && $wc()===2,'anchor outage does not write anything');
 $aAdmin->exec('GRANT SELECT ON perf_f138_anchor.epoch_floor TO perf_f138_anchor_reader');
 $check($ae()===9,'anchor epoch preserved at 9');
}else{
 throw new RuntimeException('Expected phase seed|advance|after-double-restore');
}
echo "F138 {$phase}: {$n} PASS / 0 FAIL — three-DB CI only, NO-GO\n";
