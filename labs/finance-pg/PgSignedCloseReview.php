<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\MonthlySettlementReviewHtml;
use Imagine\Performance\Commercial\MonthlySpendEvidencePreview;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * TEST-ONLY read-only HTML adapter for a previously HMAC-verified Portal quote
 * and an immutable, unbillable PG review draft.
 *
 * Neither the caller's "expectedTenant" nor a PostgreSQL session GUC is trusted
 * IAM. No web entrypoint, gateway, approval or official ad-spend verification.
 */
final class PgSignedCloseReview
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signedEnvelope Real Portal issuer shape, synthetic key
     * @param array<string,mixed> $verifiedOutput Prior trusted internal guard output
     * @param list<array<string,mixed>> $syntheticRows Campaign spend fixtures
     */
    public function render(
        string $expectedTenant,
        array $signedEnvelope,
        array $verifiedOutput,
        array $syntheticRows
    ): string {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_FIN_SIGNED_HTML_LAB') !== '1') {
            throw new RuntimeException('Signed financial HTML is strictly a gated laboratory visualization.');
        }
        if ($this->db->inTransaction() || !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $expectedTenant)) {
            throw new InvalidArgumentException('Invalid review context.');
        }
        $signedTenant = $signedEnvelope['tenant_id'] ?? null;
        $verifiedTenant = $verifiedOutput['tenant_id'] ?? null;
        if (!is_string($signedTenant) || !is_string($verifiedTenant) ||
            !hash_equals($expectedTenant, $signedTenant) ||
            !hash_equals($expectedTenant, $verifiedTenant) ||
            ($verifiedOutput['quote_claim_hmac_verified'] ?? null) !== true ||
            ($verifiedOutput['billing_period_bound'] ?? null) !== true ||
            ($signedEnvelope['billing_period'] ?? null) !== ($verifiedOutput['preview']['period'] ?? null) ||
            ($verifiedOutput['portal_iam_authenticated'] ?? null) !== false ||
            ($verifiedOutput['finance_approved'] ?? null) !== false ||
            ($verifiedOutput['asaas_write_enabled'] ?? null) !== false ||
            ($verifiedOutput['ads_write_enabled'] ?? null) !== false ||
            !is_array($signedEnvelope['quote'] ?? null) ||
            !is_array($verifiedOutput['preview'] ?? null)) {
            throw new InvalidArgumentException('Signed review is not an unbillable internal claim.');
        }
        $sig = $signedEnvelope['signature'] ?? null;
        if (!is_string($sig) || !preg_match('/\\A[a-f0-9]{64}\\z/D', $sig) ||
            !is_string($verifiedOutput['signed_signature_fingerprint_sha256_not_auth'] ?? null) ||
            !hash_equals(hash('sha256', $sig), $verifiedOutput['signed_signature_fingerprint_sha256_not_auth'])) {
            throw new InvalidArgumentException('Signed envelope signature differs from verified claim.');
        }
        $unsigned = $signedEnvelope;
        unset($unsigned['signature']);
        $raw = PortalSignedQuoteGuard::signingBytes($unsigned);
        $quoteClaim = hash('sha256', $raw);
        if (!is_string($verifiedOutput['quote_claim_sha256'] ?? null) ||
            !hash_equals($quoteClaim, $verifiedOutput['quote_claim_sha256'])) {
            throw new InvalidArgumentException('Signed quote bytes do not match verified claim.');
        }
        $period = $verifiedOutput['preview']['period'] ?? null;
        if (!is_string($period)) {
            throw new InvalidArgumentException('Missing validated finance period.');
        }
        $spend = MonthlySpendEvidencePreview::build($signedEnvelope['quote'], $period, $syntheticRows);
        $sourcePreview = $verifiedOutput['preview'];
        $spendPreview = $spend['preview'];
        foreach ([
            'period', 'currency', 'planned_media_cents', 'unverified_actual_media_cents',
            'estimated_advance_fee_cents', 'unverified_earned_fee_cents',
            'review_only_adjustment_cents', 'quote_fingerprint_sha256_not_signature',
        ] as $field) {
            if (!array_key_exists($field, $sourcePreview) ||
                $sourcePreview[$field] !== ($spendPreview[$field] ?? null)) {
                throw new InvalidArgumentException('Signed preview and synthetic expense conflict.');
            }
        }
        $closeId = hash('sha256', 'perf-close-lab:v1:' . $expectedTenant . ':' . $period . ':' . $quoteClaim);
        try {
            // A dedicated non-owner CI role is enforced. Do not emulate authorization
            // using a caller-writable SQL GUC in production.
            $this->db->beginTransaction();
            if ($this->db->query('SELECT current_user')->fetchColumn() !== 'perf_fin_lab_app') {
                throw new RuntimeException('Read-only review requires the isolated application role.');
            }
            $this->db->prepare("SELECT set_config('app.tenant_id', :tenant, true)")
                ->execute(['tenant' => $expectedTenant]);
            $stmt = $this->db->prepare(
                'SELECT tenant_id, close_id, period, quote_claim_sha256, spend_claim_sha256,
                        planned_media_cents, unverified_actual_media_cents, advance_fee_cents,
                        unverified_earned_fee_cents, adjustment_cents, billable,
                        finance_approved, source_verified, status
                 FROM perf_fin_lab.reconciliation_drafts
                 WHERE tenant_id = :tenant AND close_id = :close'
            );
            $stmt->execute(['tenant' => $expectedTenant, 'close' => $closeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('No immutable draft for this signed quote and tenant.');
            }
            $expected = [
                'tenant_id' => $expectedTenant,
                'close_id' => $closeId,
                'period' => $period,
                'quote_claim_sha256' => $quoteClaim,
                'spend_claim_sha256' => $spend['records_fingerprint_sha256_not_proof'],
                'planned_media_cents' => $sourcePreview['planned_media_cents'],
                'unverified_actual_media_cents' => $sourcePreview['unverified_actual_media_cents'],
                'advance_fee_cents' => $sourcePreview['estimated_advance_fee_cents'],
                'unverified_earned_fee_cents' => $sourcePreview['unverified_earned_fee_cents'],
                'adjustment_cents' => $sourcePreview['review_only_adjustment_cents'],
            ];
            foreach ($expected as $key => $value) {
                if (!array_key_exists($key, $row) ||
                    (is_int($value) ? (string)$value !== (string)$row[$key] : $value !== $row[$key])) {
                    throw new RuntimeException('Immutable draft differs from verified synthetic inputs.');
                }
            }
            if ($row['status'] !== 'lab_draft_unverified_spend' ||
                !self::isFalse($row['billable']) ||
                !self::isFalse($row['finance_approved']) ||
                !self::isFalse($row['source_verified'])) {
                throw new RuntimeException('Finance approval, source verification or billing is not allowed.');
            }
            $this->db->rollBack();
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new RuntimeException('Signed monthly draft is unavailable or inconsistent.');
        }
        $month = $period . '-01';
        $candidate = [
            'state' => 'synthetic_only_not_authorized_for_financial_execution',
            'tenant_authenticated' => false,
            'portal_quote_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'gateway_write_enabled' => false,
            'review_row' => [
                'reference_month' => $month,
                'advance_fee_cents' => $expected['advance_fee_cents'],
                'earned_fee_cents' => $expected['unverified_earned_fee_cents'],
                'adjustment_cents' => $expected['adjustment_cents'],
            ],
            'spend_rows' => array_map(static fn(array $item): array => [
                'reference_month' => $month,
                'channel' => $item['channel'],
                'spend_cents' => $item['spend_cents'],
            ], $syntheticRows),
        ];
        return MonthlySettlementReviewHtml::render($candidate);
    }

    private static function isFalse(mixed $value): bool
    {
        return in_array($value, [false, 0, '0', 'f'], true);
    }
}
