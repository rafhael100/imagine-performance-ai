<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';

use Imagine\Performance\Commercial\PortalSignedQuoteGuard;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;

function isSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException($message . ' differs');
}
function rejects(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected refusal: ' . $message);
}
$dsn = getenv('PERF_LAB_PG_DSN');
$user = getenv('PERF_LAB_PG_USER');
$password = getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || !$user || !$password) {
    throw new RuntimeException('Isolated PostgreSQL lab configuration required; no fallback to production.');
}
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$ledger = new PgNonceClaims($db);
$tenant1 = 'tenant_test_alpha9876';
$tenant2 = 'tenant_test_beta87654';
$keyId = 'k_lab001';
$nonce = 'nonce_1234567890abcdef_one';
$now = 1794660000;
$secret = str_repeat('N', 48); // synthetic key used solely in CI
$catalog = str_repeat('b', 64);
$quote = [
    'catalog_version' => $catalog,
    'product' => 'performance',
    'currency' => 'BRL',
    'gateway' => 'asaas',
    'real_charge_enabled' => false,
    'media_by_channel_cents' => ['google_ads' => 70000],
    'media_paid_directly_to_platforms' => true,
    'management_fee_basis_points' => 2000,
    'estimated_management_fee_cents' => 14000,
    'reference_period' => 'monthly',
    'amount_now_cents' => 14000,
    'settlement_policy' => 'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment',
];
$build = static function(string $tenant, string $nonce) use ($quote, $now, $secret): array {
    $signed = [
        'tenant_id' => $tenant,
        'key_id' => 'k_lab001',
        'nonce' => $nonce,
        'audience' => 'imagine-performance-finance-preview',
        'issued_at' => $now - 2,
        'expires_at' => $now + 120,
        'quote' => $quote,
    ];
    $signed['signature'] = hash_hmac('sha256', PortalSignedQuoteGuard::signingBytes($signed), $secret);
    return $signed;
};
$resolve = static fn(string $tenant, string $key): ?string =>
    in_array($tenant, ['tenant_test_alpha9876', 'tenant_test_beta87654'], true) && $key === 'k_lab001'
        ? str_repeat('N', 48) : null;
$claim = [$ledger, 'claim'];
$s1 = $build($tenant1, $nonce);
$first = PortalSignedQuoteGuard::verifyAndPreview(
    $s1, $tenant1, $catalog, '2026-11', ['google_ads' => 70000],
    $now, $resolve, $claim
);
isSame(true, $first['quote_claim_hmac_verified'], 'signed claim');
isSame(false, $first['asaas_write_enabled'], 'no Asaas');
isSame(false, $first['provider_spend_verified'], 'no official spend');
rejects(fn() => PortalSignedQuoteGuard::verifyAndPreview(
    $s1, $tenant1, $catalog, '2026-11', ['google_ads' => 70000],
    $now, $resolve, $claim
), 'same signed envelope after committed nonce');
isSame(false, $ledger->claim($tenant1, 'k_rotated', $nonce, $now + 120), 'same tenant replay after key rotation');
isSame(true, $ledger->claim($tenant2, $keyId, $nonce, $now + 120), 'distinct tenant scoped claim');

// Verify RLS SELECT visibility under explicitly set transaction-local tenant.
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id', ?, true)")->execute([$tenant1]);
isSame(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(), 'RLS tenant 1');
$db->rollBack();
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id', ?, true)")->execute([$tenant2]);
isSame(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(), 'RLS tenant 2');
$db->rollBack();

// RLS forbids direct cross-tenant INSERT despite an otherwise valid SQL statement.
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id', ?, true)")->execute([$tenant1]);
$blocked = false;
try {
    $s = $db->prepare('INSERT INTO perf_fin_lab.signed_quote_nonces (tenant_id, nonce, key_id, expires_at) VALUES (?, ?, ?, ?)');
    $s->execute([$tenant2, 'nonce_unapproved_xxxxx_001', $keyId, $now + 120]);
} catch (PDOException) { $blocked = true; }
$db->rollBack();
isSame(true, $blocked, 'cross-tenant RLS write denied');

rejects(fn() => $ledger->claim('invalid', $keyId, 'nonce_1234567890abcdef_002', $now + 120), 'invalid tenant');
rejects(fn() => $ledger->claim($tenant1, 'notkey', 'nonce_1234567890abcdef_003', $now + 120), 'invalid key');
rejects(fn() => $ledger->claim($tenant1, $keyId, 'short', $now + 120), 'invalid nonce');
$db->beginTransaction();
rejects(fn() => $ledger->claim($tenant1, $keyId, 'nonce_1234567890abcdef_004', $now + 120), 'nested transaction rejected');
$db->rollBack();
echo "OK: signed envelope replay blocked by PostgreSQL unique ledger, tenant RLS isolation checked\n";
