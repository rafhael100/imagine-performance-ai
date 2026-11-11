<?php
declare(strict_types=1);

/**
 * F207: generate ONE static demo HTML in memory from stdin.
 * Shell redirect is caller-owned; no server, route, storage, advertiser
 * authorization, API call, HTML script, campaign execution or billing.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F207_HTML_CLI_LAB')!=='1'||$argc!==1){
    fwrite(STDERR,"F207 unavailable outside the synthetic test CLI\n");
    exit(3);
}
$root=dirname(__DIR__);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetRollup.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioHtmlPreview.php';

try{
    $input=stream_get_contents(STDIN,20_001);
    if(!is_string($input)||strlen($input)<10||strlen($input)>20_000){
        throw new DomainException('Invalid bounded synthetic input');
    }
    $decoded=json_decode($input,true,20,JSON_THROW_ON_ERROR);
    if(!is_array($decoded)){
        throw new DomainException('Expected a versioned synthetic object');
    }
    $out=\App\Performance\Planning\OfflinePortfolioHtmlPreview::render($decoded);
    if(($out['content_type']??null)!=='text/html; charset=utf-8'
        ||($out['simulation_only']??null)!==true
        ||($out['media_execution_allowed']??null)!==false
        ||($out['billing_allowed']??null)!==false
        ||!is_string($out['html']??null)
        ||!hash_equals((string)$out['html_sha256'],hash('sha256',$out['html']))){
        throw new DomainException('Unsafe HTML preview');
    }
    $html=$out['html'];
    if(fwrite(STDOUT,$html)!==strlen($html)){
        throw new DomainException('Incomplete CLI HTML output');
    }
    exit(0);
}catch(Throwable){
    fwrite(STDERR,"F207 invalid or unavailable synthetic HTML preview\n");
    exit(2);
}
