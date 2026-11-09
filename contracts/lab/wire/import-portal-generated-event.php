<?php

declare(strict_types=1);

/**
 * F1-14 — import dynamic event actually produced by Portal F13 test into a
 * separate mocked authority DB for signed HTTP loopback integration.
 * This is a fixture-import boundary, not a real Portal→Performance connector.
 */
if (PHP_SAPI !== 'cli'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_FENCE_DB') !== 'perf_test_relay_authority'
    || getenv('PGPASSWORD') !== 'local-ci-only') {
    throw new RuntimeException('Refusing non-disposable F1-14 import environment.');
}
$filename = getenv('PERF_WIRE_EVENT_FIXTURE');
$root = realpath((string) getenv('RUNNER_TEMP'));
if (!is_string($filename) || !is_string($root)
    || realpath(dirname($filename)) !== $root
    || basename($filename) !== 'portal-perf-f13-event.json'
    || !is_file($filename) || is_link($filename)) {
    throw new RuntimeException('Refusing non-runner-temp Portal export.');
}
$raw = file_get_contents($filename);
if (!is_string($raw) || strlen($raw) > 2048 || strlen($raw) < 80) {
    throw new RuntimeException('Portal event is absent or too large.');
}
$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$fields = [
    'event_id', 'service_ref', 'organization_ref', 'tenant_ref',
    'source_revision', 'event_type', 'status', 'attempts'
];
if (!is_array($data) || array_keys($data) !== $fields
    || !is_string($data['event_id'])
    || !preg_match('/^evt-demo-[a-f0-9]{24}$/D', $data['event_id'])
    || $data['service_ref'] !== 'svc-demo-b'
    || $data['organization_ref'] !== 'org-demo-b'
    || $data['tenant_ref'] !== 'perf-demo-b'
    || $data['source_revision'] !== 2
    || $data['event_type'] !== 'service.authorization_changed.synthetic'
    || $data['status'] !== 'pending'
    || $data['attempts'] !== 0) {
    throw new RuntimeException('Portal event schema or tenant not permitted in F1-14.');
}
$db = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_authority',
    'postgres', 'local-ci-only', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$db->beginTransaction();
try {
    $change = $db->prepare(
        'UPDATE perf_external_lab.mock_authority_fences
         SET active=false, min_revision=2, updated_at=clock_timestamp()
         WHERE service_ref=? AND organization_ref=? AND tenant_ref=?
           AND active=true AND min_revision=1'
    );
    $change->execute([$data['service_ref'], $data['organization_ref'], $data['tenant_ref']]);
    if ($change->rowCount() !== 1) {
        throw new RuntimeException('F1-14 refuses missing prior active demo service.');
    }
    $insert = $db->prepare(
        'INSERT INTO perf_external_lab.mock_revocation_outbox
         (event_id,service_ref,organization_ref,tenant_ref,source_revision,
          event_type,status,attempts)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $insert->execute(array_values($data));
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
echo "PASS F1-14 imported dynamically MFA-issued Portal event into disposable source authority\n";
