<?php
declare(strict_types=1);

require_once __DIR__ . '/monthly_settlement_candidate_test.php';

use Imagine\Performance\Commercial\MonthlySettlementCandidate as Candidate;

if (!extension_loaded('pdo_pgsql')) {
    throw new RuntimeException('pdo_pgsql extension required; do not silently skip the PostgreSQL test.');
}
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    getenv('PGHOST') ?: 'localhost',
    getenv('PGPORT') ?: '5432',
    getenv('PGDATABASE') ?: 'postgres'
);
$pdo = new PDO(
    $dsn,
    getenv('PGUSER') ?: 'postgres',
    getenv('PGPASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$tenant = '33333333-3333-4333-8333-333333333333';
$candidate = Candidate::build($tenant, syntheticQuote(), '2026-11', syntheticRows());

$insertEvidence = 'INSERT INTO perf_fin_lab.spend_evidence
 (tenant_id, reference_month, channel, report_id, spend_cents, record_sha256)
 VALUES (:tenant_id, :reference_month, :channel, :report_id, :spend_cents, :record_sha256)';
$insertReview = 'INSERT INTO perf_fin_lab.monthly_review_candidate
 (tenant_id, reference_month, quote_sha256, evidence_sha256, advance_fee_cents, earned_fee_cents, adjustment_cents)
 VALUES (:tenant_id, :reference_month, :quote_sha256, :evidence_sha256, :advance_fee_cents, :earned_fee_cents, :adjustment_cents)';

function beginLab(PDO $db, string $tenantId): void {
    $db->beginTransaction();
    // Superuser CI connection delegates to a NOLOGIN, no-writeback role for testing only.
    // NEVER use a superuser or client-controlled tenant session for production API calls.
    $db->exec('SET LOCAL ROLE perf_fin_lab_app');
    $statement = $db->prepare("SELECT set_config('perf_lab.tenant_id', :tenant, true)");
    $statement->execute(['tenant' => $tenantId]);
}
function persistCandidate(PDO $db, array $candidate, string $eSql, string $rSql): void {
    foreach ($candidate['spend_rows'] as $row) {
        $db->prepare($eSql)->execute($row);
    }
    $db->prepare($rSql)->execute($candidate['review_row']);
}
beginLab($pdo, $tenant);
persistCandidate($pdo, $candidate, $insertEvidence, $insertReview);
$count = (int)$pdo->query('SELECT count(*) FROM perf_fin_lab.spend_evidence')->fetchColumn();
if ($count !== 2) {
    throw new RuntimeException('Expected 2 persisted synthetic evidence rows');
}
$adjustment = (int)$pdo->query('SELECT adjustment_cents FROM perf_fin_lab.monthly_review_candidate')->fetchColumn();
if ($adjustment !== -4000) {
    throw new RuntimeException('Mismatch between PHP calculated adjustment and persisted PostgreSQL candidate');
}
$pdo->commit();

$replayBlocked = false;
try {
    beginLab($pdo, $tenant);
    persistCandidate($pdo, $candidate, $insertEvidence, $insertReview);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $replayBlocked = $e->getCode() === '23505';
}
if (!$replayBlocked) throw new RuntimeException('Expected durable replay to be blocked by unique constraint');

beginLab($pdo, '44444444-4444-4444-8444-444444444444');
$count = (int)$pdo->query('SELECT count(*) FROM perf_fin_lab.spend_evidence')->fetchColumn();
if ($count !== 0) throw new RuntimeException('Cross tenant evidence was readable by app role');
$pdo->rollBack();
echo "OK: PHP->PostgreSQL parameterized synthetic monthly settlement was isolated and replay-safe\n";
