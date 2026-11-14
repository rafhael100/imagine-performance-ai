<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * No-network three-channel LEAD-MAGNET scenario calculator (NOT a quote).
 * Uses customer-entered estimated CPL and desired lead count. These are
 * assumptions, NOT provider spend evidence, a forecast or an Ads guarantee.
 */
final class TrichannelBudgetCalculator
{
    private const MAX_TARGET_LEADS = 3000;
    private const MAX_CPL_CENTS = 10000000;
    private const MAX_MONTHLY_MEDIA_CENTS = 100000000;

    /**
     * @param array<string,int> $estimatedCostPerLeadCents
     * @return array<string,mixed>
     */
    public static function estimate(
        string $period,
        int $desiredLeads,
        array $estimatedCostPerLeadCents
    ): array {
        if (!preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])\z/D', $period, $match) ||
            (int)$match[1] < 2000 || (int)$match[1] > 2100 ||
            $desiredLeads < 1 || $desiredLeads > self::MAX_TARGET_LEADS ||
            $estimatedCostPerLeadCents === [] ||
            count($estimatedCostPerLeadCents) > 3) {
            throw new InvalidArgumentException('Invalid planning period, lead target or channel selection.');
        }

        $proposal = ChannelMinimumBudgetProposal::guidance();
        $minima = $proposal['proposed_minima_cents'];
        $reference = $proposal['reference_recommended_cents'];
        ksort($estimatedCostPerLeadCents, SORT_STRING);
        $count = count($estimatedCostPerLeadCents);
        $base = intdiv($desiredLeads, $count);
        $remainder = $desiredLeads % $count;
        $totalFloor = 0;
        $totalSuggested = 0;
        $detail = [];
        $expectedCapacity = 0;
        foreach ($estimatedCostPerLeadCents as $channel => $cpl) {
            if (!is_string($channel) || !array_key_exists($channel, $minima) ||
                !is_int($cpl) || $cpl < 1 || $cpl > self::MAX_CPL_CENTS) {
                throw new InvalidArgumentException('Unsupported channel or cost-per-lead assumption.');
            }
            // Equal split is a transparent planning assumption, not a model
            // of comparative platform performance.
            $allocated = $base + ($remainder-- > 0 ? 1 : 0);
            $targetSpend = $allocated * $cpl;
            if ($targetSpend > self::MAX_MONTHLY_MEDIA_CENTS) {
                throw new InvalidArgumentException('Requested plan exceeds offline spending ceiling.');
            }
            $monthlyFloor = $minima[$channel];
            $suggested = max($monthlyFloor, $reference[$channel], $targetSpend);
            if ($suggested > self::MAX_MONTHLY_MEDIA_CENTS) {
                throw new InvalidArgumentException('Recommended plan exceeds cap.');
            }
            $totalFloor += $monthlyFloor;
            $totalSuggested += $suggested;
            $expected = intdiv($suggested, $cpl);
            $expectedCapacity += $expected;
            $detail[$channel] = [
                'desired_leads_allocated' => $allocated,
                'assumed_cpl_cents' => $cpl,
                'proposed_monthly_floor_cents' => $monthlyFloor,
                'reference_recommended_cents' => $reference[$channel],
                'target_based_media_cents' => $targetSpend,
                'suggested_monthly_media_cents' => $suggested,
                'illustrative_capacity_leads_at_assumed_cpl' => $expected,
            ];
        }
        // Presentation only; no real payment/ads or guaranteed leads.
        $fee = static fn(int $media): int => intdiv($media * 20 + 50, 100);
        return [
            'period' => $period,
            'currency' => 'BRL',
            'state' => 'illustrative_lead_magnet_scenario_not_a_quote',
            'desired_leads' => $desiredLeads,
            'allocation_strategy' => 'equal_by_selected_channel_alphabetical_remainder',
            'channels' => $detail,
            'proposed_monthly_floor_total_cents' => $totalFloor,
            'suggested_monthly_media_total_cents' => $totalSuggested,
            'illustrative_fee_on_suggested_media_cents' => $fee($totalSuggested),
            'illustrative_total_separate_outlay_cents' => $totalSuggested + $fee($totalSuggested),
            'illustrative_capacity_leads_at_assumed_cpl' => $expectedCapacity,
            'cpl_is_user_assumption_not_provider_estimate' => true,
            'leads_or_results_guaranteed' => false,
            'portal_catalog_approved' => false,
            'source_spend_verified' => false,
            'crm_lead_saved' => false,
            'personal_data_collected' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }
}
