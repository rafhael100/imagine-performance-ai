<?php
require __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';require __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';
use Imagine\Performance\Administration\AdministrativeAdsReadConnector as C;
$n=0;$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('Connector assertion '.($n+1));$n++;};
$deny=function($f)use($ok){try{$f();}catch(Throwable){$ok(true);return;}$ok(false);};
$row=['account_id'=>'1234567890','campaign_id'=>'42','currency'=>'BRL','date'=>'2026-11-01','spend'=>'9007199254740993','clicks'=>'0'];
$lease=str_repeat('a',64);$auth=fn()=>$lease;
$c=new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$row]]);$r=$c->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','America/Fortaleza');
$ok($r['rows'][0]['spend']==='9007199254.740993');$ok($r['rows'][0]['impressions']===null&&$r['rows'][0]['clicks']==='0');$ok($r['source_kind']==='laboratory_fixture'&&$r['official_evidence']===null&&$r['billable']===false);
$deny(fn()=>(new C(fn()=>null,fn()=>[]))->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','UTC'));
$deny(fn()=>(new C($auth,fn()=>['source_kind'=>'official','rows'=>[$row]]))->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','UTC'));
$deny(fn()=>(new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$row+[]],'next_cursor'=>'https://attacker.invalid/']))->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','UTC'));
$meta=$row;$meta['account_id']='123456';$meta['spend']='123.450001';$m=(new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$meta]]))->read('company_alfa','meta_ads','123456','2026-11-01','2026-11-30','UTC');$ok($m['rows'][0]['spend']==='123.450001');
$meta['spend']=0.1;$deny(fn()=>(new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$meta]]))->read('company_alfa','meta_ads','123456','2026-11-01','2026-11-30','UTC'));
$calls=0;$deny(fn()=>(new C(function()use(&$calls){return ++$calls>1?null:str_repeat('a',64);},fn()=>[]))->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','UTC'));
echo "OK: $n exact decimal/provider provenance/consent/pagination assertions; no provider API invoked\n";
