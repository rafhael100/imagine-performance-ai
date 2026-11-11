<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F199: compare two fully reconciled F105 BRL simulations, never media spend.
 * Integer centavos only; absolutely no provider API, tenant identities,
 * approval, billing, persistence, or production authority.
 */
final class OfflineBudgetScenarioComparison
{
    public const SCHEMA='perf.planning.offline-budget-comparison.synthetic.v1';

    public static function compare(array $request):array
    {
        $keys=array_keys($request);
        sort($keys);
        if($keys!==['alternative','baseline','schema_version']
            ||($request['schema_version']??null)!==self::SCHEMA
            ||!is_array($request['baseline'])
            ||!is_array($request['alternative'])){
            throw new DomainException('Invalid offline comparison contract');
        }

        // Reuse existing F105 canonical date validation, exact integer
        // allocation, scope and channel-split validation.
        $baseline=OfflineBudgetScenarioPreview::calculate($request['baseline']);
        $alternative=OfflineBudgetScenarioPreview::calculate($request['alternative']);
        if($baseline['scenario_ref']===$alternative['scenario_ref']){
            throw new DomainException('Comparison requires distinct scenario references');
        }
        if($baseline['currency']!==$alternative['currency']
            ||$baseline['start_date']!==$alternative['start_date']
            ||$baseline['end_date']!==$alternative['end_date']
            ||$baseline['number_of_days']!==$alternative['number_of_days']){
            throw new DomainException('Use identical dates and currency for fair comparison');
        }
        $deltaTotal=$alternative['total_centavos']-$baseline['total_centavos'];
        $deltaGoogle=$alternative['channel_totals_centavos']['google_ads']
            -$baseline['channel_totals_centavos']['google_ads'];
        $deltaMeta=$alternative['channel_totals_centavos']['meta_ads']
            -$baseline['channel_totals_centavos']['meta_ads'];
        if($deltaGoogle+$deltaMeta!==$deltaTotal){
            throw new DomainException('Channel delta reconciliation failed');
        }

        $daily=[];
        $sumTotal=$sumGoogle=$sumMeta=0;
        foreach($baseline['daily'] as $i=>$b){
            $a=$alternative['daily'][$i]??null;
            if(!is_array($a)||$a['date']!==$b['date']){
                throw new DomainException('Misaligned calendar allocation');
            }
            $difference=$a['total_centavos']-$b['total_centavos'];
            $google=$a['google_ads_centavos']-$b['google_ads_centavos'];
            $meta=$a['meta_ads_centavos']-$b['meta_ads_centavos'];
            if($google+$meta!==$difference){
                throw new DomainException('Daily channel delta reconciliation failed');
            }
            $daily[]=[
                'date'=>$b['date'],
                'baseline_centavos'=>$b['total_centavos'],
                'alternative_centavos'=>$a['total_centavos'],
                'delta_centavos'=>$difference,
                'google_ads_delta_centavos'=>$google,
                'meta_ads_delta_centavos'=>$meta,
            ];
            $sumTotal+=$difference;
            $sumGoogle+=$google;
            $sumMeta+=$meta;
        }
        if($sumTotal!==$deltaTotal||$sumGoogle!==$deltaGoogle
            ||$sumMeta!==$deltaMeta){
            throw new DomainException('Daily totals did not reconcile');
        }
        return [
            'schema_version'=>self::SCHEMA,
            'currency'=>'BRL',
            'period'=>[
                'start_date'=>$baseline['start_date'],
                'end_date'=>$baseline['end_date'],
                'days'=>$baseline['number_of_days'],
            ],
            'baseline'=>[
                'scenario_ref'=>$baseline['scenario_ref'],
                'total_centavos'=>$baseline['total_centavos'],
                'channel_totals_centavos'=>$baseline['channel_totals_centavos'],
            ],
            'alternative'=>[
                'scenario_ref'=>$alternative['scenario_ref'],
                'total_centavos'=>$alternative['total_centavos'],
                'channel_totals_centavos'=>$alternative['channel_totals_centavos'],
            ],
            'delta_centavos'=>$deltaTotal,
            'channel_delta_centavos'=>[
                'google_ads'=>$deltaGoogle,
                'meta_ads'=>$deltaMeta,
            ],
            'daily'=>$daily,
            'simulation_only'=>true,
            'advertiser_approval_verified'=>false,
            'provider_connected'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }
}
