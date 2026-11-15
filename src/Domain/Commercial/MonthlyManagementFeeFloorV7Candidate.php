<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * VERSIONED CANDIDATE ONLY, NOT a Portal price catalog or payment instruction.
 * Monthly management fee: 20% of actual provider spend, subject to a
 * BRL 170.00 minimum for EACH active contracted channel.
 *
 * Separate from V4 and V6 to avoid silently changing signed quote semantics.
 * No provider verification, billing, automatic credit, Ads or CRM writes.
 */
final class MonthlyManagementFeeFloorV7Candidate
{
    private const CHANNEL_MEDIA_FLOOR_CENTS = [
        'chatgpt_ads'=>120000,
        'google_ads'=>100000,
        'meta_ads'=>70000,
    ];
    private const MAX_CHANNEL_MEDIA_CENTS = 100000000;
    private const RATE_BPS = 2000;
    private const MONTHLY_CHANNEL_FEE_FLOOR_CENTS = 17000;

    /**
     * @param array<string,array{planned_cents:int,actual_cents:int}> $channels
     * @return array<string,mixed>
     */
    public static function preview(string $period,array $channels):array
    {
        if(!preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$period) ||
           $channels===[] || count($channels)>3 ||
           array_diff(array_keys($channels),array_keys(self::CHANNEL_MEDIA_FLOOR_CENTS))!==[]) {
            throw new InvalidArgumentException('Invalid V7 month or contracted channel scope.');
        }
        ksort($channels,SORT_STRING);
        $plannedTotal=0;
        $actualTotal=0;
        $estimatedTotal=0;
        $earnedTotal=0;
        $byChannel=[];
        foreach($channels as $channel=>$value){
            if(!is_array($value) || array_keys($value)!==['planned_cents','actual_cents'] ||
               !is_int($value['planned_cents']) || !is_int($value['actual_cents']) ||
               $value['planned_cents']<self::CHANNEL_MEDIA_FLOOR_CENTS[$channel] ||
               $value['planned_cents']>self::MAX_CHANNEL_MEDIA_CENTS ||
               $value['actual_cents']<0 || $value['actual_cents']>self::MAX_CHANNEL_MEDIA_CENTS) {
                throw new InvalidArgumentException('Invalid channel amounts in BRL integer cents.');
            }
            $planned=$value['planned_cents'];
            $actual=$value['actual_cents'];
            // Per-channel minimum MUST NOT be replaced by max(20% of portfolio,
            // N*170): each active contracted channel has its own fee floor.
            $advance=max(self::MONTHLY_CHANNEL_FEE_FLOOR_CENTS,self::percentage($planned));
            $earned=max(self::MONTHLY_CHANNEL_FEE_FLOOR_CENTS,self::percentage($actual));
            $plannedTotal+=$planned;
            $actualTotal+=$actual;
            $estimatedTotal+=$advance;
            $earnedTotal+=$earned;
            $byChannel[$channel]=[
                'planned_media_cents'=>$planned,
                'unverified_actual_media_cents'=>$actual,
                'minimum_media_cents'=>self::CHANNEL_MEDIA_FLOOR_CENTS[$channel],
                'minimum_monthly_management_fee_cents'=>self::MONTHLY_CHANNEL_FEE_FLOOR_CENTS,
                'estimated_management_fee_cents'=>$advance,
                'unverified_earned_fee_cents'=>$earned,
                'review_only_adjustment_cents'=>$earned-$advance,
            ];
        }
        return [
            'state'=>'v7_per_channel_minimum_management_proposal_only',
            'commercial_catalog_active'=>false,
            'currency'=>'BRL',
            'period'=>$period,
            'management_fee_basis_points'=>self::RATE_BPS,
            'active_contracted_channel_count'=>count($byChannel),
            'channels'=>$byChannel,
            'planned_media_cents'=>$plannedTotal,
            'unverified_actual_media_cents'=>$actualTotal,
            'estimated_management_fee_cents'=>$estimatedTotal,
            'unverified_earned_fee_cents'=>$earnedTotal,
            'review_only_adjustment_cents'=>$earnedTotal-$estimatedTotal,
            'media_paid_directly_to_platforms'=>true,
            'provider_spend_verified'=>false,
            'portal_agreement_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'automatic_charge_or_credit_enabled'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
            'crm_write_enabled'=>false,
        ];
    }

    private static function percentage(int $cents):int
    {
        // 20% with half-up integer-cent rounding PER channel; never floats.
        return intdiv($cents*self::RATE_BPS+5000,10000);
    }
}
