<?php

declare(strict_types=1);

/**
 * F1-13 TEST-ONLY receiver, never Laravel route or production server.
 * Listen exclusively on 127.0.0.1:18783 via php -S during CI.
 */
require_once __DIR__.'/SyntheticWireEnvelope.php';
require_once __DIR__.'/../php/SyntheticRevocationRelay.php';

use Imagine\Protocol\Lab\SyntheticRevocationRelay;
use Imagine\Protocol\Lab\SyntheticWireEnvelope as Wire;

$respond = static function (int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['result' => $message], JSON_THROW_ON_ERROR);
    exit;
};

if (PHP_SAPI !== 'cli-server'
    || getenv('PERF_F13_WIRE_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_FENCE_DB') !== 'perf_test_relay_authority'
    || getenv('PERF_WIRE_LAB_CLOCK') !== '1800000000'
    || (string) ($_SERVER['SERVER_PORT'] ?? '') !== '18783'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    $respond(404, 'not_found');
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($method !== Wire::METHOD) {
    $respond(405, 'method_not_allowed');
}
if ($path !== Wire::PATH
    || ($_SERVER['QUERY_STRING'] ?? '') !== '') {
    $respond(404, 'not_found');
}

$contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
if (!is_string($contentLength)
    || !ctype_digit($contentLength)
    || (int) $contentLength < 1
    || (int) $contentLength > Wire::MAX_BYTES) {
    $respond(413, 'payload_size_refused');
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) !== (int) $contentLength) {
    $respond(400, 'invalid_body');
}
$keyHex = getenv('PERF_WIRE_LAB_TEST_KEY_HEX');
if (!is_string($keyHex)
    || !preg_match('/^[a-f0-9]{64}$/D', $keyHex)) {
    $respond(503, 'test_key_unavailable');
}
$key = hex2bin($keyHex);
if (!is_string($key)) {
    $respond(503, 'test_key_unavailable');
}

$incoming = getallheaders();
$wireHeaders = [];
foreach ($incoming as $header => $value) {
    if (strncasecmp($header, 'X-Imagine-Lab-', 14) === 0) {
        $wireHeaders[$header] = $value;
    }
}
$wireDecision = Wire::verifyForLab($method, $path, $raw, $wireHeaders, $key, 1800000000);
if ($wireDecision !== 'wire_valid_demo') {
    $respond(401, $wireDecision);
}

try {
    $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $respond(400, 'invalid_event_body');
}
if (!is_array($event)
    || array_keys($event) !== [
        'event_id', 'service_ref', 'organization_ref', 'tenant_ref',
        'source_revision', 'event_type', 'status', 'attempts'
    ]) {
    $respond(400, 'invalid_event_shape');
}
if (!is_string($event['event_id'])
    || !preg_match('/^evt-demo-[a-z0-9-]{1,80}$/D', $event['event_id'])
    || $event['service_ref'] !== 'svc-demo-b'
    || $event['organization_ref'] !== 'org-demo-b'
    || $event['tenant_ref'] !== 'perf-demo-b'
    || !is_int($event['source_revision']) || $event['source_revision'] !== 2
    || $event['event_type'] !== 'service.authorization_changed.synthetic'
    || $event['status'] !== 'pending'
    || $event['attempts'] !== 0) {
    $respond(403, 'event_binding_denied');
}

$password = getenv('PGPASSWORD');
if (!is_string($password) || $password !== 'local-ci-only') {
    $respond(503, 'test_storage_disabled');
}
$connect = static fn (string $db, string $role, string $pass): PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$db.';connect_timeout=3',
    $role, $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

try {
    $reader = $connect('perf_test_relay_authority', 'perf_test_fence_reader',
        'ci-only-fence-reader');
    $acker = $connect('perf_test_relay_authority', 'perf_test_outbox_acker',
        'ci-only-outbox-acker');
    $main = $connect('perf_test_relay_main', 'perf_test_main_revoker',
        'ci-only-main-revoker');

    // Nonce receipt persists between real HTTP request processes. The outbox
    // ID is also unique: new nonce must not revive an ACKed old event.
    $receipt = $acker->prepare(
        'SELECT event_id FROM perf_external_lab.mock_wire_receipts
         WHERE key_id=? AND nonce=?'
    );
    $nonce = $wireHeaders['X-Imagine-Lab-Nonce'] ??
        $wireHeaders['x-imagine-lab-nonce'] ?? '';
    $receipt->execute([Wire::KEY_ID, $nonce]);
    if ($receipt->fetchColumn() !== false) {
        $respond(409, 'wire_replay_denied');
    }

    $record = $reader->prepare(
        'SELECT event_id,service_ref,organization_ref,tenant_ref,
                source_revision,event_type,status,attempts
         FROM perf_external_lab.mock_revocation_outbox
         WHERE event_id=?'
    );
    $record->execute([$event['event_id']]);
    $stored = $record->fetch(PDO::FETCH_ASSOC);
    if (!is_array($stored)) {
        $respond(404, 'source_event_not_found');
    }
    foreach ($event as $field => $value) {
        if ((string) $stored[$field] !== (string) $value) {
            $respond(409, 'source_event_not_current');
        }
    }

    $fence = $reader->prepare(
        'SELECT organization_ref,tenant_ref,min_revision,active
         FROM perf_external_lab.mock_authority_fences
         WHERE service_ref=?'
    );
    $fence->execute([$event['service_ref']]);
    $authority = $fence->fetch(PDO::FETCH_ASSOC);
    if (!is_array($authority)
        || $authority['organization_ref'] !== $event['organization_ref']
        || $authority['tenant_ref'] !== $event['tenant_ref']
        || (int) $authority['min_revision'] !== $event['source_revision']
        || !in_array($authority['active'], [false, 'f', '0', 0], true)) {
        $respond(403, 'source_grant_not_revoked');
    }

    // The F1-11 relay still reconciles CURRENT source, never the body state.
    $relay = new SyntheticRevocationRelay($reader, $main, $acker);
    $result = $relay->deliver($event['event_id']);
    if (!in_array($result, ['relay_synced_demo', 'relay_already_synced_demo'], true)) {
        $respond(503, 'mock_relay_denied');
    }
    $save = $acker->prepare(
        'INSERT INTO perf_external_lab.mock_wire_receipts
         (event_id,key_id,nonce,body_sha256)
         VALUES(?,?,?,?) ON CONFLICT DO NOTHING'
    );
    $save->execute([
        $event['event_id'], Wire::KEY_ID, $nonce, hash('sha256', $raw)
    ]);
    if ($save->rowCount() !== 1) {
        $respond(409, 'wire_replay_denied');
    }
    $respond(202, 'revocation_applied_synthetic_only');
} catch (Throwable) {
    // No response leaks DB details or falls back to unverified operation.
    $respond(503, 'mock_storage_unavailable');
}
