<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Immutable, unbillable laboratory close draft from already validated quote and
 * synthetic spend previews. Input objects MUST originate from trusted internal
 * call sites; this class does not independently verify HMAC or provider invoices.
 * No payments, approval transitions, refunds, real invoices or Ads writes.
 */
final class PgFinanceCloseDrafts
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array<string,mixed> $signedResult output from PortalSignedQuoteGuard
     * @param array<string,mixed> $spendResult output from MonthlySpendEvidencePreview
     * @return array{close_id:string,created:bool,status:string,billable:bool}
     */
    public function stage(string $expectedTenant, array $signedResult, array $spendResult): array
    {
        $tenant = $signedResult['tenant_id'] ?? null;
        $claimSha = $signedResult['quote_claim_sha256'] ?? null;
        $spendSha = $spendResult['records_fingerprint_sha256_not_proof'] ?? null;
        $s = $signedResult['preview'] ?? null;
        $e = $spendResult['preview'] ?? null;
        $period = $spendResult['period'] ?? null;
        if (!is_string($tenant) || !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !hash_equals($expectedTenant, $tenant) ||
            !is_string($period) || !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $period) ||
            !self::isSha($claimSha) || !self::isSha($spendSha) ||
            ($signedResult['quote_claim_hmac_verified'] ?? null) !== true ||
            ($signedResult['portal_iam_authenticated'] ?? null) !== false ||
            ($signedResult['finance_approved'] ?? null) !== false ||
            ($signedResult['asaas_write_enabled'] ?? null) !== false ||
            ($signedResult['ads_write_enabled'] ?? null) !== false ||
            ($spendResult['source_authenticated'] ?? null) !== false ||
            ($spendResult['provider_spend_verified'] ?? null) !== false ||
            ($spendResult['finance_approved'] ?? null) !== false ||
            ($spendResult['automatic_charge_or_credit_enabled'] ?? null) !== false ||
            ($spendResult['ads_mutation_enabled'] ?? null) !== false ||
            !is_array($s) || !is_array($e) ||
            ($s['period'] ?? null) !== $period ||
            ($e['period'] ?? null) !== $period ||
            ($s['currency'] ?? null) !== 'BRL' ||
            ($e['currency'] ?? null) !== 'BRL' ||
            !self::isSha($s['quote_fingerprint_sha256_not_signature'] ?? null) ||
            ($e['quote_fingerprint_sha256_not_signature'] ?? null) !== $s['quote_fingerprint_sha256_not_signature']) {
            throw new InvalidArgumentException('Incompatible laboratory close draft inputs.');
        }
        $fields = [
            'planned_media_cents' => 'planned_media_cents',
            'unverified_actual_media_cents' => 'unverified_actual_media_cents',
            'advance_fee_cents' => 'estimated_advance_fee_cents',
            'unverified_earned_fee_cents' => 'unverified_earned_fee_cents',
            'adjustment_cents' => 'review_only_adjustment_cents',
        ];
        $amounts = [];
        foreach ($fields as $column => $fromPreview) {
            $a = $s[$fromPreview] ?? null;
            $b = $e[$fromPreview] ?? null;
            if (!is_int($a) || !is_int($b) || $a !== $b ||
                abs($a) > 200000000 || ($column !== 'adjustment_cents' && $a < 0)) {
                throw new InvalidArgumentException('Unverified monetary amounts disagree.');
            }
            $amounts[$column] = $a;
        }
        if ($amounts['adjustment_cents'] !== $amounts['unverified_earned_fee_cents'] - $amounts['advance_fee_cents']) {
            throw new InvalidArgumentException('Adjustment arithmetic mismatch.');
        }
        $closeId = hash('sha256', 'perf-close-lab:v1:' . $tenant . ':' . $period . ':' . $claimSha);
        if ($this->pdo->inTransaction()) {
            throw new InvalidArgumentException('Nested transaction not allowed.');
        }
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("SELECT set_config('app.tenant_id', :tenant, true)")->execute(['tenant' => $tenant]);
            $insert = $this->pdo->prepare(
                'INSERT INTO perf_fin_lab.reconciliation_drafts
                (tenant_id, close_id, period, quote_claim_sha256, spend_claim_sha256,
                planned_media_cents, unverified_actual_media_cents, advance_fee_cents,
                unverified_earned_fee_cents, adjustment_cents)
                VALUES (:tenant, :close, :period, :quote, :spend, :planned, :actual, :advance, :earned, :delta)
                ON CONFLICT DO NOTHING RETURNING close_id'
            );
            $insert->execute([
                'tenant' => $tenant, 'close' => $closeId, 'period' => $period,
                'quote' => $claimSha, 'spend' => $spendSha,
                'planned' => $amounts['planned_media_cents'],
                'actual' => $amounts['unverified_actual_media_cents'],
                'advance' => $amounts['advance_fee_cents'],
                'earned' => $amounts['unverified_earned_fee_cents'],
                'delta' => $amounts['adjustment_cents'],
            ]);
            $created = $insert->fetchColumn() !== false;
            if (!$created) {
                $check = $this->pdo->prepare(
                    'SELECT close_id, spend_claim_sha256, planned_media_cents,
                     unverified_actual_media_cents, advance_fee_cents,
                     unverified_earned_fee_cents, adjustment_cents
                     FROM perf_fin_lab.reconciliation_drafts
                     WHERE tenant_id=:tenant AND period=:period AND quote_claim_sha256=:quote'
                );
                $check->execute(['tenant' => $tenant, 'period' => $period, 'quote' => $claimSha]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                if (!is_array($existing) || $existing['close_id'] !== $closeId ||
                    $existing['spend_claim_sha256'] !== $spendSha ||
                    (int)$existing['planned_media_cents'] !== $amounts['planned_media_cents'] ||
                    (int)$existing['unverified_actual_media_cents'] !== $amounts['unverified_actual_media_cents'] ||
                    (int)$existing['advance_fee_cents'] !== $amounts['advance_fee_cents'] ||
                    (int)$existing['unverified_earned_fee_cents'] !== $amounts['unverified_earned_fee_cents'] ||
                    (int)$existing['adjustment_cents'] !== $amounts['adjustment_cents']) {
                    throw new RuntimeException('A different immutable close draft already exists.');
                }
            }
            $this->pdo->commit();
            return ['close_id' => $closeId, 'created' => $created,
                'status' => 'lab_draft_unverified_spend', 'billable' => false];
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('Laboratory close draft store unavailable or conflicts with existing evidence.');
        }
    }

    private static function isSha(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}
