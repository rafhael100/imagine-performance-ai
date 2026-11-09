<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use Throwable;

/**
 * FAST-PERF F1-68 — offline JSON/CSV export from the *same trusted fixture
 * PostgreSQL session* used by the FORCE RLS F1-67 read model.
 *
 * No tenant input and no untrusted DTO parameter. This test-only export
 * contains no real ad metrics, clients, contracts, billing or provider IDs.
 */
final class SyntheticPgTenantReportExporter
{
    public function export(PDO $db, string $format): ?string
    {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
           ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'
           ||getenv('PERF_F68_PG_REPORT_LAB')!=='1'
           ||!in_array($format,['json','csv'],true)){
            return null;
        }
        $dto=(new SyntheticPgTenantScopeProjector())->build($db);
        if(!is_array($dto)
           ||($dto['schema_version']??null)!=='perf.portal.pg-scope-read.synthetic.v1'
           ||($dto['source']??null)!=='postgres_force_rls_fixture_only'
           ||!in_array($dto['tenant_role']??null,[
               'perf_f66_tenant_a','perf_f66_tenant_b'
           ],true)
           ||($dto['read_only']??null)!==true
           ||($dto['contracted']??null)!==false
           ||($dto['human_identity_verified']??null)!==false
           ||($dto['provider_connected']??null)!==false
           ||($dto['authorization_eligible']??null)!==false
           ||($dto['ready_for_production']??null)!==false
           ||($dto['dispatch_enabled']??null)!==false
           ||($dto['billing_enabled']??null)!==false
           ||($dto['prices_homologated']??null)!==false
           ||!is_array($dto['channels']??null)
           ||!is_array($dto['campaigns']??null)
           ||!is_array($dto['groups']??null)){
            return null;
        }

        $report=[
            'schema_version'=>'perf.report.pg-scope.synthetic.v1',
            'source'=>'pg_role_bound_fixture_only',
            'tenant_role'=>$dto['tenant_role'],
            'read_only'=>true,
            'contracted'=>false,
            'authorization_eligible'=>false,
            'provider_connected'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'prices_homologated'=>false,
            'actual_ads_metrics_available'=>false,
            'currency'=>null,
            'media_spend'=>null,
            'impressions'=>null,
            'clicks'=>null,
            'conversions'=>null,
            'revenue'=>null,
            'channels'=>$dto['channels'],
            'campaigns'=>$dto['campaigns'],
            'groups'=>$dto['groups'],
            'notice'=>'DEMONSTRACAO SQL RLS: SEM CLIENTES, ANUNCIOS OU COBRANCA REAL',
        ];
        if($format==='json'){
            try{
                $out=json_encode($report,JSON_UNESCAPED_UNICODE
                  |JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT
                  |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS
                  |JSON_HEX_QUOT|JSON_THROW_ON_ERROR)."\n";
                return strlen($out)<200000?$out:null;
            }catch(Throwable){
                return null;
            }
        }
        $fh=fopen('php://temp','w+');
        if($fh===false)return null;
        try{
            self::line($fh,[
                'record_type','tenant_fixture_role','channel','reference',
                'status','used','limit','remaining','note'
            ]);
            self::line($fh,[
                'report',$report['tenant_role'],'',
                'perf-report-fixture','synthetic_only','','','',
                'SEM CLIENTES, ANUNCIOS OU COBRANCA REAL'
            ]);
            foreach($report['channels'] as $channel){
                foreach(['campaigns','groups'] as $category){
                    $cap=$channel[$category];
                    self::line($fh,[
                        $category,$report['tenant_role'],$channel['id'],'',
                        $channel['attention'],
                        $cap['used'],$cap['limit'],$cap['remaining'],
                        'capacity_mock_not_contracted'
                    ]);
                }
            }
            foreach($report['campaigns'] as $campaign){
                self::line($fh,[
                    'campaign',$report['tenant_role'],$campaign['channel'],
                    $campaign['ref'],$campaign['state'],'','','',
                    'mock_under_management_no_provider_action'
                ]);
            }
            foreach($report['groups'] as $group){
                self::line($fh,[
                    'group',$report['tenant_role'],$group['channel'],
                    $group['ref'],$group['state'],'','','',
                    'mock_only_no_provider_action'
                ]);
            }
            rewind($fh);
            $csv=stream_get_contents($fh);
            return is_string($csv)&&strlen($csv)<65536?$csv:null;
        }catch(Throwable){
            return null;
        }finally{
            fclose($fh);
        }
    }

    /** RFC-style quoting; neutralize spreadsheet formula prefixes. */
    private static function line(mixed $file,array $fields):void
    {
        $safe=array_map(static function(mixed $raw):string{
            if($raw===null)return '';
            $value=(string)$raw;
            if(preg_match('/^[\x00-\x20]*[=+@-]/',$value)
               ||preg_match('/^[\t\r\n]/',$value)){
                return "'".$value;
            }
            return $value;
        },$fields);
        if(fputcsv($file,$safe,',','"','')===false){
            throw new \RuntimeException('F68 could not encode CSV');
        }
    }
}
