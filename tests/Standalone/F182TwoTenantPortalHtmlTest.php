<?php
declare(strict_types=1);

/** F182: two independent fictional SQL accounts rendered offline by Portal F63. */
require dirname(__DIR__,2).'/vendor/autoload.php';

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F182_MULTITENANT_PORTAL_HTML_LAB')!=='1'
 ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PORTAL_F62_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PORTAL_F63_STATIC_HTML_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'){
 throw new RuntimeException('F182 is a disposable CI-only fixture');
}
$root=dirname(__DIR__,2);
$tmp=realpath((string)getenv('RUNNER_TEMP'));
if($tmp===false)throw new RuntimeException('F182 missing runner directory');
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$pdoOpts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$pdoOpts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$pdoOpts);
$reader=new \App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader();
$aData=$reader->read($a);
$bData=$reader->read($b);
$n=0;
$check=static function(bool $v,string $label)use(&$n):void{
 ++$n;if(!$v)throw new RuntimeException('F182 FAILED: '.$label);
};
$check($aData['status']==='credential_scoped_demo_workbench_only'
 &&$bData['status']==='credential_scoped_demo_workbench_only',
 'both SQL tenant logins return bounded F100 data');
$check(count($aData['accounts'])===2&&count($bData['accounts'])===1,
 'independent PostgreSQL account candidate cardinality');
$check(count($aData['campaigns'])===1&&count($bData['campaigns'])===1
 &&count($aData['reviews'])===1&&count($bData['reviews'])===1,
 'independent tenant campaign/review cardinality');
$check($aData['campaigns'][0]['campaign_ref']!==$bData['campaigns'][0]['campaign_ref']
 &&$aData['reviews'][0]['request_ref']!==$bData['reviews'][0]['request_ref'],
 'different SQL principals return different campaign/review refs');

$source=$tmp.'/f182-b-demo.json';
$target=$tmp.'/f182-b-offline.html';
$payload=json_encode($bData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
$handle=fopen($source,'x');
if(!is_resource($handle))throw new RuntimeException('F182 cannot create exclusive fixture');
try{
 if(fwrite($handle,$payload)!==strlen($payload)||!fflush($handle)||!fsync($handle)){
  throw new RuntimeException('F182 output not persisted');
 }
}finally{fclose($handle);}
$renderer=$root.'/portal-f176-candidate/apps/portal/tools/f63-render-credential-workbench.php';
$proc=proc_open([PHP_BINARY,$renderer,$source],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,$root);
if(!is_resource($proc))throw new RuntimeException('F182 original Portal F63 unavailable');
fclose($pipes[0]);
$html=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);
$error=(string)stream_get_contents($pipes[2]);fclose($pipes[2]);
$check(proc_close($proc)===0 &&strlen($html)>2000,
 'original Portal F63 renders tenant B independent fixture');
file_put_contents($target,$html,LOCK_EX);
$aHtml=(string)file_get_contents($tmp.'/f181-bound-preview.html');
$check(strlen($aHtml)>2000,'signed A preview from F181 exists separately');
foreach(['accounts'=>'account_ref','campaigns'=>'campaign_ref','reviews'=>'request_ref'] as $kind=>$field){
 foreach($bData[$kind] as $row){
  $check(str_contains($html,$row[$field])
    &&($kind==='accounts'||!str_contains($aHtml,$row[$field])),
    'B SQL '.$kind.' reference rendered and campaign/review refs isolated');
 }
 foreach($aData[$kind] as $row){
  $check(str_contains($aHtml,$row[$field])
    &&($kind==='accounts'||!str_contains($html,$row[$field])),
    'A SQL '.$kind.' reference rendered and campaign/review refs isolated');
 }
}
$dom=new DOMDocument('1.0','UTF-8');
libxml_use_internal_errors(true);
$loaded=$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,
 LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
libxml_clear_errors();
$check($loaded===true,'B HTML parses locally without external resources');
$xpath=new DOMXPath($dom);
$check($xpath->query('//section[@aria-label="Contas publicitárias fictícias"]//tbody/tr')->length===count($bData['accounts']),
 'B HTML contains exactly its own SQL account row count, regardless of shared textual refs');
$check($xpath->query('//section[@aria-label="Campanhas fictícias"]//tbody/tr')->length===count($bData['campaigns'])
 &&$xpath->query('//section[@aria-label="Histórico de revisões fictícias"]//tbody/tr')->length===count($bData['reviews']),
 'B HTML campaign and review rows exactly match authenticated B SQL projections');
$check(str_contains($html,'DEMONSTRAÇÃO NÃO HOMOLOGADA')
 &&str_contains($html,"connect-src 'none'")
 &&str_contains($html,"script-src 'none'"),
 'B panel carries same offline security and demo warnings');
$check(!preg_match('/<(?:form|script|a|button|iframe)\b/i',$html),
 'B panel has no active controls or network links');
$check($aData['portal_identity_authenticated']===false
 &&$bData['portal_identity_authenticated']===false
 &&$aData['dispatch_enabled']===false
 &&$bData['dispatch_enabled']===false
 &&$aData['billing_enabled']===false
 &&$bData['billing_enabled']===false,
 'both preview paths remain demos, not live customer IAM or actions');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'both SQL read-only tenant transactions finish cleanly');

echo "F182 two fictional PG16 tenants -> real Portal F63 HTML: {$n} PASS / 0 FAIL — NO-GO\n";
