<?php
declare(strict_types=1);
if (getenv('CI') !== 'true' || getenv('ADMIN_PHASE4_PG_CI') !== '1') exit(1);
foreach (['AdministrativeServiceEnvelope', 'AdministrativeServiceInbox', 'AdministrativeReadReceiver', 'PrivateAdministrativeService'] as $file) {
    require __DIR__.'/../src/Domain/Administration/'.$file.'.php';
}
use Imagine\Performance\Administration\AdministrativeServiceEnvelope as Envelope;
use Imagine\Performance\Administration\PrivateAdministrativeService as Service;

// Runs only after the Phase4 migrations in the same disposable CI database.
$dsn = 'pgsql:host=127.0.0.1;port=5432;dbname=performance_phase4_ci';
$owner = new PDO($dsn, 'postgres', getenv('PGPASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$password = bin2hex(random_bytes(32));
$secret = bin2hex(random_bytes(32));
$company = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$fingerprint = str_repeat('d', 64);
$owner->exec("CREATE ROLE phase7_reader LOGIN PASSWORD '$password' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
 GRANT USAGE ON SCHEMA performance_admin_inbox TO phase7_reader;
 GRANT SELECT,INSERT ON performance_admin_inbox.receipts,performance_admin_inbox.nonces TO phase7_reader;
 GRANT INSERT ON performance_admin_inbox.request_audit TO phase7_reader;
 GRANT USAGE ON SEQUENCE performance_admin_inbox.request_audit_id_seq TO phase7_reader;
 GRANT EXECUTE ON FUNCTION performance_admin_inbox.can_scope(text,text),performance_admin_inbox.can_nonce(text),performance_admin_inbox.resolve_delegation(text,text,text,text,text) TO phase7_reader;
 INSERT INTO performance_admin_inbox.principals VALUES('phase7_reader','phase7_portal','$company',true,now()+interval '1 hour',NULL);
 INSERT INTO performance_admin_inbox.service_delegations VALUES('phase7_reader','phase7_portal','phase7_key_01','$fingerprint','$company','dashboard.read',1,true,now()+interval '1 hour',NULL)");
$runtime = new PDO($dsn, 'phase7_reader', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$keyring = tempnam(sys_get_temp_dir(), 'phase7-');
chmod($keyring, 0600);
file_put_contents($keyring, json_encode(['phase7_portal' => ['phase7_key_01' => [
    'active' => true, 'expires_at' => time() + 300, 'secret' => $secret,
]]], JSON_THROW_ON_ERROR));
$wire = function () use ($company, $secret): string {
    return json_encode(['envelope' => Envelope::issue('phase7_portal', 'phase7_key_01', $company,
        'dashboard.read', bin2hex(random_bytes(16)), bin2hex(random_bytes(16)), '{}', time(), $secret),
        'body' => '{}'], JSON_THROW_ON_ERROR);
};
$n = 0;
$ok = function (bool $value, string $label) use (&$n): void {
    if (!$value) throw new RuntimeException('Phase7 privilege assertion failed: '.$label);
    $n++;
};
$counts = fn() => $owner->query('SELECT (SELECT count(*) FROM performance_admin_inbox.receipts) AS receipts,
    (SELECT count(*) FROM performance_admin_inbox.nonces) AS nonces,
    (SELECT count(*) FROM performance_admin_inbox.request_audit) AS requests,
    (SELECT count(*) FROM performance_admin_inbox.principal_events) AS authorities')->fetch(PDO::FETCH_ASSOC);
$cases = [
    ['principals', 'SELECT(issuer)'],
    ['principals', 'INSERT(issuer)'],
    ['principals', 'UPDATE(active)'],
    ['service_delegations', 'SELECT(revision)'],
    ['service_delegations', 'INSERT(revision)'],
    ['service_delegations', 'UPDATE(revision)'],
    ['service_delegations', 'REFERENCES(revision)'],
    ['request_audit', 'SELECT(outcome)'],
    ['request_audit', 'UPDATE(outcome)'],
    ['request_audit', 'REFERENCES(outcome)'],
    ['principal_events', 'SELECT(new_state)'],
    ['principal_events', 'INSERT(event)'],
    ['principal_events', 'UPDATE(new_state)'],
    ['principal_events', 'REFERENCES(id)'],
];
foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER'] as $privilege) {
    $cases[] = ['principal_events', $privilege];
}
try {
    $service = new Service($runtime, $keyring);
    $baseline = $service->handle($wire(), $fingerprint);
    $ok($baseline['status'] === 'unavailable' && $baseline['metrics'] === null && $baseline['billable'] === false,
        'minimum role remains usable without official data');
    foreach ($cases as [$table, $privilege]) {
        // Table and privilege names come only from the literal cases above.
        $owner->exec("GRANT $privilege ON performance_admin_inbox.$table TO phase7_reader");
        $before = $counts();
        try {
            $error = null;
            try {
                (new Service($runtime, '/missing-never-read'))->handle($wire(), $fingerprint);
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
            $ok($error === 'Unsafe runtime role', "$table $privilege denied before key access");
            $ok($before === $counts(), "$table $privilege denied without inbox/audit side effects");
        } finally {
            $owner->exec("REVOKE $privilege ON performance_admin_inbox.$table FROM phase7_reader");
        }
    }
    $ok($service->handle($wire(), $fingerprint)['status'] === 'unavailable', 'minimum role works after revoking excess grants');
    echo "OK: $n runtime column/authority-ledger privilege assertions; disposable CI only\n";
} finally {
    unlink($keyring);
}
