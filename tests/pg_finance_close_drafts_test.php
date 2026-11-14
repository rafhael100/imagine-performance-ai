<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';
require_once __DIR__ . '/../labs/finance-pg/PgFinanceCloseDrafts.php';

use Imagine\Performance\Commercial\PortalSignedQuoteGuard;
use Imagine\Performance\Commercial\MonthlySpendEvidencePreview;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;
use Imagine\Performance\Commercial\Lab\PgFinanceCloseDrafts;

function sameDraft(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException('Mismatch: ' . $message);
}
function cannot(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Expected rejection: ' . $message);
}
$dsn = getenv('PERF_LAB_PG_DSN');
$user = getenv('PERF_LAB_PG_USER');
$pass = getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || !$user || !$pass) throw new RuntimeException('Database environment not set for isolated lab.');
$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
]);
$nonceLedger = new PgNonceClaims($db);
$drafts = new PgFinanceCloseDrafts($db);
$now = 1794660000;
$catalog = str_repeat('b',64);
$secret = str_repeat('Q', 48); // synthetic CI secret, not used in any service
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
$buildSigned = static function(string $tenant, string $nonce) use ($quote, $now, $secret): array {
    $env = ['audience' => 'imagine-performance-finance-preview',
        'tenant_id' => $tenant, 'key_id' => 'k_lab001', 'nonce' => $nonce,
        'issued_at' => $now - 2, 'expires_at' => $now + 120, 'quote' => $quote];
    $env['signature'] = hash_hmac('sha256', PortalSignedQuoteGuard::signingBytes($env), $secret);
    return $env;
};
$resolver = static fn(string $tenant, string $id): ?string =>
    in_array($tenant, ['tenant_stage_alpha9876', 'tenant_stage_beta9876'], true)
        && $id === 'k_lab001' ? str_repeat('Q',48) : null;
$oneTenant = 'tenant_stage_alpha9876';
$twoTenant = 'tenant_stage_beta9876';
$preview = static function(string $tenant, string $nonce) use ($buildSigned, $catalog, $now, $resolver, $nonceLedger): array {
    return PortalSignedQuoteGuard::verifyAndPreview(
        $buildSigned($tenant, $nonce), $tenant, $catalog, '2026-11',
        ['google_ads' => 100000], $now, $resolver, [$nonceLedger, 'claim']
    );
};
$records = [['channel'=>'google_ads','period'=>'2026-11',
    'report_id'=>'synthetic_receipt_01','source'=>'synthetic_fixture','spend_cents'=>100000]];
$evidence = MonthlySpendEvidencePreview::build($quote, '2026-11', $records);
$signed1 = $preview($oneTenant, 'nonce_close_123456abcdef0001');
$draft1 = $drafts->stage($oneTenant, $signed1, $evidence);
sameDraft(true, $draft1['created'], 'first close created');
sameDraft(false, $draft1['billable'], 'never billable');
sameDraft('lab_draft_unverified_spend', $draft1['status'], 'always unverified');
$again = $drafts->stage($oneTenant, $signed1, $evidence);
sameDraft(false, $again['created'], 'identical retry idempotent');
sameDraft($draft1['close_id'], $again['close_id'], 'same close ID');
$changed = $records;
$changed[0]['report_id'] = 'synthetic_receipt_02';
$changedEvidence = MonthlySpendEvidencePreview::build($quote, '2026-11', $changed);
cannot(fn() => $drafts->stage($oneTenant, $signed1, $changedEvidence), 'different evidence cannot overwrite immutable close');
$altered = $evidence; $altered['preview']['review_only_adjustment_cents'] = 999999;
cannot(fn() => $drafts->stage($oneTenant, $signed1, $altered), 'inconsistent monetary values');
$altered = $evidence; $altered['provider_spend_verified'] = true;
cannot(fn() => $drafts->stage($oneTenant, $signed1, $altered), 'fixture cannot claim provider verification');
$alteredSigned = $signed1; $alteredSigned['finance_approved'] = true;
cannot(fn() => $drafts->stage($oneTenant, $alteredSigned, $evidence), 'no synthetic finance approval');
cannot(fn() => $drafts->stage($twoTenant, $signed1, $evidence), 'tenant boundary');
$signed2 = $preview($twoTenant, 'nonce_close_123456abcdef0002');
$draft2 = $drafts->stage($twoTenant, $signed2, $evidence);
sameDraft(true, $draft2['created'], 'second tenant draft stored');
sameDraft(false, $draft2['billable'], 'second tenant also unbillable');

foreach ([$oneTenant,$twoTenant] as $tenant) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id', ?, true)")->execute([$tenant]);
    sameDraft(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn(), 'RLS draft tenant visibility');
    $row = $db->query('SELECT billable, finance_approved, source_verified, adjustment_cents FROM perf_fin_lab.reconciliation_drafts')->fetch(PDO::FETCH_ASSOC);
    sameDraft('f', $row['billable'], 'database billable flag');
    sameDraft('f', $row['finance_approved'], 'database finance flag');
    sameDraft('f', $row['source_verified'], 'database source flag');
    sameDraft(6000, (int)$row['adjustment_cents'], 'monthly 20 percent difference');
    $db->rollBack();
}
$updatesBlocked = false;
try {
    $db->exec("UPDATE perf_fin_lab.reconciliation_drafts SET adjustment_cents = 0 WHERE tenant_id = 'tenant_stage_alpha9876'");
} catch (PDOException) { $updatesBlocked = true; }
sameDraft(true, $updatesBlocked, 'runtime database role cannot update draft');
echo "OK: immutable unbillable monthly close drafts, durable retry and tenant RLS\n";
