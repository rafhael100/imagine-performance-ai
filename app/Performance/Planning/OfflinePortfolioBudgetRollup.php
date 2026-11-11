<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F202: combine 1..20 F105 budget PREVIEWS, deterministically and exactly.
 * A rollup is not an approved media plan and authorizes NO advertising.
 */
final class OfflinePortfolioBudgetRollup
{
    public const SCHEMA = 'perf.planning.offline-portfolio.synthetic.v1';
    private const MAX_SCENARIOS = 20;

    public static function aggregate(array $request): array
    {
        $fields = array_keys($request);
        sort($fields);
        if ($fields !== ['scenarios','schema_version']
            || ($request['schema_version']??null) !== self::SCHEMA
            || !is_array($request['scenarios'])
            || !array_is_list($request['scenarios'])
            || count($request['scenarios']) < 1
            || count($request['scenarios']) > self::MAX_SCENARIOS) {
            throw new DomainException('Invalid offline portfolio contract');
        }
        $seen=[];
        $plans=[];
        $dateFrom=null;
        $dateTo=null;
        $daily=[];
        $total=$google=$meta=0;

        foreach($request['scenarios'] as $scenario) {
            if(!is_array($scenario)) {
                throw new DomainException('Scenario must be an object');
            }
            $plan=OfflineBudgetScenarioPreview::calculate($scenario);
            $ref=$plan['scenario_ref'];
            if(isset($seen[$ref])) {
                throw new DomainException('Duplicate scenario reference');
            }
            $seen[$ref]=true;
            if ($dateFrom===null) {
                $dateFrom=$plan['start_date'];
                $dateTo=$plan['end_date'];
                foreach($plan['daily'] as $item) {
                    $daily[$item['date']]=[
                        'date'=>$item['date'],
                        'total_centavos'=>0,
                        'google_ads_centavos'=>0,
                        'meta_ads_centavos'=>0
                    ];
                }
            } elseif ($dateFrom!==$plan['start_date']
                ||$dateTo!==$plan['end_date']) {
                throw new DomainException('Portfolio requires identical calendar window');
            }

            $sumGoogle=$plan['channel_totals_centavos']['google_ads'];
            $sumMeta=$plan['channel_totals_centavos']['meta_ads'];
            $total+=$plan['total_centavos'];
            $google+=$sumGoogle;
            $meta+=$sumMeta;
            $plans[]=[
                'scenario_ref'=>$ref,
                'total_centavos'=>$plan['total_centavos'],
                'channel_totals_centavos'=>$plan['channel_totals_centavos'],
            ];

            foreach($plan['daily'] as $item) {
                $date=$item['date'];
                if(!isset($daily[$date])) {
                    throw new DomainException('Scenario days do not align');
                }
                $daily[$date]['total_centavos']+=$item['total_centavos'];
                $daily[$date]['google_ads_centavos']+=$item['google_ads_centavos'];
                $daily[$date]['meta_ads_centavos']+=$item['meta_ads_centavos'];
            }
        }
        usort($plans,static fn(array $a,array $b):int=>
            strcmp($a['scenario_ref'],$b['scenario_ref']));

        if ($total!==$google+$meta) {
            throw new DomainException('Portfolio channel allocation did not reconcile');
        }
        $dailyValues=array_values($daily);
        $checkTotal=$checkGoogle=$checkMeta=0;
        foreach($dailyValues as $row){
            if($row['total_centavos']!==$row['google_ads_centavos']
                +$row['meta_ads_centavos']) {
                throw new DomainException('Daily portfolio channels do not reconcile');
            }
            $checkTotal+=$row['total_centavos'];
            $checkGoogle+=$row['google_ads_centavos'];
            $checkMeta+=$row['meta_ads_centavos'];
        }
        if($checkTotal!==$total||$checkGoogle!==$google||$checkMeta!==$meta){
            throw new DomainException('Daily and period totals do not reconcile');
        }
        return [
            'schema_version'=>self::SCHEMA,
            'currency'=>'BRL',
            'scenario_count'=>count($plans),
            'start_date'=>$dateFrom,
            'end_date'=>$dateTo,
            'number_of_days'=>count($dailyValues),
            'total_centavos'=>$total,
            'channel_totals_centavos'=>[
                'google_ads'=>$google,'meta_ads'=>$meta
            ],
            'scenarios'=>$plans,
            'daily'=>$dailyValues,
            'simulation_only'=>true,
            'advertiser_approval_verified'=>false,
            'provider_connected'=>false,
            'media_execution_allowed'=>false,
            'automatic_pause_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }
}
