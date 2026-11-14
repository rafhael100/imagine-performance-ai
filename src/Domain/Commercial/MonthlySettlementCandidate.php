<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Converts an untrusted, synthetic spend fixture into deterministic SQL-bound
 * parameters. This does NOT authenticate tenant/Portal/provider or issue SQL.
 */
final class MonthlySettlementCandidate
{
    /**
     * @param array<string,mixed> $quote
     * @param list<array<string,mixed>> $syntheticRecords
     * @return array<string,mixed>
     */
    public static function build(string $tenantId, array $quote, string $period, array $syntheticRecords): array
    {
        if (!preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/D', $tenantId)) {
            throw new InvalidArgumentException('Invalid synthetic tenant UUID.');
        }
        // Delegates all quote, calendar, spend, source and completeness checks.
        $report = MonthlySpendEvidencePreview::build($quote, $period, $syntheticRecords);
        $preview = $report['preview'];
        foreach ([
            $report['source_authenticated'],
            $report['provider_spend_verified'],
            $report['finance_approved'],
            $report['automatic_charge_or_credit_enabled'],
            $report['ads_mutation_enabled'],
            $preview['quote_origin_authenticated'],
            $preview['actual_spend_verified'],
            $preview['finance_approved'],
            $preview['asaas_write_enabled'],
        ] as $flag) {
            if ($flag !== false) {
                throw new InvalidArgumentException('Simulated settlement must never claim verification, authorization or gateway access.');
            }
        }
        $month = $period . '-01';
        $rows = [];
        foreach ($syntheticRecords as $record) {
            $canonical = [
                'channel' => $record['channel'],
                'period' => $period,
                'report_id' => $record['report_id'],
                'source' => 'synthetic_fixture',
                'spend_cents' => $record['spend_cents'],
            ];
            $rows[] = [
                'tenant_id' => $tenantId,
                'reference_month' => $month,
                'channel' => $canonical['channel'],
                'report_id' => $canonical['report_id'],
                'spend_cents' => $canonical['spend_cents'],
                'record_sha256' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ];
        }
        usort($rows, static fn(array $a, array $b): int =>
            [$a['channel'], $a['report_id']] <=> [$b['channel'], $b['report_id']]
        );
        $review = [
            'tenant_id' => $tenantId,
            'reference_month' => $month,
            'quote_sha256' => $preview['quote_fingerprint_sha256_not_signature'],
            'evidence_sha256' => $report['records_fingerprint_sha256_not_proof'],
            'advance_fee_cents' => $preview['estimated_advance_fee_cents'],
            'earned_fee_cents' => $preview['unverified_earned_fee_cents'],
            'adjustment_cents' => $preview['review_only_adjustment_cents'],
        ];
        return [
            'spend_rows' => $rows,
            'review_row' => $review,
            'state' => 'synthetic_only_not_authorized_for_financial_execution',
            'tenant_authenticated' => false,
            'portal_quote_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'gateway_write_enabled' => false,
        ];
    }
}
