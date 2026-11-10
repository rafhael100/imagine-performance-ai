<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/src/Lab/F134SignedWitnessRegistration.php';

use Imagine\Performance\Lab\F134SignedWitnessRegistration;

if (getenv('APP_ENV')!=='testing' || getenv('PERF_F134_LAB')!=='1'
    || getenv('PGDATABASE')!=='perf_f132_ci'
    || getenv('WITNESS_DB')!=='perf_f132_witness_ci') {
    throw new RuntimeException('F134 confined to disposable CI');
}
function f134Db(int $port,string $db,string $user,string $pass): PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>4,
    ]);
}
$adminP=f134Db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$adminW=f134Db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$primary=f134Db(5432,'perf_f132_ci','perf_f134_primary_bridge',(string)getenv('PERF_F134_PRIMARY_PASSWORD'));
$witness=f134Db(5433,'perf_f132_witness_ci','perf_f134_witness_bridge',(string)getenv('PERF_F134_WITNESS_PASSWORD'));
$oldP=f134Db(5432,'perf_f132_ci','perf_f132_primary_bridge',(string)getenv('PERF_F132_PRIMARY_PASSWORD'));
$oldW=f134Db(5433,'perf_f132_witness_ci','perf_f132_witness_bridge',(string)getenv('PERF_F132_WITNESS_PASSWORD'));
$sut=new F134SignedWitnessRegistration($primary,$witness);
$fp=static fn(string $s):string=>hash('sha256','f134-ci-'.$s);
$pc=static fn():int=>(int)$adminP->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f130_witness.seen_fingerprints')->fetchColumn();
$n=0;
$check=static function(bool $ok,string $message) use (&$n):void {
    ++$n; if (!$ok) throw new RuntimeException("F134 FAILED: {$message}");
};
$denied=static function(callable $fn):bool {
    try {$fn(); return false;} catch(PDOException $e) {return $e->getCode()==='42501';}
};
$issue=static function(string $id,string $tenant,string $digest,int $epoch)use($witness):array|false{
    $q=$witness->prepare('SELECT issued_at,signature FROM perf_f134_witness.issue_permit(?,?,?,?)');
    $q->execute([$id,$tenant,$digest,$epoch]);
    return $q->fetch(PDO::FETCH_ASSOC);
};
$record=static function(string $id,string $tenant,string $digest,int $epoch,
                         int $when,string $sig)use($primary):bool {
    $q=$primary->prepare('SELECT CASE WHEN perf_f134_primary.record_signed(?,?,?,?,?,?) THEN 1 ELSE 0 END');
    $q->execute([$id,$tenant,$digest,$epoch,$when,$sig]);
    return (int)$q->fetchColumn()===1;
};
$check($pc()===0 && $wc()===0,'both ledgers initially empty');
$check($denied(static fn()=>$oldP->query(
    "SELECT perf_f132_primary.record_with_generation('operation_9000','tenant_demo_A','".$fp('x')."',8)")),
    'F132 direct primary credential is revoked');
$check($denied(static fn()=>$oldW->query(
    "SELECT perf_f132_witness.reserve_if_current('".$fp('x')."',8)")),
    'F132 direct witness credential is revoked');
$check($denied(static fn()=>$primary->query('SELECT * FROM perf_f134_primary.key_material')),
    'primary app login cannot read HMAC key');
$check($denied(static fn()=>$witness->query('SELECT * FROM perf_f134_witness.key_material')),
    'witness app login cannot read HMAC key');
$check($denied(static fn()=>$primary->query('SELECT * FROM perf_f129_lab.receipts')),
    'primary app login cannot read receipt table');
$check($denied(static fn()=>$witness->query('SELECT * FROM perf_f130_witness.seen_fingerprints')),
    'witness login cannot read nonce table');
$check(!$record('operation_9001','tenant_demo_A',$fp('x'),8,time(),str_repeat('0',64)),
    'direct SQL without matching witness signature denied');
$check($pc()===0 && $wc()===0,'unauthenticated attempt writes no records');
$check($sut->register('bad','tenant_demo_A',$fp('x'))['status']==='invalid_inputs',
    'bridge rejects invalid operation before witness');
$check($wc()===0,'invalid data does not burn nonce');
$p=$issue('operation_9001','tenant_demo_A',$fp('x'),8);
$check(is_array($p) && strlen((string)$p['signature'])===64, 'witness issues a bound HMAC after reservation');
$check($pc()===0 && $wc()===1,'permit issuance persists witness before primary');
$ts=(int)$p['issued_at']; $sig=(string)$p['signature'];
$check(!$record('operation_9002','tenant_demo_A',$fp('x'),8,$ts,$sig),'HMAC operation binding works');
$check(!$record('operation_9001','tenant_demo_B',$fp('x'),8,$ts,$sig),'HMAC tenant binding works');
$check(!$record('operation_9001','tenant_demo_A',$fp('other'),8,$ts,$sig),'HMAC fingerprint binding works');
$check(!$record('operation_9001','tenant_demo_A',$fp('x'),9,$ts,$sig),'HMAC epoch binding works');
$check(!$record('operation_9001','tenant_demo_A',$fp('x'),8,$ts+1,$sig),'HMAC timestamp binding works');
$check(!$record('operation_9001','tenant_demo_A',$fp('x'),8,$ts,str_repeat('f',64)),
    'forged signature denied');
$check($pc()===0,'tampered claims do not register primary receipt');
$check($record('operation_9001','tenant_demo_A',$fp('x'),8,$ts,$sig),
    'valid signed receipt accepted by primary SQL gate');
$check(!$record('operation_9001','tenant_demo_A',$fp('x'),8,$ts,$sig),
    'duplicate signed permit denied by F129 primary journal');
$check($pc()===1 && $wc()===1,'exactly one record in each ledger');
$check($sut->register('operation_9001','tenant_demo_A',$fp('x'))['status']==='witness_denied',
    'PHP bridge cannot reserve a nonce previously spent');
$approved=$sut->register('operation_9003','tenant_demo_B',$fp('fresh'));
$check($approved['status']==='registered_lab' && $approved['registered'],
    'end-to-end PHP two-DB lab registration accepted');
$check($approved['action_authorized']===false,'no Ads or billing authorization');
$check($pc()===2 && $wc()===2,'two separate successful records');

$old=time()-50; $oldDigest=$fp('old');
$payload="F134-v1|operation_9004|tenant_demo_A|{$oldDigest}|8|{$old}";
$q=$adminP->prepare(
    "SELECT encode(public.hmac(convert_to(?,'UTF8'),secret,'sha256'),'hex')
     FROM perf_f134_primary.key_material WHERE singleton=1");
$q->execute([$payload]); $validExpired=(string)$q->fetchColumn();
$check(!$record('operation_9004','tenant_demo_A',$oldDigest,8,$old,$validExpired),
    'correct HMAC expires after 30s');
$check($pc()===2,'expired token leaves journal unchanged');
$future=time()+45;
$payload="F134-v1|operation_9005|tenant_demo_A|".$fp('future')."|8|{$future}";
$q->execute([$payload]);$validFuture=(string)$q->fetchColumn();
$check(!$record('operation_9005','tenant_demo_A',$fp('future'),8,$future,$validFuture),
    'correct HMAC with timestamp too far in future denied');

$adminW->exec('REVOKE EXECUTE ON FUNCTION perf_f134_witness.issue_permit(text,text,text,bigint) FROM perf_f134_witness_bridge');
$blocked=$sut->register('operation_9006','tenant_demo_A',$fp('down'));
$check($blocked['status']==='witness_unknown','witness grant loss fails closed');
$check($pc()===2 && $wc()===2,'witness unavailability writes nothing');
$adminW->exec('GRANT EXECUTE ON FUNCTION perf_f134_witness.issue_permit(text,text,text,bigint) TO perf_f134_witness_bridge');

$adminP->exec('REVOKE EXECUTE ON FUNCTION perf_f134_primary.record_signed(text,text,text,bigint,bigint,text) FROM perf_f134_primary_bridge');
$spent=$sut->register('operation_9007','tenant_demo_A',$fp('spent'));
$check($spent['status']==='primary_unknown_witness_spent','primary write failure burns witness permit');
$check($pc()===2 && $wc()===3,'primary failure creates witness-only receipt');
$adminP->exec('GRANT EXECUTE ON FUNCTION perf_f134_primary.record_signed(text,text,text,bigint,bigint,text) TO perf_f134_primary_bridge');
$check($sut->register('operation_9007','tenant_demo_A',$fp('spent'))['status']==='witness_denied',
    'spent witness nonce never retried');
$check($pc()===2 && $wc()===3, 'no extra primary writes after failure');

echo "F134 signed SQL witness: {$n} PASS / 0 FAIL. CI LAB / NO-GO\n";
