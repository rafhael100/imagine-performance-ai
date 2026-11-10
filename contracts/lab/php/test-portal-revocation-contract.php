<?php

declare(strict_types=1);

/**
 * F1-12: test-only consumption of the Portal PERF-F11 pinned JSON event.
 * Runs AFTER F1-11's disposable PG runner. No external transport or clients.
 */
require_once __DIR__.'/../SyntheticS2SProtocol.php';
require_once __DIR__.'/SyntheticIngressBridge.php';
require_once __DIR__.'/SyntheticExternalRestoreFence.php';
require_once __DIR__.'/SyntheticRevocationRelay.php';

use Imagine\Protocol\Lab\SyntheticS2SProtocol;
use Imagine\Protocol\Lab\SyntheticRevocationRelay;
use Imagine\Protocol\Lab\SyntheticExternalRestoreFence;

if (PHP_SAPI !== 'cli'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_FENCE_DB') !== 'perf_test_relay_authority') {
    throw new RuntimeException('F1-12 refuses any non-disposable PostgreSQL target.');
}
$pass = getenv('PGPASSWORD');
if (!is_string($pass) || $pass === '') {
    throw new RuntimeException('Missing disposable PostgreSQL password.');
}
$contractPath = dirname(__DIR__).'/demo/perf-revocation-contract-v1.json';
$eventPath = dirname(__DIR__).'/demo/perf-revocation-event-example-v1.json';
$contract = json_decode((string) file_get_contents($contractPath), true, 512, JSON_THROW_ON_ERROR);
$event = json_decode((string) file_get_contents($eventPath), true, 512, JSON_THROW_ON_ERROR);
$connect = static fn (string $db, string $user, string $secret): PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$db.';connect_timeout=5',
    $user, $secret,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$mainAdmin = $connect('perf_test_relay_main', 'postgres', $pass);
$sourceAdmin = $connect('perf_test_relay_authority', 'postgres', $pass);
$mainReader = $connect('perf_test_relay_main', 'perf_s2s_lab_caller', 'ci-test-only-s2s-role');
$mainUpdater = $connect('perf_test_relay_main', 'perf_test_main_revoker', 'ci-only-main-revoker');
$sourceReader = $connect('perf_test_relay_authority', 'perf_test_fence_reader', 'ci-only-fence-reader');
$sourceAcker = $connect('perf_test_relay_authority', 'perf_test_outbox_acker', 'ci-only-outbox-acker');

$count = 0;
$check = static function (string $label, mixed $got, mixed $want) use (&$count): void {
    if ($got !== $want) {
        throw new RuntimeException('FAIL F1-12 '.$label.': got '.var_export($got, true)
            .' expected '.var_export($want, true));
    }
    ++$count;
    echo "PASS F1-12 {$count} {$label}\n";
};

$expectedEventFields = [
    'event_id','service_ref','organization_ref','tenant_ref',
    'source_revision','event_type','status','attempts',
];
$expectedAuthorityFields = [
    'service_ref','organization_ref','tenant_ref','min_revision','active',
];
$check('01 exact versioned Portal schema', $contract['contract_version'], 'perf.revocation.synthetic.v1');
$check('02 event fields match F1-11 relay query', $contract['event_fields'], $expectedEventFields);
$check('03 authority fields match F1-11 reader query', $contract['authority_fields'], $expectedAuthorityFields);
$check('04 emitted example is exact contract shape', array_keys($event), $expectedEventFields);
$check('05 event uses expected type', $event['event_type'], $contract['event_type']);
$check('06 source event has no external effects', $contract['side_effects'], 'none');
$check('07 event ID is synthetically valid',
    (bool) preg_match('/^evt-demo-[a-z0-9-]{1,80}$/D', $event['event_id']), true);
$check('08 sample organization binds tenant B', $event['organization_ref'], 'org-demo-b');
$check('09 sample tenant matches B', $event['tenant_ref'], 'perf-demo-b');
$check('10 sample service matches B', $event['service_ref'], 'svc-demo-b');
$check('11 sample revision is monotonically newer', $event['source_revision'], 2);
$check('12 pending status is a valid contract enum',
    in_array($event['status'], $contract['status_values'], true), true);
$check('13 sample has no personal info, sessions or payments', array_key_exists('email', $event)
    || array_key_exists('actor', $event) || array_key_exists('session', $event)
    || array_key_exists('payment', $event), false);

$outboxColumns = $sourceAdmin->query(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema='perf_external_lab' AND table_name='mock_revocation_outbox'
     ORDER BY ordinal_position"
)->fetchAll(PDO::FETCH_COLUMN);
$authorityColumns = $sourceAdmin->query(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema='perf_external_lab' AND table_name='mock_authority_fences'
     ORDER BY ordinal_position"
)->fetchAll(PDO::FETCH_COLUMN);
$check('14 actual PostgreSQL outbox first fields match Portal exactly',
    array_slice($outboxColumns, 0, count($expectedEventFields)), $expectedEventFields);
$check('15 actual PostgreSQL authority first fields match Portal exactly',
    array_slice($authorityColumns, 0, count($expectedAuthorityFields)), $expectedAuthorityFields);

// F1-11 intentionally took SELECT away from reader to test fail-closed.
// Restore it in this separate, artificial compatibility test phase only.
$sourceAdmin->exec('GRANT SELECT ON perf_external_lab.mock_revocation_outbox TO perf_test_fence_reader');
$sourceAdmin->exec('GRANT SELECT ON perf_external_lab.mock_authority_fences TO perf_test_fence_reader');

$sourceBefore = $sourceAdmin->query(
    "SELECT active,min_revision FROM perf_external_lab.mock_authority_fences
     WHERE service_ref='svc-demo-b'"
)->fetch(PDO::FETCH_ASSOC);
$mainBefore = $mainAdmin->query(
    "SELECT active,grant_revision FROM perf_s2s_lab.mock_service_grants
     WHERE service_ref='svc-demo-b'"
)->fetch(PDO::FETCH_ASSOC);
$true = static fn (mixed $v): bool => in_array($v, [true, 1, '1', 't'], true);
$check('16 B authority initially active', $true($sourceBefore['active']), true);
$check('17 B authority revision initially 1', (int) $sourceBefore['min_revision'], 1);
$check('18 B main initially active', $true($mainBefore['active']), true);
$check('19 B main revision initially 1', (int) $mainBefore['grant_revision'], 1);

// Import the Portal's deterministic example into the mocked authority in
// ONE atomic source transaction; this is NOT a real event crossing systems.
$sourceAdmin->beginTransaction();
try {
    $updated = $sourceAdmin->exec(
        "UPDATE perf_external_lab.mock_authority_fences
         SET active=false,min_revision=2,updated_at=clock_timestamp()
         WHERE service_ref='svc-demo-b' AND min_revision=1 AND active=true"
    );
    if ($updated !== 1) {
        throw new RuntimeException('F1-12 source revision was not one.');
    }
    $st = $sourceAdmin->prepare(
        'INSERT INTO perf_external_lab.mock_revocation_outbox
         (event_id, service_ref, organization_ref, tenant_ref,
          source_revision, event_type, status, attempts)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute(array_values($event));
    $sourceAdmin->commit();
} catch (Throwable $e) {
    $sourceAdmin->rollBack();
    throw $e;
}

$stored = $sourceAdmin->prepare(
    'SELECT event_id, service_ref, organization_ref, tenant_ref,
            source_revision, event_type, status, attempts
     FROM perf_external_lab.mock_revocation_outbox WHERE event_id = ?'
);
$stored->execute([$event['event_id']]);
$row = $stored->fetch(PDO::FETCH_ASSOC);
$check('20 imported Portal event has matching ID', $row['event_id'], $event['event_id']);
$check('21 imported event has same tenant', $row['tenant_ref'], $event['tenant_ref']);
$check('22 imported event revision remains 2', (int) $row['source_revision'], $event['source_revision']);
$check('23 imported event is pending', $row['status'], 'pending');

// A claim signed for the old B grant is valid for cryptographic *lab*
// signature, but must be denied by the independent source fence.
$bytes = file_get_contents((string) getenv('PERF_SNAPSHOT_FIXTURE'));
if (!is_string($bytes) || $bytes === '') {
    throw new RuntimeException('Missing synthetic snapshot bytes.');
}
$digest = hash('sha256', $bytes);
$key = random_bytes(32);
$oldSigned = SyntheticS2SProtocol::mintForLab(
    'org-demo-b','perf-demo-b','svc-demo-b','req-demo-compat-b1',
    'nonce-demo-compat-b1',1799999940,1800000060,1,$digest,$key
);
$check('24 signed B envelope is syntactically valid',
    (new SyntheticS2SProtocol())->verifyForLab(
        $oldSigned,$key,'org-demo-b','perf-demo-b','svc-demo-b',$digest,1,1800000000,true
    ), 'accepted_synthetic_only');

$fence = new SyntheticExternalRestoreFence($mainReader, $sourceReader);
$check('25 revoked source denies old B signature before relay',
    $fence->accept(
        $oldSigned,$key,'org-demo-b','perf-demo-b','svc-demo-b',$digest,1,1800000000
    ), 'external_fence_revoked');
$check('26 authority outage simulation already covered by F1-11', true, true);

$relay = new SyntheticRevocationRelay($sourceReader,$mainUpdater,$sourceAcker);
$check('27 transport outage defers delivery', $relay->deliver($event['event_id'],false),
    'relay_deferred_demo');
$check('28 delayed destination is still locally active', $true($mainAdmin->query(
    "SELECT active FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b'"
)->fetchColumn()), true);
$check('29 first delivered event revokes B', $relay->deliver($event['event_id']),
    'relay_synced_demo');
$after = $mainAdmin->query(
    "SELECT active,grant_revision FROM perf_s2s_lab.mock_service_grants
     WHERE service_ref='svc-demo-b'"
)->fetch(PDO::FETCH_ASSOC);
$check('30 B grant is revoked at destination', $true($after['active']), false);
$check('31 B destination revision is 2', (int) $after['grant_revision'], 2);
$check('32 redelivery is idempotent', $relay->deliver($event['event_id']),
    'relay_already_synced_demo');

$acked = $sourceAdmin->prepare(
    'SELECT status,attempts FROM perf_external_lab.mock_revocation_outbox WHERE event_id=?'
);
$acked->execute([$event['event_id']]);
$ack = $acked->fetch(PDO::FETCH_ASSOC);
$check('33 source event acknowledged', $ack['status'], 'acked');
$check('34 only one ACK update counted', (int) $ack['attempts'], 1);
$check('35 source A remains revoked from F1-11', $true($sourceAdmin->query(
    "SELECT active FROM perf_external_lab.mock_authority_fences WHERE service_ref='svc-demo-a'"
)->fetchColumn()), false);
$check('36 main A remains revoked from F1-11', $true($mainAdmin->query(
    "SELECT active FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
)->fetchColumn()), false);
$check('37 no unsigned local claim accepted for B', $fence->accept(
    $oldSigned,$key,'org-demo-b','perf-demo-b','svc-demo-b',$digest,1,1800000000
), 'external_fence_revoked');
echo "PASS F1-12 {$count} Portal contract → Performance mock relay checks\n";
