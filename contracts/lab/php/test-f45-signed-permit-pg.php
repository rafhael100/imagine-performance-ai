<?php
declare(strict_types=1);

/** F1-45: disposable SQL-only short-lived HMAC permit, four phases. */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticSignedPermitGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticSignedPermitGateway;

$phase=getenv('PERF_F45_PHASE');
$artifact=getenv('F45_CACHED_PERMITS_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F45_SIGNED_PERMIT_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($artifact)||$artifact===''
 ||!in_array($phase,['initial','external_quarantine',
                     'local_quarantine','both_released'],true)) {
    throw new RuntimeException('F45 disposable CI only with exact 4 phases');
}
$db=static fn(string $name,int $port,string $user,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$name.';connect_timeout=3',
 $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$portal=$db('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$external=$db('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$issuer=$db('perf_f31_nonce_ci',5433,'perf_f45_issuer','ci-f45-issuer-only');
$consumer=$db('portal_perf_auth_lab',5432,'perf_f45_consumer','ci-f45-consumer-only');
$previous=$db('portal_perf_auth_lab',5432,'perf_f43_sql_consumer','ci-f43-sql-only');
$gateway=new SyntheticSignedPermitGateway($issuer,$consumer);
$badGateway=new SyntheticSignedPermitGateway($external,$portal);
$writer=new SyntheticIdempotentIntentGateway(
 $db('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only')
);
$now=1800000900;
$owner='worker-f38-a';
$manifest=hash('sha256','f45-external-permit-read-fixture');
$n=0;
$check=static function(string $name,mixed $got,mixed $want)use(&$n):void{
 if($got!==$want){
  throw new RuntimeException('FAIL F45 '.$name.' got='.var_export($got,true)
     .' expected='.var_export($want,true));
 }
 echo 'PASS F45 '.++$n.' '.$name.PHP_EOL;
};
$parse=static function(string $claim):array{
 if(!preg_match('/^claimed_signed_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',
     $claim,$m)) {
  throw new RuntimeException('expected signed synthetic claim got '.$claim);
 }
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$readCache=static function()use($artifact):array{
 $raw=@file_get_contents($artifact);
 $a=is_string($raw)?json_decode($raw,true):null;
 if(!is_array($a)||!isset($a['cached1'],$a['cached2']))
  throw new RuntimeException('expected two cached short-lived demo permits');
 return $a;
};
$dispatch=static fn():int=>(int)$portal->query(
  'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$localState=static fn():string=>(string)$portal->query(
 'SELECT perf_f43_sql_guard.read_state()'
)->fetchColumn();
$externalState=static fn():string=>(string)$external->query(
 'SELECT perf_f41_recovery.read_state()'
)->fetchColumn();
$consumedCount=static fn():int=>(int)$portal->query(
 'SELECT count(*) FROM perf_f45_admission.consumed'
)->fetchColumn();
$roleDenied=static function(PDO $conn,string $sql):bool{
 try{$conn->query($sql);return false;}
 catch(PDOException $e){return $e->getCode()==='42501';}
};

switch($phase) {
case 'initial':
 $check('both sources begin released at epoch 6',
    [$externalState(),$localState()],['released_mock:6','released_mock:6']);
 $check('new synthetic read-only intent available',
    $writer->reserve('f45-signed-initial',$manifest),'reserved_synthetic');
 $check('consumer cannot call unguarded F38 SQL',
    $roleDenied($consumer,
       "SELECT perf_f38_fenced.claim('worker-f38-a',1800000900)"),true);
 $check('consumer cannot call older F43 guarded SQL',
    $roleDenied($consumer,
       "SELECT perf_f43_sql_guard.claim('worker-f38-a',1800000900)"),true);
 $check('old F43 consumer role itself no longer has EXECUTE',
    $roleDenied($previous,
       "SELECT perf_f43_sql_guard.claim('worker-f38-a',1800000900)"),true);
 $check('consumer cannot SELECT private verifying secret',
    $roleDenied($consumer,
       'SELECT * FROM perf_f45_admission.verifier_key'),true);
 $check('external issuer cannot SELECT private signing secret',
    $roleDenied($issuer,
       'SELECT * FROM perf_f45_issuer.signing_key'),true);
 $check('wrong SQL roles fail before mint',
    $badGateway->mint('claim',0,$owner,0,$now,'f45-wrong-role-aaaaaa'),
    'wrong_signed_roles');
 $check('unsupported operation cannot be minted',
    $gateway->mint('write',0,$owner,0,$now,'f45-invalid-op-abcdef'),
    'invalid_permit_request_denied');

 $first=$gateway->mint('claim',0,$owner,0,$now,'f45-claim-first-abcdef');
 $check('issuer returned signed claim-shaped token',
    str_starts_with($first,'f45|claim|0|'),true);
 $check('issued nonce cannot be minted twice',
    $gateway->mint('claim',0,$owner,0,$now,'f45-claim-first-abcdef'),
    'nonce_already_issued_denied');
 $check('invalid field alteration breaks HMAC signature',
    $gateway->consume(str_replace(
       '|worker-f38-a|','|worker-f38-b|',$first),$now),
    'invalid_signature_denied');
 $check('arbitrary unsigned request cannot consume SQL lease',
    $gateway->consume('f45|claim|fake',$now),
    'invalid_signed_permit_denied');
 [$id,$gen,$epoch]=$parse($gateway->consume($first,$now));
 $check('signed claim binds recovery epoch 6',$epoch,6);
 $check('claimed event has positive outbox id',$id>0,true);
 $check('claimed event generation positive',$gen>0,true);
 $check('signed nonce prevents replay in local SQL',
    $gateway->consume($first,$now),'signed_nonce_replay_denied');
 $check('wrong generation cannot be minted for claim',
    $gateway->mint('claim',0,$owner,1,$now,'f45-invalid-gen-aaaaaa'),
    'invalid_permit_request_denied');
 $ack=$gateway->mint('ack',$id,$owner,$gen,$now,'f45-ack-first-abcdef');
 $check('issuer signed an ACK for exact id and generation',
    str_starts_with($ack,'f45|ack|'.$id.'|'.$owner.'|'.$gen.'|6|'),true);
 $check('first signed ACK succeeds only in dry-run SQL',
    $gateway->consume($ack,$now),'acknowledged_signed_mock');
 $check('same ACK token is single-use',
    $gateway->consume($ack,$now),'signed_nonce_replay_denied');

 $expire=$gateway->mint('claim',0,$owner,0,$now,'f45-claim-expired-abc');
 $check('short-lived permit rejects synthetic time after 30 seconds',
    $gateway->consume($expire,$now+31),'invalid_or_expired_permit_denied');
 $cached1=$gateway->mint('claim',0,$owner,0,$now,'f45-cache-after-quar-aaa');
 $cached2=$gateway->mint('claim',0,$owner,0,$now,'f45-cache-local-deny-bbb');
 $check('two distinct cached permits issued before quarantine',
    $cached1!==$cached2,true);
 if(file_put_contents($artifact,json_encode(
    ['cached1'=>$cached1,'cached2'=>$cached2],JSON_THROW_ON_ERROR))===false) {
    throw new RuntimeException('Cannot persist cached synthetic permits');
 }
 $check('cached permits saved across PHP processes',is_file($artifact),true);
 $check('exactly two first request nonces consumed',$consumedCount(),2);
 $check('outbox permanently disabled',$dispatch(),0);
 break;

case 'external_quarantine':
 $check('external authority has entered quarantine epoch 7',
    $externalState(),'quarantined:7');
 $check('local SQL is still released epoch 6',
    $localState(),'released_mock:6');
 $check('external issuer cannot mint new permits in quarantine',
    $gateway->mint('claim',0,$owner,0,$now,'f45-mint-during-quar-aaa'),
    'external_quarantine_denied');
 $cache=$readCache();
 $before=$consumedCount();
 // EXPECTED NEGATIVE: bearer minted before external quarantine remains
 // usable by direct local SQL if local release and TTL remain valid.
 $claim=$gateway->consume($cache['cached1'],$now+1);
 $check('EXPECTED VULNERABILITY cached signed permit survives external quarantine',
    str_starts_with($claim,'claimed_signed_mock:'),true);
 $check('cached permit caused one additional local nonce consumption',
    $consumedCount(),$before+1);
 $check('cached token still cannot replay twice',
    $gateway->consume($cache['cached1'],$now+1),'signed_nonce_replay_denied');
 $check('external quarantine still active after local bearer bypass',
    $externalState(),'quarantined:7');
 $check('no providers activated by cached permit',$dispatch(),0);
 break;

case 'local_quarantine':
 $check('external remains quarantined epoch 7',
    $externalState(),'quarantined:7');
 $check('local SQL caught up to quarantine epoch 7',
    $localState(),'quarantined:7');
 $cache=$readCache();
 $before=$consumedCount();
 $check('remaining signed permit denied inside local SQL quarantine',
    $gateway->consume($cache['cached2'],$now+1),
    'sql_quarantine_denied');
 $check('local denial does not consume nonce receipt',
    $consumedCount(),$before);
 $check('external cannot issue permit after local catches up',
    $gateway->mint('ack',1,$owner,1,$now+1,'f45-ack-while-locked'),
    'external_quarantine_denied');
 $check('both quarantines keep outbox disabled',$dispatch(),0);
 break;

case 'both_released':
 $check('both sources released mock after CI operator actions',
    [$externalState(),$localState()],['released_mock:7','released_mock:7']);
 $check('synthetic new intent in epoch 7',
    $writer->reserve('f45-released-epoch7',$manifest),'reserved_synthetic');
 $now=$now+10;
 $fresh=$gateway->mint('claim',0,$owner,0,$now,'f45-claim-epoch7-aaaa');
 [$id,$gen,$epoch]=$parse($gateway->consume($fresh,$now));
 $check('new signed permit binds epoch 7',$epoch,7);
 $check('new claim is a synthetic outbox record',$id>0,true);
 $ack=$gateway->mint('ack',$id,$owner,$gen,$now,'f45-ack-epoch7-aaaaaa');
 $check('new signed ACK completes only dry-run bookkeeping',
    $gateway->consume($ack,$now),'acknowledged_signed_mock');
 $check('signed ACK remains replay protected',
    $gateway->consume($ack,$now),'signed_nonce_replay_denied');
 $check('old token minted at epoch 6 denied after release epoch7',
    $gateway->consume($readCache()['cached2'],$now),
    'stale_local_epoch_denied');
 $check('consumer still cannot call old F43 directly',
    $roleDenied($consumer,
       "SELECT perf_f43_sql_guard.claim('worker-f38-a',1800000910)"),true);
 $external->exec('REVOKE EXECUTE ON FUNCTION perf_f45_issuer.mint(text,bigint,text,bigint,bigint,text) FROM perf_f45_issuer');
 $check('issuer permission revoked: mint fails closed',
    $gateway->mint('claim',0,$owner,0,$now,'f45-claim-no-exec-aaa'),
    'permit_issuer_unavailable');
 $external->exec('GRANT EXECUTE ON FUNCTION perf_f45_issuer.mint(text,bigint,text,bigint,bigint,text) TO perf_f45_issuer');
 $portal->exec('REVOKE EXECUTE ON FUNCTION perf_f45_admission.consume(text,bigint) FROM perf_f45_consumer');
 $check('admission permission revoked: consume fails closed',
    $gateway->consume($fresh,$now),'permit_admission_unavailable');
 $portal->exec('GRANT EXECUTE ON FUNCTION perf_f45_admission.consume(text,bigint) TO perf_f45_consumer');
 $check('no outbox dispatch was enabled',$dispatch(),0);
 $check('all intents remain pending_synthetic',
    (int)$portal->query(
      "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
 putenv('PERF_F45_SIGNED_PERMIT_LAB=0');
 $check('disabled lab flag denies permit issuance',
    $gateway->mint('claim',0,$owner,0,$now,'f45-claim-no-flag-aaa'),
    'signed_lab_disabled');
 putenv('PERF_F45_SIGNED_PERMIT_LAB=1');
 putenv('APP_ENV=production');
 $check('production blocks permit consumers',
    $gateway->consume($fresh,$now),'signed_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F45 phase={$phase} assertions={$n} CI HMAC permits only".PHP_EOL;
