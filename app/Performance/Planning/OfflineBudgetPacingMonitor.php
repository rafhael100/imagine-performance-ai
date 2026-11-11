<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F200: deterministic preview of hypothetical pacing against F105 daily
 * budgets. Observations are SYNTHETIC, never sourced from Google/Meta.
 * No automated spend, pause, provider request, billing or authorization.
 */
final class OfflineBudgetPacingMonitor
{
    public const SCHEMA='perf.planning.offline-pacing.synthetic.v1';
    private const MAX_CENTAVOS=2_000_000_000;

    public static function analyze(array $request):array
    {
        $keys=array_keys($request);
        sort($keys);
        if($keys!==['observations','scenario','schema_version','source']
            ||($request['schema_version']??null)!==self::SCHEMA
            ||($request['source']??null)!=='synthetic_fixture_only'
            ||!is_array($request['scenario'])
            ||!is_array($request['observations'])
            ||!array_is_list($request['observations'])){
            throw new DomainException('Expected synthetic-only pacing input');
        }
        $scenario=OfflineBudgetScenarioPreview::calculate($request['scenario']);
        if(count($request['observations'])>$scenario['number_of_days']){
            throw new DomainException('Observation exceeds scenario dates');
        }

        $runningPlanned=$runningObserved=$runningGoogle=$runningMeta=0;
        $daily=[];
        foreach($request['observations'] as $i=>$observation){
            if(!is_array($observation)){
                throw new DomainException('Observation must be an object');
            }
            $fields=array_keys($observation);
            sort($fields);
            if($fields!==['date','google_ads_centavos','meta_ads_centavos']){
                throw new DomainException('Unexpected observation fields');
            }
            if(($observation['date']??null)!==$scenario['daily'][$i]['date']){
                throw new DomainException('Observations must form an ordered prefix');
            }
            foreach(['google_ads_centavos','meta_ads_centavos'] as $channel){
                if(!is_int($observation[$channel])
                    ||$observation[$channel]<0
                    ||$observation[$channel]>self::MAX_CENTAVOS){
                    throw new DomainException('Synthetic spend must be bounded integer centavos');
                }
            }
            $planned=$scenario['daily'][$i];
            $google=$observation['google_ads_centavos'];
            $meta=$observation['meta_ads_centavos'];
            $observed=$google+$meta;
            if($observed>self::MAX_CENTAVOS){
                throw new DomainException('Daily synthetic observations exceed technical cap');
            }
            $runningPlanned+=$planned['total_centavos'];
            $runningObserved+=$observed;
            $runningGoogle+=$google;
            $runningMeta+=$meta;
            $daily[]=[
                'date'=>$planned['date'],
                'planned_centavos'=>$planned['total_centavos'],
                'observed_centavos'=>$observed,
                'daily_delta_centavos'=>$observed-$planned['total_centavos'],
                'cumulative_planned_centavos'=>$runningPlanned,
                'cumulative_observed_centavos'=>$runningObserved,
                'cumulative_delta_centavos'=>$runningObserved-$runningPlanned,
                'cumulative_above_plan'=>$runningObserved>$runningPlanned,
            ];
        }
        return [
            'schema_version'=>self::SCHEMA,
            'scenario_ref'=>$scenario['scenario_ref'],
            'currency'=>'BRL',
            'source'=>'synthetic_fixture_only',
            'observed_days'=>count($daily),
            'planned_total_centavos'=>$scenario['total_centavos'],
            'observed_total_centavos'=>$runningObserved,
            'cumulative_planned_centavos'=>$runningPlanned,
            'cumulative_delta_centavos'=>$runningObserved-$runningPlanned,
            'observed_channel_totals_centavos'=>[
                'google_ads'=>$runningGoogle,
                'meta_ads'=>$runningMeta,
            ],
            'cumulative_above_plan'=>$runningObserved>$runningPlanned,
            'total_budget_exceeded'=>$runningObserved>$scenario['total_centavos'],
            'daily'=>$daily,
            'simulation_only'=>true,
            'provider_connected'=>false,
            'advertiser_approval_verified'=>false,
            'media_execution_allowed'=>false,
            'automatic_pause_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }
}
