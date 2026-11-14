<?php
declare(strict_types=1);

/**
 * Verifies that a freshly RESTORED disposable PostgreSQL lab database keeps
 * already-consumed Portal quote nonces and unbillable monthly close drafts.
 * This is not backup durability/DR certification for a production system.
 */
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard as Guard;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;

function assertRestored(mixed $expected, mixed $value, string $reason): void {
    if ($value !== $expected) throw new RuntimeException('Restore check: ' . $reason);
}
$dsn = getenv('PERF_LAB_RESTORE_DSN');
$user = getenv('PERF_LAB_PG_USER');
$pass = getenv('PERF_LAB_PG_PASSWORD');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=perf_fin_lab_restore') ||
    !is_string($user) || $user !== 'perf_fin_lab_app' ||
    !is_string($pass) || $pass === '' ||
    getenv('APP_ENV') !== 'testing' || getenv('PORTAL_PERF_SIGN_LAB') !== '1') {
    throw new RuntimeException('Restricted laboratory restore configuration required.');
}
$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
assertRestored('perf_fin_lab_app', $db->query('SELECT current_user')->fetchColumn(), 'non-owner application role');
foreach (['tenant_crossrepo_alpha1234','tenant_crossrepo_beta5678'] as $tenant) {
    $db->beginTransaction();
    $scope = $db->prepare("SELECT set_config('app.tenant_id', :tenant, true)");
    $scope->execute(['tenant'=>$tenant]);
    assertRestored(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(),
        'restored nonce per tenant');
    assertRestored(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn(),
        'restored monthly draft per tenant');
    $row = $db->query('SELECT billable, finance_approved, source_verified, adjustment_cents FROM perf_fin_lab.reconciliation_drafts')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Restore lost draft');
    if (!in_array($row['billable'], [false, 0, '0', 'f'], true)) {
        throw new RuntimeException('Restore check: draft not billable after restore');
    }
    if (!in_array($row['finance_approved'], [false, 0, '0', 'f'], true)) {
        throw new RuntimeException('Restore check: draft cannot gain approval after restore');
    }
    if (!in_array($row['source_verified'], [false, 0, '0', 'f'], true)) {
        throw new RuntimeException('Restore check: provider source still unverified');
    }
    assertRestored($tenant === 'tenant_crossrepo_alpha1234' ? -2000 : -14000,
        (int)$row['adjustment_cents'], 'restored amount did not drift');
    $canUpdate = $db->query("SELECT has_table_privilege(current_user, 'perf_fin_lab.reconciliation_drafts', 'UPDATE')")->fetchColumn();
    if (!in_array($canUpdate, [false, 'f', '0', 0], true)) {
        throw new RuntimeException('Restored app role unexpectedly has UPDATE privilege.');
    }
    $db->rollBack();
}
$db->beginTransaction();
assertRestored(0, (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn(),
    'without session tenant no draft rows visible');
$db->rollBack();

$catalog = new ApprovedOfferCatalog();
$secret = str_repeat('R', 48); // synthetic CI key
$now = 1794660000;
$replayed = Issuer::issue(
    $catalog, ['google_ads'=>70000, 'meta_ads'=>90000],
    'tenant_crossrepo_alpha1234', 'k_crossrepo_lab01',
    'nonce_crossrepo_A_1234567890abcdef', $now - 5, $now + 120, $secret
);
$resolver = static fn(string $tenant, string $key): ?string =>
    $tenant === 'tenant_crossrepo_alpha1234' && $key === 'k_crossrepo_lab01'
        ? str_repeat('R', 48) : null;
$ledger = new PgNonceClaims($db);
$blocked = false;
try {
    Guard::verifyAndPreview(
        $replayed, 'tenant_crossrepo_alpha1234', $catalog->version(),
        '2026-11', ['google_ads'=>50000, 'meta_ads'=>100000], $now,
        $resolver, [$ledger, 'claim']
    );
} catch (InvalidArgumentException) {
    $blocked = true;
}
assertRestored(true, $blocked, 'replayed genuine quote was blocked by restored nonce ledger');
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id', :tenant, true)")
    ->execute(['tenant'=>'tenant_crossrepo_alpha1234']);
assertRestored(1, (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(),
    'restore replay did not create second nonce');
$db->rollBack();
echo "OK: disposable pg_dump/restore preserved unbillable drafts, tenant RLS and nonce replay fence\n";
