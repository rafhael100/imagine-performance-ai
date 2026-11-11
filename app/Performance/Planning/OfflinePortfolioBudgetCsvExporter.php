<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F203: deterministic, in-memory CSV for the SYNTHETIC F202 budget rollup.
 * No disk, provider API, customer IDs, financial settlement or Ads controls.
 */
final class OfflinePortfolioBudgetCsvExporter
{
    public const SCHEMA = 'perf.planning.offline-portfolio-csv.synthetic.v1';

    public static function render(array $request): array
    {
        $keys=array_keys($request);
        sort($keys);
        if($keys!==['portfolio_request','schema_version']
            ||($request['schema_version']??null)!==self::SCHEMA
            ||!is_array($request['portfolio_request'])){
            throw new DomainException('Untrusted or malformed offline CSV request');
        }
        $portfolio=OfflinePortfolioBudgetRollup::aggregate($request['portfolio_request']);
        if ($portfolio['simulation_only']!==true
            || $portfolio['media_execution_allowed']!==false
            || $portfolio['billing_allowed']!==false) {
            throw new DomainException('Refuse CSV of operational data');
        }
        $stream=fopen('php://memory','w+');
        if($stream===false)throw new DomainException('CSV output unavailable');
        try {
            $write=static function(array $cols)use($stream):void {
                // RFC4180: disable legacy backslash escape entirely.
                // Every field below is derived from canonical F105/F202.
                if(fputcsv($stream,$cols,',','"','',"\n")===false){
                    throw new DomainException('CSV row unavailable');
                }
            };
            $write(['kind','reference','date','google_ads_centavos',
                'meta_ads_centavos','total_centavos']);
            $write(['portfolio','synthetic-aggregate','',
                (string)$portfolio['channel_totals_centavos']['google_ads'],
                (string)$portfolio['channel_totals_centavos']['meta_ads'],
                (string)$portfolio['total_centavos']]);
            foreach($portfolio['scenarios'] as $scenario){
                $write(['scenario',$scenario['scenario_ref'],'',
                    (string)$scenario['channel_totals_centavos']['google_ads'],
                    (string)$scenario['channel_totals_centavos']['meta_ads'],
                    (string)$scenario['total_centavos']]);
            }
            foreach($portfolio['daily'] as $day){
                $write(['daily','synthetic-aggregate',$day['date'],
                    (string)$day['google_ads_centavos'],
                    (string)$day['meta_ads_centavos'],
                    (string)$day['total_centavos']]);
            }
            rewind($stream);
            $csv=stream_get_contents($stream);
        }finally{
            fclose($stream);
        }
        if(!is_string($csv)||strlen($csv)>75_000||strlen($csv)<125){
            throw new DomainException('CSV bounds violated');
        }
        return [
            'schema_version'=>self::SCHEMA,
            'filename'=>'performance-offline-portfolio-demo.csv',
            'content_type'=>'text/csv; charset=utf-8',
            'row_count'=>2+$portfolio['scenario_count']+$portfolio['number_of_days'],
            'sha256'=>hash('sha256',$csv),
            'csv'=>$csv,
            'simulation_only'=>true,
            'contains_real_customer_data'=>false,
            'provider_connected'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }
}
