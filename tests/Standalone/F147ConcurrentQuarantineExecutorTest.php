<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F145IndependentDispatchBoundary.php';
require dirname(__DIR__,2).'/src/Lab/F146TerminalGrantIssuer.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F145IndependentDispatchBoundary;
use Imagine\Performance\Lab\F146TerminalGrantIssuer;

if(getenv('APP_ENV')!=='testing'||getenv('PERF_F141_LAB')!=='1'
 ||getenv('PERF_F145_LAB')!=='1'||getenv('PERF_F146_LAB')!=='1'
 ||getenv('PERF_F147_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f132_ci'
 ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
 ||getenv('ORACLE_DB')!=='perf_f143_oracle_ci')
 throw new RuntimeException('F147 disposable test environment required');
$root=(string)getenv('RUNNER_TEMP');
$folder=$root.'/f147-terminal';
if(!is_dir($folder)&&!mkdir($folder,0700,true))
 throw new RuntimeException('F147 fixture unavailable');
$keypair=static function():array{
 $kp=sodium_crypto_sign_keypair();
 return ['sk'=>sodium_crypto_sign_secretkey($kp),
   'pk'=>sodium_crypto_sign_publickey($kp)];
};
$terminalKey=$keypair();
$admissionKey=$keypair();
$grantKey=$keypair();
$executorKey=$keypair();
$terminalJournal=new F141SignedJournalAuthority(
 $folder.'/terminal.jsonl',$terminalKey['sk'],$terminalKey['pk']);
$terminal=new F145IndependentDispatchBoundary(
 $terminalJournal,$folder.'/terminal-highwater.json',$admissionKey['pk']);
$terminal->bootstrap();
$issuer=new F146TerminalGrantIssuer(
 $terminalJournal,$folder.'/terminal-highwater.json',$grantKey['sk']);
$fakeSql=['status'=>'registered_lab','registered'=>true,'action_authorized'=>false];
$fp=static fn(string $x):string=>hash('sha256','F147:'.$x);
$mint=static function(string $operation,string $tenant,string $fingerprint)
 use($terminal,$admissionKey,$issuer,$fakeSql):array{
 $now=time();
 $message=implode('|',['F145-v1',$operation,$tenant,$fingerprint,'8',(string)$now]);
 $ticket=['issued_at'=>$now,'signature'=>base64_encode(
   sodium_crypto_sign_detached($message,$admissionKey['sk']))];
 $decision=$terminal->decide($operation,$tenant,$fingerprint,8,$fakeSql,$ticket);
 if($decision['status']!=='mock_dispatch_recorded')
  throw new RuntimeException('F147 synthetic terminal decision denied');
 return $issuer->issue($operation,$tenant,$fingerprint,8,$decision);
};
$environment=[
 'APP_ENV'=>'testing','PERF_F141_LAB'=>'1',
 'PERF_F146_LAB'=>'1','PERF_F147_LAB'=>'1',
 'PERF_F146_EXECUTOR_ONLY'=>'1',
 'PGDATABASE'=>'perf_f132_ci','WITNESS_DB'=>'perf_f132_witness_ci',
 'ANCHOR_DB'=>'perf_f138_anchor_ci','ORACLE_DB'=>'perf_f143_oracle_ci',
 'RUNNER_TEMP'=>$root,
 'F146_RECEIPT_SECRET'=>base64_encode($executorKey['sk']),
 'F146_RECEIPT_PUBLIC'=>base64_encode($executorKey['pk']),
 'F146_GRANT_ISSUER_PUBLIC'=>base64_encode($grantKey['pk']),
 'PATH'=>(string)(getenv('PATH')?:'/usr/bin:/bin'),
];
$launch=static function(array $grant,string $fault='none',array $extra=[])use($environment):array{
 $proc=proc_open(
  [PHP_BINARY,dirname(__DIR__,2).'/bin/lab/f147_quarantined_synthetic_executor.php'],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2),array_merge($environment,$extra));
 if(!is_resource($proc))throw new RuntimeException('F147 child launch failed');
 fwrite($pipes[0],json_encode(['grant'=>$grant,'fault'=>$fault],JSON_THROW_ON_ERROR)."\n");
 fclose($pipes[0]);
 return ['proc'=>$proc,'pipes'=>$pipes];
};
$finish=static function(array $start):array{
 $stdout=stream_get_contents($start['pipes'][1]);fclose($start['pipes'][1]);
 $stderr=stream_get_contents($start['pipes'][2]);fclose($start['pipes'][2]);
 if(proc_close($start['proc'])!==0)
  throw new RuntimeException('F147 child returned nonzero: '.substr((string)$stderr,0,200));
 $result=json_decode((string)$stdout,true,512,JSON_THROW_ON_ERROR);
 if(!is_array($result))throw new RuntimeException('F147 invalid child result');
 return $result;
};
$run=static fn(array $grant,string $fault='none',array $extra=[]):array
 =>$finish($launch($grant,$fault,$extra));
$n=0;
$assert=static function(bool $ok,string $message)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F147 FAILED: '.$message);
};
$grantA=$mint('operation_1471','tenant_demo_A',$fp('A'));
$parallel1=$launch($grantA);
$parallel2=$launch($grantA);
$res1=$finish($parallel1);$res2=$finish($parallel2);
$statuses=[$res1['status'],$res2['status']];sort($statuses);
$assert($statuses===['executor_duplicate_denied','synthetic_noop_recorded'],
 'parallel processes admit only one synthetic A receipt: '.implode(',', $statuses));
$assert(!$res1['action_authorized']&&!$res2['action_authorized'],
 'neither concurrent call authorizes paid media');
$assert($run($grantA)['status']==='executor_duplicate_denied',
 'restarted process continues denying repeated A');
$grantB=$mint('operation_1472','tenant_demo_B',$fp('B'));
$outage=$run($grantB,'target_down');
$assert($outage['status']==='synthetic_target_unavailable'&&!$outage['simulated'],
 'known synthetic outage before receipt produces no execution');
$receipt=$root.'/f147-executor/receipt-log.jsonl';
$assert(substr_count((string)file_get_contents($receipt),"\n")===2,
 'outage did not append second receipt');
$assert($run($grantB)['status']==='synthetic_noop_recorded',
 'retry B safe only because outage preceded its receipt');
$assert($run($grantB)['status']==='executor_duplicate_denied',
 'subsequent B retry denied');
$grantC=$mint('operation_1473','tenant_demo_A',$fp('C'));
$unknown=$run($grantC,'ack_lost');
$assert($unknown['status']==='in_doubt_quarantined'&&!$unknown['simulated'],
 'lost ACK after synthetic receipt creates ambiguous quarantine');
$marker=$root.'/f147-executor/sticky-quarantine.json';
$assert(is_file($marker),'quarantine incident persists across child exits');
$incident=json_decode((string)file_get_contents($marker),true,512,JSON_THROW_ON_ERROR);
$assert(($incident['reason']??null)==='synthetic_ack_lost_after_receipt',
 'incident explains why no retry is allowed');
$assert(substr_count((string)file_get_contents($receipt),"\n")===4,
 'A/B/C each recorded once; ACK loss does not cause second receipt');
$assert($run($grantC)['status']==='executor_quarantined_no_auto_retry',
 'in-doubt C cannot retry');
$grantD=$mint('operation_1474','tenant_demo_A',$fp('D'));
$assert($run($grantD)['status']==='executor_quarantined_no_auto_retry',
 'new D also blocked until independent incident resolution');
$assert($run($grantD,'target_down')['status']==='executor_quarantined_no_auto_retry',
 'sticky quarantine outranks transient outage');
$assert(substr_count((string)file_get_contents($receipt),"\n")===4,
 'quarantine prevents new D receipt');
$assert($run($grantD,'none',['PERF_F147_LAB'=>'0'])['status']
 ==='executor_bootstrap_denied','guard rejects calls outside CI flag');
$assert(($incident['release']??null)==='manual_independent_review_only',
 'quarantine has no automatic release path');
$assert(!array_key_exists('GOOGLE_ADS_ACCESS_TOKEN',$environment)
 && !array_key_exists('META_ADS_ACCESS_TOKEN',$environment)
 && !array_key_exists('F146_GRANT_ISSUER_SECRET',$environment),
 'child environment excludes Ads and signer private credentials');

// Expected-negative: a host administrator sharing the same writable runner
// can delete this marker. This must NEVER be described as real isolation.
$assert(unlink($marker),'privileged-host negative test deletes local marker');
$after=$run($grantD);
$assert($after['status']==='synthetic_noop_recorded',
 'EXPECTED GAP: removing local quarantine file allows D mock receipt');
$assert(!$after['action_authorized'],'even host bypass has no Ads capability');

echo "F147 concurrent/quarantine executor: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
