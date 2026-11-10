<?php
declare(strict_types=1);

/**
 * F1-47 tests an authoritative, single-database observation only.
 * Any operation via local PostgreSQL remains PROHIBITED by this facade.
 */
require_once __DIR__.'/SyntheticServerClockPermitGateway.php';
require_once __DIR__.'/SyntheticExternalAtomicPermitObserver.php';

use Imagine\Protocol\Lab\SyntheticExternalAtomicPermitObserver;

$phase=getenv('PERF_F47_PHASE');
$cache=getenv('F47_CACHED_PERMITS_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F47_EXTERNAL_ATOMIC_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($cache)||$cache===''
 ||!in_array($phase,['issued','external_quarantine','released_new_epoch'],true)){
 throw new RuntimeException('F47 only in three CI phases with two mock PostgreSQL instances');
}
$connect=static fn(string $db,int $port,string $user,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$externalAdmin=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$portalAdmin=$connect('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$issuer=$connect('perf_f31_nonce_ci',5433,'perf_f46_issuer','ci-f46-issuer-only');
$consumer=$connect('perf_f31_nonce_ci',5433,'perf_f47_observer','ci-f47-observer-only');
$legacyLocal=$connect('portal_perf_auth_lab',5432,'perf_f46_consumer','ci-f46-consumer-only');
$authority=new SyntheticExternalAtomicPermitObserver($consumer);
$wrongAuthority=new SyntheticExternalAtomicPermitObserver($externalAdmin);
$owner='worker-f38-a';
$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void{
 if($got!==$expected)throw new RuntimeException(
  'FAIL F47 '.$name.' actual='.var_export($got,true)
    .' expected='.var_export($expected,true));
 echo 'PASS F47 '.++$n.' '.$name.PHP_EOL;
};
$state=static fn():string=>(string)$externalAdmin->query(
 'SELECT perf_f41_recovery.read_state()'
)->fetchColumn();
$localState=static fn():string=>(string)$portalAdmin->query(
 'SELECT perf_f43_sql_guard.read_state()'
)->fetchColumn();
$observed=static fn():int=>(int)$externalAdmin->query(
 'SELECT count(*) FROM perf_f47_authority.observed'
)->fetchColumn();
$dispatch=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$mint=static function(string $action,int $id,int $generation,string $nonce)
 use($issuer,$owner):string{
 $s=$issuer->prepare(
   'SELECT perf_f46_issuer.mint_server(?,CAST(? AS bigint),?,'
  .'CAST(? AS bigint),?,CAST(? AS bigint))'
 );
 $s->execute([$action,$id,$owner,$generation,$nonce,1000000000]);
 $value=$s->fetchColumn();
 return is_string($value)?$value:'issuer_no_response';
};
$denied=static function(PDO $db,string $sql):bool {
 try{$db->query($sql);return false;}
 catch(PDOException $e){return $e->getCode()==='42501';}
};
$cacheData=static function()use($cache):array{
 $json=@file_get_contents($cache);
 $data=is_string($json)?json_decode($json,true):null;
 if(!is_array($data)||!isset($data['cached1'],$data['cached2']))
   throw new RuntimeException('Expected two previously minted F47 permits');
 return $data;
};
switch($phase){
case 'issued':
 $check('external source released at epoch eight',$state(),'released_mock:8');
 $check('local SQL source released at epoch eight',$localState(),'released_mock:8');
 $check('F47 observation ledger initially empty',$observed(),0);
 $check('F47 role cannot read F45 signing key',
   $denied($consumer,'SELECT * FROM perf_f45_issuer.signing_key'),true);
 $check('F47 role cannot read issued permit table',
   $denied($consumer,'SELECT * FROM perf_f45_issuer.issued'),true);
 $check('F47 role cannot select observation ledger',
   $denied($consumer,'SELECT * FROM perf_f47_authority.observed'),true);
 $check('F47 role cannot change quarantine authority',
   $denied($consumer,"UPDATE perf_f41_recovery.guard_state SET status='quarantined'"),true);
 $check('old F46 local consumer cannot call local consume after cutover',
   $denied($legacyLocal,
     "SELECT perf_f46_admission.consume_server('nonsense',1000000000)"),true);
 $check('wrong database role denied by PHP gate',
   $wrongAuthority->observe('nonsense'),'wrong_external_observer_role');
 $check('invalid unsigned token denied',$authority->observe('fake'),
   'invalid_external_permit_denied');

 $first=$mint('claim',0,0,'f45-f47-first-claim-aaaa');
 $check('fresh external issuer signs a claim token',
   str_starts_with($first,'f45|claim|0|'),true);
 $check('tampered owner cannot bypass HMAC',
   $authority->observe(str_replace(
     '|worker-f38-a|','|worker-f38-b|',$first)),
   'external_signature_invalid_denied');
 $check('first original token recorded by external authority',
   $authority->observe($first),'observed_external_mock:f45-f47-first-claim-aaaa:8');
 $check('same token cannot be observed twice',
   $authority->observe($first),'external_nonce_replay_denied');
 $check('observation ledger contains one immutable synthetic read marker',
   $observed(),1);
 $ack=$mint('ack',4,1,'f45-f47-ack-never-aaaaa');
 $check('external observation denies ACK / write-like permit',
   $authority->observe($ack),'external_nonread_action_denied');

 // Only the CI superuser can fabricate a correctly signed earlier timestamp.
 $ts=(int)$externalAdmin->query(
   'SELECT floor(extract(epoch from clock_timestamp()))::bigint'
 )->fetchColumn();
 $back=$externalAdmin->prepare(
  'SELECT perf_f45_issuer.mint(?,CAST(? AS bigint),?,'
    .'CAST(? AS bigint),CAST(? AS bigint),?)'
 );
 $back->execute(['claim',0,$owner,0,$ts-40,'f45-f47-expired-token-aaaa']);
 $exp=(string)$back->fetchColumn();
 $check('valid old HMAC is refused using database real clock',
   $authority->observe($exp),'external_expired_permit_denied');
 $back->execute(['claim',0,$owner,0,$ts+40,'f45-f47-future-token-bbbb']);
 $future=(string)$back->fetchColumn();
 $check('valid not-yet-active HMAC is refused using database clock',
   $authority->observe($future),'external_expired_permit_denied');

 $cached1=$mint('claim',0,0,'f45-f47-cache-one-aaaaaa');
 $cached2=$mint('claim',0,0,'f45-f47-cache-two-bbbbbb');
 $check('two fresh distinct permits issued before quarantine',
   $cached1!==$cached2,true);
 if(file_put_contents($cache,json_encode(
    ['cached1'=>$cached1,'cached2'=>$cached2],JSON_THROW_ON_ERROR))===false){
   throw new RuntimeException('Failed to store CI-only cached token fixture');
 }
 $check('cache fixture saved across PHP processes',is_file($cache),true);

 // Demonstrate the SQL primitive that serializes with operator revocation:
 // a status UPDATE cannot commit while a consumer transaction holds SHARE.
 $locker=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
 $competing=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
 $locker->beginTransaction();
 $locker->query(
  'SELECT status,epoch FROM perf_f41_recovery.guard_state'
  .' WHERE singleton_id=true FOR SHARE'
 )->fetch(PDO::FETCH_ASSOC);
 $competing->exec("SET lock_timeout='150ms'");
 $wasLocked=false;
 try{
  $competing->exec(
   "UPDATE perf_f41_recovery.guard_state SET reason="
   ."'blocked operator update during CI lock test'"
   ." WHERE singleton_id=true"
  );
 }catch(PDOException $e){$wasLocked=$e->getCode()==='55P03';}
 $locker->commit();
 $check('operator change blocked by concurrent quarantine FOR SHARE lock',
   $wasLocked,true);
 $check('operator lock denial did not change recovery status',
   $state(),'released_mock:8');
 $check('outbox still completely dispatch-disabled',$dispatch(),0);
 break;

case 'external_quarantine':
 $check('external authority atomically entered quarantine epoch nine',
   $state(),'quarantined:9');
 $check('local authority is intentionally stale and released epoch eight',
   $localState(),'released_mock:8');
 $before=$observed();
 $cached=$cacheData();
 $check('cached signed permit 1 DENIED immediately by external guard',
   $authority->observe($cached['cached1']),'external_quarantine_denied');
 $check('cached signed permit 2 DENIED immediately by external guard',
   $authority->observe($cached['cached2']),'external_quarantine_denied');
 $check('quarantine failures do not consume any nonce',
   $observed(),$before);
 $check('issuer cannot mint permits while external quarantine active',
   $mint('claim',0,0,'f45-f47-mint-in-quar-aaaa'),
   'external_quarantine_denied');
 $check('old local F46 role remains revoked during external quarantine',
   $denied($legacyLocal,
    "SELECT perf_f46_admission.consume_server('nonsense',1000000000)"),true);
 $check('no synthetic external action occurred',$dispatch(),0);
 break;

case 'released_new_epoch':
 $check('external operator mock released a NEW epoch ten',
   $state(),'released_mock:10');
 $check('local SQL remains stale epoch eight (no operational path)',
   $localState(),'released_mock:8');
 $cached=$cacheData();
 $check('cached epoch-eight token stays revoked after re-release',
   $authority->observe($cached['cached1']),'stale_external_epoch_denied');
 $check('second cached epoch-eight token stays revoked',
   $authority->observe($cached['cached2']),'stale_external_epoch_denied');
 $new=$mint('claim',0,0,'f45-f47-new-epoch-cccccc');
 $check('fresh epoch-ten token is an HMAC permit',
   str_starts_with($new,'f45|claim|0|'),true);
 $check('new read-only token observed only by external authority',
   $authority->observe($new),
   'observed_external_mock:f45-f47-new-epoch-cccccc:10');
 $check('new receipt is one-time',
   $authority->observe($new),'external_nonce_replay_denied');
 $check('total real-authority read-only observations is two',
   $observed(),2);
 $check('no local SQL lease was granted as a result',
   (int)$portalAdmin->query(
    "SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled"
   )->fetchColumn(),0);
 $check('no intent gained operational state',
   (int)$portalAdmin->query(
    "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
   )->fetchColumn(),0);
 $externalAdmin->exec(
  'REVOKE EXECUTE ON FUNCTION perf_f47_authority.observe(text) '
  .'FROM perf_f47_observer'
 );
 $check('loss of SQL authority permission fails closed',
   $authority->observe($new),'external_atomic_unavailable');
 $externalAdmin->exec(
  'GRANT EXECUTE ON FUNCTION perf_f47_authority.observe(text) '
  .'TO perf_f47_observer'
 );
 putenv('PERF_F47_EXTERNAL_ATOMIC_LAB=0');
 $check('disabled lab flag blocks admission',
   $authority->observe($new),'external_atomic_lab_disabled');
 putenv('PERF_F47_EXTERNAL_ATOMIC_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment blocks this mock observer',
   $authority->observe($new),'external_atomic_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F47 phase={$phase} assertions={$n} external quarantine atomic READ-ONLY observation".PHP_EOL;
