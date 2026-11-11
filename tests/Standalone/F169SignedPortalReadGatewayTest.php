<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F169SignedPortalReadGateway.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F169SignedPortalReadGateway;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F169_SIGNED_READ_LAB')!=='1'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('pdo_pgsql')||!extension_loaded('sodium')){
 throw new RuntimeException('F169 signed Portal reader is a synthetic CI test');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$open=static fn(string $role,string $pwd):PDO=>new PDO($dsn,$role,$pwd,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$readerA=new F166Pg16ReadOnlyDashboard($a);
$readerB=new F166Pg16ReadOnlyDashboard($b);
$keys=sodium_crypto_sign_keypair();
$private=sodium_crypto_sign_secretkey($keys);
$public=sodium_crypto_sign_publickey($keys);
$gatewayA=new F169SignedPortalReadGateway($readerA,$public);
$gatewayB=new F169SignedPortalReadGateway($readerB,$public);

$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F169 FAILED: '.$why);
};
$throws=static function(callable $callback):bool{
 try{$callback();return false;}catch(RuntimeException $e){return true;}
};
$fields=['subject','audience','scope','tenant','issued_at','expires_at','request_id'];
$claims=[
 'subject'=>'imagine-portal-synthetic-s2s',
 'audience'=>'imagine-performance-readonly-v1',
 'scope'=>'performance:demo:read',
 'tenant'=>'perf-demo-aurora',
 'issued_at'=>time(),
 'expires_at'=>time()+30,
 'request_id'=>'request_f169_demo_0001',
];
$sign=static function(array $source,string $secret)use($fields):string{
 $canonical=[];
 foreach($fields as $k)$canonical[$k]=$source[$k]??null;
 $signature=sodium_crypto_sign_detached(
   json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
     |JSON_THROW_ON_ERROR),
   $secret
 );
 return json_encode(['claims'=>$source,'signature'=>base64_encode($signature)],
  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
};
$beforeA=(int)$a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn();
$beforeB=(int)$b->query('SELECT count(*) FROM perf_f162.events')->fetchColumn();

$validA=$sign($claims,$private);
$okA=$gatewayA->read($validA);
$check($okA['status']==='accepted_readonly_demo'
 &&is_array($okA['report'])
 &&$okA['report']['tenant_ref']==='perf-demo-aurora',
 'valid detached Ed25519 read capability returns only signed tenant A report');
$check($okA['report']['read_only']===true
 &&$okA['report']['iam_verified']===false
 &&$okA['report']['s2s_verified']===false
 &&$okA['report']['dispatch_enabled']===false
 &&$okA['report']['billing_enabled']===false,
 'valid synthetic signed read never claims real Portal IAM or Ads authority');
$check($okA['read_only']===true
 &&$okA['action_authorized']===false
 &&$okA['human_approval_verified']===false
 &&$okA['can_enable_ads']===false
 &&$okA['can_bill']===false
 &&$okA['ready_for_production']===false,
 'all output permission flags remain negative even on accepted read');
$check($okA['report']['active_review_count']>=1,
 'signed read returns seeded PG16 fixture content instead of fake empty response');

$claimsB=$claims;
$claimsB['tenant']='perf-demo-boreal';
$claimsB['request_id']='request_f169_demo_0002';
$validB=$sign($claimsB,$private);
$okB=$gatewayB->read($validB);
$check($okB['status']==='accepted_readonly_demo'
 &&$okB['report']['tenant_ref']==='perf-demo-boreal'
 &&$okB['report']['active_review_count']>=1,
 'second real SQL login can consume its own signed synthetic capability');
$check($gatewayA->read($validB)['status']==='tenant_session_or_report_denied'
 &&$gatewayB->read($validA)['status']==='tenant_session_or_report_denied',
 'valid signatures cannot switch database RLS tenant by claim spoofing');
$check($gatewayA->read($validB)['report']===null,
 'cross-tenant signed failure does not leak a report body');

$modified=json_decode($validA,true,8,JSON_THROW_ON_ERROR);
$modified['claims']['tenant']='perf-demo-boreal';
$check($gatewayA->read(json_encode($modified,JSON_THROW_ON_ERROR))['status']
 ==='signature_denied',
 'changing tenant after signature invalidates detached Ed25519 proof');
$modified=json_decode($validA,true,8,JSON_THROW_ON_ERROR);
$modified['claims']['request_id']='request_f169_tamper_01';
$check($gatewayA->read(json_encode($modified,JSON_THROW_ON_ERROR))['status']
 ==='signature_denied',
 'request ID tampering without resigning fails');
$check($gatewayA->read($sign($claims,random_bytes(64)))['status']
 ==='signature_denied',
 'unrelated private key cannot impersonate pinned signer');
$check($gatewayA->read($sign(array_replace($claims,['scope'=>'ads:write']),$private))['status']
 ==='scope_or_audience_denied',
 'even correctly signed paid media write scope is refused');
$check($gatewayA->read($sign(array_replace($claims,
 ['audience'=>'google-ads-provider']),$private))['status']
 ==='scope_or_audience_denied',
 'signed request for a real external provider is refused');
$check($gatewayA->read($sign(array_replace($claims,
 ['subject'=>'unknown-workload']),$private))['status']
 ==='scope_or_audience_denied',
 'signed unknown workload subject cannot read report');
$check($gatewayA->read($sign(array_replace($claims,
 ['tenant'=>'perf-demo-hidden']),$private))['status']
 ==='scope_or_audience_denied',
 'signed unknown tenant cannot request arbitrary company');
$check($gatewayA->read($sign(array_replace($claims,
 ['request_id'=>'*INVALID*']),$private))['status']
 ==='scope_or_audience_denied',
 'malformed request identifier refused despite valid signature');
$check($gatewayA->read($sign(array_replace($claims,
 ['issued_at'=>time()-80,'expires_at'=>time()-20]),$private))['status']
 ==='expired_or_out_of_window_denied',
 'expired signed request denied');
$check($gatewayA->read($sign(array_replace($claims,
 ['issued_at'=>time()+10,'expires_at'=>time()+20]),$private))['status']
 ==='expired_or_out_of_window_denied',
 'issued-at too far in future denied');
$check($gatewayA->read($sign(array_replace($claims,
 ['issued_at'=>time(),'expires_at'=>time()+120]),$private))['status']
 ==='expired_or_out_of_window_denied',
 'excessive lifetime beyond 60 seconds refused');
$check($gatewayA->read($sign(array_replace($claims,
 ['expires_at'=>time()-1]),$private))['status']
 ==='expired_or_out_of_window_denied',
 'already expired token denied even with fresh issue timestamp');
$check($gatewayA->read($sign(array_replace($claims,
 ['issued_at'=>(string)time()]),$private))['status']
 ==='expired_or_out_of_window_denied',
 'integer timestamps are mandatory, no string coercion');

$additional=json_decode($validA,true,8,JSON_THROW_ON_ERROR);
$additional['public_key']=base64_encode(random_bytes(32));
$check($gatewayA->read(json_encode($additional,JSON_THROW_ON_ERROR))['status']
 ==='untrusted_request_denied',
 'request cannot select its own verification public key');
$additional=json_decode($validA,true,8,JSON_THROW_ON_ERROR);
$additional['claims']['admin']=true;
$check($gatewayA->read(json_encode($additional,JSON_THROW_ON_ERROR))['status']
 ==='untrusted_request_denied',
 'unknown elevated claim rejected before signature validation');
$additional=json_decode($validA,true,8,JSON_THROW_ON_ERROR);
$additional['signature']='not-base64-ed25519';
$check($gatewayA->read(json_encode($additional,JSON_THROW_ON_ERROR))['status']
 ==='signature_denied',
 'malformed signature refused');
$check($gatewayA->read('{not-json')['status']==='untrusted_request_denied',
 'malformed JSON refused');
$check($gatewayA->read(str_repeat('x',5000))['status']
 ==='untrusted_request_denied',
 'oversized envelope rejected without parsing');
$check($gatewayA->read('')['status']==='untrusted_request_denied',
 'empty envelope rejected');

$otherPair=sodium_crypto_sign_keypair();
$otherPriv=sodium_crypto_sign_secretkey($otherPair);
$otherPub=sodium_crypto_sign_publickey($otherPair);
$rotated=new F169SignedPortalReadGateway($readerA,$otherPub);
$check($rotated->read($validA)['status']==='signature_denied',
 'rotating pinned verification key revokes old signed requests');
$check($rotated->read($sign($claims,$otherPriv))['status']
 ==='accepted_readonly_demo',
 'only new trusted signer works after synthetic key rotation');
$check($throws(static fn()=>new F169SignedPortalReadGateway($readerA,'short')),
 'invalid pinned public key length refuses constructor');

$check($gatewayA->read($validA)===$okA,
 'read-only short-lived signed capability permits same in-process replay');
$check((int)$a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()===$beforeA
 &&(int)$b->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()===$beforeB,
 'synthetic signed read attempts never mutate PG16 review or audit records');
echo "F169 signed Portal read gateway: {$n} PASS / 0 FAIL — CI only / NO-GO\n";
