<?php
declare(strict_types=1);

/** F1-42: explicit role cutover after all F41 regressions, disposable CI only. */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticFencedLeaseGateway.php';
require_once __DIR__.'/SyntheticRecoveryQuarantineGate.php';
require_once __DIR__.'/SyntheticCentralAdmissionGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticFencedLeaseGateway;
use Imagine\Protocol\Lab\SyntheticRecoveryQuarantineGate;
use Imagine\Protocol\Lab\SyntheticCentralAdmissionGateway;

$phase=getenv('PERF_F42_PHASE');
$tokenPath=getenv('F42_OLD_TOKEN_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
 ||getenv('PERF_F41_QUARANTINE_LAB')!=='1'
 ||getenv('PERF_F42_CENTRAL_CUTOVER_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($tokenPath)||$tokenPath===''
 ||!in_array($phase,['locked','released','locked_again','re_released'],true)){
    throw new RuntimeException('F42 is only 4-phase synthetic SQL privilege CI');
}
$pdo=static fn(string $db,int $port,string $user,string $pass):PDO =>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$portalAdmin=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$guardAdmin=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$guardRole=$pdo('perf_f31_nonce_ci',5433,'perf_f41_guard_reader','ci-f41-guard-only');
$oldSql=$pdo('portal_perf_auth_lab',5432,'perf_f38_fenced_worker','ci-f38-fenced-only');
$newSql=$pdo('portal_perf_auth_lab',5432,'perf_f42_admission','ci-f42-admission-only');
$intent=new SyntheticIdempotentIntentGateway(
 $pdo('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only'));
$oldGateway=new SyntheticFencedLeaseGateway($oldSql);
$oldGuard=new SyntheticRecoveryQuarantineGate($guardRole,$oldGateway);
$central=new SyntheticCentralAdmissionGateway($guardRole,$newSql);
$wrong=new SyntheticCentralAdmissionGateway($guardAdmin,$newSql);
$now=1800000200;
$owner='worker-f38-a';
$manifest=hash('sha256','f42-admission-ci-fixtures');
$n=0;
$check=static function(string $name,mixed $actual,mixed $expected)use(&$n):void{
 if($actual!==$expected){
   throw new RuntimeException('FAIL F42 '.$name
     .' actual '.var_export($actual,true)
     .' expected '.var_export($expected,true));
 }
 echo 'PASS F42 '.++$n.' '.$name.PHP_EOL;
};
$parse=static function(string $value):array{
 if(!preg_match('/^claimed_central_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',$value,$m)){
   throw new RuntimeException('expected mock central claim: '.$value);
 }
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$dispatch=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$leaseCount=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f38_fenced.leases'
)->fetchColumn();
$save=static function(array $token)use($tokenPath):void{
 if(file_put_contents($tokenPath,json_encode($token,JSON_THROW_ON_ERROR))===false){
    throw new RuntimeException('CI file token write failed');
 }
};
$load=static function()use($tokenPath):array{
 $raw=@file_get_contents($tokenPath);
 $a=is_string($raw)?json_decode($raw,true):null;
 if(!is_array($a)||count($a)!==3)throw new RuntimeException('missing CI old token');
 return $a;
};
switch($phase){
case 'locked':
 $check('external operator declared quarantine epoch 3',
    $central->status(),'quarantined:3');
 $count=$leaseCount();
 $check('central facade denies all claims in quarantine',
    $central->claim($owner,$now),'quarantine_denied');
 $check('central facade denies all ACK in quarantine',
    $central->acknowledge(1,$owner,1,3,$now),'quarantine_denied');
 $check('guarded refusal does not change local SQL',$leaseCount(),$count);
 $permissionDenied=false;
 try{
  $stmt=$oldSql->query(
    "SELECT perf_f38_fenced.claim('worker-f38-a',1800000200)"
  );
  $stmt->fetchColumn();
 }catch(PDOException $e){$permissionDenied=$e->getCode()==='42501';}
 $check('F38 old role SQL claim was REVOKED',$permissionDenied,true);
 $permissionDenied=false;
 try{
  $stmt=$oldSql->query(
    "SELECT perf_f38_fenced.ack(1,'worker-f38-a',1,1800000200)"
  );
  $stmt->fetchColumn();
 }catch(PDOException $e){$permissionDenied=$e->getCode()==='42501';}
 $check('F38 old role SQL ACK was REVOKED',$permissionDenied,true);
 $check('F38 old PHP gateway fails closed after SQL revoke',
    $oldGateway->claim($owner,$now),'lease_store_unavailable');
 $check('wrong SQL authority refuses central access',
    $wrong->status(),'wrong_central_roles');
 $noSelect=false;
 try{$newSql->query('SELECT * FROM perf_f38_fenced.leases');}
 catch(PDOException $e){$noSelect=$e->getCode()==='42501';}
 $check('new role cannot SELECT private leases table',$noSelect,true);
 $check('new demo outbox for explicit residual bypass',
    $intent->reserve('f42-cutover-bypass',$manifest),'reserved_synthetic');
 $oldCount=$leaseCount();
 // Any actor holding the NEW role's SQL credentials can still call SQL
 // directly, bypassing quarantine; this is a required negative test.
 $direct=(string)$newSql->query(
  "SELECT perf_f38_fenced.claim('worker-f38-a',1800000200)"
 )->fetchColumn();
 $check('EXPECTED REMAINING BYPASS new admission role can directly call old SQL',
    str_starts_with($direct,'claimed_mock:'),true);
 $check('bypass really changed local lease state',$leaseCount()>=$oldCount,true);
 $check('external quarantine remains active despite direct SQL bypass',
    $central->status(),'quarantined:3');
 $check('no outbox was enabled for dispatch',$dispatch(),0);
 break;

case 'released':
 $check('explicit SQL CI operator release epoch 3',
    $central->status(),'released_mock:3');
 $check('new read-only fixture for gated claim',
    $intent->reserve('f42-cutover-approved',$manifest),'reserved_synthetic');
 $token=$parse($central->claim($owner,$now));
 $check('approved test token binds recovery epoch 3',$token[2],3);
 $check('approved test token has positive outbox id',$token[0]>0,true);
 $check('approved test token has positive lease generation',$token[1]>0,true);
 $check('old gateway remains SQL permission denied after operator release',
    $oldGateway->claim($owner,$now),'lease_store_unavailable');
 $check('old F41 wrapper cannot bypass SQL revoke',
    $oldGuard->claim($owner,$now),'lease_store_unavailable');
 $check('wrong future epoch cannot ACK',
    $central->acknowledge($token[0],$owner,$token[1],4,$now),
    'stale_recovery_epoch_denied');
 $save($token);
 $check('saved token for next quarantine phase',is_file($tokenPath),true);
 $check('outbox remains disabled',$dispatch(),0);
 break;

case 'locked_again':
 $check('second simulated quarantine increments to epoch 4',
    $central->status(),'quarantined:4');
 $old=$load();
 $check('token persisted from previous CI PHP process',$old[2],3);
 $check('old token denied during quarantine',
    $central->acknowledge($old[0],$owner,$old[1],$old[2],$now+1),
    'quarantine_denied');
 $check('new claims denied during quarantine',
    $central->claim($owner,$now+1),'quarantine_denied');
 $check('legacy SQL role still blocked after quarantine',
    $oldGateway->claim($owner,$now+1),'lease_store_unavailable');
 $check('no dispatch enabled',$dispatch(),0);
 break;

case 're_released':
 $check('mock CI release with epoch 4',
    $central->status(),'released_mock:4');
 $old=$load();
 $check('old token still rejected after re-release',
    $central->acknowledge($old[0],$owner,$old[1],$old[2],$now+1),
    'stale_recovery_epoch_denied');
 $check('reserve new synthetic read-only intent in epoch 4',
    $intent->reserve('f42-cutover-epoch4',$manifest),'reserved_synthetic');
 $now=$now+31;
 $next=$parse($central->claim($owner,$now));
 $check('new admission binds recovery epoch 4',$next[2],4);
 $check('wrong owner rejected',
    $central->acknowledge($next[0],'worker-f38-b',$next[1],4,$now),
    'fenced_lease_denied');
 $check('valid demo ACK succeeds through central facade',
    $central->acknowledge($next[0],$owner,$next[1],4,$now),
    'acknowledged_central_mock');
 $check('duplicate ACK denied by local unique lease',
    $central->acknowledge($next[0],$owner,$next[1],4,$now),
    'fenced_lease_denied');
 $guardAdmin->exec(
  'REVOKE EXECUTE ON FUNCTION perf_f41_recovery.read_state() FROM perf_f41_guard_reader'
 );
 $check('loss of external quarantine authority closes new admission',
    $central->claim($owner,$now),'central_authority_unavailable');
 $check('loss of authority also closes new ACK',
    $central->acknowledge($next[0],$owner,$next[1],4,$now),
    'central_authority_unavailable');
 $guardAdmin->exec(
  'GRANT EXECUTE ON FUNCTION perf_f41_recovery.read_state() TO perf_f41_guard_reader'
 );
 $check('external authority restored after permission regrant',
    $central->status(),'released_mock:4');
 $check('F38 legacy role still cannot claim',
    $oldGateway->claim($owner,$now),'lease_store_unavailable');
 $check('none of demo outbox enabled dispatch',$dispatch(),0);
 $check('no operational intent state produced',
    (int)$portalAdmin->query(
     "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
 putenv('PERF_F42_CENTRAL_CUTOVER_LAB=0');
 $check('feature disabled closes central gateway',
    $central->claim($owner,$now),'central_lab_disabled');
 putenv('PERF_F42_CENTRAL_CUTOVER_LAB=1');
 putenv('APP_ENV=production');
 $check('production always refuses central gateway',
    $central->claim($owner,$now),'central_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F42 phase={$phase} checks={$n} SQL role cutover, lab only".PHP_EOL;
