<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';

use Imagine\Performance\Commercial\PortalSignedQuoteGuard as Guard;

function eq(mixed $expected, mixed $received, string $name): void {
    if ($expected !== $received) throw new RuntimeException($name . ': unexpected value');
}
function denies(callable $call, string $name): void {
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Must reject: ' . $name);
}
$secret = str_repeat('S', 48); // synthetic, non-production test string
$tenant = 'tenant_demo_12345678';
$keyId = 'k_lab001';
$catalog = str_repeat('a', 64);
$now = 1794660000;
$quote = [
    'catalog_version' => $catalog,
    'product' => 'performance',
    'currency' => 'BRL',
    'gateway' => 'asaas',
    'real_charge_enabled' => false,
    'media_by_channel_cents' => ['google_ads' => 70003, 'meta_ads' => 70000],
    'media_paid_directly_to_platforms' => true,
    'management_fee_basis_points' => 2000,
    'estimated_management_fee_cents' => 28001,
    'reference_period' => 'monthly',
    'amount_now_cents' => 28001,
    'settlement_policy' => 'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment',
];
$env = [
    'audience' => 'imagine-performance-finance-preview',
    'expires_at' => $now + 120,
    'issued_at' => $now - 1,
    'key_id' => $keyId,
    'nonce' => 'nonce_0123456789abcdef_01',
    'quote' => $quote,
    'tenant_id' => $tenant,
];
$sign = static function(array $payload) use ($secret): array {
    $payload['signature'] = hash_hmac('sha256', Guard::signingBytes($payload), $secret);
    return $payload;
};
$signed = $sign($env);
$resolver = static fn(string $scope, string $key): ?string =>
    $scope === 'tenant_demo_12345678' && $key === 'k_lab001' ? str_repeat('S', 48) : null;
$claims = [];
$claim = static function(string $tenant, string $key, string $nonce, int $expires) use (&$claims): bool {
    $id = $tenant . ':' . $key . ':' . $nonce;
    if (isset($claims[$id])) return false;
    $claims[$id] = $expires;
    return true;
};
$spend = ['meta_ads' => 90000, 'google_ads' => 60000];
$result = Guard::verifyAndPreview($signed, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim);
eq(true, $result['quote_claim_hmac_verified'], 'HMAC verified');
eq(false, $result['portal_iam_authenticated'], 'not IAM');
eq(false, $result['provider_spend_verified'], 'spend unverified');
eq(false, $result['finance_approved'], 'finance not approved');
eq(false, $result['asaas_write_enabled'], 'no payments');
eq(false, $result['ads_write_enabled'], 'no ad mutation');
eq(1999, $result['preview']['review_only_adjustment_cents'], 'correct simulated adjustment');
eq(1, count($claims), 'nonce claimed once');
denies(fn() => Guard::verifyAndPreview($signed, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'replay');
eq(1, count($claims), 'replay not inserted');
$swapOrder = $env;
$swapOrder['quote']['media_by_channel_cents'] = ['meta_ads' => 70000, 'google_ads' => 70003];
eq($signed['signature'], $sign($swapOrder)['signature'], 'canonical map order');
$try = $signed; $try['quote']['estimated_management_fee_cents'] += 1;
denies(fn() => Guard::verifyAndPreview($try, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'quote tamper');
$try = $signed; $try['tenant_id'] = 'tenant_other_12345678';
denies(fn() => Guard::verifyAndPreview($try, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'tenant mismatch');
$try = $signed; $try['audience'] = 'other';
denies(fn() => Guard::verifyAndPreview($try, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'audience mismatch');
$try = $signed; $try['signature'] = str_repeat('0', 64);
denies(fn() => Guard::verifyAndPreview($try, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'bad mac');
$try = $signed; $try['key_id'] = 'k_other';
denies(fn() => Guard::verifyAndPreview($try, $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'unknown key');
denies(fn() => Guard::verifyAndPreview($signed, $tenant, str_repeat('b', 64), '2026-11', $spend, $now, $resolver, $claim), 'stale catalog');
denies(fn() => Guard::verifyAndPreview($signed, $tenant, $catalog, '2026-11', $spend, $now + 120, $resolver, $claim), 'expired');
$try = $env; $try['expires_at'] = $now + 301;
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'excess TTL');
$try = $env; $try['issued_at'] = $now + 31;
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, $resolver, $claim), 'future issue');
$try = $env; $try['quote']['estimated_management_fee_cents'] += 1;
$called = false;
$tracking = static function(string $tenant, string $key, string $nonce, int $exp) use (&$called): bool { $called = true; return true; };
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, $resolver, $tracking), 're-signed incorrect fee');
eq(false, $called, 'invalid quote must not consume nonce');
$try = $env; $try['nonce'] = 'nonce_0123456789abcdef_02';
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, $resolver, fn(...$args): bool => false), 'nonce store down');
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, fn(...$args): ?string => null, $claim), 'key unavailable');
denies(fn() => Guard::verifyAndPreview($sign($try), $tenant, $catalog, '2026-11', $spend, $now, fn(...$args): string => 'weak', $claim), 'weak key');
$extra = $env; $extra['quote']['arbitrary'] = str_repeat('a', 9000);
denies(fn() => $sign($extra), 'oversized signed envelope');
$extra = $env; $extra['quote']['unknown_array'] = [1,2,3];
denies(fn() => $sign($extra), 'non-canonical list');
echo "OK: signed Portal quote lab validates MAC, tenant, time, nonce and fails closed\n";
