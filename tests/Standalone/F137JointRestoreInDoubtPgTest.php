<?php
declare(strict_types=1);

/**
 * F137: expected-negative cross-database failure/recovery lab.
 * Only two disposable PostgreSQL 16 services in CI. No live dispatch.
 */
require dirname(__DIR__, 2).'/src/Lab/F136OnlineWitnessRedemption.php';
use Imagine\Performance\Lab\F136OnlineWitnessRedemption;

if (getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F137_LAB') !== '1'
    || getenv('PERF_F136_LAB') !== '1'
    || getenv('PGDATABASE') !== 'perf_f132_ci'
    || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
    throw new RuntimeException('F137 is restricted to disposable CI databases');
}

function f137db(int $port, string $database, string $role, string $secret): PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$database}",$role,$secret,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>4,
    ]);
}

$pAdmin = f137db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wAdmin = f137db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$primary = f137db(5432,'perf_f132_ci','perf_f136_primary_bridge',(string)getenv('PERF_F136_PRIMARY_PASSWORD'));
$witness = f137db(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$sut = new F136OnlineWitnessRedemption($primary, $witness);

$fp = static fn(string $s):string => hash('sha256', 'F137-disposable-'.$s);
$pc = static fn():int => (int)$pAdmin->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc = static fn():int => (int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc = static fn():int => (int)$wAdmin->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$pe = static fn():int => (int)$pAdmin->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we = static fn():int => (int)$wAdmin->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$checks = 0;
$check = static function(bool $condition,string $label) use (&$checks):void {
    ++$checks;
    if (!$condition) throw new RuntimeException('F137 FAIL: '.$label);
};
$mint = static function(string $id,string $tenant,string $digest) use($witness):array {
    $q=$witness->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,8)');
    $q->execute([$id,$tenant,$digest]);
    $permit=$q->fetch(PDO::FETCH_ASSOC);
    if (!is_array($permit)) throw new RuntimeException('F137: unexpected failed mint');
    return $permit;
};
$write = static function(PDO $conn,string $id,string $tenant,string $digest,array $permit):bool {
    $q=$conn->prepare(
        'SELECT CASE WHEN perf_f136_primary.redeem_and_record(?,?,?,?,?,?) THEN 1 ELSE 0 END'
    );
    $q->execute([$id,$tenant,$digest,8,(int)$permit['issued_at'],(string)$permit['signature']]);
    return (int)$q->fetchColumn()===1;
};
$phase = $argv[1] ?? '';
if ($phase==='seed') {
    $check($pe()===8 && $we()===8,'both generation floors start at 8');
    $check($pc()===0 && $wc()===0,'both ledgers empty');
    $a=$sut->register('operation_9701','tenant_demo_A',$fp('A'));
    $check($a['status']==='registered_lab','A registered through F136');
    $check(!$a['action_authorized'],'no real-world dispatch permission');
    $check($pc()===1 && $wc()===1 && $rc()===1,'A persisted and redeemed in both');
} elseif ($phase==='after-snapshot') {
    $check($pc()===1 && $wc()===1 && $rc()===1,'snapshot baseline A');
    $b=$sut->register('operation_9702','tenant_demo_B',$fp('B'));
    $check($b['status']==='registered_lab','B registered after snapshot');
    $check($pc()===2 && $wc()===2 && $rc()===2,'B has a durable remote redemption');

    // Inject pre-commit local abort AFTER FDW remote redemption and primary
    // journal insert. This tests local PostgreSQL rollback of the remote FDW
    // transaction, not an actual distributed crash/2PC guarantee.
    $c=$mint('operation_9703','tenant_demo_A',$fp('C'));
    $check($wc()===3 && $rc()===2,'C issued but not redeemed');
    $primary->beginTransaction();
    $check($write($primary,'operation_9703','tenant_demo_A',$fp('C'),$c),
       'C provisionally redeemed inside open local transaction');
    $primary->rollBack();
    $check($pc()===2 && $wc()===3 && $rc()===2,
       'local rollback also undoes remote redemption in normal FDW session');
    $check(!$pAdmin->query(
       "SELECT EXISTS (SELECT 1 FROM perf_f129_lab.receipts WHERE operation_id='operation_9703')"
    )->fetchColumn(),'C not present after abort');

    // Inject post-commit response loss: the client discards the success
    // return, reconnects and inspects both authorities rather than replaying.
    $d=$mint('operation_9704','tenant_demo_B',$fp('D'));
    $check($wc()===4 && $rc()===2,'D issued before simulated unknown ack');
    $primary->beginTransaction();
    $check($write($primary,'operation_9704','tenant_demo_B',$fp('D'),$d),
       'D provisionally accepted in open transaction');
    $primary->commit(); // Simulate losing the response AFTER committing.
    $check($pc()===3 && $wc()===4 && $rc()===3,
       'D transaction completed although ack was treated as unknown');
    $fresh = f137db(5432,'perf_f132_ci','perf_f136_primary_bridge',
       (string)getenv('PERF_F136_PRIMARY_PASSWORD'));
    $check(!$write($fresh,'operation_9704','tenant_demo_B',$fp('D'),$d),
       'replay denied on fresh connection after unknown acknowledgement');
    $check($pc()===3 && $wc()===4 && $rc()===3,
       'no duplicate from observed post-commit retry');

    // The C permit remains unredeemed. Do not automatically retry an
    // ambiguous operation: reconciliation and human review are missing.
    $check((int)$wAdmin->query(
       "SELECT count(*) FROM perf_f136_witness.issued WHERE operation_id='operation_9703' AND NOT redeemed"
    )->fetchColumn()===1,'C remains issued but not consumed; no auto-recovery');
} elseif ($phase==='after-double-restore') {
    $check($pe()===8 && $we()===8,'both independent-looking generation floors rolled back');
    $check($pc()===1 && $wc()===1 && $rc()===1,
       'both DBs lost operation B, C and D history');
    $b=$sut->register('operation_9702','tenant_demo_B',$fp('B'));
    $check($b['status']==='registered_lab',
       'EXPECTED SECURITY GAP: B accepted again after JOINT restore');
    $check($b['registered']===true && $b['action_authorized']===false,
       'replay means lab journal entry, NOT ad authorization');
    $check($pc()===2 && $wc()===2 && $rc()===2,
       'B replay recreated receipts at both restored authorities');
    $check($sut->register('operation_9702','tenant_demo_B',$fp('B'))['status']==='witness_denied',
       'subsequent replay denied only because witness now has new B');
    $check($pc()===2 && $wc()===2 && $rc()===2,'no additional duplicate after first replay');
} else {
    throw new RuntimeException('Expected seed|after-snapshot|after-double-restore');
}
echo "F137 {$phase}: {$checks} PASS / 0 FAIL — expected-negative / NO-GO\n";
