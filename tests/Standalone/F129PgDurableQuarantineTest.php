<?php
declare(strict_types=1);

/**
 * F129: PostgreSQL 16 CI-only tests. Does not connect to the product or Ads.
 * Requires separate disposable perf_f129_ci DB, schema and two restricted roles.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F129_LAB') !== '1'
    || getenv('PGDATABASE') !== 'perf_f129_ci') {
    throw new RuntimeException('F129 may run only with the explicit disposable CI gate');
}

function connection(string $role): PDO
{
    $host = getenv('PGHOST') ?: '127.0.0.1';
    $port = getenv('PGPORT') ?: '5432';
    $database = getenv('PGDATABASE') ?: '';
    $login = $role === 'writer' ? 'perf_f129_writer'
        : ($role === 'simulator' ? 'perf_f129_failure_simulator' : 'postgres');
    $password = $role === 'writer' ? getenv('PERF_F129_WRITER_PASSWORD')
        : ($role === 'simulator' ? getenv('PERF_F129_SIM_PASSWORD') : getenv('PGPASSWORD'));
    return new PDO("pgsql:host={$host};port={$port};dbname={$database}", $login, $password ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 4,
    ]);
}
$admin = connection('admin');
$writer = connection('writer');
$sim = connection('simulator');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException("F129 FAILURE: {$message}");
    }
};
$record = static function(PDO $db,string $id,string $tenant,string $fingerprint,int $epoch): bool {
    $q=$db->prepare('SELECT CASE WHEN perf_f129_lab.record_intent(?,?,?,?) THEN 1 ELSE 0 END');
    $q->execute([$id,$tenant,$fingerprint,$epoch]);
    return (int)$q->fetchColumn() === 1;
};
$uncertain = static function(PDO $db,string $id,string $fingerprint,string $reason): bool {
    $q=$db->prepare('SELECT CASE WHEN perf_f129_lab.mark_uncertain(?,?,?) THEN 1 ELSE 0 END');
    $q->execute([$id,$fingerprint,$reason]);
    return (int)$q->fetchColumn() === 1;
};
$snapshot = static function(PDO $db): array {
    return json_decode((string)$db->query('SELECT perf_f129_lab.snapshot()')->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
};
$denied = static function(callable $call): bool {
    try { $call(); return false; }
    catch(PDOException $error) { return $error->getCode() === '42501'; }
};
$fp1=hash('sha256','f129-one'); $fp2=hash('sha256','f129-two'); $fp3=hash('sha256','f129-three');
$check($snapshot($writer)['quarantined'] === false,'initial circuit open only to journal registration');
$check($snapshot($writer)['action_authorized'] === false,'not authorization for external action');
$check($record($writer,'operation_0001','tenant_demo_A',$fp1,8),'first intent persists');
$check(!$record($writer,'operation_0001','tenant_demo_A',$fp1,8),'identical replay denied');
$check(!$record($writer,'operation_0001','tenant_demo_B',$fp2,8),'conflicting operation ID denied');
$check(!$record($writer,'operation_0002','tenant_demo_B',$fp1,8),'fingerprint cannot replay across tenants');
$check(!$record($writer,'short','tenant_demo_A',$fp2,8),'short ID denied');
$check(!$record($writer,'operation_0002','tenant_demo_A','bad-fingerprint',8),'invalid digest denied');
$check(!$record($writer,'operation_0002','tenant_demo_A',$fp2,0),'invalid epoch denied');
$check($record($writer,'operation_0002','tenant_demo_B',$fp2,8),'second independent intent recorded');
$check($snapshot($writer)['receipts'] === 2,'only unique receipts persisted');
$check($denied(static fn()=>$writer->query('SELECT * FROM perf_f129_lab.receipts')),
       'runtime writer cannot read raw receipts');
$check($denied(static fn()=>$writer->exec('DELETE FROM perf_f129_lab.receipts')),
       'runtime writer cannot delete receipts');
$check($denied(static fn()=>$writer->query(
       "SELECT perf_f129_lab.mark_uncertain('operation_0001','{$fp1}','timeout')")),
       'writer cannot call incident function');
$check($denied(static fn()=>$sim->query(
       "SELECT perf_f129_lab.record_intent('operation_0003','tenant_demo_B','{$fp3}',8)")),
       'incident role cannot register an intent');

$check((int)$admin->query("SELECT count(*) FROM perf_f129_lab.receipts")->fetchColumn() === 2,
       'DB receipts survive an independent admin connection');
$restarted = connection('writer');
$check(!$record($restarted,'operation_0001','tenant_demo_A',$fp1,8),
       'restarted connection cannot replay accepted operation');

// Race: the PostgreSQL singleton row lock fences a competing registration.
$admin->beginTransaction();
$admin->query('SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1 FOR UPDATE')->fetchColumn();
$writer->exec("SET statement_timeout='350ms'");
$lockedOut=false;
try { $record($writer,'operation_0003','tenant_demo_A',$fp3,8); }
catch (PDOException $error) { $lockedOut = $error->getCode() === '57014'; }
$check($lockedOut,'writer is blocked by circuit lock rather than racing past it');
$admin->exec('UPDATE perf_f129_lab.circuit SET quarantined=true, changed_at=clock_timestamp() WHERE singleton=1');
$admin->commit();
$writer->exec('SET statement_timeout=0');
$check(!$record($writer,'operation_0003','tenant_demo_A',$fp3,8),
       'writer denied after gate flips while it waited');
$check((int)$admin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn() === 2,
       'no third operation sneaked through during quarantine');
$check($snapshot($restarted)['quarantined'] === true,'quarantine visible on second session');

// Fail-closed on uncertain result, including unknown receipt and repeated alert.
$check(!$uncertain($sim,'operation_0001',$fp1,'timeout'),
       'witness timeout never returns authorization');
$check($snapshot($writer)['uncertain_events'] === 1,'timeout persisted in separate journal');
$check(!$uncertain($sim,'operation_0001',$fp1,'contradiction'),
       'late conflicting result never replaces first evidence');
$check($snapshot($writer)['uncertain_events'] === 1,'first incident remains immutable');
$check(!$uncertain($sim,'operation_9999',$fp3,'unknown_receipt'),
       'unknown receipt cannot authorize');
$check($snapshot($writer)['uncertain_events'] === 2,'unknown receipt logged conservatively');
$check(!$record(connection('writer'),'operation_0004','tenant_demo_A',
       hash('sha256','f129-four'),9),'quarantine persists across another reconnect');
$check($snapshot($writer)['action_authorized'] === false,
       'snapshot always hardcodes external action as forbidden');
$check($denied(static fn()=>$sim->exec(
       "UPDATE perf_f129_lab.circuit SET quarantined=false WHERE singleton=1")),
       'incident role cannot reopen the circuit');
$check($denied(static fn()=>$writer->exec(
       "UPDATE perf_f129_lab.circuit SET quarantined=false WHERE singleton=1")),
       'writer role cannot reopen the circuit');
$check((int)$admin->query("SELECT count(*) FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
       WHERE n.nspname='perf_f129_lab' AND p.proname LIKE '%recover%'")->fetchColumn() === 0,
       'no recovery function exists in the lab schema');
$check($snapshot($writer)['quarantined'] === true,'final state permanently quarantined');
echo "F129 real PostgreSQL lab: {$checks} PASS / 0 FAIL\n";
