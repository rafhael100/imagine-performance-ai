<?php
declare(strict_types=1);

/**
 * F1-50: restore F41/F47 external SQL snapshots while a THIRD disposable
 * PostgreSQL retains the epoch floor and nonce ledger. Negative CI also
 * demonstrates that direct F47 calls still bypass this facade.
 */
require_once __DIR__.'/SyntheticRestoreSentinelAdmission.php';
use Imagine\Protocol\Lab\SyntheticRestoreSentinelAdmission;

$phase=getenv('PERF_F50_PHASE');
$file=getenv('F50_TEST_TOKEN_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F50_RESTORE_SENTINEL_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($file)||$file===''
 ||!in_array($phase,['rolled_back','quarantined','released_prepare',
    'released_used','restored_receipts'],true)){
 throw new RuntimeException('F50 only uses three disposable PostgreSQL CI services');
}
$pdo=static fn(string $db,int $port,string $user,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$externalAdmin=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$portalAdmin=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$sentinelAdmin=$pdo('perf_f50_sentinel_ci',5434,'postgres','ci-f50-sentinel-db-only');
$external=$pdo('perf_f31_nonce_ci',5433,'perf_f47_observer','ci-f47-observer-only');
$sentinel=$pdo('perf_f50_sentinel_ci',5434,'perf_f50_observer','ci-f50-observer-only');
$issuer=$pdo('perf_f31_nonce_ci',5433,'perf_f46_issuer','ci-f46-issuer-only');
$guard=new SyntheticRestoreSentinelAdmission($external,$sentinel);
$wrong=new SyntheticRestoreSentinelAdmission($externalAdmin,$sentinel);
$owner='worker-f38-a';
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
 if($got!==$expected){
  throw new RuntimeException('FAIL F50 '.$label.' actual='.
    var_export($got,true).' expected='.var_export($expected,true));
 }
 echo 'PASS F50 '.++$n.' '.$label.PHP_EOL;
};
$mint=static function(string $nonce)use($issuer,$owner):string{
 $s=$issuer->prepare('SELECT perf_f46_issuer.mint_server('
  .'?,CAST(? AS bigint),?,CAST(? AS bigint),?,CAST(? AS bigint))');
 $s->execute(['claim',0,$owner,0,$nonce,1000000000]);
 $v=$s->fetchColumn();
 return is_string($v)?$v:'unavailable_test_issuer';
};
$source=static fn():string=>(string)$externalAdmin->query(
 'SELECT perf_f41_recovery.read_state()'
)->fetchColumn();
$minimum=static fn():int=>(int)$sentinelAdmin->query(
 'SELECT minimum_epoch FROM perf_f50_sentinel.floor'
)->fetchColumn();
$seen=static fn():int=>(int)$sentinelAdmin->query(
 'SELECT count(*) FROM perf_f50_sentinel.seen'
)->fetchColumn();
$receipts=static fn():int=>(int)$externalAdmin->query(
 'SELECT count(*) FROM perf_f47_authority.observed'
)->fetchColumn();
$dispatch=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$read=static function()use($file):array{
 $v=@file_get_contents($file);
 $result=is_string($v)?json_decode($v,true):null;
 return is_array($result)?$result:[];
};
$save=static function(array $value)use($file):void{
 if(file_put_contents($file,json_encode($value,JSON_THROW_ON_ERROR))===false)
    throw new RuntimeException('Cannot save F50 token in CI temporary directory');
};
$direct=static function(string $token)use($external):string {
 $s=$external->prepare('SELECT perf_f47_authority.observe(?)');
 $s->execute([$token]);
 return (string)$s->fetchColumn();
};
$expect=static fn(string $nonce,int $epoch):string=>
 'observed_with_sentinel_mock:'.$nonce.':'.$epoch;

switch($phase) {
case 'rolled_back':
 $check('source restored to stale epoch ten by F48',$source(),'released_mock:10');
 $check('third domain independently remembers quarantine epoch eleven',
    $minimum(),11);
 $check('third domain initially has no nonce observations',$seen(),0);
 $token=$mint('f45-f50-old-rollback-aa');
 $check('restored source can still mint obsolete epoch token',
    str_contains($token,'|10|'),true);
 $check('NEW guarded path detects restored authority epoch rollback',
    $guard->observe($token),'restore_rollback_detected_denied');
 $check('rollback refusal did not consume third-domain nonce',$seen(),0);
 $check('EXPECTED LEGACY BYPASS: direct F47 SQL accepts token without sentinel',
    $direct($token),'observed_external_mock:f45-f50-old-rollback-aa:10');
 $check('direct bypass did not alter third-domain receipts',$seen(),0);
 $check('malformed token denied before both sources',
    $guard->observe('invalid'),'malformed_sentinel_permit_denied');
 $check('wrong identity denied by three-domain facade',
    $wrong->observe($token),'wrong_sentinel_roles');
 $check('no dispatch anywhere in rollback test',$dispatch(),0);
 $save(['old'=>$token]);
 break;

case 'quarantined':
 $check('external source now quarantined at epoch eleven',
    $source(),'quarantined:11');
 $check('sentinel minimum remains epoch eleven',$minimum(),11);
 $check('unreconciled source quarantine always denies previous token',
    $guard->observe($read()['old']),'external_quarantine_denied');
 $check('independent floor cannot advance backwards',
    $sentinelAdmin->query(
      'SELECT perf_f50_sentinel.advance_floor(10)'
    )->fetchColumn(),'floor_rollback_denied');
 $check('source cannot issue new permit in quarantine',
    $mint('f45-f50-denied-during-aa'),'external_quarantine_denied');
 $check('no third-party dispatch during quarantine',$dispatch(),0);
 break;

case 'released_prepare':
 $check('source mock recovery moved to epoch twelve',
    $source(),'released_mock:12');
 $check('third-domain floor advanced explicitly to epoch twelve',
    $minimum(),12);
 $check('floor twelve cannot be moved back to eleven',
    $sentinelAdmin->query(
      'SELECT perf_f50_sentinel.advance_floor(11)'
    )->fetchColumn(),'floor_rollback_denied');
 $check('reader cannot UPDATE sentinel state',
    (static function()use($sentinel):bool{
      try{$sentinel->exec(
       'UPDATE perf_f50_sentinel.floor SET minimum_epoch=1'
      );return false;}catch(PDOException $e){
       return $e->getCode()==='42501';
      }
    })(),true);
 $check('reader cannot SELECT private nonce ledger',
    (static function()use($sentinel):bool{
      try{$sentinel->query(
       'SELECT * FROM perf_f50_sentinel.seen'
      );return false;}catch(PDOException $e){
       return $e->getCode()==='42501';
      }
    })(),true);
 $token=$mint('f45-f50-new-epoch-aaaa');
 $check('source can mint only new epoch twelve token',
    str_contains($token,'|12|'),true);
 $v=$read();$v['fresh']=$token;$save($v);
 $check('fresh token persists across PHP CI phases',
    array_key_exists('fresh',$read()),true);
 $check('third-domain nonce ledger still empty before observation',$seen(),0);
 break;

case 'released_used':
 $check('both domains agree on epoch twelve',
    [$source(),$minimum()],['released_mock:12',12]);
 $check('fresh token admitted through both authorities',
    $guard->observe($read()['fresh']),
    $expect('f45-f50-new-epoch-aaaa',12));
 $check('third-domain nonce is recorded exactly once',$seen(),1);
 $check('same token denied by third-domain nonce ledger',
    $guard->observe($read()['fresh']),'sentinel_nonce_replay_denied');
 $check('new permission does not activate real dispatch',$dispatch(),0);
 break;

case 'restored_receipts':
 $check('source still released epoch twelve',$source(),'released_mock:12');
 $check('sentinel floor unaffected by restoring external receipts',$minimum(),12);
 $check('sentinel still remembers the already used nonce',$seen(),1);
 $check('after external receipts restore, facade still denies SAME token',
    $guard->observe($read()['fresh']),'sentinel_nonce_replay_denied');
 $check('EXPECTED BYPASS: direct F47 SQL still admits restored nonce',
    $direct($read()['fresh']),
    'observed_external_mock:f45-f50-new-epoch-aaaa:12');
 $check('third-domain nonce count remains unchanged by direct bypass',$seen(),1);
 $check('senitel denies invalid future epoch request',
    $sentinel->query(
     "SELECT perf_f50_sentinel.check_and_reserve(13,'f45-f50-future-aaaaa')"
    )->fetchColumn(),'unreconciled_future_epoch_denied');
 $sentinelAdmin->exec(
   'REVOKE EXECUTE ON FUNCTION '
   .'perf_f50_sentinel.check_and_reserve(bigint,text) FROM perf_f50_observer'
 );
 $next=$mint('f45-f50-permission-denied-aaaa');
 $check('sentinel permission loss fails closed',
    $guard->observe($next),'sentinel_unavailable');
 $sentinelAdmin->exec(
   'GRANT EXECUTE ON FUNCTION '
   .'perf_f50_sentinel.check_and_reserve(bigint,text) TO perf_f50_observer'
 );
 $check('sentinel state still high-watermark twelve after retry',$minimum(),12);
 $check('no intent has moved outside synthetic pending state',
    (int)$portalAdmin->query(
      "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
 $check('outbox permanently dispatch disabled',$dispatch(),0);
 putenv('PERF_F50_RESTORE_SENTINEL_LAB=0');
 $check('feature flag disables sentinel admission',
    $guard->observe($next),'restore_sentinel_lab_disabled');
 putenv('PERF_F50_RESTORE_SENTINEL_LAB=1');
 putenv('APP_ENV=production');
 $check('production runtime cannot use synthetic admission',
    $guard->observe($next),'restore_sentinel_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo 'PASS F50 phase='.$phase.' assertions='.$n.' third PostgreSQL mock restore sentinel'.PHP_EOL;
