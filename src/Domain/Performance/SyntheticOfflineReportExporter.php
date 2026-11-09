<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use Throwable;

/**
 * FAST-PERF F1-56 - offline, fictional, read-only report serializer.
 *
 * A DTO is NOT an authorization credential. Only the hard-coded synthetic
 * fixture tenant is accepted; no Portal users, real accounts or media metrics.
 * Fail closed on unfamiliar fields, inconsistent totals or unsafe references.
 */
final class SyntheticOfflineReportExporter
{
    private const TENANT='perf-demo-aurora';

    private static function enabled(): bool
    {
        return PHP_SAPI==='cli'
            && getenv('APP_ENV')==='testing'
            && getenv('PERF_F52_SCOPE_LAB')==='1'
            && getenv('PERF_F53_PORTAL_PREVIEW_LAB')==='1'
            && getenv('PERF_F56_EXPORT_LAB')==='1';
    }

    private static function fields(array $row, array $expected): bool
    {
        $keys=array_keys($row);
        sort($keys);
        sort($expected);
        return $keys===$expected;
    }

    /** @return array<string,mixed>|null */
    private static function verified(?array $dto, string $tenant): ?array
    {
        if ($dto===null || $tenant!==self::TENANT
            || !self::fields($dto,[
                'schema_version','source','tenant_ref','read_only',
                'contracted','ready_for_production','authorization_eligible',
                'dispatch_enabled','billing_enabled','prices_homologated',
                'commercial','channels','campaigns','groups','review_summary'
            ])
            || $dto['schema_version']!=='perf.portal.scope-preview.synthetic.v1'
            || $dto['source']!=='in_memory_fixture_only'
            || $dto['tenant_ref']!==$tenant || $dto['read_only']!==true
            || $dto['contracted']!==false
            || $dto['ready_for_production']!==false
            || $dto['authorization_eligible']!==false
            || $dto['dispatch_enabled']!==false
            || $dto['billing_enabled']!==false
            || $dto['prices_homologated']!==false
            || $dto['commercial']!=='not_homologated'
            || !is_array($dto['channels'])||count($dto['channels'])!==2
            || !is_array($dto['campaigns'])||count($dto['campaigns'])>20
            || !is_array($dto['groups'])||count($dto['groups'])>80
            || !is_array($dto['review_summary'])
            || !self::fields($dto['review_summary'],[
                'audit_entries_demo','rejected_actions_demo','approval_source'
            ])
            || $dto['review_summary']['approval_source']!=='none_real'
        ) return null;

        foreach(['audit_entries_demo','rejected_actions_demo'] as $name){
            if(!is_int($dto['review_summary'][$name])
                || $dto['review_summary'][$name]<0
                || $dto['review_summary'][$name]>1000) return null;
        }
        if($dto['review_summary']['rejected_actions_demo']
            >$dto['review_summary']['audit_entries_demo']) return null;

        $channels=[];
        $allow=[
            'google_ads'=>'Google Ads',
            'meta'=>'Meta (Instagram/Facebook)',
        ];
        foreach($dto['channels'] as $row){
            if(!is_array($row)||!self::fields($row,[
                'id','label','capacity_status','campaigns','groups','attention'
            ])||!is_string($row['id'])||!isset($allow[$row['id']])
              ||isset($channels[$row['id']])
              ||$row['label']!==$allow[$row['id']]
              ||!in_array($row['capacity_status'],[
                  'configured_synthetic_only','unconfigured_blocked_demo'
              ],true)
              ||!in_array($row['attention'],[
                  'commercial_capacity_not_approved',
                  'review_scope_no_auto_upgrade','none_demo'
              ],true)) return null;
            foreach(['campaigns','groups'] as $type){
                $cap=$row[$type];
                if(!is_array($cap)
                   ||!self::fields($cap,['used','limit','remaining'])
                   ||!is_int($cap['used'])||$cap['used']<0
                   ||$cap['used']>($type==='campaigns'?1000:10000)){
                    return null;
                }
                if($cap['limit']===null){
                    if($row['capacity_status']!=='unconfigured_blocked_demo'
                       ||$cap['remaining']!==null||$cap['used']!==0){
                        return null;
                    }
                } elseif(!is_int($cap['limit'])||$cap['limit']<0
                    ||$cap['limit']>($type==='campaigns'?1000:10000)
                    ||$cap['used']>$cap['limit']
                    ||$cap['remaining']!==$cap['limit']-$cap['used']){
                    return null;
                }
            }
            if(($row['capacity_status']==='configured_synthetic_only')
                !==($row['campaigns']['limit']!==null
                   &&$row['groups']['limit']!==null)) return null;
            $expectedAttention= $row['capacity_status']!=='configured_synthetic_only'
                ? 'commercial_capacity_not_approved'
                : (($row['campaigns']['remaining']===0
                    ||$row['groups']['remaining']===0)
                    ? 'review_scope_no_auto_upgrade':'none_demo');
            if($row['attention']!==$expectedAttention)return null;
            $channels[$row['id']]=$row;
        }
        if(array_keys($channels)!==['google_ads','meta']) return null;

        $unique=[];
        foreach(['campaigns'=>'cam-','groups'=>'grp-'] as $type=>$prefix){
            foreach($dto[$type] as $row){
                if(!is_array($row)
                   ||!self::fields($row,['ref','state','managed','provider_action'])
                   ||!is_string($row['ref'])
                   ||!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$row['ref'])
                   ||isset($unique[$row['ref']])
                   ||$row['managed']!==true
                   ||$row['provider_action']!=='none_synthetic'
                   ||!in_array($row['state'],[
                       'active_demo','paused_demo','draft_demo'
                   ],true))return null;
                $unique[$row['ref']]=true;
            }
        }
        if(count($dto['campaigns'])>
            $channels['google_ads']['campaigns']['used']
            +$channels['meta']['campaigns']['used'])return null;
        if(count($dto['groups'])>
            $channels['google_ads']['groups']['used']
            +$channels['meta']['groups']['used'])return null;
        return $dto;
    }

    /**
     * Creates only a synthetic snapshot. User-provided DTOs and tenant refs
     * are NOT authenticated; only the fixed demo tenant can be serialized.
     */
    public function export(?array $dto, string $tenant, string $format): ?string
    {
        if(!self::enabled()||!in_array($format,['json','csv'],true))return null;
        $verified=self::verified($dto,$tenant);
        if($verified===null)return null;

        $out=[
            'schema_version'=>'perf.report.synthetic.v1',
            'source'=>'derived_from_f53_in_memory_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,
            'contracted'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'ready_for_production'=>false,
            'real_provider_metrics_available'=>false,
            'currency'=>null,
            'media_spend'=>null,
            'note'=>'DEMONSTRACAO: sem clientes, anuncios, aprovacao ou cobranca reais',
            'channels'=>array_map(static fn(array $row):array=>[
                'id'=>$row['id'],
                'label'=>$row['label'],
                'campaigns'=>$row['campaigns'],
                'groups'=>$row['groups'],
                'attention'=>$row['attention'],
            ],$verified['channels']),
            'campaigns'=>array_map(static fn(array $row):array=>[
                'ref'=>$row['ref'],'state'=>$row['state']
            ],$verified['campaigns']),
            'groups'=>array_map(static fn(array $row):array=>[
                'ref'=>$row['ref'],'state'=>$row['state']
            ],$verified['groups']),
            'review_summary'=>$verified['review_summary'],
        ];
        if($format==='json'){
            try{
                return json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
                   |JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            }catch(Throwable){return null;}
        }

        $fh=fopen('php://temp','w+');
        if($fh===false)return null;
        $columns=[
            'record_type','channel','reference','status',
            'used','limit','remaining','note'
        ];
        self::csvLine($fh,$columns);
        self::csvLine($fh,[
            'report','','perf-demo-aurora','synthetic_only','','','',
            'SEM DADOS REAIS OU COBRANCA'
        ]);
        foreach($out['channels'] as $row){
            foreach(['campaigns','groups'] as $type){
                $cap=$row[$type];
                self::csvLine($fh,[
                    $type,$row['id'],'',$row['attention'],
                    $cap['used'],$cap['limit'],$cap['remaining'],
                    'capacidades ficticias nao contratadas'
                ]);
            }
        }
        foreach(['campaigns'=>'campaign','groups'=>'group'] as $category=>$kind){
            foreach($out[$category] as $item){
                self::csvLine($fh,[
                    $kind,'',$item['ref'],$item['state'],'','','',
                    'sob gestao somente na demonstracao'
                ]);
            }
        }
        rewind($fh);
        $csv=stream_get_contents($fh);
        fclose($fh);
        return is_string($csv)&&strlen($csv)<65536?$csv:null;
    }

    /** Every CSV cell is neutralized before RFC-style quoting. */
    private static function csvLine(mixed $fh, array $row): void
    {
        $safe=array_map(static function(mixed $value):string{
            if($value===null)return '';
            $s=(string)$value;
            if(preg_match('/^[\x00-\x20]*[=+@-]/',$s)
               ||preg_match('/^[\t\r\n]/',$s)){
                return "'".$s;
            }
            return $s;
        },$row);
        if(fputcsv($fh,$safe,',','"','')===false){
            throw new \RuntimeException('CSV write failed');
        }
    }
}
