<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F145IndependentDispatchBoundary.php';
require dirname(__DIR__,2).'/src/Lab/F146TerminalGrantIssuer.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F145IndependentDispatchBoundary;
use Imagine\Performance\Lab\F146TerminalGrantIssuer;

if (getenv('APP_ENV')!=='testing'
    || getenv('PERF_F141_LAB')!=='1'
    || getenv('PERF_F145_LAB')!=='1'
    || getenv('PERF_F146_LAB')!=='1'
    || getenv('PGDATABASE')!=='perf_f132_ci'
    || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
    || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
    || getenv('ORACLE_DB')!=='perf_f143_oracle_ci') {
    throw new RuntimeException('F146 may run only in synthetic CI');
}
$root=(string)getenv('RUNNER_TEMP');
if ($root==='' || !is_dir($root)) throw new RuntimeException('F146 missing ephemeral runner');
$dir=$root.'/f146-terminal-test';
if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('F146 test directory creation failed');

$keys=static function():array {
    $kp=sodium_crypto_sign_keypair();
    return [
        'secret'=>sodium_crypto_sign_secretkey($kp),
        'public'=>sodium_crypto_sign_publickey($kp),
    ];
};
$gateKeys=$keys();
$admissionKeys=$keys();
$grantKeys=$keys();
$receiptKeys=$keys();
$terminalLog=$dir.'/terminal-signed-log.jsonl';
$terminalCheckpoint=$dir.'/terminal-checkpoint.json';
$terminalAuthority=new F141SignedJournalAuthority(
    $terminalLog,$gateKeys['secret'],$gateKeys['public']
);
$terminal=new F145IndependentDispatchBoundary(
    $terminalAuthority,$terminalCheckpoint,$admissionKeys['public']
);
$terminal->bootstrap();
$issuer=new F146TerminalGrantIssuer(
    $terminalAuthority,$terminalCheckpoint,$grantKeys['secret']
);
$fakeSql=['status'=>'registered_lab','registered'=>true,'action_authorized'=>false];
$deniedSql=['status'=>'denied','registered'=>false,'action_authorized'=>false];
$fingerprint=static fn(string $label):string=>hash('sha256','F146:'.$label);
$terminalTicket=static function(string $op,string $tenant,string $fp,int $epoch=8)
 use($admissionKeys):array {
    $time=time();
    $bytes=implode('|',['F145-v1',$op,$tenant,$fp,(string)$epoch,(string)$time]);
    return ['issued_at'=>$time,
        'signature'=>base64_encode(
            sodium_crypto_sign_detached($bytes,$admissionKeys['secret'])
        ),
    ];
};
$startGrant=static function(string $op,string $tenant,string $fp) use(
    $terminal,$terminalTicket,$fakeSql,$issuer
):array {
    $decision=$terminal->decide(
        $op,$tenant,$fp,8,$fakeSql,$terminalTicket($op,$tenant,$fp)
    );
    if ($decision['status']!=='mock_dispatch_recorded'
        || $decision['lab_delivered']!==true
        || $decision['action_authorized']!==false) {
        throw new RuntimeException('F146 test was not granted terminal approval');
    }
    return $issuer->issue($op,$tenant,$fp,8,$decision);
};

$childEnv=[
    'APP_ENV'=>'testing',
    'PERF_F141_LAB'=>'1',
    'PERF_F146_LAB'=>'1',
    'PERF_F146_EXECUTOR_ONLY'=>'1',
    'PGDATABASE'=>'perf_f132_ci',
    'WITNESS_DB'=>'perf_f132_witness_ci',
    'ANCHOR_DB'=>'perf_f138_anchor_ci',
    'ORACLE_DB'=>'perf_f143_oracle_ci',
    'RUNNER_TEMP'=>$root,
    'F146_RECEIPT_SECRET'=>base64_encode($receiptKeys['secret']),
    'F146_RECEIPT_PUBLIC'=>base64_encode($receiptKeys['public']),
    'F146_GRANT_ISSUER_PUBLIC'=>base64_encode($grantKeys['public']),
    'PATH'=>(string)(getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
];
$invoke=static function(array $grant,array $env=[])use($childEnv):array {
    $proc=proc_open(
        [PHP_BINARY,dirname(__DIR__,2).'/bin/lab/f146_synthetic_executor.php'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
        $pipes,
        dirname(__DIR__,2),
        array_merge($childEnv,$env)
    );
    if(!is_resource($proc))throw new RuntimeException('F146 cannot launch restricted PHP child');
    fwrite($pipes[0],json_encode($grant,JSON_THROW_ON_ERROR)."\n");
    fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
    $code=proc_close($proc);
    if($code!==0 || !is_string($stdout)) {
        throw new RuntimeException('F146 child failed without a valid response: '.substr((string)$stderr,0,200));
    }
    $value=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($value))throw new RuntimeException('F146 child output invalid');
    return $value;
};
$n=0;
$check=static function(bool $value,string $description)use(&$n):void {
    ++$n;
    if (!$value)throw new RuntimeException('F146 FAILED: '.$description);
};
$throws=static function(callable $action):bool {
    try {$action();return false;} catch(RuntimeException $e){return true;}
};

$check($throws(static fn()=>$issuer->issue(
    'operation_1460','tenant_demo_A',$fingerprint('absent'),8,$fakeSql
)), 'cannot mint executor grant from a forged SQL success');
$check($throws(static fn()=>$issuer->issue(
    'operation_1460','tenant_demo_A',$fingerprint('absent'),8,$deniedSql
)), 'cannot mint executor grant from SQL denial');
$check($invoke([])['status']==='invalid_grant_envelope',
    'direct child request without signed envelope denied');
$check(!array_key_exists('F146_GRANT_ISSUER_SECRET',$childEnv),
    'isolated child receives only issuer public key, not private signing key');

$opA='operation_1461';$tenant='tenant_demo_A';$fpA=$fingerprint('A');
$grantA=$startGrant($opA,$tenant,$fpA);
$check($grantA['payload']['target']==='synthetic-noop',
    'issuer can only sign fixed synthetic-noop target');
$check($throws(static fn()=>$issuer->issue(
    $opA,'tenant_demo_B',$fpA,8,
    ['status'=>'mock_dispatch_recorded','lab_delivered'=>true,'action_authorized'=>false]
)), 'journal cannot certify a different tenant for same operation');
$check($throws(static fn()=>$issuer->issue(
    $opA,$tenant,$fingerprint('unbound'),8,
    ['status'=>'mock_dispatch_recorded','lab_delivered'=>true,'action_authorized'=>false]
)), 'journal cannot certify unrelated fingerprint with existing operation');

$first=$invoke($grantA);
$check($first['status']==='synthetic_noop_recorded' && $first['simulated']===true,
    'A creates exactly one synthetic receipt in separate executor process');
$check($first['action_authorized']===false,
    'executor NEVER grants actual Google or Meta campaigns');
$check($invoke($grantA)['status']==='executor_duplicate_denied',
    'relaunching identical signed grant cannot repeat receipt');
$check($invoke($grantA)['action_authorized']===false,
    'replayed grant still never authorizes paid media');

$alteredTenant=$grantA;
$alteredTenant['payload']['tenant']='tenant_demo_B';
$check($invoke($alteredTenant)['status']==='issuer_signature_invalid',
    'changing tenant on signed grant is detected');
$alteredFp=$grantA;
$alteredFp['payload']['fingerprint']=$fingerprint('fake');
$check($invoke($alteredFp)['status']==='issuer_signature_invalid',
    'changing fingerprint on grant is detected');
$wrongTarget=$grantA;
$wrongTarget['payload']['target']='google-ads';
$check($invoke($wrongTarget)['status']==='invalid_grant_claims_or_expired',
    'non-synthetic action type is categorically rejected');
$old=$issuer->issue($opA,$tenant,$fpA,8,
    ['status'=>'mock_dispatch_recorded','lab_delivered'=>true,'action_authorized'=>false],
    time()-61
);
$check($invoke($old)['status']==='invalid_grant_claims_or_expired',
    'valid grant signed in the past is rejected after TTL');
$garbled=$grantA;$garbled['signature']='!!invalid-base64!!';
$check($invoke($garbled)['status']==='issuer_signature_invalid',
    'malformed Ed25519 signature rejected');

$opB='operation_1462';$fpB=$fingerprint('B');
$grantB=$startGrant($opB,'tenant_demo_B',$fpB);
$check($invoke($grantB)['status']==='synthetic_noop_recorded',
    'B has independent synthetic receipt after A');
$check($invoke($grantB)['status']==='executor_duplicate_denied',
    'B cannot re-execute after new executor process restart');

$receiptPath=$root.'/f146-executor/receipt-log.jsonl';
$checkpointPath=$root.'/f146-executor/receipt-checkpoint.json';
$check(is_file($receiptPath)&&is_file($checkpointPath),
    'executor receipt ledger and checkpoint exist outside SQL backups');
$original=(string)file_get_contents($receiptPath);
$check(substr_count($original,"\n")===3,
    'executor ledger has genesis plus A/B, no duplicate events');
$check(strpos($original,'google')===false,
    'synthetic receipt ledger contains no external provider target');

$opC='operation_1463';$fpC=$fingerprint('C');
$grantC=$startGrant($opC,'tenant_demo_A',$fpC);
$oldPrefix=explode("\n",$original)[0]."\n";
file_put_contents($receiptPath,$oldPrefix);
$check($invoke($grantC)['status']==='executor_unknown_fail_closed',
    'signed valid-prefix truncation denied by independent high-watermark');
file_put_contents($receiptPath,$original);
rename($checkpointPath,$checkpointPath.'.absent');
$check($invoke($grantC)['status']==='executor_unknown_fail_closed',
    'missing executor high-watermark denies all new grants');
rename($checkpointPath.'.absent',$checkpointPath);
$check($invoke($grantC)['status']==='synthetic_noop_recorded',
    'unmodified ledger and checkpoint recover only with same authorized grant');

$opD='operation_1464';$fpD=$fingerprint('D');
$grantD=$startGrant($opD,'tenant_demo_A',$fpD);
$journal=new F141SignedJournalAuthority(
    $receiptPath,$receiptKeys['secret'],$receiptKeys['public']
);
$check($journal->reserve($opD,'tenant_demo_A',$fpD,8),
    'injected interruption after append but before checkpoint for D');
$check($invoke($grantD)['status']==='executor_unknown_fail_closed',
    'in-doubt append cannot automatically retry D or any other grant');
$check($invoke($grantA)['status']==='executor_unknown_fail_closed',
    'after integrity mismatch even previously accepted grant fails closed');

$check(!is_file($root.'/f146-executor/ads-transmissions.json'),
    'no actual or simulated network transmission file is produced');
echo "F146 isolated executor: {$n} PASS / 0 FAIL — synthetic-only / NO-GO\n";
