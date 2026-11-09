<?php

declare(strict_types=1);

// This test makes ONLY 127.0.0.1 loopback HTTP requests to an ephemeral php -S
// process. No real Portal route, real secrets, remote APIs or customer data.
require_once __DIR__.'/SyntheticWireEnvelope.php';
use Imagine\Protocol\Lab\SyntheticWireEnvelope as Wire;

if (PHP_SAPI !== 'cli'
    || getenv('PERF_F13_WIRE_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_FENCE_DB') !== 'perf_test_relay_authority') {
    throw new RuntimeException('F1-13 client must run in isolated test environment.');
}
$hex = getenv('PERF_WIRE_LAB_TEST_KEY_HEX');
if (!is_string($hex) || !preg_match('/^[a-f0-9]{64}$/D', $hex)) {
    throw new RuntimeException('Missing ephemeral test key.');
}
$key = hex2bin($hex);
if (!is_string($key) || strlen($key) !== 32) {
    throw new RuntimeException('Bad synthetic signing key.');
}
$externalEvent = getenv('PERF_WIRE_EVENT_FIXTURE');
$fixture = dirname(__DIR__).'/demo/perf-revocation-event-example-v1.json';
if (is_string($externalEvent) && $externalEvent !== '') {
    $runnerTemp = realpath((string) getenv('RUNNER_TEMP'));
    if (!is_string($runnerTemp)
        || realpath(dirname($externalEvent)) !== $runnerTemp
        || basename($externalEvent) !== 'portal-perf-f13-event.json'
        || !is_file($externalEvent) || is_link($externalEvent)) {
        throw new RuntimeException('F1-14 refuses non-temporary Portal event source.');
    }
    $fixture = $externalEvent;
}
$body = file_get_contents($fixture);
if (!is_string($body) || strlen($body) < 50) {
    throw new RuntimeException('Missing Portal example fixture.');
}
$event = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
$clock = 1800000000;
$nonce = 'nonce-demo-http-accepted001';
$headers = Wire::signForLab($body, $key, $clock, $nonce);
$portalSigned = getenv('PERF_WIRE_HEADERS_FIXTURE');
if (is_string($portalSigned) && $portalSigned !== '') {
    $runnerTemp = realpath((string) getenv('RUNNER_TEMP'));
    if (!is_string($runnerTemp)
        || realpath(dirname($portalSigned)) !== $runnerTemp
        || basename($portalSigned) !== 'portal-perf-f13-wire-headers.json'
        || !is_file($portalSigned) || is_link($portalSigned)) {
        throw new RuntimeException('F1-14 refuses non-temp Portal signed headers.');
    }
    $headers = json_decode((string) file_get_contents($portalSigned),
        true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($headers)
        || Wire::verifyForLab('POST', Wire::PATH, $body, $headers, $key, $clock)
            !== 'wire_valid_demo') {
        throw new RuntimeException('F1-14 Portal-generated signature did not verify.');
    }
}
$sourceAdmin = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_authority',
    'postgres', (string) getenv('PGPASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$mainAdmin = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_main',
    'postgres', (string) getenv('PGPASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$send = static function (
    string $raw,
    array $h,
    string $method = 'POST',
    string $path = '/_synthetic/perf/revocation',
): array {
    $url = 'http://127.0.0.1:18783'.$path;
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Missing lab-only curl.');
    }
    $lines = ['Content-Type: application/json'];
    foreach ($h as $k => $v) {
        $lines[] = $k.': '.$v;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $lines,
        CURLOPT_POSTFIELDS => $method === 'POST' ? $raw : null,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_PROXY => '',
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $status < 100) {
        throw new RuntimeException('Local loopback HTTP failed: '.$error);
    }
    $parsed = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    return [$status, $parsed['result'] ?? null];
};
$n = 0;
$check = static function (string $label, mixed $got, mixed $expected) use (&$n): void {
    if ($got !== $expected) {
        throw new RuntimeException("FAIL F1-13 {$label}: got ".var_export($got, true)
            .' expected '.var_export($expected, true));
    }
    ++$n;
    echo "PASS F1-13 $n $label\n";
};
$receiptCount = static fn (): int => (int) $sourceAdmin->query(
    'SELECT count(*) FROM perf_external_lab.mock_wire_receipts'
)->fetchColumn();

$check('01 temporary loopback rejects GET', $send('', [], 'GET'), [405,'method_not_allowed']);
$check('02 temporary loopback rejects other URL',
    $send($body,$headers,'POST','/api/live'), [404,'not_found']);
$check('03 temporary loopback rejects query strings',
    $send($body,$headers,'POST',Wire::PATH.'?unsafe=true'), [404,'not_found']);
$check('04 HTTP body size bound enforced',
    $send(str_repeat('x',Wire::MAX_BYTES+1),$headers), [413,'payload_size_refused']);
$check('05 missing signature headers rejected', $send($body,[]),
    [401,'wire_invalid_headers_demo']);
$forged = $headers;
$forged['X-Imagine-Lab-Signature'] = str_repeat('a',64);
$check('06 forged HMAC rejected',
    $send($body,$forged), [401,'wire_bad_signature_demo']);
$check('07 altered JSON bytes rejected',
    $send($body." ",$headers), [401,'wire_body_modified_demo']);
$expired = Wire::signForLab($body,$key,$clock-121,'nonce-demo-http-expired001');
$check('08 stale signed request rejected',
    $send($body,$expired), [401,'wire_expired_demo']);
$future = Wire::signForLab($body,$key,$clock+16,'nonce-demo-http-future0001');
$check('09 far-future signed request rejected',
    $send($body,$future), [401,'wire_expired_demo']);
$wrongKey = $headers;
$wrongKey['X-Imagine-Lab-Key-Id'] = 'kid-demo-unknown-2';
$check('10 unknown key identity rejected',
    $send($body,$wrongKey), [401,'wire_wrong_identity_demo']);
$wrongVersion = $headers;
$wrongVersion['X-Imagine-Lab-Version'] = 'perf.wire.prod.v1';
$check('11 unknown protocol version rejected',
    $send($body,$wrongVersion), [401,'wire_wrong_identity_demo']);

$crossTenant = $event;
$crossTenant['tenant_ref'] = 'perf-demo-a';
$crossBody = json_encode($crossTenant, JSON_THROW_ON_ERROR);
$crossHeaders = Wire::signForLab(
    $crossBody,$key,$clock,'nonce-demo-http-crosstenant001'
);
$check('12 correctly signed cross-tenant event still forbidden',
    $send($crossBody,$crossHeaders), [403,'event_binding_denied']);
$unknown = $event;
$unknown['event_id'] = 'evt-demo-nonexistent';
$unknownBody = json_encode($unknown,JSON_THROW_ON_ERROR);
$unknownHeaders = Wire::signForLab(
    $unknownBody,$key,$clock,'nonce-demo-http-unknown001'
);
$check('13 correctly signed unknown event is not trusted',
    $send($unknownBody,$unknownHeaders), [404,'source_event_not_found']);
$check('14 attacks stored no successful wire receipts', $receiptCount(), 0);

$sourceAdmin->exec(
    'REVOKE SELECT ON perf_external_lab.mock_revocation_outbox FROM perf_test_fence_reader'
);
$check('15 source read outage fails closed at receiver',
    $send($body,$headers), [503,'mock_storage_unavailable']);
$check('16 unavailable authority cannot consume replay ID', $receiptCount(), 0);
$sourceAdmin->exec(
    'GRANT SELECT ON perf_external_lab.mock_revocation_outbox TO perf_test_fence_reader'
);

$check('17 signed revocation delivered via real local HTTP',
    $send($body,$headers), [202,'revocation_applied_synthetic_only']);
$check('18 identical signed HTTP request replay denied across requests',
    $send($body,$headers), [409,'wire_replay_denied']);
$newNonce = Wire::signForLab($body,$key,$clock,'nonce-demo-http-secondnonce001');
$check('19 already ACKed source event cannot repeat with new nonce',
    $send($body,$newNonce), [409,'source_event_not_current']);
$check('20 one durable accepted wire receipt', $receiptCount(), 1);
$check('21 original event ID persisted in receipt',
    $sourceAdmin->query(
        "SELECT event_id FROM perf_external_lab.mock_wire_receipts LIMIT 1"
    )->fetchColumn(), $event['event_id']);
$check('22 canonical key ID persisted with receipt',
    $sourceAdmin->query(
        'SELECT key_id FROM perf_external_lab.mock_wire_receipts LIMIT 1'
    )->fetchColumn(), Wire::KEY_ID);
$check('23 verified raw digest persisted',
    $sourceAdmin->query(
        'SELECT body_sha256 FROM perf_external_lab.mock_wire_receipts LIMIT 1'
    )->fetchColumn(), hash('sha256',$body));
$check('24 authority records source event ACK',
    $sourceAdmin->query(
        "SELECT status FROM perf_external_lab.mock_revocation_outbox
         WHERE service_ref='svc-demo-b' AND source_revision=2"
    )->fetchColumn(), 'acked');
$check('25 destination grant is revoked',
    in_array($mainAdmin->query(
        "SELECT active FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b'"
    )->fetchColumn(), [false, 'f', '0', 0], true), true);
$check('26 destination revision advanced to 2',
    (int) $mainAdmin->query(
        "SELECT grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b'"
    )->fetchColumn(), 2);
$check('27 tenant A is still revoked',
    in_array($mainAdmin->query(
        "SELECT active FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(), [false, 'f', '0', 0], true), true);
$check('28 tenant A revision untouched',
    (int) $mainAdmin->query(
        "SELECT grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(), 6);
$check('29 denied requests never created extra receipt', $receiptCount(), 1);
$check('30 source revocation source remains false',
    in_array($sourceAdmin->query(
        "SELECT active FROM perf_external_lab.mock_authority_fences WHERE service_ref='svc-demo-b'"
    )->fetchColumn(), [false, 'f', '0', 0], true), true);
$check('31 signed event sample has no person/session or payment data',
    array_key_exists('email',$event)||array_key_exists('session',$event)
        ||array_key_exists('payment',$event), false);
echo "PASS F1-13 $n signed loopback HTTP→PostgreSQL relay assertions\n";
