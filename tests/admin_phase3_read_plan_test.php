<?php
declare(strict_types=1);
require __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
use Imagine\Performance\Administration\AdministrativeAdsReadPlan as P;
$n=0;$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('Read plan assertion '.($n+1));$n++;};
$deny=function($f)use($ok){try{$f();}catch(InvalidArgumentException){$ok(true);return;}$ok(false);};
$g=P::prepare('google_ads','1234567890','2026-11-01','2026-11-30');
$m=P::prepare('meta_ads','1234567890123','2026-11-01','2026-11-30');
foreach([$g,$m] as $p){$ok($p['metrics']===null&&$p['official_evidence']===null);$ok($p['executable']===false&&$p['external_operations_enabled']===false&&$p['billable']===false);$ok(!str_contains(json_encode($p),'access_token'));}
$ok($g['read_plan']['method']==='POST'&&str_ends_with($g['read_plan']['url'],'/googleAds:search'));
$ok(!isset($g['read_plan']['json']['pageSize'])&&str_contains($g['read_plan']['json']['query'],'metrics.cost_micros'));
$ok($m['read_plan']['method']==='GET'&&$m['read_plan']['query']['limit']===100);
$deny(fn()=>P::prepare('chatgpt_ads','1234567890','2026-11-01','2026-11-30'));
$deny(fn()=>P::prepare('google_ads',"1234567890/../mutate",'2026-11-01','2026-11-30'));
$deny(fn()=>P::prepare('google_ads','1234567890','2026-02-30','2026-03-01'));
$deny(fn()=>P::prepare('meta_ads','123456','2026-01-01','2026-11-30'));
$deny(fn()=>P::prepare('meta_ads','123456','2026-11-30','2026-11-01'));
echo "OK: $n bounded read-only request-plan assertions; no provider request or metrics fabricated\n";
