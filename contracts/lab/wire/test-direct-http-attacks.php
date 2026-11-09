<?php

declare(strict_types=1);

/** F1-15 adversarial HTTP tests; no operational server is contacted. */
require_once __DIR__.'/SyntheticWireEnvelope.php';
use Imagine\Protocol\Lab\SyntheticWireEnvelope as Wire;

if (PHP_SAPI !== 'cli' || getenv('PERF_F15_DIRECT_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_WIRE_LAB_CLOCK') !== '1800000000') {
    throw new RuntimeException('F15 attack tests require disposable localhost.');
}
$hex = getenv('PERF_WIRE_LAB_TEST_KEY_HEX');
if (!is_string($hex) || !preg_match('/^[a-f0-9]{64}$/D', $hex)) {
    throw new RuntimeException('Missing ephemeral CI key.');
}
$key = hex2bin($hex);
$body = file_get_contents((string) getenv('PERF_WIRE_EVENT_FIXTURE'));
if (!is_string($body)) {
    throw new RuntimeException('No dynamically issued Portal event in runner temp.');
}
$event = json_decode($body,true,512,JSON_THROW_ON_ERROR);
$send = static function (
    string $data,
    array $headers,
    string $method = 'POST',
    string $path = '/_synthetic/perf/revocation'
): array {
    $curl = curl_init('http://127.0.0.1:18784'.$path);
    if ($curl === false) {
        throw new RuntimeException('No test cURL.');
    }
    $h = ['Content-Type: application/json'];
    foreach ($headers as $name=>$val) {
        $h[] = $name.': '.$val;
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_POSTFIELDS => $method === 'POST' ? $data : null,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_PROXY => '',
    ]);
    $response = curl_exec($curl);
    $code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (!is_string($response) || $code < 100) {
        throw new RuntimeException('Local F15 HTTP unavailable.');
    }
    return [$code,json_decode($response,true,512,JSON_THROW_ON_ERROR)['result'] ?? null];
};
$h = Wire::signForLab($body,$key,1800000000,'nonce-demo-direct-attack001');
$n=0;
$check = static function (string $name,mixed $a,mixed $b) use (&$n):void {
    if ($a!==$b) {
        throw new RuntimeException('FAIL F15 '.$name.' got '.var_export($a,true).' expected '.var_export($b,true));
    }
    ++$n;
    echo "PASS F15 $n $name\n";
};
$check('01 method GET rejected',$send('',[],'GET'),[405,'method_not_allowed']);
$check('02 foreign route refused',$send($body,$h,'POST','/nonexistent'),[404,'not_found']);
$check('03 no signature refused',$send($body,[]),[401,'wire_invalid_headers_demo']);
$tamper=$h;$tamper['X-Imagine-Lab-Signature']=str_repeat('a',64);
$check('04 forged MAC refused',$send($body,$tamper),[401,'wire_bad_signature_demo']);
$check('05 modified payload refused',$send($body." ",$h),[401,'wire_body_modified_demo']);
$check('06 oversized payload refused',$send(str_repeat('x',Wire::MAX_BYTES+1),$h),[413,'payload_size_refused']);
$old=Wire::signForLab($body,$key,1799999879,'nonce-demo-direct-expired001');
$check('07 expired message denied',$send($body,$old),[401,'wire_expired_demo']);
$keyId=$h;$keyId['X-Imagine-Lab-Key-Id']='kid-demo-other';
$check('08 unknown key denied',$send($body,$keyId),[401,'wire_wrong_identity_demo']);
$v=$event;$v['tenant_ref']='perf-demo-a';$raw=json_encode($v,JSON_THROW_ON_ERROR);
$cross=Wire::signForLab($raw,$key,1800000000,'nonce-demo-direct-cross001');
$check('09 signed foreign tenant denied',$send($raw,$cross),[403,'event_binding_denied']);
$v=$event;$v['source_revision']=1;$raw=json_encode($v,JSON_THROW_ON_ERROR);
$stale=Wire::signForLab($raw,$key,1800000000,'nonce-demo-direct-stale001');
$check('10 signed old revision denied',$send($raw,$stale),[403,'event_binding_denied']);
$v=$event;$v['status']='acked';$raw=json_encode($v,JSON_THROW_ON_ERROR);
$wrong=Wire::signForLab($raw,$key,1800000000,'nonce-demo-direct-wrongstatus001');
$check('11 signed unauthorized state denied',$send($raw,$wrong),[403,'event_binding_denied']);
$v=$event;$v['service_ref']='svc-demo-a';$raw=json_encode($v,JSON_THROW_ON_ERROR);
$foreign=Wire::signForLab($raw,$key,1800000000,'nonce-demo-direct-foreign001');
$check('12 signed foreign service denied',$send($raw,$foreign),[403,'event_binding_denied']);

$pdo = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_main',
    'postgres',(string)getenv('PGPASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$check('13 denied requests create no receipt',
    (int)$pdo->query('SELECT count(*) FROM perf_s2s_lab.mock_direct_wire_receipts')->fetchColumn(),0);
$check('14 tenant B still active before genuine delivery',
    in_array($pdo->query(
        "SELECT active FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b'"
    )->fetchColumn(),[true,'t','1',1],true),true);
$check('15 tenant A revision remains 6',
    (int)$pdo->query(
        "SELECT grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(),6);

$role = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_relay_main',
    'perf_test_wire_ingress','ci-only-direct-wire-ingress',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$check('16 ingress role cannot read A under RLS',
    (int)$role->query(
        "SELECT count(*) FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(),0);
try {
    $changed = $role->exec("UPDATE perf_s2s_lab.mock_service_grants
         SET active=true WHERE service_ref='svc-demo-a'");
    $check('17 ingress role cannot mutate A', $changed, 0);
} catch (PDOException) {
    $check('17 ingress role cannot mutate A', true, true);
}
echo "PASS F15 $n signed inbound denial / tenant RLS assertions\n";
