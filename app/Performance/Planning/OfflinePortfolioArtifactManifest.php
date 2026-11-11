<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F206: reproducible evidence hashes of synthetic portfolio/CSV/HTML.
 * NOT an independent signature, WORM log, attestation or real approval.
 * Anyone controlling inputs and outputs can make a new matching manifest.
 */
final class OfflinePortfolioArtifactManifest
{
    public const SCHEMA='perf.planning.offline-artifact-manifest.synthetic.v1';

    public static function build(array $request): array
    {
        $keys=array_keys($request);
        sort($keys);
        if($keys!==['portfolio_request','schema_version']
            ||($request['schema_version']??null)!==self::SCHEMA
            ||!is_array($request['portfolio_request'])){
            throw new DomainException('Malformed synthetic artifact manifest input');
        }
        $portfolio=OfflinePortfolioBudgetRollup::aggregate($request['portfolio_request']);
        $canonical=json_encode(
            $portfolio,
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR
        );
        $csv=OfflinePortfolioBudgetCsvExporter::render([
            'schema_version'=>OfflinePortfolioBudgetCsvExporter::SCHEMA,
            'portfolio_request'=>$request['portfolio_request'],
        ]);
        $html=OfflinePortfolioHtmlPreview::render([
            'schema_version'=>OfflinePortfolioHtmlPreview::SCHEMA,
            'portfolio_request'=>$request['portfolio_request'],
        ]);
        if(!is_string($canonical)
            ||$csv['simulation_only']!==true
            ||$html['simulation_only']!==true
            ||$csv['media_execution_allowed']!==false
            ||$html['media_execution_allowed']!==false){
            throw new DomainException('Not a synthetic-only artifact collection');
        }
        $sourceHash=hash('sha256',$canonical);
        $csvHash=hash('sha256',$csv['csv']);
        $htmlHash=hash('sha256',$html['html']);
        if(!hash_equals($csvHash,$csv['sha256'])
            ||!hash_equals($htmlHash,$html['html_sha256'])){
            throw new DomainException('Output digest mismatch');
        }
        $aggregateHash=hash('sha256',
            self::SCHEMA."\n".$sourceHash."\n".$csvHash."\n".$htmlHash."\n");
        return [
            'schema_version'=>self::SCHEMA,
            'manifest_ref'=>'perf-demo-'.substr($aggregateHash,0,24),
            'source_sha256'=>$sourceHash,
            'csv_sha256'=>$csvHash,
            'html_sha256'=>$htmlHash,
            'bundle_sha256'=>$aggregateHash,
            'scenario_count'=>$portfolio['scenario_count'],
            'currency'=>'BRL',
            'start_date'=>$portfolio['start_date'],
            'end_date'=>$portfolio['end_date'],
            'total_centavos'=>$portfolio['total_centavos'],
            'simulation_only'=>true,
            'artifact_signature_verified'=>false,
            'independent_authority_verified'=>false,
            'advertiser_approval_verified'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }

    /** Only checks repeatability of a candidate snapshot; no trust attestation. */
    public static function matches(array $request,array $manifest):bool
    {
        try{
            return self::build($request)===$manifest;
        }catch(\Throwable){
            return false;
        }
    }
}
