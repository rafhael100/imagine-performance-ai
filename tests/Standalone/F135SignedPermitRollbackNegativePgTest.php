<?php
declare(strict_types=1);

/**
 * Expected-negative CI demonstration:
 * A still-valid witness-signed permit can be replayed after primary-only restore.
 * A witness epoch increment after permit issuance is not checked again by primary.
 */
if (getenv('APP_ENV')!=='testing' || getenv('PERF_F134_LAB')!=='1'
    || getenv('PERF_F135_LAB')!=='1'
    || getenv('PGDATABASE')!=='perf_f132_ci'
    || getenv('WITNESS_DB')!=='perf_f132_witness_ci') {
    throw new RuntimeException('F135: disposable CI-only');
}
function f135db(int $port,string $db,string $user,string $pass): PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>4,
    ]);
}
$pAdmin=f135db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wAdmin=f135db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$p=f135db(5432,'perf_f132_ci','perf_f134_primary_bridge',(string)getenv('PERF_F134_PRIMARY_PASSWORD'));
$w=f135db(5433,'perf_f132_witness_ci','perf_f134_witness_bridge',(string)getenv('PERF_F134_WITNESS_PASSWORD'));
$pc=static fn():int=>(int)$pAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wAdmin->query('SELECT count(*) FROM perf_f130_witness.seen_fingerprints')->fetchColumn();
$pe=static fn():int=>(int)$pAdmin->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wAdmin->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$fp=static fn(string $s):string=>hash('sha256','F135-'.$s);
$mint=static function(string $id,string $tenant,string $digest,int $epoch)use($w):array|false{
    $q=$w->prepare('SELECT issued_at,signature FROM perf_f134_witness.issue_permit(?,?,?,?)');
    $q->execute([$id,$tenant,$digest,$epoch]);
    return $q->fetch(PDO::FETCH_ASSOC);
};
$write=static function(string $id,string $tenant,string $digest,int $epoch,array $permit)use($p):bool {
    $q=$p->prepare('SELECT CASE WHEN perf_f134_primary.record_signed(?,?,?,?,?,?) THEN 1 ELSE 0 END');
    $q->execute([$id,$tenant,$digest,$epoch,(int)$permit['issued_at'],(string)$permit['signature']]);
    return (int)$q->fetchColumn()===1;
};
$n=0;
$assert=static function(bool $ok,string $text)use(&$n):void{
    ++$n;if(!$ok)throw new RuntimeException("F135 FAILED: {$text}");
};
$path=(getenv('RUNNER_TEMP') ?: '').'/f135-synthetic-permits.json';
$phase=$argv[1] ?? '';
if($phase==='seed'){
    $assert($pc()===0 && $wc()===0,'initial databases empty');
    $pa=$mint('operation_9501','tenant_demo_A',$fp('A'),8);
    $assert(is_array($pa),'A witness reservation');
    $assert($write('operation_9501','tenant_demo_A',$fp('A'),8,$pa),'A recorded primary');
    $assert($pc()===1 && $wc()===1,'A registered once in both');
} elseif($phase==='after-snapshot'){
    $assert($pc()===1 && $wc()===1,'backup baseline verified');
    $pb=$mint('operation_9502','tenant_demo_B',$fp('B'),8);
    $pcandidate=$mint('operation_9503','tenant_demo_A',$fp('C'),8);
    $assert(is_array($pb) && is_array($pcandidate),'B and C witness permits minted');
    $assert($write('operation_9502','tenant_demo_B',$fp('B'),8,$pb),'B first primary admission');
    $assert($pc()===2 && $wc()===3,'B persisted, C only witness');
    $save=json_encode(['B'=>$pb,'C'=>$pcandidate],JSON_THROW_ON_ERROR);
    $assert(file_put_contents($path,$save)!==false,'test-only cached signed permits stored');
} elseif($phase==='after-restore'){
    $data=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $assert($pe()===8 && $we()===8,'epochs agree after primary-only rollback');
    $assert($pc()===1 && $wc()===3,'B receipt lost in primary, witness still has B and C');
    $assert($write('operation_9502','tenant_demo_B',$fp('B'),8,$data['B']),
       'EXPECTED VULNERABILITY: signed B replay accepted in restored primary');
    $assert($pc()===2 && $wc()===3,'replay created primary row without new witness event');
    $wAdmin->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $assert($we()===9 && $pe()===8,'external floor advanced after prior C permit');
    $assert($write('operation_9503','tenant_demo_A',$fp('C'),8,$data['C']),
       'EXPECTED VULNERABILITY: cached C permit accepted despite newer external floor');
    $assert($pc()===3 && $wc()===3,'primary accepted stale-generation permit without witness recheck');
    $assert($mint('operation_9504','tenant_demo_A',$fp('D'),8)===false,
       'fresh mint denied at old epoch after witness floor advanced');
    $assert(!$write('operation_9504','tenant_demo_A',$fp('D'),8,
       ['issued_at'=>time(),'signature'=>str_repeat('0',64)]),
       'forged replacement still rejected by SQL signature check');
    $assert($pc()===3 && $wc()===3,'no unsigned new entries');
} else throw new RuntimeException('Expected seed|after-snapshot|after-restore');
echo "F135 {$phase}: {$n} PASS / 0 FAIL — vulnerabilities reproduced, NO-GO\n";
