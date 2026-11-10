<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/src/Lab/F132EpochFencedRegistration.php';
use Imagine\Performance\Lab\F132EpochFencedRegistration;

if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F132_LAB') !== '1'
    || getenv('PERF_F133_LAB') !== '1'
    || getenv('PGDATABASE') !== 'perf_f132_ci'
    || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
    throw new RuntimeException('F133: disposable CI only');
}

function f133db(int $port,string $name,string $role,string $pwd): PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$name}",$role,$pwd,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 4,
    ]);
}
$primaryAdmin=f133db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$witnessAdmin=f133db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$primary=f133db(5432,'perf_f132_ci','perf_f132_primary_bridge',(string)getenv('PERF_F132_PRIMARY_PASSWORD'));
$witness=f133db(5433,'perf_f132_witness_ci','perf_f132_witness_bridge',(string)getenv('PERF_F132_WITNESS_PASSWORD'));
$sut=new F132EpochFencedRegistration($primary,$witness);
$fp=static fn(string $s):string => hash('sha256','F133-'.$s);
$pe=static fn():int=>(int)$primaryAdmin->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$witnessAdmin->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$pc=static fn():int=>(int)$primaryAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$witnessAdmin->query('SELECT count(*) FROM perf_f130_witness.seen_fingerprints')->fetchColumn();
$n=0;
$check=static function(bool $condition,string $desc) use (&$n):void {
  ++$n;
  if (!$condition) throw new RuntimeException('F133 failure: '.$desc);
};
$phase=$argv[1] ?? '';
if ($phase === 'advance') {
    $check($pe()===8 && $we()===8,'starting epoch 8 in both databases');
    $check($pc()===1 && $wc()===1,'one receipt in both databases before snapshot');
    $witnessAdmin->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $primaryAdmin->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
    $check($pe()===9 && $we()===9,'both domains upgraded to generation 9');
    $accepted=$sut->register('operation_8002','tenant_demo_B',$fp('B'));
    $check($accepted['status']==='registered_lab','B registered before destructive restore');
    $check($accepted['action_authorized']===false,'registration never allows external action');
    $check($pc()===2 && $wc()===2,'B persisted at both epoch-9 domains');
    $check($sut->register('operation_8002','tenant_demo_B',$fp('B'))['status']==='witness_epoch_or_replay_denied',
       'witness blocks duplicate before restore');
} elseif ($phase === 'after-double-restore') {
    $check($pe()===8 && $we()===8,'both independent-looking epochs rolled back to 8');
    $check($pc()===1 && $wc()===1,'B receipts erased from BOTH domains by snapshot');
    $reopened=$sut->register('operation_8002','tenant_demo_B',$fp('B'));
    $check($reopened['status']==='registered_lab', 'EXPECTED VULNERABILITY: B replay admitted by two restored databases');
    $check($reopened['registered'] && $reopened['action_authorized']===false,
       'replay is database registration, never Ads dispatch permission');
    $check($pc()===2 && $wc()===2, 'B replay creates one receipt in each restored domain');
    $c=$fp('C');
    $statement=$primary->prepare('SELECT CASE WHEN perf_f132_primary.record_with_generation(?,?,?,?) THEN 1 ELSE 0 END');
    $statement->execute(['operation_8003','tenant_demo_A',$c,8]);
    $check((int)$statement->fetchColumn()===1,
       'EXPECTED VULNERABILITY: direct primary SQL function bypasses external witness');
    $check($pc()===3 && $wc()===2, 'direct SQL produced no corresponding witness receipt');
    $check($sut->register('operation_8003','tenant_demo_A',$c)['status']
       ==='primary_denied_witness_spent','later bridge call cannot repair mismatched records safely');
    $check($pc()===3 && $wc()===3,'witness nonce burned by attempted repair without second primary record');
    $check($sut->register('operation_8003','tenant_demo_A',$c)['status']
       ==='witness_epoch_or_replay_denied','subsequent replay remains rejected');
} else { throw new RuntimeException('phase required: advance|after-double-restore'); }

echo "F133 {$phase}: {$n} PASS / 0 FAIL — EXPECTED-NEGATIVE SECURITY GAPS, NO-GO\n";
