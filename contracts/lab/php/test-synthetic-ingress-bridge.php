<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/SyntheticS2SProtocol.php';
require_once __DIR__.'/SyntheticIngressBridge.php';

use Imagine\Protocol\Lab\SyntheticIngressBridge;
use Imagine\Protocol\Lab\SyntheticS2SProtocol;

$host = getenv('PGHOST');
$port = getenv('PGPORT');
$database = getenv('PGDATABASE');
$password = getenv('PGPASSWORD');
$fixture = getenv('PERF_SNAPSHOT_FIXTURE');

if ($host !== '127.0.0.1' || $port !== '5432'
    || !is_string($database) || !preg_match('/^perf_test_[a-zA-Z0-9_]+$/D', $database)
    || !is_string($password) || $password === ''
    || !is_string($fixture) || !is_file($fixture)) {
    throw new RuntimeException('Only an isolated disposable test DB and fixture are permitted.');
}
$bytes = file_get_contents($fixture);
if (!is_string($bytes) || strlen($bytes) > 32768) {
    throw new RuntimeException('Missing or oversized synthetic fixture.');
}
$sha = hash('sha256', $bytes);
$key = random_bytes(32);
$now = 1800000000;

$dsn = "pgsql:host=127.0.0.1;port=5432;dbname={$database};connect_timeout=4";
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$admin = new PDO($dsn, 'postgres', $password, $opts);
$testUser = static fn (): PDO => new PDO($dsn, 'perf_s2s_lab_caller', 'ci-test-only-s2s-role', $opts);
$admin->exec("INSERT INTO perf_s2s_lab.mock_service_grants
(service_ref, organization_ref, tenant_ref, grant_revision, active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true)");
$bridge = new SyntheticIngressBridge($testUser());

$sign = static fn (
    string $req,
    string $nonce,
    string $org = 'org-demo-a',
    string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a',
    int $revision = 3,
    int $issued = 1799999940,
    int $expires = 1800000060,
    string $hash = ''
): array => SyntheticS2SProtocol::mintForLab(
    $org, $tenant, $service, $req, $nonce, $issued, $expires,
    $revision, $hash === '' ? $sha : $hash, $key
);
$accept = static fn (
    SyntheticIngressBridge $consumer,
    mixed $envelope,
    string $org = 'org-demo-a',
    string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a',
    int $revision = 3,
    bool $grantActive = true
): string => $consumer->accept($envelope, $key, $org, $tenant, $service,
    $sha, $revision, $now, $grantActive);

$n = 0;
$check = static function (string $description, mixed $actual, mixed $expected) use (&$n): void {
    if ($actual !== $expected) {
        throw new RuntimeException("FAIL {$description}: actual ".var_export($actual, true)
            ." expected ".var_export($expected, true));
    }
    ++$n;
    echo "PASS $n $description\n";
};
$a1 = $sign('req-demo-a1', 'nonce-demo-a1');
$check('01 authenticated mock reaches durable claim', $accept($bridge, $a1), 'accepted_synthetic_only');
$check('02 replay after separate PHP verifier refused by DB', $accept($bridge, $a1), 'mock_replay_denied');
$reconnected = new SyntheticIngressBridge($testUser());
$check('03 replay after reconnect refused by DB', $accept($reconnected, $a1), 'mock_replay_denied');
$check('04 duplicate nonce refused by DB', $accept($reconnected, $sign('req-demo-a2', 'nonce-demo-a1')), 'mock_replay_denied');
$check('05 duplicate request ID refused by DB', $accept($reconnected, $sign('req-demo-a1', 'nonce-demo-a2')), 'mock_replay_denied');

$badSignature = $sign('req-demo-tamper', 'nonce-demo-tamper');
$badSignature['tenant_ref'] = 'perf-demo-attacker';
$check('06 altered signed tenant never reaches claim', $accept($bridge, $badSignature), 'invalid_lab_signature');
$badSignature = $sign('req-demo-tamper2', 'nonce-demo-tamper2');
$badSignature['signature'] = str_repeat('f', 64);
$check('07 invalid HMAC refused', $accept($bridge, $badSignature), 'invalid_lab_signature');
$check('08 signed tenant B cannot masquerade as A', $accept($bridge,
    $sign('req-demo-badbinding', 'nonce-demo-badbinding', 'org-demo-b', 'perf-demo-b', 'svc-demo-b', 1)),
    'mock_binding_denied');
$check('09 expired signed envelope denied', $accept($bridge,
    $sign('req-demo-expired', 'nonce-demo-expired', issued: $now - 60, expires: $now)),
    'mock_message_expired');
$check('10 mismatched digest rejected before SQL', $accept($bridge,
    $sign('req-demo-digest', 'nonce-demo-digest', hash: str_repeat('e', 64))),
    'mock_binding_denied');
$check('11 signed envelope for stale grant rejected in DB', $accept($bridge,
    $sign('req-demo-stale', 'nonce-demo-stale', revision: 4), revision: 4),
    'mock_grant_revision_stale');

$b = $sign('req-demo-b1', 'nonce-demo-b1', 'org-demo-b', 'perf-demo-b', 'svc-demo-b', 1);
$check('12 tenant B own signed envelope accepted', $accept($bridge, $b, 'org-demo-b', 'perf-demo-b', 'svc-demo-b', 1), 'accepted_synthetic_only');
$check('13 signed tenant B reused A request blocked globally', $accept($bridge,
    $sign('req-demo-a1', 'nonce-demo-b2', 'org-demo-b', 'perf-demo-b', 'svc-demo-b', 1),
    'org-demo-b', 'perf-demo-b', 'svc-demo-b', 1), 'mock_replay_denied');
$check('14 initially only two durable receipts', (int) $admin->query(
    'SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts'
)->fetchColumn(), 2);

$admin->exec("UPDATE perf_s2s_lab.mock_service_grants SET active=false,grant_revision=4
WHERE service_ref='svc-demo-a'");
$check('15 revoked tenant A cannot claim', $accept($bridge,
    $sign('req-demo-a3', 'nonce-demo-a3', revision: 4), revision: 4),
    'mock_grant_revoked');
$check('16 fake grant switch cannot bypass SQL revocation', $accept($bridge,
    $sign('req-demo-a4', 'nonce-demo-a4', revision: 4), revision: 4, grantActive: true),
    'mock_grant_revoked');
$check('17 revocation leaves original replay rows untouched', (int) $admin->query(
    'SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts'
)->fetchColumn(), 2);

$admin->exec("UPDATE perf_s2s_lab.mock_service_grants SET active=true,grant_revision=5
WHERE service_ref='svc-demo-a'");
$check('18 old signed revision remains stale', $accept($bridge,
    $sign('req-demo-a5', 'nonce-demo-a5'), revision: 3),
    'mock_grant_revision_stale');
$check('19 new signed revision accepted', $accept($bridge,
    $sign('req-demo-a5', 'nonce-demo-a5', revision: 5), revision: 5),
    'accepted_synthetic_only');
$check('20 original replay is denied even after reactivation', $accept($bridge,
    $sign('req-demo-a1', 'nonce-demo-a99', revision: 5), revision: 5),
    'mock_replay_denied');
$check('21 only three accepted receipts ever persisted', (int) $admin->query(
    'SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts'
)->fetchColumn(), 3);

try {
    $testUser()->query('SELECT * FROM perf_s2s_lab.mock_service_grants');
    throw new RuntimeException('Caller read lab grant table unexpectedly');
} catch (PDOException) {
    $check('22 SQL caller still has no direct table read rights', true, true);
}
$check('23 test connection is truly pgsql', $admin->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql');
$check('24 no web request or payment hidden in signed envelope',
    array_key_exists('payment_state', $a1) || array_key_exists('http_url', $a1), false);

echo "PASS $n end-to-end SYNTHETIC HMAC-to-PG laboratory checks\n";
