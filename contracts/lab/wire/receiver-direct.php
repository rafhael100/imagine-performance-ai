<?php

declare(strict_types=1);

/**
 * FAST-PERF F1-15 — test-only direct revocation ingress. It has NO live
 * source-authority query, no real identity/SSO, no TLS, no remote effects.
 * Invoked by php -S 127.0.0.1:18784 only during CI. Never route in Laravel.
 */
require_once __DIR__.'/SyntheticWireEnvelope.php';
use Imagine\Protocol\Lab\SyntheticWireEnvelope as Wire;

$respond = static function (int $status, string $code): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['result' => $code], JSON_THROW_ON_ERROR);
    exit;
};
if (PHP_SAPI !== 'cli-server'
    || getenv('PERF_F15_DIRECT_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_WIRE_LAB_CLOCK') !== '1800000000'
    || (string) ($_SERVER['SERVER_PORT'] ?? '') !== '18784'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    $respond(404, 'not_found');
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($method !== Wire::METHOD) {
    $respond(405, 'method_not_allowed');
}
if ($path !== Wire::PATH || ($_SERVER['QUERY_STRING'] ?? '') !== '') {
    $respond(404, 'not_found');
}
$len = $_SERVER['CONTENT_LENGTH'] ?? '0';
if (!is_string($len) || !ctype_digit($len)
    || (int) $len < 1 || (int) $len > Wire::MAX_BYTES) {
    $respond(413, 'payload_size_refused');
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) !== (int) $len) {
    $respond(400, 'invalid_body');
}
$keyHex = getenv('PERF_WIRE_LAB_TEST_KEY_HEX');
if (!is_string($keyHex) || !preg_match('/^[a-f0-9]{64}$/D', $keyHex)) {
    $respond(503, 'test_key_unavailable');
}
$key = hex2bin($keyHex);
if (!is_string($key)) {
    $respond(503, 'test_key_unavailable');
}
$inputHeaders = getallheaders();
$headers = [];
foreach ($inputHeaders as $name => $value) {
    if (strncasecmp($name, 'X-Imagine-Lab-', 14) === 0) {
        $headers[$name] = $value;
    }
}
$valid = Wire::verifyForLab(
    $method, $path, $raw, $headers, $key, 1800000000,
);
if ($valid !== 'wire_valid_demo') {
    $respond(401, $valid);
}
try {
    $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $respond(400, 'invalid_event_json');
}
$expected = [
    'event_id','service_ref','organization_ref','tenant_ref',
    'source_revision','event_type','status','attempts',
];
if (!is_array($event) || array_keys($event) !== $expected
    || !is_string($event['event_id'])
    || !preg_match('/^evt-demo-[a-f0-9]{24}$/D', $event['event_id'])
    || $event['service_ref'] !== 'svc-demo-b'
    || $event['organization_ref'] !== 'org-demo-b'
    || $event['tenant_ref'] !== 'perf-demo-b'
    || !is_int($event['source_revision']) || $event['source_revision'] !== 2
    || $event['event_type'] !== 'service.authorization_changed.synthetic'
    || $event['status'] !== 'pending'
    || $event['attempts'] !== 0) {
    $respond(403, 'event_binding_denied');
}
$nonce = null;
foreach ($headers as $name => $value) {
    if (strcasecmp($name, 'X-Imagine-Lab-Nonce') === 0) {
        $nonce = $value;
    }
}
if (!is_string($nonce)) {
    $respond(401, 'wire_missing_nonce');
}
try {
    $db = new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_main;connect_timeout=3',
        'perf_test_wire_ingress', 'ci-only-direct-wire-ingress', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $db->beginTransaction();
    $grant = $db->prepare(
        'SELECT organization_ref,tenant_ref,active,grant_revision
         FROM perf_s2s_lab.mock_service_grants WHERE service_ref=? FOR UPDATE'
    );
    $grant->execute([$event['service_ref']]);
    $current = $grant->fetch(PDO::FETCH_ASSOC);
    if (!is_array($current)
        || $current['organization_ref'] !== $event['organization_ref']
        || $current['tenant_ref'] !== $event['tenant_ref']) {
        $db->rollBack();
        $respond(403, 'service_not_found');
    }
    $revision = (int) $current['grant_revision'];
    $active = in_array($current['active'], [true,'t','1',1], true);
    if ($revision > 2 || ($revision === 2 && $active)) {
        $db->rollBack();
        $respond(409, 'stale_or_conflicting_revision');
    }
    if ($revision < 2) {
        $update = $db->prepare(
            'UPDATE perf_s2s_lab.mock_service_grants
             SET active=false,grant_revision=2,updated_at=clock_timestamp()
             WHERE service_ref=? AND organization_ref=? AND tenant_ref=?
               AND grant_revision < 2'
        );
        $update->execute([
            $event['service_ref'],$event['organization_ref'],$event['tenant_ref']
        ]);
        if ($update->rowCount() !== 1) {
            $db->rollBack();
            $respond(409, 'concurrent_grant_conflict');
        }
    }
    // ON CONFLICT protects both duplicate event ID and duplicate signed nonce.
    // The UPDATE and receipt commit together, so a transport/ACK loss can
    // be retried safely without revoking an unrelated tenant.
    $insert = $db->prepare(
        'INSERT INTO perf_s2s_lab.mock_direct_wire_receipts
          (event_id,service_ref,organization_ref,tenant_ref,grant_revision,
           key_id,nonce,body_sha256)
         VALUES(?,?,?,?,?,?,?,?) ON CONFLICT DO NOTHING'
    );
    $insert->execute([
        $event['event_id'], $event['service_ref'], $event['organization_ref'],
        $event['tenant_ref'], $event['source_revision'],
        Wire::KEY_ID, $nonce, hash('sha256', $raw),
    ]);
    if ($insert->rowCount() !== 1) {
        $db->rollBack();
        $respond(409, 'wire_event_replay_denied');
    }
    $db->commit();
    $respond(202, 'revocation_applied_synthetic_only');
} catch (Throwable) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    $respond(503, 'mock_storage_unavailable');
}
