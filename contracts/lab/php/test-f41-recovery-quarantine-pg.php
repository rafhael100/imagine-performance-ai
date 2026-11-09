<?php
declare(strict_types=1);

/**
 * F1-41: externally governed quarantine is laboratory-only and defaults
 * CLOSED after restore; direct F38 still bypasses this facade. Four CI phases.
 */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticFencedLeaseGateway.php';
require_once __DIR__.'/SyntheticRecoveryQuarantineGate.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticFencedLeaseGateway;
use Imagine\Protocol\Lab\SyntheticRecoveryQuarantineGate;

$phase=getenv('PERF_F41_PHASE');
$tokenFile=getenv('F41_LEASE_TOKEN_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
 ||getenv('PERF_F41_QUARANTINE_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($tokenFile)||$tokenFile===''
 ||!in_array($phase,['initial','released','quarantined_again','re_released'],true)){
 throw new RuntimeException('F41 only runs as four phases in disposable CI');
}
$connect=static fn(string $db,int $port,string $role,string $pw):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $role,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$portalAdmin=$connect('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$externalAdmin=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$writer=new SyntheticIdempotentIntentGateway(
 $connect('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only'));
$local=new SyntheticFencedLeaseGateway(
 $connect('portal_perf_auth_lab',5432,'perf_f38_fenced_worker','ci-f38-fenced-only'));
$reader=$connect('perf_f31_nonce_ci',5433,'perf_f41_guard_reader','ci-f41-guard-only');
$guard=new SyntheticRecoveryQuarantineGate($reader,$local);
$wrong=new SyntheticRecoveryQuarantineGate($externalAdmin,$local);
$now=1800000000;
$owner='worker-f38-q';
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
 if($got!==$expected)throw new RuntimeException(
  'FAIL F41 '.$label.' got='.var_export($got,true)
  .' expected='.var_export($expected,true)
 );
 echo 'PASS F41 '.++$n.' '.$label.PHP_EOL;
};
$parse=static function(string $token):array {
 if(!preg_match('/^claimed_guarded_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',$token,$m)){
  throw new RuntimeException('Invalid guarded token '.$token);
 }
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$leaseCount=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f38_fenced.leases'
)->fetchColumn();
$dispatchCount=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$manifest=hash('sha256','f41-demo-recovery-quarantine');
$saved=static function()use($tokenFile):array{
 $contents=@file_get_contents($tokenFile);
 $j=is_string($contents)?json_decode($contents,true):null;
 if(!is_array($j)||count($j)!==3)return [];
 return $j;
};

switch($phase){
case 'initial':
 $check('default state always quarantine after F40',
    $guard->state(),'quarantined:1');
 $before=$leaseCount();
 $check('guarded claim denied in default quarantine',
    $guard->claim($owner,$now),'quarantine_denied');
 $check('guarded ACK denied even for invented token',
    $guard->acknowledge(1,$owner,1,1,$now),'quarantine_denied');
 $check('quarantine denial never creates lease',$leaseCount(),$before);
 $check('quarantine denial never enables outbox',$dispatchCount(),0);
 $check('wrong database role cannot access guard',
    $wrong->state(),'wrong_recovery_authority');
 $noSelect=false;
 try{$reader->query('SELECT * FROM perf_f41_recovery.guard_state');}
 catch(PDOException $e){$noSelect=$e->getCode()==='42501';}
 $check('reader cannot SELECT private quarantine state',$noSelect,true);
 $noUpdate=false;
 try{$reader->exec("UPDATE perf_f41_recovery.guard_state SET status='released_mock'");}
 catch(PDOException $e){$noUpdate=$e->getCode()==='42501';}
 $check('reader cannot release quarantine via SQL',$noUpdate,true);
 $denied=false;
 try{
  $externalAdmin->exec("UPDATE perf_f41_recovery.guard_state SET status='released_mock',approval_ref=NULL");
 }catch(PDOException $e){$denied=$e->getCode()==='23514';}
 $check('even admin release missing a CI approval reference violates SQL constraint',
    $denied,true);
 $check('bad operator release cannot change state',$guard->state(),'quarantined:1');
 // A deliberately unguarded F38 call still bypasses the external quarantine!
 $check('synthetic pending intent for bypass proof',
    $writer->reserve('f41-unguarded-bypass',$manifest),'reserved_synthetic');
 $legacy=$local->claim($owner,$now);
 $check('EXPECTED BYPASS: direct F38 unguarded lease can claim during quarantine',
    str_starts_with($legacy,'claimed_mock:'),true);
 $check('external quarantine remained active despite legacy bypass',
    $guard->state(),'quarantined:1');
 putenv('APP_ENV=production');
 $check('production does not allow guarded claim',
    $guard->claim($owner,$now),'quarantine_lab_disabled');
 putenv('APP_ENV=testing');
 break;
case 'released':
 $check('admin mock release explicitly sets released status',
    $guard->state(),'released_mock:1');
 $check('new demo intent for guarded lease',
    $writer->reserve('f41-released-intent',$manifest),'reserved_synthetic');
 $token=$guard->claim($owner,$now);
 $tokens=$parse($token);
 $check('guarded lease returns current recovery epoch',$tokens[2],1);
 $check('guarded token has a positive outbox id',$tokens[0]>0,true);
 $check('guarded token has a positive lease generation',$tokens[1]>0,true);
 $check('epoch from future cannot acknowledge',
    $guard->acknowledge($tokens[0],$owner,$tokens[1],2,$now),
    'stale_recovery_epoch_denied');
 $check('wrong owner cannot ACK current guarded token',
    $guard->acknowledge($tokens[0],'worker-f38-other',$tokens[1],1,$now),
    'fenced_lease_denied');
 $check('no synthetic event was dispatched',$dispatchCount(),0);
 if(file_put_contents($tokenFile,json_encode($tokens,JSON_THROW_ON_ERROR))===false)
    throw new RuntimeException('CI token artifact missing');
 $check('short-lived demo token saved for next CI phase',is_file($tokenFile),true);
 break;
case 'quarantined_again':
 $check('new operator quarantine increments epoch',
    $guard->state(),'quarantined:2');
 $tokens=$saved();
 $check('prior token persisted across isolated PHP processes',
    count($tokens),3);
 $check('guarded claim immediately denied after quarantine',
    $guard->claim($owner,$now+1),'quarantine_denied');
 $check('guarded ACK immediately denied after quarantine',
    $guard->acknowledge($tokens[0],$owner,$tokens[1],$tokens[2],$now+1),
    'quarantine_denied');
 $check('read-only quarantine cannot change outbox dispatch',$dispatchCount(),0);
 $check('EXPECTED BYPASS: direct old F38 ACK ignores recovery quarantine',
    $local->acknowledge($tokens[0],$owner,$tokens[1],$now+1),
    'acknowledged_mock');
 $check('external quarantine survives direct legacy bypass',
    $guard->state(),'quarantined:2');
 break;
case 're_released':
 $check('operator mock re-release keeps incremented epoch',
    $guard->state(),'released_mock:2');
 $tokens=$saved();
 $check('lease from epoch one denied even after admin mock re-release',
    $guard->acknowledge($tokens[0],$owner,$tokens[1],$tokens[2],$now+1),
    'stale_recovery_epoch_denied');
 $check('fresh synthetic intent for new recovery epoch',
    $writer->reserve('f41-second-epoch',$manifest),'reserved_synthetic');
 $new=$parse($guard->claim($owner,$now+1));
 $check('new guarded lease uses epoch two',$new[2],2);
 $check('fresh lease is not old already acknowledged token',
    $new[0]!==$tokens[0],true);
 $check('new epoch mock ACK may complete on guarded facade',
    $guard->acknowledge($new[0],$owner,$new[1],$new[2],$now+1),
    'acknowledged_guarded_mock');
 $check('same ACK not repeated',
    $guard->acknowledge($new[0],$owner,$new[1],$new[2],$now+1),
    'fenced_lease_denied');
 // Revocation in the second SQL authority must stop every guarded call.
 $externalAdmin->exec(
   'REVOKE EXECUTE ON FUNCTION perf_f41_recovery.read_state() FROM perf_f41_guard_reader'
 );
 $check('lost guard privilege fails closed on status',
    $guard->state(),'recovery_authority_unavailable');
 $check('lost guard privilege fails closed on claim',
    $guard->claim($owner,$now+1),'recovery_authority_unavailable');
 $check('lost guard privilege fails closed on ACK',
    $guard->acknowledge($new[0],$owner,$new[1],$new[2],$now+1),
    'recovery_authority_unavailable');
 $externalAdmin->exec(
   'GRANT EXECUTE ON FUNCTION perf_f41_recovery.read_state() TO perf_f41_guard_reader'
 );
 $check('SQL authority restored to released mock state',
    $guard->state(),'released_mock:2');
 $check('all outbox events remain disabled',$dispatchCount(),0);
 putenv('PERF_F41_QUARANTINE_LAB=0');
 $check('disabled lab flag closes admission',
    $guard->claim($owner,$now+1),'quarantine_lab_disabled');
 putenv('PERF_F41_QUARANTINE_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment always denied',
    $guard->state(),'quarantine_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F41 phase={$phase} assertions={$n} mock quarantine only".PHP_EOL;
