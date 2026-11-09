<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticReplayBoundTenantUnitOfWork.php';

use Imagine\Protocol\Lab\SyntheticReplayBoundTenantUnitOfWork;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F28_REPLAY_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F1-28 must run in disposable CI PostgreSQL.');
}
$key = (string)getenv('PERF_F26_ISSUER_KEY');
if (!preg_match('/^[0-9a-f]{64}$/D', $key)) {
    throw new RuntimeException('Missing ephemeral F1-26 signing secret in CI.');
}
$A = '11111111-1111-4111-8111-111111111111';
$B = '22222222-2222-4222-8222-222222222222';
$sign = static function(string $tenant, int $exp, string $nonce, string $secret): string {
    $data = implode('|', [$tenant, (string)$exp, $nonce, 'perf-f26-ci']);
    return $data.'|'.hash_hmac('sha256', $data, hex2bin($secret));
};
$pdo = static fn(string $role, string $pw): PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $role, $pw, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$user = $pdo('perf_campaign_test_user', 'ci-f22-role-only');
$admin = $pdo('postgres', 'ci-f22-db-only');
$uow = new SyntheticReplayBoundTenantUnitOfWork($user);
$count = 0;
$check = static function(string $name, mixed $got, mixed $expected) use (&$count): void {
    if ($got !== $expected) {
        throw new RuntimeException('F28 '.$name.' got '
            .var_export($got, true).' expected '.var_export($expected, true));
    }
    echo 'PASS F28 '.++$count.' '.$name.PHP_EOL;
};
$read = static fn(PDO $db): int => (int)$db->query(
    'SELECT count(*) FROM performance_managed_campaigns'
)->fetchColumn();
$nonceCount = static fn(): int => (int)$admin->query(
    'SELECT count(*) FROM perf_f28_replay.used_nonces'
)->fetchColumn();
$expiry = time() + 130;
$first = $sign($A, $expiry, 'nonce-f28-first', $key);
$check('initial nonce ledger is empty', $nonceCount(), 0);
$check('connection without token sees no campaigns', $read($user), 0);
$ok = $uow->execute($first, $read);
$check('first signed A claim allowed', $ok['state'], 'allowed_synthetic_only');
$check('first signed A claim sees two A campaigns', $ok['value'], 2);
$check('nonce consumed exactly once after commit', $nonceCount(), 1);
$check('reused connection cleared after successful commit', $read($user), 0);
$check('identical signed claim cannot be replayed after commit',
    $uow->execute($first, $read)['state'], 'replay_or_nonce_denied');
$check('reissued signed claim with same nonce cannot replay',
    $uow->execute($sign($A, $expiry+1, 'nonce-f28-first', $key), $read)['state'],
    'replay_or_nonce_denied');
$check('denied replay never adds ledger rows', $nonceCount(), 1);

$fresh = $sign($A, $expiry, 'nonce-f28-fresh', $key);
$check('fresh signed nonce permits a new transaction',
    $uow->execute($fresh, $read)['state'], 'allowed_synthetic_only');
$check('fresh nonce adds a committed ledger row', $nonceCount(), 2);
$check('forged tenant B rejected',
    $uow->execute($sign($B, $expiry, 'nonce-f28-b', $key), $read)['state'],
    'signed_tenant_denied');
$bad = explode('|', $sign($A, $expiry, 'nonce-f28-forged', $key));
$bad[0] = $B;
$check('tampered signature rejected',
    $uow->execute(implode('|', $bad), $read)['state'],
    'signed_tenant_denied');
$check('expired token rejected',
    $uow->execute($sign($A, time()-2, 'nonce-f28-expired', $key), $read)['state'],
    'signed_tenant_denied');
$check('failed signatures leave nonce ledger unchanged', $nonceCount(), 2);

$abort = $sign($A, $expiry, 'nonce-f28-rollback', $key);
$check('exception rolls back operation and nonce insert',
    $uow->execute($abort, static function(PDO $db): never {
        $db->query('SELECT count(*) FROM performance_managed_campaigns');
        throw new RuntimeException('deliberate rollback');
    })['state'], 'transaction_rolled_back');
$check('rollback does not durably consume nonce (known limitation)',
    $nonceCount(), 2);
$check('rolled-back nonce may be retried: NOT durable anti-replay',
    $uow->execute($abort, $read)['state'], 'allowed_synthetic_only');
$check('retry after rollback consumes nonce on commit', $nonceCount(), 3);
$check('retry after successful commit now blocked',
    $uow->execute($abort, $read)['state'], 'replay_or_nonce_denied');
$check('no tenant context persists through retries', $read($user), 0);

$wrong = new SyntheticReplayBoundTenantUnitOfWork($admin);
$check('superuser never accepted as application actor',
    $wrong->execute($first, $read)['state'], 'wrong_database_role');
$denied = false;
try {
    $user->query('SELECT count(*) FROM perf_f28_replay.used_nonces');
} catch (PDOException $e) {
    $denied = $e->getCode() === '42501';
}
$check('app role cannot read nonce ledger', $denied, true);
$denied = false;
try {
    $user->exec('DELETE FROM perf_f28_replay.used_nonces');
} catch (PDOException $e) {
    $denied = $e->getCode() === '42501';
}
$check('app role cannot erase the nonce ledger', $denied, true);

$oldUnused = $sign($A, $expiry, 'nonce-f28-old-key-unused', $key);
$newKey = bin2hex(random_bytes(32));
$rotate = $admin->prepare(
    "UPDATE perf_f26_identity.test_issuer SET secret=decode(?, 'hex') WHERE id='ci-issuer'"
);
$rotate->execute([$newKey]);
$check('ephemeral issuer key rotation changes exactly one row', $rotate->rowCount(), 1);
$check('unconsumed claim signed by revoked key is denied',
    $uow->execute($oldUnused, $read)['state'], 'signed_tenant_denied');
$check('old key cannot reissue a used nonce',
    $uow->execute($sign($A, $expiry+2, 'nonce-f28-first', $key), $read)['state'],
    'signed_tenant_denied');
$check('fresh claim signed by rotated key is accepted',
    $uow->execute($sign($A, $expiry, 'nonce-f28-new-key', $newKey), $read)['state'],
    'allowed_synthetic_only');
$check('new key cannot reuse a nonce spent under previous key',
    $uow->execute($sign($A, $expiry, 'nonce-f28-first', $newKey), $read)['state'],
    'replay_or_nonce_denied');
$check('ledger records only committed successful novel nonces', $nonceCount(), 4);
$check('no actual dispatch enabled by experiments',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns WHERE dispatch_state<>'disabled'"
    )->fetchColumn(), 0);

putenv('PERF_F28_REPLAY_LAB=0');
$check('lab gate can be turned off', $uow->execute(
    $sign($A, $expiry, 'nonce-f28-disabled', $newKey), $read
)['state'], 'lab_disabled');
$check('no context survives disabled call', $read($user), 0);

echo "PASS F28 {$count} disposable nonce-replay and key-rotation checks".PHP_EOL;
