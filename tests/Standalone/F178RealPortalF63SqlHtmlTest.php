<?php
declare(strict_types=1);

/**
 * F178: validate actual HTML from original Portal F63 renderer, generated
 * from a freshly SQL-backed Performance F100 DTO — not a hand-built fixture.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F178_REAL_PORTAL_HTML_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('dom')){
 throw new RuntimeException('F178 HTML contract test is disposable CI only');
}
$path=(string)getenv('PERF_F178_HTML_OUTPUT_FILE');
$dtoPath=(string)getenv('PERF_F100_JSON_OUTPUT_FILE');
$root=(string)getenv('RUNNER_TEMP');
$root=realpath($root);
$real=realpath($path);
if($root===false ||$real===false
 ||!str_starts_with($real,$root.'/')
 ||is_link($path)||!is_file($path)
 ||filesize($path)<2000||filesize($path)>150000
 ||!str_starts_with($dtoPath,'/tmp/')
 ||!is_file($dtoPath)){
 throw new RuntimeException('F178 requires bounded, generated runner HTML and F100 DTO');
}
$html=(string)file_get_contents($path);
$dto=json_decode((string)file_get_contents($dtoPath),true,24,JSON_THROW_ON_ERROR);
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F178 FAILED: '.$why);
};
$check(str_starts_with($html,'<!doctype html>')
 &&str_contains($html,'<html lang="pt-BR">'),
 'actual original Portal F63 HTML is standards-based and localized');
$check(str_contains($html,'DEMONSTRAÇÃO NÃO HOMOLOGADA'),
 'actual HTML visibly distinguishes fictitious demo from production');
$check(str_contains($html,'Sem login de cliente')
 &&str_contains($html,'Nenhuma conta publicitária real está conectada'),
 'no verified login, advertiser accounts or live metrics are implied');
$check(str_contains($html,'Google Ads')
 &&str_contains($html,'Meta (Facebook e Instagram)'),
 'both supported demo advertising channels have explicit labels');
$check(str_contains($html,'Revisão interna DEMO — sem execução'),
 'mock approval is never displayed as permission to publish');

libxml_use_internal_errors(true);
$dom=new DOMDocument('1.0','UTF-8');
$ok=$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,
    LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
libxml_clear_errors();
$check($ok===true,'actual Portal HTML is parsed offline without networking');
$xpath=new DOMXPath($dom);
$check($xpath->query('//title')->length===1
 &&str_contains((string)$xpath->query('//title')->item(0)?->textContent,'Imagine Portal'),
 'Portal document has a single recognizable title');
$check($xpath->query('//meta[@http-equiv="Content-Security-Policy"]')->length===1,
 'original HTML contains exactly one Content Security Policy meta');
$csp=(string)$xpath->query('//meta[@http-equiv="Content-Security-Policy"]')
    ->item(0)?->getAttribute('content');
foreach([
 "default-src 'none'","style-src 'unsafe-inline'",
 "script-src 'none'","img-src 'none'","connect-src 'none'",
 "form-action 'none'","frame-ancestors 'none'"
] as $rule){
 $check(str_contains($csp,$rule),'F63 real HTML protects CSP '.$rule);
}
foreach(['script','form','button','a','link','iframe','input',
 'textarea','video','img','audio','object','embed'] as $tag){
 $check($dom->getElementsByTagName($tag)->length===0,
  'read-only snapshot cannot contain network/interaction tag '.$tag);
}
$check(!preg_match('/https?:\/\//i',$html),
 'F100-backed HTML does not include external links or fetch endpoints');

$accountRows=$xpath->query(
 '//section[@aria-label="Contas publicitárias fictícias"]//tbody/tr');
$campaignRows=$xpath->query(
 '//section[@aria-label="Campanhas fictícias"]//tbody/tr');
$reviewRows=$xpath->query(
 '//section[@aria-label="Histórico de revisões fictícias"]//tbody/tr');
$check($accountRows->length===$dto['counts']['account_candidates']
 &&$accountRows->length===2,
 'actual Portal table has exactly two F100 SQL-backed account candidates');
$check($campaignRows->length===$dto['counts']['linked_campaigns']
 &&$campaignRows->length===1,
 'actual Portal table has exactly one credential-bound SQL campaign');
$check($reviewRows->length===$dto['counts']['visible_reviews']
 &&$reviewRows->length===1,
 'actual Portal HTML preserves F100 joined review without made-up rows');

foreach($dto['accounts'] as $account){
 $check(str_contains($html,$account['account_ref']),
  'SQL-origin account reference is displayed unchanged');
}
foreach($dto['campaigns'] as $campaign){
 $check(str_contains($html,$campaign['campaign_ref'])
  &&str_contains($html,$campaign['campaign_title']),
  'actual SQL campaign identifier/title displayed unchanged');
}
foreach($dto['reviews'] as $review){
 $check(str_contains($html,$review['request_ref'])
  &&str_contains($html,$review['campaign_ref']),
  'SQL review links to the same visible campaign, not forged Portal stage');
}
$cards=$xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," card ")]/b');
$check($cards->length===6,'real Portal summary contains six DEMO indicators');
$values=[];
foreach($cards as $node)$values[]=(int)$node->textContent;
$check($values===[
 $dto['counts']['account_candidates'],
 $dto['counts']['linked_campaigns'],
 $dto['counts']['visible_reviews'],
 $dto['counts']['review_pending'],
 $dto['counts']['review_internal_no_execution'],
 $dto['counts']['review_rejected'],
], 'Portal visible indicators equal the actual F100 PostgreSQL query totals');

$check(!str_contains($html,'ci-f91-only-a')
 &&!str_contains($html,'ci-f91-only-b')
 &&!str_contains($html,'access_token')
 &&!str_contains($html,'refresh_token')
 &&!str_contains($html,'oauth_secret'),
 'generated HTML leaks no database fixture credentials or provider tokens');
$check($dto['portal_identity_authenticated']===false
 &&$dto['authorization_eligible']===false
 &&$dto['dispatch_enabled']===false
 &&$dto['billing_enabled']===false
 &&$dto['ready_for_production']===false,
 'original SQL data is never treated as human IAM, Ads or billing authority');
$check(str_contains($html,'Prévia offline somente para validação'),
 'rendered document explains contractual/demo limitations');
echo "F178 real Performance F100 PG16 -> Portal F63 HTML: {$n} PASS / 0 FAIL — NO-GO\n";
