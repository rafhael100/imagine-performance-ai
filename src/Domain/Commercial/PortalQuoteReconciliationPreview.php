<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Read-only handshake for the exact Performance quote shape produced by Portal
 * ApprovedOfferCatalog::quote(). Structural validation is NOT authentication:
 * only an authenticated, server-to-server Portal integration can attest origin.
 * No gateway, media API, database, webhook or payment side effects.
 */
final class PortalQuoteReconciliationPreview
{
    /**
     * @param array<string, mixed> $quote Snapshot attributed to Portal (untrusted here)
     * @param array<string, int> $actualMediaCents Unverified actual spend; operator input in this lab
     * @return array<string, mixed> Human-review-only reconciliation preview
     */
    public static function build(array $quote, string $period, array $actualMediaCents): array
    {
        $expectedFields = [
            'catalog_version', 'product', 'currency', 'gateway', 'real_charge_enabled',
            'media_by_channel_cents', 'media_paid_directly_to_platforms',
            'management_fee_basis_points', 'estimated_management_fee_cents',
            'reference_period', 'amount_now_cents', 'settlement_policy',
        ];
        $fields = array_keys($quote);
        sort($fields, SORT_STRING);
        sort($expectedFields, SORT_STRING);
        if ($fields !== $expectedFields) {
            throw new InvalidArgumentException('Unexpected Portal quote shape.');
        }
        $version = $quote['catalog_version'];
        if (!is_string($version) || !preg_match('/\A[a-f0-9]{64}\z/D', $version)) {
            throw new InvalidArgumentException('Invalid catalog fingerprint syntax.');
        }
        if ($quote['product'] !== 'performance' ||
            $quote['currency'] !== 'BRL' ||
            $quote['gateway'] !== 'asaas' ||
            $quote['real_charge_enabled'] !== false ||
            $quote['media_paid_directly_to_platforms'] !== true ||
            $quote['management_fee_basis_points'] !== 2000 ||
            $quote['reference_period'] !== 'monthly' ||
            $quote['settlement_policy'] !== 'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment') {
            throw new InvalidArgumentException('Incompatible commercial quote policy.');
        }
        $planned = $quote['media_by_channel_cents'];
        if (!is_array($planned) || !is_int($quote['estimated_management_fee_cents']) ||
            !is_int($quote['amount_now_cents'])) {
            throw new InvalidArgumentException('Invalid financial quote fields.');
        }
        $plannedKeys = array_keys($planned);
        $actualKeys = array_keys($actualMediaCents);
        sort($plannedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        if ($plannedKeys !== $actualKeys) {
            throw new InvalidArgumentException('Actual channels differ from contracted quote.');
        }
        $channels = [];
        foreach ($planned as $channel => $cents) {
            $channels[$channel] = [
                'planned_cents' => $cents,
                'actual_cents' => $actualMediaCents[$channel],
            ];
        }
        $reconciled = MonthlyFeeReconciliation::calculate($period, $channels);
        if ($quote['estimated_management_fee_cents'] !== $reconciled['advance_fee_cents'] ||
            $quote['amount_now_cents'] !== $reconciled['advance_fee_cents']) {
            throw new InvalidArgumentException('Quoted fee differs from approved price calculation.');
        }

        $canonicalQuote = $quote;
        ksort($canonicalQuote, SORT_STRING);
        ksort($canonicalQuote['media_by_channel_cents'], SORT_STRING);

        return [
            'product' => 'performance',
            'period' => $period,
            'currency' => 'BRL',
            'planned_media_cents' => $reconciled['planned_media_cents'],
            'unverified_actual_media_cents' => $reconciled['actual_media_cents'],
            'estimated_advance_fee_cents' => $reconciled['advance_fee_cents'],
            'unverified_earned_fee_cents' => $reconciled['earned_fee_cents'],
            'review_only_adjustment_cents' => $reconciled['adjustment_cents'],
            'origin_catalog_sha256_claim' => $version,
            'quote_fingerprint_sha256_not_signature' => hash('sha256', json_encode($canonicalQuote, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'state' => 'requires_trusted_portal_identity_spend_evidence_and_finance_approval',
            'quote_origin_authenticated' => false,
            'actual_spend_verified' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }
}
