<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-56: CSV export ONLY of versioned synthetic Portal DTO.
 * No real provider metrics, accounts, users, prices, billing, network, DB
 * or writes. Known labels only; untrusted labels cannot inject formulas.
 */
final class SyntheticScopeCsvExporter
{
    public function export(?array $dto): ?string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F56_CSV_LAB')!=='1'
            ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1')return null;
        if(!is_array($dto)
            ||($dto['schema_version']??null)!=='perf.portal.scope-preview.synthetic.v1'
            ||($dto['source']??null)!=='in_memory_fixture_only'
            ||($dto['commercial']??null)!=='not_homologated'
            ||($dto['read_only']??null)!==true
            ||($dto['contracted']??null)!==false
            ||($dto['ready_for_production']??null)!==false
            ||($dto['authorization_eligible']??null)!==false
            ||($dto['dispatch_enabled']??null)!==false
            ||($dto['billing_enabled']??null)!==false
            ||($dto['prices_homologated']??null)!==false
            ||!is_string($dto['tenant_ref']??null)
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$dto['tenant_ref'])
            ||!isset($dto['channels'])||!is_array($dto['channels'])
            ||count($dto['channels'])!==2){
            return null;
        }
        $byChannel=[];
        foreach($dto['channels'] as $row){
            if(!is_array($row)||!is_string($row['id']??null)
                ||!in_array($row['id'],['google_ads','meta'],true)
                ||isset($byChannel[$row['id']])
                ||!in_array($row['capacity_status']??null,
                    ['configured_synthetic_only','unconfigured_blocked_demo'],true)){
                return null;
            }
            foreach(['campaigns','groups'] as $field){
                $v=$row[$field]??null;
                if(!is_array($v)||!isset($v['used'])||!is_int($v['used'])
                    ||$v['used']<0||$v['used']>10000
                    ||!array_key_exists('limit',$v)
                    ||($v['limit']!==null&&(!is_int($v['limit'])
                        ||$v['limit']<0||$v['limit']>10000))){
                    return null;
                }
                if($v['limit']!==null&&$v['used']>$v['limit'])return null;
            }
            if($row['capacity_status']==='configured_synthetic_only'
                &&($row['campaigns']['limit']===null
                   ||$row['groups']['limit']===null)){
                return null;
            }
            if(($row['capacity_status']==='unconfigured_blocked_demo')
                !==($row['campaigns']['limit']===null
                    &&$row['groups']['limit']===null)){
                return null;
            }
            $byChannel[$row['id']]=$row;
        }
        if(!isset($byChannel['google_ads'],$byChannel['meta']))return null;
        $handle=fopen('php://temp','w+');
        if($handle===false)return null;
        try{
            fwrite($handle,"\xEF\xBB\xBF");
            // Explicit empty escape per modern fputcsv; semicolon separator.
            $write=static fn(array $fields):int|false=>
                fputcsv($handle,$fields,';','"','');
            $head=['ambiente','canal','campanhas_sob_gestao',
                'limite_campanhas_ilustrativo','grupos_conjuntos_sob_gestao',
                'limite_grupos_ilustrativo','contagem_grupos','situacao'];
            if($write($head)===false)return null;
            foreach([
                'google_ads'=>'Google Ads','meta'=>'Meta (Instagram/Facebook)'
            ] as $id=>$label){
                $row=$byChannel[$id];
                $campaignLimit=$row['campaigns']['limit'];
                $groupLimit=$row['groups']['limit'];
                $values=[
                    'DEMONSTRACAO - NAO CONTRATADO',$label,
                    (string)$row['campaigns']['used'],
                    $campaignLimit===null?'NAO HOMOLOGADO':(string)$campaignLimit,
                    (string)$row['groups']['used'],
                    $groupLimit===null?'NAO HOMOLOGADO':(string)$groupLimit,
                    'HIPOTESE NAO HOMOLOGADA','SEM FATURAMENTO OU EXECUCAO'
                ];
                if($write($values)===false)return null;
            }
            rewind($handle);
            $csv=stream_get_contents($handle);
            return is_string($csv)?$csv:null;
        }finally{
            fclose($handle);
        }
    }
}
