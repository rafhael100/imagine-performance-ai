<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-issuer-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-issuer-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';
require_once __DIR__ . '/../labs/finance-pg/PgFinanceCloseDrafts.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard;
use Imagine\Performance\Commercial\MonthlySpendEvidencePreview;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;
use Imagine\Performance\Commercial\Lab\PgFinanceCloseDrafts;

function sameRound(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException('Cross-repository mismatch: ' . $message);
}
function blockedRound(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Expected cross-repo refusal: ' . $message);
}
$dsn = getenv('PERF_LAB_PG_DSN');
$user = getenv('PERF_LAB_PG_USER');
$pass = getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || !$user || !$pass || getenv('APP_ENV') !== 'testing' || getenv('PORTAL_PERF_SIGN_LAB') !== '1') {
    throw new RuntimeException('Only isolated testing with PostgreSQL and explicit Portal issuer gate.');
}
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$catalog = new ApprovedOfferCatalog();
$tenant = 'tenant_crossrepo_12345678';
$keyId = 'k_lab001';
$nonce = 'nonce_crossrepo_123456789abcdef';
$secret = str_repeat('T', 48); // synthetic CI only, never production
$now = 1794660000;
$quote = $catalog->quote('performance', ['google_ads'=>70000]);
$signed = PortalPerformanceQuoteLabIssuer::issue(
    $catalog, ['google_ads'=>70000], $tenant, $keyId, $nonce, $now - 2, $now + 120, $secret
);
sameRound($quote, $signed['quote'], 'real Portal class returns its server quote');
$nonceDb = new PgNonceClaims($pdo);
$actual = ['google_ads'=>90000]; // synthetic, not official Google data
$keyResolver = static fn(string $t,string $k): ?string => $t === 'tenant_crossrepo_12345678' && $k === 'k_lab001' ? str_repeat('T',48) : null;
$verify = static fn(array $envelope) => PortalSignedQuoteGuard::verifyAndPreview(
    $envelope, $tenant, $catalog->version(), '2026-11', $actual, $now, $keyResolver, [$nonceDb, 'claim']
);
$auth = $verify($signed);
sameRound(true, $auth['quote_claim_hmac_verified'], 'genuine HMAC roundtrip');
sameRound(false, $auth['portal_iam_authenticated'], 'not real IAM');
sameRound(false, $auth['provider_spend_verified'], 'not official provider spend');
sameRound(false, $auth['asaas_write_enabled'], 'no Asaas');
sameRound(4000, $auth['preview']['review_only_adjustment_cents'], '20 percent reconciliation');
blockedRound(fn() => $verify($signed), 'durable nonce replay from real Portal issuer');
$tampered = $signed;
$tampered['quote']['amount_now_cents'] = 1;
blockedRound(fn() => $verify($tampered), 'financial tampering after signature');
$monthlyEvidence = MonthlySpendEvidencePreview::build(
    $signed['quote'], '2026-11',
    [['channel'=>'google_ads','period'=>'2026-11',
      'report_id'=>'portal_roundtrip_synthetic_001', 'source'=>'synthetic_fixture',
      'spend_cents'=>90000]]
);
$closeDb = new PgFinanceCloseDrafts($pdo);
$draft = $closeDb->stage($tenant,$auth,$monthlyEvidence);
sameRound(true, $draft['created'], 'draft stored');
sameRound(false, $draft['billable'], 'cannot bill');
sameRound('lab_draft_unverified_spend', $draft['status'], 'no source proof');
sameRound(false, $closeDb->stage($tenant,$auth,$monthlyEvidence)['created'], 'repeated close same id');
echo "OK: real Portal issuer -> Performance HMAC guard -> PG replay ledger -> unbillable close draft\n";
