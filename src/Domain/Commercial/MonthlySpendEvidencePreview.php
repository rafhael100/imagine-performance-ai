<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Offline-only receipt intake: these rows are synthetic CLAIMS, never official
 * provider statements or permission to charge, refund or mutate an ad account.
 * No network, persistence, identity assertion or external side effects.
 */
final class MonthlySpendEvidencePreview
{
    private const MAX_ROWS = 200;
    private const MAX_CHANNEL_SPEND_CENTS = 100000000;

    /**
     * @param array<string,mixed> $quote Untrusted Portal-shaped quote
     * @param list<array<string,mixed>> $records Synthetic campaign spend rows
     * @return array<string,mixed>
     */
    public static function build(array $quote, string $period, array $records): array
    {
        if (count($records) < 1 || count($records) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Expected 1..200 synthetic spend records.');
        }
        $planned = $quote['media_by_channel_cents'] ?? null;
        if (!is_array($planned) || $planned === []) {
            throw new InvalidArgumentException('Missing quoted channel scope.');
        }
        $totals = [];
        $counts = [];
        $seen = [];
        $canonical = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Spend record must be a map.');
            }
            $keys = array_keys($record);
            sort($keys, SORT_STRING);
            if ($keys !== ['channel', 'period', 'report_id', 'source', 'spend_cents']) {
                throw new InvalidArgumentException('Unexpected spend record fields.');
            }
            $channel = $record['channel'];
            $id = $record['report_id'];
            $amount = $record['spend_cents'];
            if (!is_string($channel) || !array_key_exists($channel, $planned) ||
                !in_array($channel, ['google_ads', 'meta_ads'], true)) {
                throw new InvalidArgumentException('Spend channel not in quoted scope.');
            }
            if (!is_string($record['period']) || $record['period'] !== $period) {
                throw new InvalidArgumentException('Spend period mismatch.');
            }
            if ($record['source'] !== 'synthetic_fixture') {
                throw new InvalidArgumentException('Only synthetic fixture source is supported.');
            }
            if (!is_string($id) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $id)) {
                throw new InvalidArgumentException('Invalid synthetic report identifier.');
            }
            if (!is_int($amount) || $amount < 0 || $amount > self::MAX_CHANNEL_SPEND_CENTS) {
                throw new InvalidArgumentException('Invalid spend amount in cents.');
            }
            $dedupeKey = $channel . ':' . $id;
            if (isset($seen[$dedupeKey])) {
                throw new InvalidArgumentException('Repeated synthetic report identifier within channel.');
            }
            $seen[$dedupeKey] = true;
            $current = $totals[$channel] ?? 0;
            if ($current > self::MAX_CHANNEL_SPEND_CENTS - $amount) {
                throw new InvalidArgumentException('Aggregate channel spend exceeds the Portal quote cap.');
            }
            $totals[$channel] = $current + $amount;
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
            $canonical[] = [
                'channel' => $channel,
                'period' => $period,
                'report_id' => $id,
                'source' => 'synthetic_fixture',
                'spend_cents' => $amount,
            ];
        }
        // A missing channel is NOT zero spend. Require an explicit zero row.
        foreach (array_keys($planned) as $channel) {
            if (!isset($counts[$channel])) {
                throw new InvalidArgumentException('Missing channel report: zero must be explicit.');
            }
        }
        ksort($totals, SORT_STRING);
        ksort($counts, SORT_STRING);
        usort($canonical, static function (array $a, array $b): int {
            return [$a['channel'], $a['report_id']] <=> [$b['channel'], $b['report_id']];
        });

        // Validates the actual Portal quote shape and price formula as well.
        $preview = PortalQuoteReconciliationPreview::build($quote, $period, $totals);
        $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'period' => $period,
            'currency' => 'BRL',
            'record_count' => count($canonical),
            'record_counts_by_channel' => $counts,
            'unverified_spend_by_channel_cents' => $totals,
            'records_fingerprint_sha256_not_proof' => $fingerprint,
            'preview' => $preview,
            'source_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'durable_idempotency_guaranteed' => false,
            'automatic_charge_or_credit_enabled' => false,
            'ads_mutation_enabled' => false,
            'state' => 'synthetic_only_requires_provider_evidence_finance_review_and_durable_deduplication',
        ];
    }
}
