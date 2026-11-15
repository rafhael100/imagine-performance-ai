<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';

use Imagine\Performance\Commercial\PortalQuoteReconciliationPreview as Preview;

function checkValue(mixed $expected, mixed $actual, string $case): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function mustDeny(callable $action, string $case): void
{
    try { $action(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected rejection: ' . $case);
}
// Synthetic fixture reproduces fields of Portal ApprovedOfferCatalog::quote();
// structurally valid does not mean authenticated or approved for charging.
$quote = [
    'catalog_version' => str_repeat('a', 64),
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
$actual = ['google_ads' => 60000, 'meta_ads' => 90000];
$result = Preview::build($quote, '2026-11', $actual);
checkValue(28001, $result['estimated_advance_fee_cents'], 'estimated advance');
checkValue(30000, $result['unverified_earned_fee_cents'], 'earned amount');
checkValue(1999, $result['review_only_adjustment_cents'], 'monthly adjustment');
checkValue(false, $result['quote_origin_authenticated'], 'never assume authenticated');
checkValue(false, $result['actual_spend_verified'], 'never assume evidence confirmed');
checkValue(false, $result['finance_approved'], 'no financial approval');
checkValue(false, $result['asaas_write_enabled'], 'no gateway');
checkValue(false, $result['ads_write_enabled'], 'no campaign changes');
checkValue('requires_trusted_portal_identity_spend_evidence_and_finance_approval', $result['state'], 'always review');
checkValue(str_repeat('a',64), $result['origin_catalog_sha256_claim'], 'unverified catalog claim');

$reordered = $quote;
$reordered['media_by_channel_cents'] = ['meta_ads' => 70000, 'google_ads' => 70003];
checkValue($result['quote_fingerprint_sha256_not_signature'], Preview::build($reordered, '2026-11', ['meta_ads' => 90000, 'google_ads' => 60000])['quote_fingerprint_sha256_not_signature'], 'fingerprint stable across input order');
$zero = Preview::build($quote, '2026-11', ['meta_ads' => 0, 'google_ads' => 0]);
checkValue(-28001, $zero['review_only_adjustment_cents'], 'refund candidate requires review');

$bad = $quote; $bad['estimated_management_fee_cents']++;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'tampered fee');
$bad = $quote; $bad['amount_now_cents']++;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'tampered due');
$bad = $quote; $bad['real_charge_enabled'] = true;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'real charge request');
$bad = $quote; $bad['media_paid_directly_to_platforms'] = false;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'media cannot be billed by Imagine');
$bad = $quote; $bad['settlement_policy'] = 'auto-charge';
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'unauthorized policy');
$bad = $quote; $bad['catalog_version'] = 'unverified';
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'invalid version syntax');
$bad = $quote; $bad['charge_id'] = 'pay_fake';
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'unexpected billing field');
$bad = $quote; $bad['media_by_channel_cents']['meta_ads'] = 69999;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'below contracted media floor');
$bad = $quote; $bad['media_by_channel_cents']['google_ads'] = 100000001;
mustDeny(fn() => Preview::build($bad, '2026-11', $actual), 'above sandbox cap');
mustDeny(fn() => Preview::build($quote, '2026-11', ['google_ads' => 60000]), 'missing actual channel');
mustDeny(fn() => Preview::build($quote, '2026-11', ['google_ads' => 60000, 'meta_ads' => -1]), 'negative actual');
mustDeny(fn() => Preview::build($quote, '2026-11', ['google_ads' => 60000, 'meta_ads' => 100000001]), 'actual above cap');
mustDeny(fn() => Preview::build($quote, '2026-00', $actual), 'invalid month');
echo "OK: Portal quote read-only handshake preview\n";
