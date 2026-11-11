<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F145IndependentDispatchBoundary.php';
require dirname(__DIR__,2).'/src/Lab/F146TerminalGrantIssuer.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F145IndependentDispatchBoundary;
use Imagine\Performance\Lab\F146TerminalGrantIssuer;

if(getenv('APP_ENV')!=='testing'||getenv('PERF_F148_LAB')!=='1'
 ||getenv('PERF_F146_LAB')!=='1'||getenv('PERF_F147_LAB')!=='1'
 ||getenv('PERF_F145_LAB')!=='1'||getenv('PERF_F141_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f132_ci'
 ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
 ||getenv('ORACLE_DB')!=='perf_f143_oracle_ci')
 throw new RuntimeException('F148 run only in synthetic Actions CI');
$root=(string)getenv('RUNNER_TEMP');
$hostSandbox=$root.'/f148-sandbox';
$hostIssuer=$root.'/f148-issuer';
foreach([$hostSandbox,$hostIssuer] as $folder) {
 if(!is_dir($folder)&&!mkdir($folder,0700,true))
  throw new RuntimeException('F148 CI sandbox directory not available');
}
$keypair=static function():array {
 $pair=sodium_crypto_sign_keypair();
 return ['secret'=>sodium_crypto_sign_secretkey($pair),
         'public'=>sodium_crypto_sign_publickey($pair)];
};
$terminalKey=$keypair();
$admissionKey=$keypair();
$grantKey=$keypair();
$receiptKey=$keypair();
$workloadKey=$keypair();

$terminalJournal=new F141SignedJournalAuthority(
 $hostIssuer.'/terminal.jsonl',$terminalKey['secret'],$terminalKey['public']
);
$terminal=new F145IndependentDispatchBoundary(
 $terminalJournal,$hostIssuer.'/terminal-checkpoint.json',$admissionKey['public']
);
$terminal->bootstrap();
$issuer=new F146TerminalGrantIssuer(
 $terminalJournal,$hostIssuer.'/terminal-checkpoint.json',$grantKey['secret']
);
$fakeSql=['status'=>'registered_lab','registered'=>true,'action_authorized'=>false];
$fingerprint=static fn(string $label):string=>hash('sha256','F148:'.$label);
$grant=static function(string $operation,string $tenant,string $fingerprint)
 use($terminal,$admissionKey,$issuer,$fakeSql):array {
 $stamp=time();
 $message=implode('|',[
  'F145-v1',$operation,$tenant,$fingerprint,'8',(string)$stamp,
 ]);
 $signed=['issued_at'=>$stamp,
  'signature'=>base64_encode(sodium_crypto_sign_detached($message,$admissionKey['secret']))];
 $decision=$terminal->decide(
  $operation,$tenant,$fingerprint,8,$fakeSql,$signed
 );
 if($decision['status']!=='mock_dispatch_recorded')
  throw new RuntimeException('F148 terminal refused synthetic preparation');
 return $issuer->issue($operation,$tenant,$fingerprint,8,$decision);
};
$workload=static function(array $grant,?int $issued=null,array $replace=[])
 use($workloadKey):array {
 $p=$grant['payload'];
 $claims=[
  'subject'=>'imagine-f148-lab-workload',
  'audience'=>'synthetic-noop-executor',
  'scope'=>'simulate:receipt',
  'operation'=>$p['operation'],
  'tenant'=>$p['tenant'],
  'fingerprint'=>$p['fingerprint'],
  'issued_at'=>$issued??time(),
 ];
 $claims=array_replace($claims,$replace);
 $bytes=json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $claims['signature']=base64_encode(
  sodium_crypto_sign_detached($bytes,$workloadKey['secret'])
 );
 return $claims;
};
$envFile=$hostIssuer.'/sandbox.env';
$environment=[
 'APP_ENV'=>'testing',
 'PERF_F141_LAB'=>'1','PERF_F146_LAB'=>'1',
 'PERF_F147_LAB'=>'1','PERF_F148_LAB'=>'1',
 'PERF_F146_EXECUTOR_ONLY'=>'1','PERF_F148_NO_EGRESS'=>'1',
 'PGDATABASE'=>'perf_f132_ci','WITNESS_DB'=>'perf_f132_witness_ci',
 'ANCHOR_DB'=>'perf_f138_anchor_ci','ORACLE_DB'=>'perf_f143_oracle_ci',
 'RUNNER_TEMP'=>'/sandbox',
 'F146_RECEIPT_SECRET'=>base64_encode($receiptKey['secret']),
 'F146_RECEIPT_PUBLIC'=>base64_encode($receiptKey['public']),
 'F146_GRANT_ISSUER_PUBLIC'=>base64_encode($grantKey['public']),
 'F148_WORKLOAD_PUBLIC'=>base64_encode($workloadKey['public']),
];
$writeEnv=static function(array $vars)use($envFile):void {
 foreach($vars as $key=>$value) {
  if(!preg_match('/^[A-Z0-9_]+$/D',$key)
   ||!is_string($value)||str_contains($value,"\n")) {
   throw new RuntimeException('F148 invalid sandbox env');
  }
 }
 $lines=[];
 foreach($vars as $key=>$value) $lines[]=$key.'='.$value;
 file_put_contents($envFile,implode("\n",$lines)."\n");
 chmod($envFile,0600);
};
$writeEnv($environment);
$baseArgs=[
 'docker','run','--rm','--network','none','--read-only',
 '--cap-drop','ALL','--security-opt','no-new-privileges',
 '--pids-limit','64','--user',getmyuid().':'.getmygid(),
 '--mount','type=bind,src='.$hostSandbox.',dst=/sandbox',
 '--env-file',$envFile,
 'perf-f148-no-egress-lab:ci',
];
$run=static function(array $request,array $envOverride=[])use(
 $baseArgs,$writeEnv,$environment
):array {
 $writeEnv(array_merge($environment,$envOverride));
 $proc=proc_open(
  $baseArgs,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2)
 );
 if(!is_resource($proc))throw new RuntimeException('F148 docker invocation failed');
 fwrite($pipes[0],json_encode($request,JSON_THROW_ON_ERROR)."\n");
 fclose($pipes[0]);
 $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
 $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
 $exit=proc_close($proc);
 if($exit!==0)throw new RuntimeException('F148 sandbox container exited: '.substr((string)$stderr,0,400));
 $response=json_decode((string)$stdout,true,512,JSON_THROW_ON_ERROR);
 if(!is_array($response))throw new RuntimeException('F148 unexpected child response');
 return $response;
};
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void {
 ++$n;
 if(!$ok)throw new RuntimeException('F148 FAILED: '.$msg);
};
$a=$grant('operation_1481','tenant_demo_A',$fingerprint('A'));
$wA=$workload($a);
$requestA=['grant'=>$a,'workload'=>$wA,'fault'=>'none'];
$check($run(['grant'=>$a,'workload'=>[],'fault'=>'none'])['status']
 ==='invalid_workload_contract','bare SQL or grant alone cannot call no-op executor');
$check($run($requestA)['status']==='synthetic_noop_recorded',
 'valid independent workload and grant create one no-op receipt');
$check($run($requestA)['status']==='executor_duplicate_denied',
 'restarting isolated Docker container preserves duplicate refusal');
$check($run($requestA)['action_authorized']===false,
 'no Ads authorization even after a recorded receipt');

$fake=$requestA;
$fake['workload']['signature']=base64_encode(random_bytes(64));
$check($run($fake)['status']==='workload_signature_denied',
 'workload cannot forge Ed25519 identity');
$otherTenant=$requestA;
$otherTenant['workload']=$workload($a,null,['tenant'=>'tenant_demo_B']);
$check($run($otherTenant)['status']==='workload_grant_binding_denied',
 'workload tenant mismatch with signed grant denied');
$otherOperation=$requestA;
$otherOperation['workload']=$workload($a,null,['operation'=>'operation_9999']);
$check($run($otherOperation)['status']==='workload_grant_binding_denied',
 'workload operation mismatch denied');
$wrongScope=$requestA;
$wrongScope['workload']=$workload($a,null,['scope'=>'ads:write']);
$check($run($wrongScope)['status']==='workload_scope_or_ttl_denied',
 'paid-media scope refused even if signed by test identity');
$wrongAudience=$requestA;
$wrongAudience['workload']=$workload($a,null,['audience'=>'google-ads']);
$check($run($wrongAudience)['status']==='workload_scope_or_ttl_denied',
 'real ad provider audience categorically refused');
$expired=$requestA;
$expired['workload']=$workload($a,time()-61);
$check($run($expired)['status']==='workload_scope_or_ttl_denied',
 'valid workload proof expired beyond 30 seconds denied');
$modified=$requestA;$modified['grant']['payload']['target']='meta-ads';
$check($run($modified)['status']==='workload_grant_binding_denied',
 'workload cannot rebind no-op grant to Meta Ads');

$b=$grant('operation_1482','tenant_demo_B',$fingerprint('B'));
$reqB=['grant'=>$b,'workload'=>$workload($b),'fault'=>'none'];
$check($run($reqB,[
  'GOOGLE_ADS_ACCESS_TOKEN'=>'synthetic-canary-should-fail-bootstrap'
 ])['status']==='executor_bootstrap_denied',
 'injected Google Ads token causes fail-closed startup');
$check($run($reqB,[
  'DATABASE_URL'=>'postgresql://synthetic-denied'
 ])['status']==='executor_bootstrap_denied',
 'injected database credential causes fail-closed startup');
$check($run($reqB,[
  'F148_WORKLOAD_SECRET'=>'synthetic-private-key-injection'
 ])['status']==='executor_bootstrap_denied',
 'workload private key injected to sandbox is denied');
$check($run($reqB)['status']==='synthetic_noop_recorded',
 'valid B works only in credential-free container with private network disabled');

$c=$grant('operation_1483','tenant_demo_A',$fingerprint('C'));
$reqC=['grant'=>$c,'workload'=>$workload($c),'fault'=>'ack_lost'];
$check($run($reqC)['status']==='in_doubt_quarantined',
 'lost no-op ACK after receipt enters sticky quarantine');
$check($run($reqC)['status']==='executor_quarantined_no_auto_retry',
 'retry of in-doubt C blocked after new Docker process starts');
$d=$grant('operation_1484','tenant_demo_A',$fingerprint('D'));
$reqD=['grant'=>$d,'workload'=>$workload($d),'fault'=>'none'];
$check($run($reqD)['status']==='executor_quarantined_no_auto_retry',
 'unrelated D also denied during quarantine');
$journalFile=$hostSandbox.'/f148-executor/receipt-log.jsonl';
$incidentFile=$hostSandbox.'/f148-executor/sticky-quarantine.json';
$check(is_file($incidentFile)&&is_file($journalFile),
 'sticky incident and receipts survived container recreation');
$check(substr_count((string)file_get_contents($journalFile),"\n")===4,
 'only A/B/C records, no repeats or D');
$check($run($reqD)['action_authorized']===false,
 'quarantine and all positive flows never grant real media authorization');

echo "F148 no-egress workload container: {$n} PASS / 0 FAIL — CI ONLY / NO-GO\n";
