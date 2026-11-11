<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetRollup.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioHtmlPreview.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflinePortfolioBudgetRollup as Rollup;
use App\Performance\Planning\OfflinePortfolioHtmlPreview as Html;

$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F205 FAIL: '.$why);
};
$scenario=static fn(string $name,int $total,int $google):array=>[
 'schema_version'=>Preview::SCHEMA,'scenario_ref'=>$name,
 'currency'=>'BRL','start_date'=>'2026-11-12','end_date'=>'2026-11-16',
 'total_centavos'=>$total,
 'channel_split_bp'=>['google_ads'=>$google,'meta_ads'=>10000-$google]
];
$input=['schema_version'=>Html::SCHEMA,'portfolio_request'=>[
 'schema_version'=>Rollup::SCHEMA,'scenarios'=>[
  $scenario('scenario-demo-beta',99,3000),
  $scenario('scenario-demo-alpha',101,5000),
 ]]];
$preview=Html::render($input);
$check(str_starts_with($preview['html'],'<!DOCTYPE html>')
 &&str_contains($preview['html'],'lang="pt-BR"')
 &&str_contains($preview['html'],'DEMONSTRAÇÃO NÃO HOMOLOGADA'),
 'static page starts as Portuguese non-homologated preview');
$check(str_contains($preview['html'],'R$ 2,00')
 &&str_contains($preview['html'],'R$ 1,01')
 &&str_contains($preview['html'],'R$ 0,99'),
 'integer centavos display exact BRL without float drift');
$check(substr_count($preview['html'],'<tr>')===9
 &&substr_count($preview['html'],'<table>')===2
 &&strpos($preview['html'],'scenario-demo-alpha')
   <strpos($preview['html'],'scenario-demo-beta'),
 'scenario and daily rows are deterministic and ordered');
$check($preview['html_sha256']===hash('sha256',$preview['html'])
 &&$preview['content_type']==='text/html; charset=utf-8',
 'HTML is byte-checksummed and explicitly typed');
$check(str_contains($preview['html'],'Content-Security-Policy')
 &&!preg_match('/<(?:script|style|iframe|form|button|a|img|link|input)\b/i',$preview['html'])
 &&!preg_match('~https?://~i',$preview['html']),
 'no executable actions, external origins or interactive controls');
$check($preview['simulation_only']===true
 &&$preview['contains_real_customer_data']===false
 &&$preview['provider_connected']===false
 &&$preview['media_execution_allowed']===false
 &&$preview['billing_allowed']===false
 &&$preview['ready_for_production']===false,
 'offline HTML never conveys customer or Ads/billing authority');
$again=Html::render(array_replace($input,['portfolio_request'=>[
 'schema_version'=>Rollup::SCHEMA,
 'scenarios'=>array_reverse($input['portfolio_request']['scenarios'])
]]));
$check($again===$preview,'scenario ordering does not alter rendered bytes');
foreach([
 array_replace($input,['schema_version'=>'unknown']),
 array_replace($input,['approved'=>true]),
 array_replace($input,['portfolio_request'=>[]]),
 array_replace($input,['portfolio_request'=>[
  'schema_version'=>Rollup::SCHEMA,
  'scenarios'=>[$scenario('=HYPERLINK(1)',1,5000)]
 ]]),
] as $bad) {
 try{Html::render($bad);$check(false,'malicious input must fail');}
 catch(DomainException){$check(true,'untrusted preview input rejected');}
}
echo "F205 offline portfolio HTML: {$n} PASS / 0 FAIL — NO HTTP/ADS\n";
