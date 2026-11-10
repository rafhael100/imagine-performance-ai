<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/src/Lab/F136OnlineWitnessRedemption.php';
use Imagine\Performance\Lab\F136OnlineWitnessRedemption;

if (getenv('APP_ENV')!=='testing' || getenv('PERF_F136_LAB')!=='1'
 || getenv('PGDATABASE')!=='perf_f132_ci'
 || getenv('WITNESS_DB')!=='perf_f132_witness_ci') {
 throw new RuntimeException('F136 may run only in named disposable CI databases');
}
function db136(int $port,string $name,string $role,string $password): PDO {
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$name}",$role,$password,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false,
  PDO::ATTR_TIMEOUT=>4,
 ]);
}
$adminP=db136(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$adminW=db136(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$primary=db136(5432,'perf_f132_ci','perf_f136_primary_bridge',(string)getenv('PERF_F136_PRIMARY_PASSWORD'));
$witness=db136(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$legacyPrimary=db136(5432,'perf_f132_ci','perf_f134_primary_bridge',(string)getenv('PERF_F134_PRIMARY_PASSWORD'));
$legacyWitness=db136(5433,'perf_f132_witness_ci','perf_f134_witness_bridge',(string)getenv('PERF_F134_WITNESS_PASSWORD'));
$sut=new F136OnlineWitnessRedemption($primary,$witness);
$fp=static fn(string $x):string=>hash('sha256','F136:'.$x);
$pc=static fn():int=>(int)$adminP->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$redeemed=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$assertions=0;
$assert=static function(bool $ok,string $message) use (&$assertions):void {
 ++$assertions; if(!$ok) throw new RuntimeException('F136 FAILED: '.$message);
};
$denied=static function(callable $fn):bool{
 try{$fn();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$mint=static function(string $id,string $tenant,string $digest,int $epoch) use($witness):array|false {
 $q=$witness->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)');
 $q->execute([$id,$tenant,$digest,$epoch]);
 return $q->fetch(PDO::FETCH_ASSOC);
};
$redeem=static function(PDO $conn,string $id,string $tenant,string $digest,
 int $epoch,array $permit):bool{
 $q=$conn->prepare('SELECT CASE WHEN perf_f136_primary.redeem_and_record(?,?,?,?,?,?) THEN 1 ELSE 0 END');
 $q->execute([$id,$tenant,$digest,$epoch,(int)$permit['issued_at'],(string)$permit['signature']]);
 return (int)$q->fetchColumn()===1;
};
$file=(getenv('RUNNER_TEMP') ?: '').'/f136-ci-permits.json';
$phase=$argv[1]??'';
if($phase==='seed'){
 $assert($pc()===0 && $wc()===0,'both ledgers start empty');
 $assert($denied(static fn()=>$legacyPrimary->query(
   "SELECT perf_f134_primary.record_signed('operation_9601','tenant_demo_A','".$fp('fake')."',8,1,'".str_repeat('a',64)."')")),
   'old F134 primary SQL bridge cannot bypass online redemption');
 $assert($denied(static fn()=>$legacyWitness->query(
   "SELECT * FROM perf_f134_witness.issue_permit('operation_9601','tenant_demo_A','".$fp('fake')."',8)")),
   'old F134 witness bridge cannot sign without F136 journal');
 $assert($denied(static fn()=>$primary->query('SELECT * FROM perf_f136_primary.remote_issued')),
   'new primary bridge cannot directly query foreign witness table');
 $assert($denied(static fn()=>$witness->query('SELECT * FROM perf_f136_witness.issued')),
   'witness issuer cannot edit or read redemption table');
 $assert($sut->register('x','tenant_demo_A',$fp('A'))['status']==='invalid_inputs',
   'invalid operation denied before foreign writes');
 $assert($pc()===0 && $wc()===0,'invalid registration left both journals empty');
 $result=$sut->register('operation_9601','tenant_demo_A',$fp('A'));
 $assert($result['status']==='registered_lab' && $result['registered'],
   'normal online F136 registration works');
 $assert(!$result['action_authorized'],'never returns external Ads permission');
 $assert($pc()===1 && $wc()===1 && $redeemed()===1,'A persisted in both with redeemed witness');
 $assert($sut->register('operation_9601','tenant_demo_A',$fp('A'))['status']==='witness_denied',
   'witness nonce cannot be reissued');
}elseif($phase==='after-snapshot'){
 $assert($pc()===1 && $wc()===1,'A backup baseline');
 $b=$mint('operation_9602','tenant_demo_B',$fp('B'),8);
 $assert(is_array($b),'B permit was issued online');
 $assert($redeem($primary,'operation_9602','tenant_demo_B',$fp('B'),8,$b),
   'B redeemed in witness and recorded in primary');
 $assert($pc()===2 && $wc()===2 && $redeemed()===2,'B accepted once in both');
 $c=$mint('operation_9603','tenant_demo_A',$fp('C'),8);
 $assert(is_array($c),'C issued but not yet redeemed');
 $tampered=$c; $tampered['signature']=str_repeat('0',64);
 $assert(!$redeem($primary,'operation_9603','tenant_demo_A',$fp('C'),8,$tampered),
   'wrong signature cannot redeem witness');
 $assert($pc()===2 && $wc()===3 && $redeemed()===2,
   'tampered proof neither admits nor spends C');
 $d=$mint('operation_9604','tenant_demo_B',$fp('D'),8);
 $assert(is_array($d),'D issued for concurrent redemption');
 $primary->beginTransaction();
 $assert($redeem($primary,'operation_9604','tenant_demo_B',$fp('D'),8,$d),
   'first transaction redeems D while transaction is open');
 $second=db136(5432,'perf_f132_ci','perf_f136_primary_bridge',
   (string)getenv('PERF_F136_PRIMARY_PASSWORD'));
 $second->exec("SET statement_timeout='500ms'");
 $timedOut=false;
 try{$redeem($second,'operation_9604','tenant_demo_B',$fp('D'),8,$d);}
 catch(PDOException $e){$timedOut=$e->getCode()==='57014';}
 $assert($timedOut,'second concurrent redemption blocks and times out');
 $primary->commit();
 $second->exec('SET statement_timeout=0');
 $assert(!$redeem($second,'operation_9604','tenant_demo_B',$fp('D'),8,$d),
   'retry after first commit is denied by witness redeemed flag');
 $assert($pc()===3 && $wc()===4 && $redeemed()===3,
   'D persisted only once despite competing connections');
 $e=$mint('operation_9605','tenant_demo_A',$fp('E'),8);
 $assert(is_array($e),'E issued to simulate witness login permission outage');
 $adminW->exec('REVOKE UPDATE (redeemed) ON perf_f136_witness.issued FROM perf_f136_redeemer');
 $unavailable=false;
 try{$redeem($primary,'operation_9605','tenant_demo_A',$fp('E'),8,$e);}
 catch(PDOException $err){$unavailable=true;}
 $assert($unavailable,'remote witness permission outage blocks primary SQL write');
 $assert($pc()===3 && $redeemed()===3,'outage did not write principal or redeem E');
 $adminW->exec('GRANT UPDATE (redeemed) ON perf_f136_witness.issued TO perf_f136_redeemer');
 $assert(file_put_contents($file,json_encode(['B'=>$b,'C'=>$c,'D'=>$d],JSON_THROW_ON_ERROR))!==false,
   'synthetic permits staged for restore test');
}elseif($phase==='after-restore'){
 $permits=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
 $assert($pc()===1,'older primary backup lost B and D');
 $assert($wc()===5 && $redeemed()===3,'witness not restored and retains B,C,D,E');
 $assert(!$redeem($primary,'operation_9602','tenant_demo_B',$fp('B'),8,$permits['B']),
   'B signed proof cannot be redeemed twice after primary restore');
 $assert(!$redeem($primary,'operation_9604','tenant_demo_B',$fp('D'),8,$permits['D']),
   'D concurrent proof cannot replay after primary restore');
 $assert($pc()===1 && $redeemed()===3,'replayed signed proofs leave both ledgers unchanged');
 $adminW->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $assert((int)$adminW->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn()===9,
   'external witness epoch now 9');
 $staleDenied=false;
 try{$redeem($primary,'operation_9603','tenant_demo_A',$fp('C'),8,$permits['C']);}
 catch(PDOException $err){$staleDenied=true;}
 $assert($staleDenied,'signed C from earlier epoch denied by witness trigger online');
 $assert($pc()===1 && $redeemed()===3,'stale signature neither admitted nor spent');
 $fresh=$sut->register('operation_9606','tenant_demo_A',$fp('fresh'));
 $assert($fresh['status']==='witness_denied','new nonce at primary epoch8 denied by witness epoch9');
 $assert(!$fresh['action_authorized'] && !$fresh['registered'],
   'any denied case grants no external action');
 $assert($pc()===1 && $redeemed()===3,'final primary and witness redemption totals unchanged');
}else{
 throw new RuntimeException('Expected phase: seed|after-snapshot|after-restore');
}
echo "F136 {$phase}: {$assertions} PASS / 0 FAIL — LAB ONLY / NO-GO\n";
