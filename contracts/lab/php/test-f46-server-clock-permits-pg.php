<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticServerClockPermitGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticServerClockPermitGateway;

$phase=getenv('PERF_F46_PHASE');
$cacheFile=getenv('F46_CACHED_PERMITS_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F46_SERVER_CLOCK_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($cacheFile)||$cacheFile===''
 ||!in_array($phase,['initial','external_quarantine',
   'local_quarantine','both_released'],true)){
 throw new RuntimeException('F46 requires four phases in disposable CI');
}
$pdo=static fn(string $db,int $port,string $role,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $role,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$portal=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$external=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$issuer=$pdo('perf_f31_nonce_ci',5433,'perf_f46_issuer','ci-f46-issuer-only');
$consumer=$pdo('portal_perf_auth_lab',5432,'perf_f46_consumer','ci-f46-consumer-only');
$oldIssuer=$pdo('perf_f31_nonce_ci',5433,'perf_f45_issuer','ci-f45-issuer-only');
$oldConsumer=$pdo('portal_perf_auth_lab',5432,'perf_f45_consumer','ci-f45-consumer-only');
$gateway=new SyntheticServerClockPermitGateway($issuer,$consumer);
$writer=new SyntheticIdempotentIntentGateway(
 $pdo('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only'));
$owner='worker-f38-a';
$manifest=hash('sha256','f46-server-clock-only');
$forgedPast=1000000000;
$forgedFuture=2100000000;
$n=0;
$check=static function(string $label,mixed $got,mixed $want)use(&$n):void{
 if($got!==$want)throw new RuntimeException(
   'FAIL F46 '.$label.' got='.var_export($got,true)
   .' expected='.var_export($want,true));
 echo 'PASS F46 '.++$n.' '.$label.PHP_EOL;
};
$parse=static function(string $value):array{
 if(!preg_match('/^claimed_signed_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',
  $value,$m))throw new RuntimeException('Not a valid mock signed lease '.$value);
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$parts=static function(string $token):array{
 $p=explode('|',$token);
 if(count($p)!==10||$p[0]!=='f45')throw new RuntimeException('Signed permit expected');
 return $p;
};
$timeOf=static fn(PDO $db):int=>(int)$db->query(
 'SELECT floor(extract(epoch from clock_timestamp()))::bigint'
)->fetchColumn();
$state=static fn(PDO $db,string $name):string=>(string)$db->query(
 'SELECT '.$name.'.read_state()'
)->fetchColumn();
$dispatch=static fn():int=>(int)$portal->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$permissionDenied=static function(PDO $db,string $query):bool{
 try{$db->query($query);return false;}
 catch(PDOException $e){return $e->getCode()==='42501';}
};
$load=static function()use($cacheFile):array{
 $raw=@file_get_contents($cacheFile);
 $a=is_string($raw)?json_decode($raw,true):null;
 if(!is_array($a)||!isset($a['cached1'],$a['cached2']))
   throw new RuntimeException('F46 expected 2 cached permits');
 return $a;
};
$now=$timeOf($external);
switch($phase) {
case 'initial':
 $check('external F41 released epoch seven',
   $state($external,'perf_f41_recovery'),'released_mock:7');
 $check('local F43 released epoch seven',
   $state($portal,'perf_f43_sql_guard'),'released_mock:7');
 $check('local and external PostgreSQL server clocks within 5 seconds',
   abs($timeOf($external)-$timeOf($portal))<=5,true);
 $check('old F45 external issuer role can no longer mint SQL permits',
   $permissionDenied($oldIssuer,
    "SELECT perf_f45_issuer.mint('claim',0,'worker-f38-a',0,1800000000,'f46-old-role-denied-aaa')"),true);
 $check('old F45 local consumer role cannot consume directly',
   $permissionDenied($oldConsumer,
    "SELECT perf_f45_admission.consume('nonsense',1800000000)"),true);
 $check('new issuer role cannot call the old F45 mint function',
   $permissionDenied($issuer,
    "SELECT perf_f45_issuer.mint('claim',0,'worker-f38-a',0,1800000000,'f46-new-role-denied-aaa')"),true);
 $check('new consumer role cannot call old F45 consume',
   $permissionDenied($consumer,
    "SELECT perf_f45_admission.consume('nonsense',1800000000)"),true);
 $check('new issuer cannot SELECT private HMAC secret',
   $permissionDenied($issuer,'SELECT * FROM perf_f45_issuer.signing_key'),true);
 $check('new consumer cannot SELECT private HMAC verification secret',
   $permissionDenied($consumer,'SELECT * FROM perf_f45_admission.verifier_key'),true);
 $check('new roles remain unable to call F38 raw SQL',
   $permissionDenied($consumer,
    "SELECT perf_f38_fenced.claim('worker-f38-a',1800000000)"),true);
 $check('new safe synthetic read-only intent reserved',
   $writer->reserve('f46-db-clock-first',$manifest),'reserved_synthetic');

 $first=$gateway->mint('claim',0,$owner,0,'f46-server-claim-aaaacc',$forgedPast);
 $p=$parts($first);
 $check('forged past time not used as token issue time',(int)$p[6]!==$forgedPast,true);
 $check('permit uses external database clock',
   abs((int)$p[6]-$timeOf($external))<=4,true);
 $check('permit TTL still 30 seconds from database time',
   (int)$p[7]-(int)$p[6],30);
 $check('nonce matches explicit demo-only format',
   $p[8],'f46-server-claim-aaaacc');
 [$id,$gen,$ep]=$parse($gateway->consume($first,$forgedFuture));
 $check('forged future time cannot invalidate a fresh permit',$ep,7);
 $check('fresh permit claim provides id and generation',
   $id>0&&$gen>0,true);
 $check('same signed permit replay denied with false clock',
   $gateway->consume($first,$forgedPast),'signed_nonce_replay_denied');
 $ack=$gateway->mint('ack',$id,$owner,$gen,
     'f46-server-ack-aaaacc',$forgedFuture);
 $check('ACK permit minted despite supplied future clock',
   str_starts_with($ack,'f45|ack|'.$id.'|'),true);
 $check('ACK with forged past accepted only if server time valid',
   $gateway->consume($ack,$forgedPast),'acknowledged_signed_mock');
 $check('signed ACK replay denied regardless of claimed clock',
   $gateway->consume($ack,$forgedFuture),'signed_nonce_replay_denied');

 // A privileged CI fixture deliberately signs a backdated F45 permit.
 // F46 admission MUST refuse even if the caller supplies its forged time.
 $past=$now-45;
 $stmt=$external->prepare(
  'SELECT perf_f45_issuer.mint(?,CAST(? AS bigint),?,'
  .'CAST(? AS bigint),CAST(? AS bigint),?)'
 );
 $stmt->execute(['claim',0,$owner,0,$past,'f46-backdated-expired-aa']);
 $expired=(string)$stmt->fetchColumn();
 $check('backdated privileged CI fixture has valid token shape',
   str_starts_with($expired,'f45|claim|'),true);
 $check('forged backdated clock cannot revive already expired signed permit',
   $gateway->consume($expired,$past),'invalid_or_expired_permit_denied');
 $stmt->execute(['claim',0,$owner,0,$now+45,'f46-future-premature-aa']);
 $future=(string)$stmt->fetchColumn();
 $check('forged future clock cannot admit not-yet-active permit',
   $gateway->consume($future,$now+45),'invalid_or_expired_permit_denied');
 $cache1=$gateway->mint('claim',0,$owner,0,
     'f46-cache-external-aaaa',$forgedPast);
 $cache2=$gateway->mint('claim',0,$owner,0,
     'f46-cache-local-bbbbbb',$forgedPast);
 $check('two separate issued permits available for quarantine experiments',
   $cache1!==$cache2,true);
 if(file_put_contents($cacheFile,json_encode(
   ['cached1'=>$cache1,'cached2'=>$cache2],JSON_THROW_ON_ERROR))===false)
     throw new RuntimeException('Cannot save F46 cached CI permits');
 $check('two cached tokens stored for cross-PHP-process negative tests',
   is_file($cacheFile),true);
 $check('no provider dispatch in server-clock tests',$dispatch(),0);
 break;

case 'external_quarantine':
 $check('F41 external entered quarantine epoch eight',
   $state($external,'perf_f41_recovery'),'quarantined:8');
 $check('F43 local still released epoch seven',
   $state($portal,'perf_f43_sql_guard'),'released_mock:7');
 $check('new issuance during external quarantine denied',
   $gateway->mint('claim',0,$owner,0,
     'f46-external-locked-aa',$forgedPast),
   'external_quarantine_denied');
 // Expected remaining problem: local SQL cannot independently detect source
 // quarantine and still accepts previously issued bearer until TTL ends.
 $old=$load();
 $cached=$gateway->consume($old['cached1'],$forgedFuture);
 $check('EXPECTED VULNERABILITY cached permit still usable after external quarantine',
   str_starts_with($cached,'claimed_signed_mock:'),true);
 $check('repeat cached claim denied by nonce ledger',
   $gateway->consume($old['cached1'],$forgedPast),
   'signed_nonce_replay_denied');
 $check('no external provider call was made',$dispatch(),0);
 break;

case 'local_quarantine':
 $check('external remains quarantined epoch eight',
   $state($external,'perf_f41_recovery'),'quarantined:8');
 $check('local quarantine now also epoch eight',
   $state($portal,'perf_f43_sql_guard'),'quarantined:8');
 $check('remaining older cached permit denied by local SQL quarantine',
   $gateway->consume($load()['cached2'],$forgedFuture),
   'sql_quarantine_denied');
 $check('no new permit issued during external quarantine',
   $gateway->mint('claim',0,$owner,0,
      'f46-both-quarantined-aa',$forgedPast),
   'external_quarantine_denied');
 $check('outbox stays dispatch-disabled',$dispatch(),0);
 break;

case 'both_released':
 $check('both sources released with new recovery epoch eight',
   [$state($external,'perf_f41_recovery'),$state($portal,'perf_f43_sql_guard')],
   ['released_mock:8','released_mock:8']);
 $check('new read-only synthetic intent reserved in epoch eight',
   $writer->reserve('f46-after-quarantine',$manifest),'reserved_synthetic');
 $token=$gateway->mint('claim',0,$owner,0,
   'f46-claim-epoch-eight-aa',$forgedFuture);
 [$id,$gen,$epoch]=$parse($gateway->consume($token,$forgedPast));
 $check('server-clock signed claim uses recovery epoch eight',$epoch,8);
 $ack=$gateway->mint('ack',$id,$owner,$gen,
   'f46-ack-epoch-eight-aa',$forgedPast);
 $check('signed ACK accepted under current server time and epoch',
   $gateway->consume($ack,$forgedFuture),'acknowledged_signed_mock');
 $check('old epoch-seven cached permit cannot be consumed after release',
   $gateway->consume($load()['cached2'],$forgedPast),
   'stale_local_epoch_denied');
 $check('ACK cannot be replayed with a different time',
   $gateway->consume($ack,$forgedPast),'signed_nonce_replay_denied');
 $external->exec(
   'REVOKE EXECUTE ON FUNCTION perf_f46_issuer.mint_server(text,bigint,text,bigint,text,bigint) FROM perf_f46_issuer'
 );
 $check('issuer permission loss causes fail-closed',
   $gateway->mint('claim',0,$owner,0,'f46-no-role-permit-aaaa',$forgedPast),
   'server_issuer_unavailable');
 $external->exec(
   'GRANT EXECUTE ON FUNCTION perf_f46_issuer.mint_server(text,bigint,text,bigint,text,bigint) TO perf_f46_issuer'
 );
 $portal->exec(
   'REVOKE EXECUTE ON FUNCTION perf_f46_admission.consume_server(text,bigint) FROM perf_f46_consumer'
 );
 $check('admission permission loss causes fail-closed',
   $gateway->consume($token,$forgedPast),'server_admission_unavailable');
 $portal->exec(
   'GRANT EXECUTE ON FUNCTION perf_f46_admission.consume_server(text,bigint) TO perf_f46_consumer'
 );
 $check('outbox remains disabled after all permutations',$dispatch(),0);
 $check('all requests remain synthetic pending',
   (int)$portal->query(
    "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
   )->fetchColumn(),0);
 putenv('PERF_F46_SERVER_CLOCK_LAB=0');
 $check('disabled feature switch closes mint',
   $gateway->mint('claim',0,$owner,0,'f46-disabled-feature-aa',$forgedPast),
   'server_clock_lab_disabled');
 putenv('PERF_F46_SERVER_CLOCK_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment closes permit admission',
   $gateway->consume($token,$forgedFuture),'server_clock_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F46 phase={$phase} count={$n} trusted SQL demo clock checks".PHP_EOL;
