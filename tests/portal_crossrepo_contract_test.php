<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';

$portalDir = __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce';
$portalClass = $portalDir . '/ApprovedOfferCatalog.php';
$portalJson = $portalDir . '/approved-catalog.json';
if (!is_file($portalClass) || !is_file($portalJson)) {
    throw new RuntimeException('Missing pinned public Portal checkout; refuse parity claim.');
}
require_once $portalClass;
$reference = new \App\Homologation\Commerce\ApprovedOfferCatalog();
$fixture = json_decode(file_get_contents(__DIR__ . '/../contracts/portal/performance-offer-v4.pinned.json'), true, 512, JSON_THROW_ON_ERROR);
$rawCatalog = file_get_contents($portalJson);
$data = json_decode($rawCatalog, true, 512, JSON_THROW_ON_ERROR);

function expectExact(mixed $a, mixed $b, string $case): void {
    if ($a !== $b) throw new RuntimeException($case . ' mismatch: ' . var_export($a, true) . ' vs ' . var_export($b, true));
}
// This is the real Git blob SHA of the Portal catalog, not its runtime SHA256.
// It ensures the checked-out bytes match the frozen, independently reviewed input.
$gitBlobSha = sha1('blob ' . strlen($rawCatalog) . "\0" . $rawCatalog);
expectExact($fixture['source']['catalog_blob_sha'], $gitBlobSha, 'pinned catalog blob');
expectExact($fixture['catalog_schema_version'], $data['schema_version'], 'catalog schema');
$offers = array_values(array_filter($data['offers'], static fn(array $offer): bool => $offer['id'] === 'performance'));
expectExact(1, count($offers), 'single Performance offer');
$offer = $offers[0];
foreach ([
    'management_fee_basis_points' => 'management_fee_basis_points',
    'minimum_media_investment_per_channel_cents' => 'minimum_media_investment_per_channel_cents',
    'channels' => 'channels',
    'reference_period' => 'reference_period',
    'operational_settlement_policy' => 'operational_settlement_policy',
    'media_spend_paid_directly_to_platforms' => 'media_spend_paid_directly_to_platforms',
    'direct_checkout_enabled' => 'direct_checkout_enabled',
] as $local => $remote) {
    expectExact($fixture[$local], $offer[$remote], 'canonical catalog ' . $remote);
}
// Real Portal class, pinned at the reviewed commit, exercises its actual quote code.
$plans = [
    ['google_ads' => 70000],
    ['meta_ads' => 70001],
    ['google_ads' => 70003],
    ['google_ads' => 70003, 'meta_ads' => 70003],
    ['meta_ads' => 100000000, 'google_ads' => 70000],
    ['google_ads' => 100000000, 'meta_ads' => 100000000],
];
foreach ($plans as $media) {
    $quote = $reference->quote('performance', $media);
    $actual = [];
    foreach ($media as $channel => $amount) $actual[$channel] = $amount;
    $draft = \Imagine\Performance\Commercial\PortalQuoteReconciliationPreview::build($quote, '2026-11', $actual);
    expectExact($quote['estimated_management_fee_cents'], $draft['estimated_advance_fee_cents'], 'live Portal quote parity');
    expectExact(0, $draft['review_only_adjustment_cents'], 'no adjustment when equal');
    expectExact($reference->version(), $draft['origin_catalog_sha256_claim'], 'source version syntax');
    expectExact(false, $draft['asaas_write_enabled'], 'never enable PSP');
    expectExact(false, $draft['quote_origin_authenticated'], 'checked-out catalog is not live auth');
    expectExact(false, $draft['actual_spend_verified'], 'synthetic amounts not independently verified');
    $actualZero = array_fill_keys(array_keys($media), 0);
    $zero = \Imagine\Performance\Commercial\PortalQuoteReconciliationPreview::build($quote, '2026-11', $actualZero);
    expectExact(-$quote['estimated_management_fee_cents'], $zero['review_only_adjustment_cents'], 'full unused budget candidate');
}
$tooHigh = false;
try { $reference->quote('performance', ['google_ads' => $fixture['quote_maximum_per_channel_cents'] + 1]); }
catch (InvalidArgumentException) { $tooHigh = true; }
expectExact(true, $tooHigh, 'Portal cap is enforced by actual class');
$bad = $reference->quote('performance', ['google_ads' => 70000]);
$bad['amount_now_cents'] += 1;
$rejected = false;
try { \Imagine\Performance\Commercial\PortalQuoteReconciliationPreview::build($bad, '2026-11', ['google_ads' => 70000]); }
catch (InvalidArgumentException) { $rejected = true; }
expectExact(true, $rejected, 'tampered quote rejected');
echo "OK: real pinned Portal class + catalog match Performance offline preview\n";
