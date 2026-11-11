<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F152: CSV of ONLY fictional scoped creative reviews (never an API or a
 * real client export). Rejects operational payloads and formula injection.
 */
final class F152SyntheticCreativeReviewCsvExporter
{
    /** @return string|null */
    public function export(?array $dto,string $expectedTenant):?string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
            ||getenv('PERF_F152_CSV_LAB')!=='1'){
            return null;
        }
        if(!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$expectedTenant))return null;
        if(!is_array($dto)
            ||($dto['tenant_ref']??null)!==$expectedTenant
            ||($dto['schema_version']??null)!=='perf.portal.creative-reviews.synthetic.v1'
            ||($dto['source']??null)!=='in_memory_fixture_only'
            ||($dto['read_only']??null)!==true
            ||($dto['contracted']??null)!==false
            ||($dto['human_approval_verified']??null)!==false
            ||($dto['authorization_eligible']??null)!==false
            ||($dto['dispatch_enabled']??null)!==false
            ||($dto['billing_enabled']??null)!==false
            ||($dto['ready_for_production']??null)!==false
            ||!is_string($dto['tenant_ref']??null)
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$dto['tenant_ref'])
            ||!is_array($dto['reviews']??null)
            ||!is_int($dto['review_count']??null)
            ||$dto['review_count']!==count($dto['reviews'])
            ||$dto['review_count']>25
            ||!is_array($dto['summary']??null))return null;

        $statuses=[
            'pending_demo'=>'Pendente ficticio',
            'changes_requested_demo'=>'Ajustes ficticios solicitados',
            'approved_mock_only'=>'Aprovada somente em teste',
            'rejected_mock_only'=>'Rejeitada somente em teste',
        ];
        $counts=array_fill_keys(array_keys($statuses),0);
        $seen=[];
        $outRows=[];
        foreach($dto['reviews'] as $item){
            if(!is_array($item)
                ||!is_string($item['review_ref']??null)
                ||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$item['review_ref'])
                ||isset($seen[$item['review_ref']])
                ||!is_string($item['campaign_ref']??null)
                ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$item['campaign_ref'])
                ||!in_array($item['channel']??null,['google_ads','meta'],true)
                ||!is_string($item['title']??null)
                ||$item['title']===''
                ||strlen($item['title'])>100
                ||preg_match('/[<>\x00-\x1f\x7f]/',$item['title'])
                ||!array_key_exists($item['status']??'', $statuses)
                ||!is_int($item['version']??null)
                ||$item['version']<1||$item['version']>1000
                ||($item['action_authorized']??null)!==false
                ||($item['human_approval_verified']??null)!==false
                ||($item['billing_enabled']??null)!==false) {
                return null;
            }
            $seen[$item['review_ref']]=true;
            $counts[$item['status']]++;
            $outRows[]=[
                'DEMONSTRACAO - SEM CONTRATO',
                $dto['tenant_ref'],
                $item['review_ref'],
                $item['campaign_ref'],
                $item['channel']==='google_ads'?'Google Ads':'Meta (Instagram/Facebook)',
                self::safeCell($item['title']),
                $statuses[$item['status']],
                (string)$item['version'],
                'NAO E APROVACAO REAL',
                'SEM ANUNCIOS',
                'SEM COBRANCA',
            ];
        }
        foreach($counts as $status=>$count){
            if(!isset($dto['summary'][$status])
                ||!is_int($dto['summary'][$status])
                ||$dto['summary'][$status]!==$count)return null;
        }
        if(count($dto['summary'])!==count($counts))return null;

        $file=fopen('php://temp','w+');
        if($file===false)return null;
        try {
            fwrite($file,"\xEF\xBB\xBF");
            $write=static fn(array $cells):int|false=>fputcsv($file,$cells,';','"','');
            if($write([
                'ambiente','empresa_ficticia','revisao','campanha_ficticia',
                'canal','titulo','situacao_ficticia','versao',
                'aprovacao_humana','execucao','faturamento',
            ])===false)return null;
            foreach($outRows as $row){
                if($write($row)===false)return null;
            }
            rewind($file);
            $output=stream_get_contents($file);
            return is_string($output)?$output:null;
        } finally {
            fclose($file);
        }
    }

    private static function safeCell(string $cell):string
    {
        // Excel/LibreOffice can interpret CSV values beginning with these
        // characters as formulas, even when fputcsv quotes them.
        return preg_match('/^\s*[=+\-@]/u',$cell) ? "'".$cell : $cell;
    }
}
