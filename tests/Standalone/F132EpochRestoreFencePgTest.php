<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/src/Lab/F132EpochFencedRegistration.php';
use Imagine\Performance\Lab\F132EpochFencedRegistration;

if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F132_LAB') !== '1'
    || getenv('PGDATABASE') !== 'perf_f132_ci'
    || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
    throw new RuntimeException('CI-only F132 gate');
}
function f132Db(int $port, string $db, string $login, string $password): PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$login,$password,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 4,
    ]);
}
$adminP=f132Db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$adminW=f132Db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$primary=f132Db(5432,'perf_f132_ci','perf_f132_primary_bridge',(string)getenv('PERF_F132_PRIMARY_PASSWORD'));
$witness=f132Db(5433,'perf_f132_witness_ci','perf_f132_witness_bridge',(string)getenv('PERF_F132_WITNESS_PASSWORD'));
$oldPrimary=f132Db(5432,'perf_f132_ci','perf_f131_primary_bridge',(string)getenv('PERF_F131_PRIMARY_PASSWORD'));
$oldWitness=f132Db(5433,'perf_f132_witness_ci','perf_f131_witness_bridge',(string)getenv('PERF_F131_WITNESS_PASSWORD'));
$sut = new F132EpochFencedRegistration($primary, $witness);
$checks=0;
$check=static function(bool $ok,string $reason) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException('F132 FAIL: '.$reason);
};
$denied=static function(callable $fn): bool {
    try { $fn(); return false; }
    catch(PDOException $e) { return $e->getCode()==='42501'; }
};
$fp = static fn(string $s):string=>hash('sha256','F132-TEST-'.$s);
$pCount=static fn():int=>(int)$adminP->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wCount=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f130_witness.seen_fingerprints')->fetchColumn();
$pEpoch=static fn():int=>(int)$adminP->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$wEpoch=static fn():int=>(int)$adminW->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$phase=$argv[1] ?? '';
if ($phase==='seed') {
    $check($pEpoch()===8 && $wEpoch()===8,'initial primary and witness generations 8');
    $check($pCount()===0 && $wCount()===0, 'no initial receipts');
    $check($denied(static fn()=>$oldPrimary->query(
       "SELECT perf_f129_lab.record_intent('operation_7001','tenant_demo_A','".$fp('A')."',8)")),
       'F131 direct primary caller revoked');
    $check($denied(static fn()=>$oldWitness->query(
       "SELECT perf_f130_witness.record_fingerprint('".$fp('A')."')")),
       'F131 direct witness caller revoked');
    $check($denied(static fn()=>$primary->query('SELECT * FROM perf_f132_primary.local_generation')),
       'primary bridge no table access');
    $check($denied(static fn()=>$witness->query('SELECT * FROM perf_f132_witness.epoch_floor')),
       'witness bridge no floor table access');
    $check($denied(static fn()=>$witness->query('SELECT perf_f132_witness.advance_floor(9)')),
       'only admin can advance floor');
    $check($sut->register('bad','tenant_demo_A',$fp('A'))['status']==='invalid_inputs',
       'invalid operation fails before both DBs');
    $accepted=$sut->register('operation_7001','tenant_demo_A',$fp('A'));
    $check($accepted['status']==='registered_lab' && $accepted['registered'],
       'new operation registered on generation 8');
    $check($accepted['action_authorized']===false, 'never authorizes Ads');
    $check($pCount()===1 && $wCount()===1, 'receipt in both databases');
    $check($sut->register('operation_7001','tenant_demo_A',$fp('A'))['status']
       ==='witness_epoch_or_replay_denied','nonce duplicate denied in witness');
} elseif ($phase==='advance') {
    $check($pCount()===1 && $wCount()===1, 'old backup captured before phase');
    $check($adminW->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn() === true
       || $wEpoch()===9, 'external floor advanced to 9');
    $check($wEpoch()===9 && $pEpoch()===8, 'witness epoch moves first');
    $before=$sut->register('operation_7002','tenant_demo_B',$fp('B'));
    $check($before['status']==='witness_epoch_or_replay_denied','floor 9 rejects old primary epoch 8');
    $check($pCount()===1 && $wCount()===1, 'epoch mismatch does not burn nonce');
    $adminP->exec('UPDATE perf_f132_primary.local_generation SET generation=9,changed_at=clock_timestamp() WHERE singleton=1');
    $check($pEpoch()===9, 'local generation upgraded by CI administrator');
    $result=$sut->register('operation_7002','tenant_demo_B',$fp('B'));
    $check($result['status']==='registered_lab', 'new generation 9 accepts after both sides match');
    $check($pCount()===2 && $wCount()===2, 'B recorded exactly once');
    $check($adminW->query('SELECT perf_f132_witness.advance_floor(8)')->fetchColumn() === false
       || $wEpoch()===9, 'floor refuses decrement in function');
    $check($wEpoch()===9, 'external floor stayed at 9');
    $adminW->exec('REVOKE EXECUTE ON FUNCTION perf_f132_witness.reserve_if_current(text,bigint) FROM perf_f132_witness_bridge');
    $noWitness=$sut->register('operation_7003','tenant_demo_A',$fp('C'));
    $check($noWitness['status']==='witness_unavailable_or_unknown', 'witness permission outage fails closed');
    $check($pCount()===2 && $wCount()===2,'unavailable witness writes nothing');
    $adminW->exec('GRANT EXECUTE ON FUNCTION perf_f132_witness.reserve_if_current(text,bigint) TO perf_f132_witness_bridge');
    $adminP->exec('REVOKE EXECUTE ON FUNCTION perf_f132_primary.record_with_generation(text,text,text,bigint) FROM perf_f132_primary_bridge');
    $spent=$sut->register('operation_7004','tenant_demo_B',$fp('D'));
    $check($spent['status']==='primary_unknown_witness_spent','primary unavailable after witness burns nonce');
    $check($pCount()===2 && $wCount()===3, 'witness spent, primary unchanged');
    $adminP->exec('GRANT EXECUTE ON FUNCTION perf_f132_primary.record_with_generation(text,text,text,bigint) TO perf_f132_primary_bridge');
    $check($sut->register('operation_7004','tenant_demo_B',$fp('D'))['status']
       ==='witness_epoch_or_replay_denied', 'cannot retry spent D nonce');
    $check($pCount()===2,'no replay from ambiguous primary outcome');
} elseif ($phase==='restore') {
    $check($pEpoch()===8 && $wEpoch()===9, 'primary-only old restore detected by generation comparison');
    $check($pCount()===1 && $wCount()===3, 'old primary receipts lost, witness not restored');
    $fresh=new F132EpochFencedRegistration(
       f132Db(5432,'perf_f132_ci','perf_f132_primary_bridge',(string)getenv('PERF_F132_PRIMARY_PASSWORD')),
       f132Db(5433,'perf_f132_witness_ci','perf_f132_witness_bridge',(string)getenv('PERF_F132_WITNESS_PASSWORD')));
    $blocked=$fresh->register('operation_7005','tenant_demo_A',$fp('E'));
    $check($blocked['status']==='witness_epoch_or_replay_denied',
       'NEW fingerprint denied after old primary restore');
    $check(!$blocked['registered'] && !$blocked['action_authorized'], 'fail closed after restore');
    $check($pCount()===1 && $wCount()===3, 'no nonce spent during epoch mismatch');
    $check($fresh->register('operation_7002','tenant_demo_B',$fp('B'))['status']
       ==='witness_epoch_or_replay_denied','old nonce blocked too');
    $check($denied(static fn()=>$oldPrimary->query(
       "SELECT perf_f129_lab.record_intent('operation_7006','tenant_demo_A','".$fp('F')."',8)")),
       'F131 direct-primary legacy bypass remains revoked after data-only restore');
    $check($denied(static fn()=>$oldWitness->query(
       "SELECT perf_f130_witness.record_fingerprint('".$fp('F')."')")),
       'F131 direct-witness bypass remains revoked after data-only restore');
    $check($pCount()===1 && $wCount()===3,'all blocked cases leave state intact');
    // CI admin manual recovery is NOT an operational protocol or cryptographic proof.
    $adminP->exec('UPDATE perf_f132_primary.local_generation SET generation=9,changed_at=clock_timestamp() WHERE singleton=1');
    $check($pEpoch()===9, 'lab admin manually moves local epoch, not automatic recovery');
    $allowed=$fresh->register('operation_7005','tenant_demo_A',$fp('E'));
    $check($allowed['status']==='registered_lab' && !$allowed['action_authorized'],
       'lab admin intervention restores registration but does not authorize Ads');
    $check($pCount()===2 && $wCount()===4,'new intent present in both labs after manual epoch fix');
} else {
    throw new RuntimeException('Expected phase seed|advance|restore');
}
echo "F132 {$phase}: {$checks} PASS / 0 FAIL — CI-ONLY NO-GO\n";
