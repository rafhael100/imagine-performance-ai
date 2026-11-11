<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F205: static HTML view of F202 validated synthetic campaign portfolio.
 * No JS, CSS, URL, real data, file persistence, web routes, Ads, or billing.
 */
final class OfflinePortfolioHtmlPreview
{
    public const SCHEMA = 'perf.planning.offline-portfolio-html.synthetic.v1';

    public static function render(array $request): array
    {
        $keys=array_keys($request);
        sort($keys);
        if($keys!==['portfolio_request','schema_version']
            ||($request['schema_version']??null)!==self::SCHEMA
            ||!is_array($request['portfolio_request'])){
            throw new DomainException('Invalid synthetic HTML preview contract');
        }
        $rollup=OfflinePortfolioBudgetRollup::aggregate($request['portfolio_request']);
        if ($rollup['simulation_only']!==true
            || $rollup['media_execution_allowed']!==false
            || $rollup['billing_allowed']!==false) {
            throw new DomainException('Cannot display operational portfolio');
        }
        $safe=static fn(string $value):string=>
            htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8',false);
        // Use integer arithmetic end-to-end; centavos never touch float.
        $money=static function(int $cents):string {
            if($cents<0)throw new DomainException('Negative portfolio budget');
            return 'R$ '.intdiv($cents,100).','.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
        };
        $html='<!DOCTYPE html>'."\n"
            .'<html lang="pt-BR"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; '
            .'style-src &#39;none&#39;; script-src &#39;none&#39;; '
            .'connect-src &#39;none&#39;; img-src &#39;none&#39;; '
            .'form-action &#39;none&#39;; base-uri &#39;none&#39;">'
            .'<meta name="robots" content="noindex,nofollow,noarchive">'
            .'<title>Imagine Performance — simulação de orçamento</title>'
            .'</head><body>'
            .'<main><h1>Planejamento demonstrativo de campanhas</h1>'
            .'<p><strong>DEMONSTRAÇÃO NÃO HOMOLOGADA</strong> — '
            .'dados fictícios, nenhum anúncio ou pagamento autorizado.</p>'
            .'<p>Período: '.$safe($rollup['start_date'])
            .' a '.$safe($rollup['end_date'])
            .' | Cenários: '.$rollup['scenario_count'].'</p>'
            .'<p>Orçamento total simulado: '
            .$safe($money($rollup['total_centavos'])).'</p>'
            .'<h2>Distribuição por cenário</h2>'
            .'<table><thead><tr><th scope="col">Cenário</th>'
            .'<th scope="col">Google Ads</th><th scope="col">Meta Ads</th>'
            .'<th scope="col">Total</th></tr></thead><tbody>';
        foreach($rollup['scenarios'] as $s){
            $html.='<tr><th scope="row">'.$safe($s['scenario_ref']).'</th>'
                .'<td>'.$safe($money($s['channel_totals_centavos']['google_ads'])).'</td>'
                .'<td>'.$safe($money($s['channel_totals_centavos']['meta_ads'])).'</td>'
                .'<td>'.$safe($money($s['total_centavos'])).'</td></tr>';
        }
        $html.='</tbody></table><h2>Distribuição diária</h2>'
            .'<table><thead><tr><th scope="col">Data</th>'
            .'<th scope="col">Google Ads</th><th scope="col">Meta Ads</th>'
            .'<th scope="col">Total</th></tr></thead><tbody>';
        foreach($rollup['daily'] as $d){
            $html.='<tr><th scope="row">'.$safe($d['date']).'</th>'
                .'<td>'.$safe($money($d['google_ads_centavos'])).'</td>'
                .'<td>'.$safe($money($d['meta_ads_centavos'])).'</td>'
                .'<td>'.$safe($money($d['total_centavos'])).'</td></tr>';
        }
        $html.='</tbody></table></main></body></html>'."\n";

        if(strlen($html)>150_000
            ||preg_match('/<(?:script|style|iframe|form|button|a|img|link|input)\b/i',$html)
            ||preg_match('~https?://~i',$html)){
            throw new DomainException('Unsafe or oversized static HTML preview');
        }
        return [
            'schema_version'=>self::SCHEMA,
            'content_type'=>'text/html; charset=utf-8',
            'html'=>$html,
            'html_sha256'=>hash('sha256',$html),
            'simulation_only'=>true,
            'contains_real_customer_data'=>false,
            'provider_connected'=>false,
            'media_execution_allowed'=>false,
            'billing_allowed'=>false,
            'ready_for_production'=>false,
        ];
    }
}
