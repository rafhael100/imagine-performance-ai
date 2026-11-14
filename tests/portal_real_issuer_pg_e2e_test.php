<?php
declare(strict_types=1);

/**
 * Cross-repository PHP integration, pinned Portal source + Performance verifier
 * + disposable PostgreSQL 16 only. This is NOT a trusted runtime connection.
 */
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';
require_once __DIR__ . '/../labs/finance-pg/PgFinanceCloseDrafts.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer as PortalIssuer;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard as Guard;
use Imagine\Performance\Commercial\MonthlySpendEvidencePreview as Evidence;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;
use Imagine\Performance\Commercial\Lab\PgFinanceCloseDrafts;

function sameCross(mixed $expected, mixed $actual, string $case): void {
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true)
            . ' actual ' . var_export($actual, true));
    }
}
function rejectCross(callable $action, string $case): void {
    $denied = false;
    try { $action(); }
    catch (InvalidArgumentException | RuntimeException) { $denied = true; }
    if (!$denied) throw new RuntimeException('Unacceptable input was allowed: ' . $case);
}
foreach (['PERF_LAB_PG_DSN', 'PERF_LAB_PG_USER', 'PERF_LAB_PG_PASSWORD'] as $env) {
    if (!is_string(getenv($env)) || getenv($env) === '') {
        throw new RuntimeException('Missing isolated laboratory database configuration.');
    }
}
if (getenv('APP_ENV') !== 'testing' || getenv('PORTAL_PERF_SIGN_LAB') !== '1') {
    throw new RuntimeException('Cross-repository signer testing gate is disabled.');
}
$catalogFile = __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/approved-catalog.json';
$fixtureFile = __DIR__ . '/../contracts/portal/performance-offer-v4.pinned.json';
$rawCatalog = file_get_contents($catalogFile);
$fixture = json_decode(file_get_contents($fixtureFile), true, 512, JSON_THROW_ON_ERROR);
if (!is_string($rawCatalog) || !is_array($fixture)) {
    throw new RuntimeException('Pinned source missing.');
}
$blobSha = sha1('blob ' . strlen($rawCatalog) . "\0" . $rawCatalog);
sameCross($fixture['source']['catalog_blob_sha'], $blobSha, 'real Portal catalog Git blob');
sameCross('b12d518f86827f31bfd6308735dca8f986f43adc',
    $fixture['source']['commit'], 'expected Portal commercial base');

$catalog = new ApprovedOfferCatalog();
$db = new PDO(getenv('PERF_LAB_PG_DSN'), getenv('PERF_LAB_PG_USER'),
    getenv('PERF_LAB_PG_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$nonces = new PgNonceClaims($db);
$drafts = new PgFinanceCloseDrafts($db);
$secret = str_repeat('R', 48); // fixed synthetic CI secret: NEVER a real key
$now = 1794660000;
$keyId = 'k_crossrepo_lab01';
$tenantA = 'tenant_crossrepo_alpha1234';
$tenantB = 'tenant_crossrepo_beta5678';
$keyResolver = static fn(string $tenant, string $id): ?string =>
    in_array($tenant, ['tenant_crossrepo_alpha1234', 'tenant_crossrepo_beta5678'], true)
    && $id === 'k_crossrepo_lab01' ? str_repeat('R', 48) : null;

$issue = static function(string $tenant, string $nonce, array $budget) use ($catalog, $keyId, $now, $secret): array {
    return PortalIssuer::issue(
        $catalog, $budget, $tenant, $keyId, $nonce,
        $now - 5, $now + 120, $secret
    );
};
$recordsA = [
    ['channel'=>'meta_ads','period'=>'2026-11','report_id'=>'meta-proof-1',
     'source'=>'synthetic_fixture','spend_cents'=>100000],
    ['channel'=>'google_ads','period'=>'2026-11','report_id'=>'google-proof-1',
     'source'=>'synthetic_fixture','spend_cents'=>50000],
];
$signedA = $issue($tenantA, 'nonce_crossrepo_A_1234567890abcdef',
    ['google_ads'=>70000, 'meta_ads'=>90000]);
sameCross($catalog->version(), $signedA['quote']['catalog_version'], 'issuer catalog version');
sameCross(32000, $signedA['quote']['estimated_management_fee_cents'], 'two-channel quote at 20%');
sameCross(false, $signedA['quote']['real_charge_enabled'], 'no real charge');
sameCross(true, $signedA['quote']['media_paid_directly_to_platforms'], 'media direct to channels');

$evidenceA = Evidence::build($signedA['quote'], '2026-11', $recordsA);
$verifiedA = Guard::verifyAndPreview($signedA, $tenantA, $catalog->version(),
    '2026-11', $evidenceA['unverified_spend_by_channel_cents'], $now,
    $keyResolver, [$nonces, 'claim']);
sameCross(true, $verifiedA['quote_claim_hmac_verified'], 'HMAC from actual Portal issuer accepted');
sameCross(false, $verifiedA['portal_iam_authenticated'], 'HMAC not equivalent to Portal IAM');
sameCross(false, $verifiedA['finance_approved'], 'no approval');
sameCross(false, $verifiedA['asaas_write_enabled'], 'no Asaas writes');
sameCross(30000, $verifiedA['preview']['unverified_earned_fee_cents'], '20% of synthetic media');
sameCross(-2000, $verifiedA['preview']['review_only_adjustment_cents'], 'negative review adjustment');

$draftA = $drafts->stage($tenantA, $verifiedA, $evidenceA);
sameCross(true, $draftA['created'], 'first cross-repo financial draft inserted');
sameCross(false, $draftA['billable'], 'draft cannot bill');
sameCross('lab_draft_unverified_spend', $draftA['status'], 'draft not financially approved');
$retryA = $drafts->stage($tenantA, $verifiedA, $evidenceA);
sameCross(false, $retryA['created'], 'safe duplicate of identical draft');
sameCross($draftA['close_id'], $retryA['close_id'], 'same draft ID on retry');
rejectCross(fn()=>Guard::verifyAndPreview($signedA, $tenantA, $catalog->version(),
    '2026-11', $evidenceA['unverified_spend_by_channel_cents'], $now,
    $keyResolver, [$nonces, 'claim']), 'nonce reused');
$forged = $signedA;
$forged['quote']['estimated_management_fee_cents'] += 1;
rejectCross(fn()=>Guard::verifyAndPreview($forged, $tenantA, $catalog->version(),
    '2026-11', $evidenceA['unverified_spend_by_channel_cents'], $now,
    $keyResolver, [$nonces, 'claim']), 'altered Portal quote MAC');
rejectCross(fn()=>Guard::verifyAndPreview($signedA, $tenantB, $catalog->version(),
    '2026-11', $evidenceA['unverified_spend_by_channel_cents'], $now,
    $keyResolver, [$nonces, 'claim']), 'incorrect trusted tenant scope');

$changedRows = $recordsA;
$changedRows[0]['report_id'] = 'meta-proof-2';
$changedEvidence = Evidence::build($signedA['quote'], '2026-11', $changedRows);
rejectCross(fn()=> $drafts->stage($tenantA, $verifiedA, $changedEvidence),
    'changed source cannot overwrite original close');
rejectCross(fn()=> $issue($tenantA, 'nonce_too_small_1234567890', ['google_ads'=>69999]),
    'Portal refuses spend planning below R700');

$recordsB = [
    ['channel'=>'google_ads','period'=>'2026-11','report_id'=>'google-beta-1',
     'source'=>'synthetic_fixture','spend_cents'=>0]
];
$signedB = $issue($tenantB, 'nonce_crossrepo_B_1234567890abcdef', ['google_ads'=>70000]);
$evidenceB = Evidence::build($signedB['quote'], '2026-11', $recordsB);
$verifiedB = Guard::verifyAndPreview($signedB, $tenantB, $catalog->version(),
    '2026-11', $evidenceB['unverified_spend_by_channel_cents'], $now,
    $keyResolver, [$nonces, 'claim']);
$draftB = $drafts->stage($tenantB, $verifiedB, $evidenceB);
sameCross(true, $draftB['created'], 'second tenant quote issued by Portal code');
sameCross(-14000, $verifiedB['preview']['review_only_adjustment_cents'],
    'zero actually invested does not force a media minimum top-up');
sameCross(false, $draftB['billable'], 'zero-spend draft not billable');

foreach ([$tenantA, $tenantB] as $tenant) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id', :tenant, true)")
        ->execute(['tenant' => $tenant]);
    $count = (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn();
    sameCross(1, $count, 'tenant-specific draft RLS read');
    $countNonces = (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn();
    sameCross(1, $countNonces, 'tenant-specific nonce RLS read');
    $db->rollBack();
}
putenv('PORTAL_PERF_SIGN_LAB=0');
rejectCross(fn()=> $issue($tenantA, 'nonce_crossrepo_disabled_123456', ['google_ads'=>70000]),
    'Portal issuer closed outside explicit lab gate');
echo "OK: pinned real Portal issuer -> Performance HMAC -> PG nonce -> monthly unbillable draft; replay, cross-tenant, zero-spend and tamper blocked\n";
