<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Pure proposal-only calculator. NOT Portal's approved commercial catalog.
 * No charges, no signed quotations, no account access, no ad activation.
 */
final class ChannelMinimumBudgetProposal
{
    private const MAX_CENTS_PER_CHANNEL = 100000000;

    private const PROPOSED_MINIMA = [
        'chatgpt_ads' => 120000,
        'google_ads' => 100000,
        'meta_ads' => 70000,
    ];

    private const RECOMMENDED = [
        'chatgpt_ads' => 180000,
        'google_ads' => 150000,
        'meta_ads' => 100000,
    ];

    /**
     * @param array<string,int> $plannedCents channel => BRL monthly media plan in cents
     * @return array<string,mixed>
     */
    public static function preview(array $plannedCents): array
    {
        if ($plannedCents === [] || count($plannedCents) > 3) {
            throw new InvalidArgumentException('Select one to three unique supported channels.');
        }
        ksort($plannedCents, SORT_STRING);
        $sum = 0;
        $details = [];
        foreach ($plannedCents as $channel => $amount) {
            if (!array_key_exists($channel, self::PROPOSED_MINIMA) ||
                !is_int($amount) ||
                $amount < self::PROPOSED_MINIMA[$channel] ||
                $amount > self::MAX_CENTS_PER_CHANNEL) {
                throw new InvalidArgumentException('Unsupported channel or media plan below PROPOSED channel floor.');
            }
            $sum += $amount;
            $details[$channel] = [
                'planned_media_cents' => $amount,
                'proposed_monthly_floor_cents' => self::PROPOSED_MINIMA[$channel],
                'recommended_monthly_media_cents' => self::RECOMMENDED[$channel],
            ];
        }
        return [
            'state' => 'owner_approved_values_pending_portal_catalog_migration_offline_preview',
            'commercial_minima_owner_approved' => true,
            'currency' => 'BRL',
            'channels' => $details,
            'planned_media_cents' => $sum,
            'illustrative_management_fee_cents' => intdiv($sum * 20 + 50, 100),
            'management_percent' => 20,
            'media_paid_directly_to_providers' => true,
            'actual_fee_requires_verified_spend' => true,
            'portal_catalog_approved' => false,
            'provider_spend_verified' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }
}
