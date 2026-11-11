<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F153: compare two independently rendered, but fictional, offline reports.
 * No operational authorization, identity, provider API, HTTP or customer data.
 */
final class F153OfflinePreviewCsvParity
{
    /** @return array{ok:bool,count:int,digest:string}|null */
    public function compare(string $html,string $csv):?array
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F153_PARITY_LAB')!=='1'
            ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
            ||getenv('PERF_F152_CSV_LAB')!=='1')return null;
        if(strlen($html)>150000||strlen($csv)>35000
            ||!str_starts_with($csv,"\xEF\xBB\xBF"))return null;
        $pattern='~<script id="preview-data" type="application/json">(.*?)</script>~s';
        if(preg_match_all($pattern,$html,$matches)!==1)return null;
        try{$data=json_decode(trim($matches[1][0]),true,32,JSON_THROW_ON_ERROR);}
        catch(\Throwable $e){return null;}
        if(!is_array($data)
            ||($data['schema_version']??null)!=='perf.portal.scope-preview.synthetic.v1'
            ||($data['tenant_ref']??null)!=='perf-demo-aurora'
            ||($data['contracted']??null)!==false
            ||($data['dispatch_enabled']??null)!==false
            ||($data['billing_enabled']??null)!==false
            ||($data['authorization_eligible']??null)!==false
            ||($data['ready_for_production']??null)!==false
            ||($data['creative_reviews']['schema_version']??null)
                !=='perf.portal.creative-reviews.synthetic.v1'
            ||($data['creative_reviews']['human_approval_verified']??null)!==false
            ||($data['creative_reviews']['dispatch_enabled']??null)!==false
            ||($data['creative_reviews']['billing_enabled']??null)!==false
            ||!is_array($data['approvals']??null)
            ||count($data['approvals'])!==2)return null;
        $approvals=[];
        $labelMap=[
            'Aguardando revisão fictícia'=>'Pendente ficticio',
            'Ajustes solicitados (fictício)'=>'Ajustes ficticios solicitados',
            'Aprovada apenas na simulação'=>'Aprovada somente em teste',
            'Rejeitada apenas na simulação'=>'Rejeitada somente em teste',
        ];
        foreach($data['approvals'] as $row){
            if(!is_array($row)
                ||!is_string($row['review_ref']??null)
                ||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$row['review_ref'])
                ||isset($approvals[$row['review_ref']])
                ||!is_string($row['title']??null)
                ||!in_array($row['channel']??null,['google_ads','meta'],true)
                ||!isset($labelMap[$row['status']??''])
                ||($row['approval_source']??null)!=='none_real')return null;
            $approvals[$row['review_ref']]=[
                'title'=>$row['title'],'channel'=>$row['channel'],
                'csv_state'=>$labelMap[$row['status']],
            ];
        }
        $handle=fopen('php://temp','w+');
        if($handle===false)return null;
        try {
            fwrite($handle,substr($csv,3));
            rewind($handle);
            $header=fgetcsv($handle,0,';','"','');
            if($header!==[
                'ambiente','empresa_ficticia','revisao','campanha_ficticia',
                'canal','titulo','situacao_ficticia','versao',
                'aprovacao_humana','execucao','faturamento',
            ])return null;
            $seen=[];
            while(($cols=fgetcsv($handle,0,';','"',''))!==false){
                if(count($cols)!==11)return null;
                [$environment,$tenant,$review,$campaign,$channel,$title,$status,
                    $version,$human,$action,$billing]=$cols;
                if($environment!=='DEMONSTRACAO - SEM CONTRATO'
                    ||$tenant!=='perf-demo-aurora'
                    ||!isset($approvals[$review])||isset($seen[$review])
                    ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$campaign)
                    ||!ctype_digit($version)||(int)$version<1
                    ||$human!=='NAO E APROVACAO REAL'
                    ||$action!=='SEM ANUNCIOS'
                    ||$billing!=='SEM COBRANCA')return null;
                $expected=$approvals[$review];
                $expectedChannel=$expected['channel']==='google_ads'
                    ?'Google Ads':'Meta (Instagram/Facebook)';
                $safeTitle=preg_match('/^\s*[=+\-@]/u',$expected['title'])
                    ?"'".$expected['title']:$expected['title'];
                if($channel!==$expectedChannel||$title!==$safeTitle
                    ||$status!==$expected['csv_state'])return null;
                $seen[$review]=true;
            }
            if(count($seen)!==count($approvals))return null;
        } finally {
            fclose($handle);
        }
        $canonical=[];
        foreach($approvals as $ref=>$v)$canonical[$ref]=$v;
        ksort($canonical,SORT_STRING);
        return [
            'ok'=>true,'count'=>count($canonical),
            'digest'=>hash('sha256',json_encode($canonical,
                JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        ];
    }
}
