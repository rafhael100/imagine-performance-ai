<?php
declare(strict_types=1);

/**
 * F1-48 expected-negative proof: real pg_dump/psql data restoration of
 * authority observation receipts and quarantine status REOPENS old permits.
 * This CI demonstration is a known vulnerability, not a mitigation.
 */
require_once __DIR__.'/SyntheticExternalAtomicPermitObserver.php';

use Imagine\Protocol\Lab\SyntheticExternalAtomicPermitObserver;

$phase=getenv('PERF_F48_PHASE');
$path=getenv('F48_SAVED_TOKENS_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F47_EXTERNAL_ATOMIC_LAB')!=='1'
 ||getenv('PERF_F48_RESTORE_NEGATIVE_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($path)||$path===''
 ||!in_array($phase,[
   'before_snapshot','first_use','restored_receipts',
   'quarantined','restored_authority'
 ],true)){
 throw new RuntimeException('F48 isolated restore CI requires five exact phases');
}
$pdo=static fn(string $db,int $port,string $role,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $role,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$external=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$portal=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$issuer=$pdo('perf_f31_nonce_ci',5433,'perf_f46_issuer','ci-f46-issuer-only');
$observer=new SyntheticExternalAtomicPermitObserver(
 $pdo('perf_f31_nonce_ci',5433,'perf_f47_observer','ci-f47-observer-only')
);
$owner='worker-f38-a';
$n=0;
$check=static function(string $name,mixed $got,mixed $want)use(&$n):void{
 if($got!==$want){
  throw new RuntimeException('FAIL F48 '.$name.' actual='.var_export($got,true)
    .' expected='.var_export($want,true));
 }
 echo 'PASS F48 '.++$n.' '.$name.PHP_EOL;
};
$mint=static function(string $nonce)use($issuer,$owner):string{
 $s=$issuer->prepare(
   'SELECT perf_f46_issuer.mint_server(?,CAST(? AS bigint),?,'
    .'CAST(? AS bigint),?,CAST(? AS bigint))'
 );
 $s->execute(['claim',0,$owner,0,$nonce,1000000000]);
 $value=$s->fetchColumn();
 return is_string($value)?$value:'permit_mint_failed';
};
$state=static fn():string=>(string)$external->query(
 'SELECT perf_f41_recovery.read_state()'
)->fetchColumn();
$localState=static fn():string=>(string)$portal->query(
 'SELECT perf_f43_sql_guard.read_state()'
)->fetchColumn();
$count=static fn():int=>(int)$external->query(
 'SELECT count(*) FROM perf_f47_authority.observed'
)->fetchColumn();
$disabled=static fn():int=>(int)$portal->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$load=static function()use($path):array{
 $raw=@file_get_contents($path);
 $json=is_string($raw)?json_decode($raw,true):null;
 if(!is_array($json)||!isset($json['first'],$json['second'])
    ||!is_string($json['first'])||!is_string($json['second'])){
   throw new RuntimeException('Missing F48 synthetic token fixtures');
 }
 return $json;
};
$valid=static function(string $nonce):string{
 return 'observed_external_mock:'.$nonce.':10';
};

switch($phase){
case 'before_snapshot':
 $check('F47 external authority starts released epoch ten',$state(),'released_mock:10');
 $check('Portal local remains stale released epoch eight',$localState(),'released_mock:8');
 $check('prior F47 test created exactly two external observations',$count(),2);
 $first=$mint('f45-f48-old-observation-aaaa');
 $second=$mint('f45-f48-pre-quarantine-bb');
 $check('fresh prior-restore token has correct signed claim prefix',
   str_starts_with($first,'f45|claim|0|'),true);
 $check('another token minted before future quarantine',
   str_starts_with($second,'f45|claim|0|'),true);
 $check('distinct token nonces embedded before snapshot',$first!==$second,true);
 if(file_put_contents($path,json_encode(
  ['first'=>$first,'second'=>$second],JSON_THROW_ON_ERROR))===false){
  throw new RuntimeException('Failed saving F48 CI token file');
 }
 $check('test tokens available to other PHP processes',is_file($path),true);
 $check('mint does not create observed nonce before first use',$count(),2);
 $check('no provider dispatch enabled before snapshots',$disabled(),0);
 break;
case 'first_use':
 $token=$load();
 $check('external authority still released epoch ten',$state(),'released_mock:10');
 $check('first consumption accepted by F47 external SQL observer',
   $observer->observe($token['first']),
   $valid('f45-f48-old-observation-aaaa'));
 $check('receipt ledger advanced by one',$count(),3);
 $check('second use refused before restoring receipts',
   $observer->observe($token['first']),'external_nonce_replay_denied');
 $check('second token has not been observed yet',
   $observer->observe('malformed'),'invalid_external_permit_denied');
 $check('outbox remains disabled after receipt',$disabled(),0);
 break;
case 'restored_receipts':
 $token=$load();
 $check('F47 receipt table restored to the old two-row snapshot',$count(),2);
 $check('source quarantine still says released epoch ten',$state(),'released_mock:10');
 $check('EXPECTED VULNERABILITY: consumed permit accepted AGAIN after receipt restore',
   $observer->observe($token['first']),
   $valid('f45-f48-old-observation-aaaa'));
 $check('newly replayed receipt now appears in the table',$count(),3);
 $check('same replay immediately refused again',
   $observer->observe($token['first']),'external_nonce_replay_denied');
 $check('no external action made despite mock replay',$disabled(),0);
 break;
case 'quarantined':
 $token=$load();
 $check('external authority now quarantined epoch eleven',$state(),'quarantined:11');
 $check('local SQL remains released epoch eight',$localState(),'released_mock:8');
 $before=$count();
 $check('quarantine prevents already used permit regardless of receipt',
   $observer->observe($token['first']),'external_quarantine_denied');
 $check('quarantine denies cached second unused permit',
   $observer->observe($token['second']),'external_quarantine_denied');
 $check('quarantine leaves receipt count unchanged',$count(),$before);
 $check('outbox still disabled',$disabled(),0);
 break;
case 'restored_authority':
 $token=$load();
 $check('restore erased external quarantine and reverted to epoch ten',
   $state(),'released_mock:10');
 $check('restore erased external receipts back to two old entries',$count(),2);
 $check('EXPECTED CRITICAL GAP: old consumed permit accepted after guard+receipt restore',
   $observer->observe($token['first']),
   $valid('f45-f48-old-observation-aaaa'));
 $check('EXPECTED CRITICAL GAP: previously revoked unused token accepted too',
   $observer->observe($token['second']),
   $valid('f45-f48-pre-quarantine-bb'));
 $check('receipt records now four after replayed and revived tokens',$count(),4);
 $check('repeated use of revived token is denied again',$observer->observe(
     $token['second']),'external_nonce_replay_denied');
 $check('intents remain synthetic pending only',
   (int)$portal->query(
    "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
   )->fetchColumn(),0);
 $check('outbox dispatch never enabled',$disabled(),0);
 putenv('PERF_F47_EXTERNAL_ATOMIC_LAB=0');
 $check('feature gate disables synthetic observer',$observer->observe(
    $token['first']),'external_atomic_lab_disabled');
 putenv('PERF_F47_EXTERNAL_ATOMIC_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment refuses mock observer',$observer->observe(
   $token['first']),'external_atomic_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F48 phase={$phase} assertions={$n} expected-negative authority restore".PHP_EOL;
