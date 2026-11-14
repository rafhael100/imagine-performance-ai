<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementReviewHtml.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgNonceClaims.php';
require_once __DIR__ . '/../labs/finance-pg/PgFinanceCloseDrafts.php';
require_once __DIR__ . '/../labs/finance-pg/PgSignedCloseReview.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard as Guard;
use Imagine\Performance\Commercial\MonthlySpendEvidencePreview as Evidence;
use Imagine\Performance\Commercial\Lab\PgNonceClaims;
use Imagine\Performance\Commercial\Lab\PgFinanceCloseDrafts;
use Imagine\Performance\Commercial\Lab\PgSignedCloseReview as Review;

function expectReview(bool $condition, string $case): void {
    if (!$condition) throw new RuntimeException('Unexpected review result: ' . $case);
}
function mustRejectReview(callable $call, string $case): void {
    try { $call(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Review guard accepted invalid input: ' . $case);
}
if (getenv('APP_ENV') !== 'testing' || getenv('PORTAL_PERF_SIGN_LAB') !== '1') {
    throw new RuntimeException('Offline lab gate not enabled.');
}
$dsn = getenv('PERF_LAB_PG_DSN');
$user = getenv('PERF_LAB_PG_USER');
$pass = getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || $user !== 'perf_fin_lab_app' || !$pass) {
    throw new RuntimeException('Isolated PG app role required.');
}
$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$catalog = new ApprovedOfferCatalog();
$tenant = 'tenant_htmlreview_alpha7777';
$secret = str_repeat('H',48);
$now = 1794660000;
$envelope = Issuer::issue($catalog,
    ['google_ads' => 70000, 'meta_ads' => 90000],
    $tenant, 'k_htmlrev_01', 'nonce_htmlrev_1234567890abcdef', $now - 2, $now + 120, $secret);
$records = [
    ['channel'=>'google_ads','period'=>'2026-11','report_id'=>'google-html-01','source'=>'synthetic_fixture','spend_cents'=>50000],
    ['channel'=>'meta_ads','period'=>'2026-11','report_id'=>'meta-html-01','source'=>'synthetic_fixture','spend_cents'=>100000],
];
$spend = Evidence::build($envelope['quote'],'2026-11',$records);
$verified = Guard::verifyAndPreview(
    $envelope,$tenant,$catalog->version(),'2026-11',
    $spend['unverified_spend_by_channel_cents'],$now,
    static fn(string $t,string $kid): ?string => $t === 'tenant_htmlreview_alpha7777' && $kid === 'k_htmlrev_01' ? str_repeat('H',48) : null,
    [new PgNonceClaims($db),'claim']
);
$drafts = new PgFinanceCloseDrafts($db);
$result = $drafts->stage($tenant,$verified,$spend);
expectReview($result['created'] === true, 'signed draft created');
$reader = new Review($db);
$html = $reader->render($tenant,$envelope,$verified,$records);
foreach (['DEMONSTRAÇÃO NÃO HOMOLOGADA','R$ 320,00','R$ 300,00',
          '−R$ 20,00','R$ 1.500,00','Google Ads','Meta Ads'] as $required) {
    expectReview(str_contains($html,$required), 'HTML value '.$required);
}
foreach (['<script','<form','<button','<iframe','href=','https://','http://'] as $blocked) {
    expectReview(!str_contains(strtolower($html),strtolower($blocked)), 'unsafe HTML '.$blocked);
}
expectReview($html === $reader->render($tenant,$envelope,$verified,array_reverse($records)),
    'canonical presentation independent of record order');
mustRejectReview(fn()=> $reader->render('tenant_htmlreview_beta9999',$envelope,$verified,$records), 'tenant change');
$altered = $records;
$altered[0]['spend_cents'] += 1;
mustRejectReview(fn()=> $reader->render($tenant,$envelope,$verified,$altered), 'tampered spend');
$alteredSigned = $envelope;
$alteredSigned['quote']['estimated_management_fee_cents'] += 1;
mustRejectReview(fn()=> $reader->render($tenant,$alteredSigned,$verified,$records), 'tampered quote');
$alteredResult = $verified;
$alteredResult['finance_approved'] = true;
mustRejectReview(fn()=> $reader->render($tenant,$envelope,$alteredResult,$records), 'claimed approval');
$alteredResult = $verified;
$alteredResult['preview']['review_only_adjustment_cents'] += 1;
mustRejectReview(fn()=> $reader->render($tenant,$envelope,$alteredResult,$records), 'wrong adjustment');
$db->beginTransaction();
mustRejectReview(fn()=> $reader->render($tenant,$envelope,$verified,$records), 'nested transaction denied');
$db->rollBack();
echo "OK: signed Portal quote -> PG18 stored unbillable close -> safe read-only HTML, tamper and tenant checks\n";
