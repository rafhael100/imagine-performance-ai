<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticS2SProtocol.php';

use Imagine\Protocol\Lab\SyntheticS2SProtocol;

// Fixture path is supplied by each repository's CI; no data is downloaded.
$fixture = getenv('PERF_SNAPSHOT_FIXTURE');
if (!is_string($fixture) || $fixture === '' || !is_file($fixture)) {
    fwrite(STDERR, "FAIL missing offline snapshot fixture\n");
    exit(2);
}
$json = file_get_contents($fixture);
if ($json === false || strlen($json) > 32768) {
    throw new RuntimeException('Invalid offline fixture bytes.');
}
$sha = hash('sha256', $json);
$key = random_bytes(32); // ephemeral test key; NEVER committed or printed
$now = 1800000000; // synthetic clock, not current wall time
$org = 'org-demo-aurora';
$tenant = 'perf-demo-aurora';
$svc = 'svc-demo-aurora';
$mint = static fn (
    string $request = 'req-demo-001',
    string $nonce = 'nonce-demo-001',
    int $issued = 1799999940,
    int $expires = 1800000060,
    int $revision = 3,
    string $digest = ''
): array => SyntheticS2SProtocol::mintForLab(
    $org, $tenant, $svc, $request, $nonce, $issued, $expires,
    $revision, $digest === '' ? $sha : $digest, $key
);
$verify = static fn (
    SyntheticS2SProtocol $v,
    mixed $envelope,
    string $expectedOrg = 'org-demo-aurora',
    string $expectedTenant = 'perf-demo-aurora',
    string $expectedService = 'svc-demo-aurora',
    int $revision = 3,
    int $clock = 1800000000,
    bool $active = true,
    string $digest = ''
): string => $v->verifyForLab(
    $envelope, $key, $expectedOrg, $expectedTenant, $expectedService,
    $digest === '' ? $sha : $digest, $revision, $clock, $active
);

$count = 0;
$assert = static function (string $label, mixed $got, mixed $expected) use (&$count): void {
    if ($got !== $expected) {
        throw new RuntimeException("FAIL $label got ".var_export($got, true).' expected '.var_export($expected, true));
    }
    ++$count;
    echo "PASS $label\n";
};

$v = new SyntheticS2SProtocol();
$m = $mint();
$assert('01 valid ephemeral signed demo message', $verify($v, $m), 'accepted_synthetic_only');
$assert('02 same message replay denied', $verify($v, $m), 'mock_replay_denied');
$assert('03 duplicate nonce across distinct requests', $verify($v, $mint('req-demo-002', 'nonce-demo-001')), 'mock_replay_denied');
$assert('04 reused request id with new nonce', $verify($v, $mint('req-demo-001', 'nonce-demo-002')), 'mock_replay_denied');

$clone = static fn (): SyntheticS2SProtocol => new SyntheticS2SProtocol();
$changed = $mint();
$changed['tenant_ref'] = 'perf-demo-attacker';
$assert('05 tampered tenant is not signed', $verify($clone(), $changed), 'invalid_lab_signature');
$changed = $mint();
$changed['organization_ref'] = 'org-demo-attacker';
$assert('06 tampered organization is not signed', $verify($clone(), $changed), 'invalid_lab_signature');
$changed = $mint();
$changed['payload_sha256'] = str_repeat('a', 64);
$assert('07 tampered payload hash denied', $verify($clone(), $changed), 'invalid_lab_signature');
$changed = $mint();
$changed['signature'] = str_repeat('0', 64);
$assert('08 forged HMAC denied', $verify($clone(), $changed), 'invalid_lab_signature');
$assert('09 expected tenant mismatch', $verify($clone(), $mint(), expectedTenant: 'perf-demo-other'), 'mock_binding_denied');
$assert('10 expected organization mismatch', $verify($clone(), $mint(), expectedOrg: 'org-demo-other'), 'mock_binding_denied');
$assert('11 expected service mismatch', $verify($clone(), $mint(), expectedService: 'svc-demo-other'), 'mock_binding_denied');
$assert('12 expected fixture digest mismatch', $verify($clone(), $mint(), digest: str_repeat('b', 64)), 'mock_binding_denied');
$assert('13 revoked synthetic grant denied', $verify($clone(), $mint(), active: false), 'mock_grant_revoked');
$assert('14 older grant revision denied', $verify($clone(), $mint(), revision: 4), 'mock_grant_revision_stale');
$assert('15 future timestamp denied', $verify($clone(), $mint('req-demo-003', 'nonce-demo-003', $now + 1, $now + 50)), 'mock_message_expired');
$assert('16 expired timestamp denied', $verify($clone(), $mint('req-demo-004', 'nonce-demo-004', $now - 120, $now)), 'mock_message_expired');

$changed = $mint();
$changed['schema_version'] = 'prod.v1';
$assert('17 unknown version denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
$changed['producer'] = 'untrusted-producer';
$assert('18 unknown source denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
$changed['audience'] = 'portal-imagine-mock';
$assert('19 audience confusion denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
$changed['api_token'] = 'not-valid';
$assert('20 additional field denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
unset($changed['signature']);
$assert('21 missing signature denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
$changed['grant_revision'] = '3';
$assert('22 revision type confusion denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$changed = $mint();
$changed['signature'] = 'xyz';
$assert('23 malformed MAC denied', $verify($clone(), $changed), 'invalid_lab_envelope');
$assert('24 invalid test key denied', (new SyntheticS2SProtocol())->verifyForLab(
    $mint(), 'short', $org, $tenant, $svc, $sha, 3, $now, true
), 'invalid_lab_key');
$assert('25 valid mock grant revision accepted', $verify($clone(), $mint('req-demo-005', 'nonce-demo-005', $now - 60, $now + 60, 4), revision: 4), 'accepted_synthetic_only');
$assert('26 zero service activation implied', array_key_exists('payment_state', $m), false);
$assert('27 no payload content transmitted', array_key_exists('payload', $m), false);
$assert('28 exact 64-character payload digest', strlen($m['payload_sha256']), 64);
$assert('29 synthetic fixture digest bound', $m['payload_sha256'], $sha);

try {
    $mint('req-demo-006', 'nonce-demo-006', $now - 1, $now + 999);
    throw new RuntimeException('Long TTL unexpectedly accepted');
} catch (InvalidArgumentException $e) {
    $assert('30 oversized TTL rejected at issuer', $e->getMessage(), 'Invalid synthetic envelope fields.');
}
try {
    SyntheticS2SProtocol::mintForLab($org, $tenant, $svc, 'req-demo-007', 'nonce-demo-007',
        $now - 1, $now + 1, 3, $sha, 'not-a-strong-key');
    throw new RuntimeException('Short test key unexpectedly accepted');
} catch (InvalidArgumentException $e) {
    $assert('31 short signing key refused', $e->getMessage(), 'Synthetic key must be ephemeral and at least 32 bytes.');
}
try {
    $mint('req-demo-008', 'nonce-without-prefix');
    throw new RuntimeException('Bad nonce unexpectedly accepted');
} catch (InvalidArgumentException $e) {
    $assert('32 invalid nonce refused', $e->getMessage(), 'Invalid synthetic envelope fields.');
}
echo "PASS {$count} S2S SYNTHETIC LAB checks (NO production authentication)\n";
