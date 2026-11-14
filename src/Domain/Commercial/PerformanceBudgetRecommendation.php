<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * PERF-CALC-R01 — exact arithmetic mirror of Comercial's PUBLIC
 * media-budget-calculator.mjs (approved starting amounts, Nov 14 2026).
 *
 * Informational recommendations only. NO live ads, CRM records, billing,
 * approval, contract, plan migration, provider evidence or guaranteed leads.
 */
final class PerformanceBudgetRecommendation
{
    private const MINIMUMS=[
        'chatgpt_ads'=>120000,
        'google_ads'=>100000,
        'meta_ads'=>70000,
    ];
    private const RECOMMENDED=[
        'chatgpt_ads'=>180000,
        'google_ads'=>150000,
        'meta_ads'=>100000,
    ];

    /**
     * @param list<string> $channels
     * @return array<string,mixed>
     */
    public static function plan(
        array $channels,
        string $objective='leads',
        int $targetLeads=0,
        int $historicalCplCents=0,
        int $monthlyLimitCents=0
    ): array {
        if (!array_is_list($channels) || count($channels)<1 ||
            count($channels)>3 || !in_array($objective,['leads','sales','awareness'],true) ||
            $targetLeads<0 || $targetLeads>100000 ||
            $historicalCplCents<0 || $historicalCplCents>100000000 ||
            $monthlyLimitCents<0 || $monthlyLimitCents>100000000 ||
            ($targetLeads===0)!==($historicalCplCents===0)) {
            throw new InvalidArgumentException('Invalid advertising planning scenario.');
        }
        $seen=[];
        foreach($channels as $id){
            if(!is_string($id) || !isset(self::MINIMUMS[$id]) || isset($seen[$id])){
                throw new InvalidArgumentException('Unknown or duplicated media channel.');
            }
            $seen[$id]=true;
        }
        sort($channels,SORT_STRING);
        $minimum=0;
        $baseline=0;
        foreach($channels as $id){
            $minimum+=self::MINIMUMS[$id];
            $baseline+=self::RECOMMENDED[$id];
        }
        // Visitor-declared CPL x visitor-declared volume is a planning
        // scenario, never a benchmark, forecast or performance guarantee.
        $scenario=$targetLeads>0
            ? $targetLeads * $historicalCplCents
            : null;
        if ($scenario!==null && $scenario>300000000) {
            throw new InvalidArgumentException('Media scenario exceeds calculator safety cap.');
        }
        $chosen=max($baseline,$scenario??0);
        $rows=[];
        $proposed=0;
        foreach($channels as $id){
            // Integer ceil to nearest R$10. Matches JS:
            // Math.ceil(chosen*recommended/baseline/1000)*1000.
            // Avoid binary floating point for currency.
            $denominator=$baseline*1000;
            $units=intdiv($chosen*self::RECOMMENDED[$id]+$denominator-1,$denominator);
            $suggested=max(self::RECOMMENDED[$id],$units*1000);
            $proposed+=$suggested;
            $rows[]=[
                'channel'=>$id,
                'minimum_cents'=>self::MINIMUMS[$id],
                'recommended_start_cents'=>self::RECOMMENDED[$id],
                'suggested_cents'=>$suggested,
            ];
        }
        $fee=intdiv($proposed*20+50,100);
        return [
            'schema'=>'performance.media_budget_recommendation.v1',
            'currency'=>'BRL',
            'objective'=>$objective,
            'channel_rows'=>$rows,
            'minimum_media_cents'=>$minimum,
            'recommended_baseline_cents'=>$baseline,
            'visitor_goal_scenario_cents'=>$scenario,
            'uses_visitor_supplied_cpl'=>$historicalCplCents>0,
            'suggested_media_cents'=>$proposed,
            'illustrative_management_cents'=>$fee,
            'informational_total_cents'=>$proposed+$fee,
            'monthly_limit_cents'=>$monthlyLimitCents,
            'exceeds_monthly_limit'=>$monthlyLimitCents>0 && $proposed>$monthlyLimitCents,
            'media_paid_direct_to_platforms'=>true,
            'provider_spend_verified'=>false,
            'results_guaranteed'=>false,
            'direct_checkout_enabled'=>false,
            'crm_intake_enabled'=>false,
            'provider_ads_write_enabled'=>false,
            'asaas_charge_enabled'=>false,
        ];
    }
}
