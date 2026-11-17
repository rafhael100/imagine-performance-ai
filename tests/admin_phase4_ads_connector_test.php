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

// A valid prefix is not a report date, and textual ordering cannot validate a calendar.
$denyDate=function($date,string $from='2026-11-01',string $until='2026-11-30')use($auth,$row,$ok){
 $invalid=$row;$invalid['date']=$date;
 $result=null;$failure=null;
 try{$result=(new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$invalid]]))->read('company_alfa','google_ads','1234567890',$from,$until,'UTC');}
 catch(RuntimeException $e){$failure=$e;}
 $ok($result===null&&$failure?->getMessage()==='Provider report date denied');
};
foreach(['2026-11-15garbage',"2026-11-15\n",'2026-11-1',20261115,[],null,'2026-10-31','2026-12-01'] as $date)$denyDate($date);
$denyDate('2026-02-30','2026-02-01','2026-03-01');
$denyDate('2026-02-29','2026-02-01','2026-03-01');

foreach([['2026-11-30','2026-11-01','2026-11-30'],['2028-02-29','2028-02-01','2028-02-29']] as [$date,$from,$until]){
 $valid=$row;$valid['date']=$date;
 $validResult=(new C($auth,fn()=>['source_kind'=>'laboratory_fixture','rows'=>[$valid]]))->read('company_alfa','google_ads','1234567890',$from,$until,'UTC');
 $ok($validResult['rows'][0]['date']===$date&&$validResult['complete']===true);
}

// Reject the whole read if a later page is malformed; no earlier rows are returned.
$pages=0;$result=null;$failure=null;
$invalid=$row;$invalid['date']='2026-11-15garbage';
$paged=new C($auth,function($plan,$cursor)use(&$pages,$row,$invalid){
 $pages++;
 return $cursor===null
  ?['source_kind'=>'laboratory_fixture','rows'=>[$row],'next_cursor'=>'page2']
  :['source_kind'=>'laboratory_fixture','rows'=>[$invalid]];
});
try{$result=$paged->read('company_alfa','google_ads','1234567890','2026-11-01','2026-11-30','UTC');}
catch(RuntimeException $e){$failure=$e;}
$ok($pages===2&&$result===null&&$failure?->getMessage()==='Provider report date denied');
echo "OK: $n exact decimal/provider provenance/consent/pagination/report date assertions; no provider API invoked\n";
