<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F139LockedAnchorRegistration.php';
use Imagine\Performance\Lab\F139LockedAnchorRegistration;

if(getenv('APP_ENV')!=='testing' || getenv('PERF_F139_LAB')!=='1'
 || getenv('PGDATABASE')!=='perf_f132_ci'
 || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci') {
 throw new RuntimeException('F139: disposable GitHub CI databases only');
}
function db139(int $port,string $db,string $role,string $pass):PDO{
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$role,$pass,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false,
  PDO::ATTR_TIMEOUT=>5,
 ]);
}
$pa=db139(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wa=db139(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aa=db139(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$p=db139(5432,'perf_f132_ci','perf_f139_primary_bridge',(string)getenv('PERF_F139_PRIMARY_PASSWORD'));
$w=db139(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$a=db139(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$old=db139(5432,'perf_f132_ci','perf_f138_primary_bridge',(string)getenv('PERF_F138_PRIMARY_PASSWORD'));
$sut=new F139LockedAnchorRegistration($p,$w,$a);

$fingerprint=static fn(string $s):string => hash('sha256','F139-CI-'.$s);
$primaryCount=static fn():int=>(int)$pa->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$witnessCount=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$redeemedCount=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$primaryEpoch=static fn():int=>(int)$pa->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$anchorEpoch=static fn():int=>(int)$aa->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$checks=0;
$check=static function(bool $condition,string $message)use(&$checks):void{
 ++$checks;
 if(!$condition)throw new RuntimeException('F139 FAILED: '.$message);
};
$expectDenied=static function(callable $callback):bool{
 try{$callback();return false;}
 catch(PDOException $ex){return $ex->getCode()==='42501';}
};
$mint=static function(string $id,string $tenant,string $finger,int $epoch=8)use($w):array{
 $statement=$w->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)');
 $statement->execute([$id,$tenant,$finger,$epoch]);
 $permit=$statement->fetch(PDO::FETCH_ASSOC);
 if(!is_array($permit))throw new RuntimeException('F139 mint missing');
 return $permit;
};
$record=static function(PDO $connection,string $id,string $tenant,string $finger,
 int $epoch,array $permit):bool{
 $statement=$connection->prepare(
  'SELECT CASE WHEN perf_f139_primary.record_with_locked_anchor(?,?,?,?,?,?) THEN 1 ELSE 0 END'
 );
 $statement->execute([$id,$tenant,$finger,$epoch,(int)$permit['issued_at'],(string)$permit['signature']]);
 return (int)$statement->fetchColumn()===1;
};
$phase=$argv[1]??'';
if($phase==='seed'){
 $check($primaryEpoch()===8 && $anchorEpoch()===8,'local and anchor start generation 8');
 $check($primaryCount()===0 && $witnessCount()===0,'receipt ledgers start empty');
 $check($expectDenied(static fn()=>$old->query(
  "SELECT perf_f138_primary.record_if_anchor_current('operation_9900','tenant_demo_A','"
  .$fingerprint('x')."',8,1,'".str_repeat('0',64)."')")),
  'F138 legacy SQL principal loses direct admission access');
 $check($expectDenied(static fn()=>$p->query('SELECT * FROM perf_f138_primary.external_epoch')),
  'F139 bridge may not bypass secure SQL by reading remote foreign table');
 $check($sut->register('bad','tenant_demo_A',$fingerprint('x'))['status']==='invalid_inputs',
  'bad operation rejected without spending witness nonce');
 $check($primaryCount()===0 && $witnessCount()===0,'bad inputs leave both journals unchanged');
 $a1=$sut->register('operation_9901','tenant_demo_A',$fingerprint('A'));
 $check($a1['status']==='registered_lab' && $a1['registered'],'normal locked-anchor registration');
 $check(!$a1['action_authorized'],'registered flag does not approve Ads');
 $check($primaryCount()===1 && $witnessCount()===1 && $redeemedCount()===1,'A durable at both ledgers');
 $check($sut->register('operation_9901','tenant_demo_A',$fingerprint('A'))['status']==='witness_denied',
  'duplicate nonce refused');
}elseif($phase==='race'){
 $check($primaryCount()===1 && $witnessCount()===1,'baseline before race');
 $b=$mint('operation_9902','tenant_demo_B',$fingerprint('B'));
 $c=$mint('operation_9903','tenant_demo_B',$fingerprint('C'));
 $check($witnessCount()===3 && $redeemedCount()===1,'B/C issued but not redeemed yet');

 // Hold explicit local transaction open after SQL registers B and remote
 // SELECT ... FOR SHARE takes a row lock on the third authority.
 $p->beginTransaction();
 try {
  $check($record($p,'operation_9902','tenant_demo_B',$fingerprint('B'),8,$b),
   'B admitted while third anchor row remains locked');
  $check($primaryCount()===1 && $redeemedCount()===1,
   'uncommitted primary B and FDW redemption hidden from other sessions');
  $aa->exec("SET statement_timeout='650ms'");
  $wasBlocked=false;
  try {
   $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
  } catch(PDOException $e) {
   $wasBlocked=$e->getCode()==='57014';
  }
  $check($wasBlocked,
   'concurrent anchor generation advancement blocks on remote row lock');
  $check($anchorEpoch()===8,'anchor stays generation 8 while primary transaction open');
  $p->commit();
 } catch(Throwable $e) {
  if($p->inTransaction())$p->rollBack();
  throw $e;
 } finally {
  $aa->exec('SET statement_timeout=0');
 }
 $check($primaryCount()===2 && $redeemedCount()===2,
  'B commits once in primary and witness after releasing anchor row lock');
 $check((int)$aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn()===1,
  'anchor advancement can complete after primary commit releases lock');
 $check($anchorEpoch()===9 && $primaryEpoch()===8,
  'anchor now newer than local primary floor');
 $check(!$record($p,'operation_9903','tenant_demo_B',$fingerprint('C'),8,$c),
  'C signed permit issued at old epoch fails SQL-side row-lock check');
 $check($primaryCount()===2 && $redeemedCount()===2,
  'stale C permit not redeemed after floor increase');
 $d=$sut->register('operation_9904','tenant_demo_A',$fingerprint('D'));
 $check($d['status']==='anchor_epoch_mismatch',
  'PHP preflight refuses new D without creating witness permit');
 $check($witnessCount()===3 && $primaryCount()===2,
  'preflight mismatch leaves both ledgers unchanged');
 $aa->exec('REVOKE SELECT ON perf_f138_anchor.epoch_floor FROM perf_f138_anchor_reader');
 $e=$sut->register('operation_9905','tenant_demo_A',$fingerprint('E'));
 $check($e['status']==='anchor_or_primary_unavailable',
  'remote anchor access revoked => fail closed at preflight');
 $check($primaryCount()===2 && $witnessCount()===3,
  'unavailable anchor cannot create or redeem nonce');
 $aa->exec('GRANT SELECT ON perf_f138_anchor.epoch_floor TO perf_f138_anchor_reader');
 $check($anchorEpoch()===9,'test never decrements authority generation');
}else{
 throw new RuntimeException('F139 expected phase seed|race');
}
echo "F139 {$phase}: {$checks} PASS / 0 FAIL — three-DB CI lab, NO-GO\n";
