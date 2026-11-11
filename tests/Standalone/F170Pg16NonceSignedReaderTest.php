<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F170NonceBoundPortalReadGateway.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F169SignedPortalReadGateway;
use Imagine\Performance\Domain\F170NonceBoundPortalReadGateway;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F170_NONCE_LAB')!=='1'
 ||getenv('PERF_F169_SIGNED_READ_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('sodium')||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F170 test restricted to disposable GitHub PG16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$pdoOptions=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $user,string $pass):PDO=>new PDO($dsn,$user,$pass,$pdoOptions);
$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$nonceDb=$open('perf_f170_gateway','ci-only-f170-gateway');
$signer=sodium_crypto_sign_keypair();
$secret=sodium_crypto_sign_secretkey($signer);
$public=sodium_crypto_sign_publickey($signer);
$baseA=new F169SignedPortalReadGateway(new F166Pg16ReadOnlyDashboard($a),$public);
$baseB=new F169SignedPortalReadGateway(new F166Pg16ReadOnlyDashboard($b),$public);
$gatewayA=new F170NonceBoundPortalReadGateway($baseA,$nonceDb);
$gatewayB=new F170NonceBoundPortalReadGateway($baseB,
 $open('perf_f170_gateway','ci-only-f170-gateway'));
$n=0;
$check=static function(bool $ok,string $reason)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F170 FAILED: '.$reason);
};
$blocked=static function(callable $call):bool{
 try{$call();return false;}catch(PDOException $e){return true;}
};
$throws=static function(callable $call):bool{
 try{$call();return false;}catch(RuntimeException $e){return true;}
};
$keys=['subject','audience','scope','tenant','issued_at','expires_at','request_id'];
$claims=[
 'subject'=>'imagine-portal-synthetic-s2s',
 'audience'=>'imagine-performance-readonly-v1',
 'scope'=>'performance:demo:read',
 'tenant'=>'perf-demo-aurora',
 'issued_at'=>time(),
 'expires_at'=>time()+55,
 'request_id'=>'request_f170_demo_primary',
];
$sign=static function(array $claims,string $secret)use($keys):string{
 $ordered=[];
 foreach($keys as $key)$ordered[$key]=$claims[$key];
 $signature=sodium_crypto_sign_detached(json_encode($ordered,
  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$secret);
 return json_encode(['claims'=>$claims,'signature'=>base64_encode($signature)],
  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
};
$dsnVerifier=static fn(string $role,string $pass):PDO=>new PDO($dsn,$role,$pass,$pdoOptions);
$check($throws(static fn()=>new F170NonceBoundPortalReadGateway($baseA,$a)),
 'tenant SQL role cannot masquerade as separate nonce gateway role');
$check($throws(static fn()=>new F170NonceBoundPortalReadGateway($baseA,$admin)),
 'superuser login cannot masquerade as fixture nonce gateway role');
$check($blocked(static fn()=>$a->query('SELECT * FROM perf_f162.consumed_read_nonces')),
 'tenant A cannot inspect nonce database directly');
$check($blocked(static fn()=>$b->exec("INSERT INTO perf_f162.consumed_read_nonces
 (tenant,request_id,expires_at)
 VALUES('perf-demo-boreal','request_f170_direct_sql',now()+interval '1 minute')")),
 'tenant B cannot write nonce table directly');
$check($blocked(static fn()=>$a->query("SELECT perf_f162.f170_consume_signed_read_nonce
 ('perf-demo-aurora','request_f170_direct_sql',extract(epoch FROM now())::bigint+30)")),
 'tenant SQL role cannot execute gateway-only nonce function');
$check($blocked(static fn()=>$nonceDb->query('SELECT * FROM perf_f162.consumed_read_nonces')),
 'gateway role can consume but cannot directly read nonce records');

$valid=$sign($claims,$secret);
$first=$gatewayA->read($valid);
$check($first['status']==='accepted_readonly_demo'
 &&$first['nonce_consumed_demo']===true
 &&is_array($first['report']),
 'first valid signed read atomically consumes nonce and returns A mock report');
$check($first['report']['tenant_ref']==='perf-demo-aurora'
 &&$first['report']['iam_verified']===false
 &&$first['report']['dispatch_enabled']===false,
 'accepted request remains lab-only A scoped report');
$second=$gatewayA->read($valid);
$check($second['status']==='replay_or_nonce_denied'
 &&$second['report']===null
 &&$second['nonce_consumed_demo']===false,
 'same signed envelope cannot be reused in same database');
$check($first['action_authorized']===false
 &&$first['can_enable_ads']===false
 &&$first['can_bill']===false
 &&$first['ready_for_production']===false,
 'one-shot read never authorizes Ads or billing');

$claimsB=$claims;
$claimsB['tenant']='perf-demo-boreal';
$forB=$sign($claimsB,$secret); // same request_id, different tenant.
$check($gatewayA->read($forB)['status']==='tenant_session_or_report_denied',
 'signed B tenant cannot use A PG16 RLS session');
$check($gatewayB->read($forB)['status']==='accepted_readonly_demo',
 'B can consume same request ID in its own isolated tenant namespace');
$check($gatewayB->read($forB)['status']==='replay_or_nonce_denied',
 'B nonce independently becomes one-use');
$check((int)$admin->query("SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE request_id='request_f170_demo_primary'")->fetchColumn()===2,
 'persisted nonce uniqueness is by tenant and request ID');

$badPair=sodium_crypto_sign_keypair();
$badPriv=sodium_crypto_sign_secretkey($badPair);
$fresh=$claims;$fresh['request_id']='request_f170_badsig_test';
$check($gatewayA->read($sign($fresh,$badPriv))['status']==='signature_denied',
 'bad Ed25519 signature cannot consume a nonce');
$check($gatewayA->read($sign(array_replace($fresh,['scope'=>'ads:write']),$secret))['status']
 ==='scope_or_audience_denied',
 'paid media scope cannot consume a nonce');
$check($gatewayA->read($sign($fresh,$secret))['status']==='accepted_readonly_demo',
 'nonce is still available after invalid signature and scope refusal');

$expired=$claims;$expired['request_id']='request_f170_expired_test';
$expired['issued_at']=time()-90;$expired['expires_at']=time()-60;
$check($gatewayA->read($sign($expired,$secret))['status']
 ==='expired_or_out_of_window_denied',
 'expired signed request denied before nonce store');
$check((int)$admin->query("SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE request_id='request_f170_expired_test'")->fetchColumn()===0,
 'expired request cannot burn nonce database record');

$claimsOutage=$claims;$claimsOutage['request_id']='request_f170_revoked_test';
$admin->exec("REVOKE EXECUTE ON FUNCTION
 perf_f162.f170_consume_signed_read_nonce(text,text,bigint)
 FROM perf_f170_gateway");
$check($gatewayA->read($sign($claimsOutage,$secret))['status']
 ==='nonce_store_unavailable_fail_closed',
 'nonce service privilege outage fails closed and withholds report');
$check((int)$admin->query("SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE request_id='request_f170_revoked_test'")->fetchColumn()===0,
 'nonce permission failure does not incorrectly mark identifier consumed');
$admin->exec("GRANT EXECUTE ON FUNCTION
 perf_f162.f170_consume_signed_read_nonce(text,text,bigint)
 TO perf_f170_gateway");
$check($gatewayA->read($sign($claimsOutage,$secret))['status']
 ==='accepted_readonly_demo',
 'after restoring CI nonce grant, original unconsumed request can be read once');

$worker=dirname(__DIR__,2).'/bin/lab/f170-signed-read-worker.php';
$procEnv=[
 'APP_ENV'=>'testing','PERF_F162_PG_LAB'=>'1',
 'PERF_F166_READ_DASHBOARD_LAB'=>'1','PERF_F169_SIGNED_READ_LAB'=>'1',
 'PERF_F170_NONCE_LAB'=>'1','PGHOST'=>'127.0.0.1','PGPORT'=>'5432',
 'PGDATABASE'=>'perf_f162_ci','PERF_F170_TENANT_ROLE'=>'perf_f162_a',
 'PERF_F170_SYNTHETIC_PUBLIC'=>base64_encode($public),
];
$raceClaims=$claims;$raceClaims['request_id']='request_f170_concurrent_x';
$raceEnvelope=$sign($raceClaims,$secret);
$jobs=[];
for($i=0;$i<2;$i++){
 $p=proc_open([PHP_BINARY,$worker],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),$procEnv);
 if(!is_resource($p))throw new RuntimeException('F170 worker unavailable');
 $jobs[]=['process'=>$p,'pipes'=>$pipes];
}
foreach($jobs as $job){
 fwrite($job['pipes'][0],$raceEnvelope);
 fclose($job['pipes'][0]);
}
$statuses=[];$results=[];
foreach($jobs as $job){
 $out=stream_get_contents($job['pipes'][1]);fclose($job['pipes'][1]);
 $err=stream_get_contents($job['pipes'][2]);fclose($job['pipes'][2]);
 $exit=proc_close($job['process']);
 $check($exit===0,
  'F170 real worker process exited safely: '.substr((string)$err,0,100));
 $result=json_decode((string)$out,true,8,JSON_THROW_ON_ERROR);
 $statuses[]=$result['status'];
 $results[]=$result;
}
$check(count(array_filter($statuses,
 static fn(string $s):bool=>$s==='accepted_readonly_demo'))===1,
 'two parallel PHP subprocesses: exactly one consumes same nonce');
$check(count(array_filter($statuses,
 static fn(string $s):bool=>$s==='replay_or_nonce_denied'))===1,
 'two parallel PHP subprocesses: duplicate receives replay denial');
$check(count(array_filter($results,
 static fn(array $r):bool=>$r['action_authorized']!==false
 ||$r['can_bill']!==false))===0,
 'neither concurrent worker can grant advertising or billing action');
$check((int)$admin->query("SELECT count(*) FROM perf_f162.consumed_read_nonces
 WHERE request_id='request_f170_concurrent_x'")->fetchColumn()===1,
 'unique constraint guarantees one persisted nonce for two workers');

// Expected-negative: a database superuser can delete the nonce table row.
// This is why database-local replay protection is NOT trusted antirollback.
$admin->exec("DELETE FROM perf_f162.consumed_read_nonces
 WHERE tenant='perf-demo-aurora' AND request_id='request_f170_demo_primary'");
$check($gatewayA->read($valid)['status']==='accepted_readonly_demo',
 'EXPECTED ANTIROLLBACK GAP: privileged nonce deletion permits replay');
$check($gatewayA->read($valid)['status']==='replay_or_nonce_denied',
 'after one restored replay, nonce is again one-use locally');
$check($first['report']['ready_for_production']===false,
 'even before privileged deletion, report never represented production authorization');

echo "F170 PostgreSQL16 one-shot signed reads: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
