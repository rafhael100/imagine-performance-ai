<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/src/Lab/F131WitnessFirstRegistration.php';

use Imagine\Performance\Lab\F131WitnessFirstRegistration;

if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F131_LAB') !== '1'
    || getenv('PGDATABASE') !== 'perf_f131_ci'
    || getenv('WITNESS_DB') !== 'perf_f131_witness_ci') {
    throw new RuntimeException('F131 named CI databases required');
}

function db131(string $database, string $login, string $password): PDO
{
    $port = $database === 'perf_f131_ci' ? 5432 : 5433;
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$database}", $login, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 4,
    ]);
}
$primary = db131('perf_f131_ci','perf_f131_primary_bridge', (string)getenv('PERF_F131_PRIMARY_PASSWORD'));
$witness = db131('perf_f131_witness_ci','perf_f131_witness_bridge', (string)getenv('PERF_F131_WITNESS_PASSWORD'));
$oldPrimary = db131('perf_f131_ci','perf_f129_writer',(string)getenv('PERF_F129_WRITER_PASSWORD'));
$oldWitness = db131('perf_f131_witness_ci','perf_f130_writer',(string)getenv('PERF_F130_WRITER_PASSWORD'));
$pAdmin = db131('perf_f131_ci','postgres',(string)getenv('PGPASSWORD'));
$wAdmin = db131('perf_f131_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$bridge = new F131WitnessFirstRegistration($primary,$witness);
$checks = 0;
$check = static function(bool $ok,string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException("FAIL F131: {$label}"); }
};
$denied = static function (callable $fn): bool {
    try { $fn(); return false; } catch(PDOException $error) { return $error->getCode() === '42501'; }
};
$fpA = hash('sha256','F131 operation A');
$fpB = hash('sha256','F131 operation B');
$fpC = hash('sha256','F131 operation C');
$fpD = hash('sha256','F131 operation D');
$fpE = hash('sha256','F131 operation E');
$fpF = hash('sha256','F131 operation F after restore');
$countPrimary = static fn():int => (int)$pAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$countWitness = static fn():int => (int)$wAdmin->query('SELECT count(*) FROM perf_f130_witness.seen_fingerprints')->fetchColumn();

$phase = $argv[1] ?? '';
if ($phase === 'seed') {
    $check($countPrimary() === 0, 'primary starts empty');
    $check($countWitness() === 0, 'witness starts empty');
    $check($bridge->register('short','tenant_demo_A',$fpA,8)['status']==='invalid_inputs', 'reject short ID before either DB');
    $check($bridge->register('operation_6001','tenant_demo_A','invalid',8)['status']==='invalid_inputs', 'reject malformed digest');
    $check($bridge->register('operation_6001','tenant_demo_A',$fpA,0)['status']==='invalid_inputs', 'reject invalid epoch');
    $check($countWitness() === 0, 'invalid entries never burn witness');
    $check($denied(static fn()=>$oldPrimary->query(
        "SELECT perf_f129_lab.record_intent('operation_6001','tenant_demo_A','{$fpA}',8)")), 'legacy primary caller revoked');
    $check($denied(static fn()=>$oldWitness->query(
        "SELECT perf_f130_witness.record_fingerprint('{$fpA}')")), 'legacy witness caller revoked');
    $check($denied(static fn()=>$primary->query('SELECT * FROM perf_f129_lab.receipts')), 'bridge has no SELECT on receipt table');
    $check($denied(static fn()=>$witness->query('SELECT * FROM perf_f130_witness.seen_fingerprints')), 'witness bridge cannot read raw receipt table');
    $first=$bridge->register('operation_6001','tenant_demo_A',$fpA,8);
    $check($first['status']==='registered_lab' && $first['registered']===true, 'first A registered in two DBs');
    $check($first['action_authorized']===false, 'registration does not authorize external action');
    $check($countPrimary() === 1 && $countWitness() === 1, 'A present in both DBs');
    $check($bridge->register('operation_6001','tenant_demo_A',$fpA,8)['status']==='witness_denied', 'replay denied by witness');
    $check($bridge->register('operation_6009','tenant_demo_B',$fpA,8)['status']==='witness_denied', 'cross-tenant fingerprint reused denied');
    $check($countPrimary()===1, 'retries have not altered primary');
} elseif ($phase === 'after-backup') {
    $check($countPrimary()===1 && $countWitness()===1, 'pre-incident backup baseline A');
    $b=$bridge->register('operation_6002','tenant_demo_B',$fpB,8);
    $check($b['status']==='registered_lab', 'B registered after backup');
    $check($countPrimary()===2 && $countWitness()===2,'B in both DBs');

    $wAdmin->exec('REVOKE EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text) FROM perf_f131_witness_bridge');
    $blocked=$bridge->register('operation_6003','tenant_demo_B',$fpC,8);
    $check($blocked['status']==='witness_unknown', 'witness grant failure fails closed');
    $check($blocked['action_authorized']===false && !$blocked['registered'], 'witness outage cannot register action');
    $check($countPrimary()===2 && $countWitness()===2,'witness unavailable means no DB writes');
    $wAdmin->exec('GRANT EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text) TO perf_f131_witness_bridge');
    $check($bridge->register('operation_6003','tenant_demo_B',$fpC,8)['status']==='registered_lab',
        'witness role restored allows an unused token');
    $check($countPrimary()===3 && $countWitness()===3, 'C written after witness permission restored');

    $pAdmin->exec('REVOKE EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint) FROM perf_f131_primary_bridge');
    $failed=$bridge->register('operation_6004','tenant_demo_B',$fpD,8);
    $check($failed['status']==='primary_unknown_witness_spent','primary failure after witness is ambiguous');
    $check(!$failed['registered'] && !$failed['action_authorized'], 'ambiguous primary failure grants nothing');
    $check($countPrimary()===3 && $countWitness()===4, 'D nonce burned in witness only');
    $pAdmin->exec('GRANT EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint) TO perf_f131_primary_bridge');
    $check($bridge->register('operation_6004','tenant_demo_B',$fpD,8)['status']==='witness_denied',
        'burned D cannot be retried after primary permission repaired');
    $check($countPrimary()===3, 'primary stays unchanged on burned D replay');

    $q=$pAdmin->query("SELECT perf_f129_lab.mark_uncertain('operation_6002','{$fpB}','timeout')")->fetchColumn();
    $check($q === false || $q === 'f' || $q === '0', 'timeout incident never grants action');
    $check(in_array($pAdmin->query('SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1')->fetchColumn(), [true, 't', '1', 1], true),
        'persistent quarantine after timeout');
    $check($bridge->register('operation_6005','tenant_demo_B',$fpE,8)['status']==='primary_denied_witness_spent',
        'primary quarantine denies even after witness reservation');
    $check($countPrimary()===3 && $countWitness()===5, 'E witness spent but no primary insert');
} elseif ($phase === 'after-restore') {
    $check($countPrimary()===1, 'real old backup erased B and C primary entries');
    $check($countWitness()===5, 'witness receipts survive primary restore');
    $check(in_array($pAdmin->query('SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1')->fetchColumn(), [false, 'f', '0', 0], true),
        'expected negative: old backup lost primary quarantine');
    $fresh=new F131WitnessFirstRegistration(
        db131('perf_f131_ci','perf_f131_primary_bridge',(string)getenv('PERF_F131_PRIMARY_PASSWORD')),
        db131('perf_f131_witness_ci','perf_f131_witness_bridge',(string)getenv('PERF_F131_WITNESS_PASSWORD')));
    foreach ([['operation_6002',$fpB],['operation_6003',$fpC],
              ['operation_6004',$fpD],['operation_6005',$fpE]] as [$id,$fp]) {
        $result=$fresh->register($id,'tenant_demo_B',$fp,8);
        $check($result['status']==='witness_denied', 'independent witness denies known receipt after primary rollback');
        $check(!$result['registered'] && !$result['action_authorized'], 'no registration and never Ads authorization');
    }
    $check($countPrimary()===1 && $countWitness()===5, 'no duplicate receipts committed');
    $check($denied(static fn()=>$oldPrimary->query(
        "SELECT perf_f129_lab.record_intent('operation_6002','tenant_demo_B','{$fpB}',8)")),
        'old direct writer remains revoked after data-only restore');
    // A new nonce is still accepted after restore. This is an acknowledged failure
    // of restore detection / protected epoch; do NOT interpret as a safe release gate.
    $new=$fresh->register('operation_6006','tenant_demo_B',$fpF,8);
    $check($new['status']==='registered_lab', 'EXPECTED GAP: new token can pass after unrecognized rollback');
    $check(!$new['action_authorized'], 'new token still never authorizes Ads or billing');
    $check($countPrimary()===2 && $countWitness()===6, 'lab witness-first registration after restore recorded');
} else {
    throw new RuntimeException('Phase must be seed, after-backup or after-restore');
}
echo "F131 {$phase}: {$checks} PASS / 0 FAIL. LAB-ONLY / NO-GO\n";
