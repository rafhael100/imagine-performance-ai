<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticSqlQuarantineGateway.php';
require_once __DIR__.'/SyntheticCrossAuthorityAdmission.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticSqlQuarantineGateway;
use Imagine\Protocol\Lab\SyntheticCrossAuthorityAdmission;

$phase=getenv('PERF_F44_PHASE');
$file=getenv('F44_CROSS_TOKEN_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F43_ATOMIC_SQL_GUARD_LAB')!=='1'
 ||getenv('PERF_F44_CROSS_AUTHORITY_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($file)||$file===''
 ||!in_array($phase,['mismatch','aligned','external_quarantine','both_aligned'],true)) {
 throw new RuntimeException('F44 only four phases on disposable CI');
}
$pdo=static fn(string $db,int $port,string $role,string $pass):PDO =>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $role,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$portalAdmin=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$externalAdmin=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$remote=$pdo('perf_f31_nonce_ci',5433,'perf_f41_guard_reader','ci-f41-guard-only');
$local=new SyntheticSqlQuarantineGateway(
 $pdo('portal_perf_auth_lab',5432,'perf_f43_sql_consumer','ci-f43-sql-only'));
$cross=new SyntheticCrossAuthorityAdmission($remote,$local);
$wrong=new SyntheticCrossAuthorityAdmission($externalAdmin,$local);
$writer=new SyntheticIdempotentIntentGateway(
 $pdo('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only'));
$owner='worker-f38-a';
$t=1800000900;
$digest=hash('sha256','f44-cross-authority-drift-test');
$n=0;
$check=static function(string $msg,mixed $got,mixed $want)use(&$n):void {
 if($got!==$want)throw new RuntimeException(
    'FAIL F44 '.$msg.' got='.var_export($got,true)
    .' expected='.var_export($want,true));
 echo 'PASS F44 '.++$n.' '.$msg.PHP_EOL;
};
$parse=static function(string $text):array {
 if(!preg_match('/^claimed_cross_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',$text,$m))
  throw new RuntimeException('Invalid cross-authority claim '.$text);
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$load=static function()use($file):array {
 $raw=@file_get_contents($file);
 $a=is_string($raw)?json_decode($raw,true):null;
 if(!is_array($a)||count($a)!==3)throw new RuntimeException('F44 token file not found');
 return $a;
};
$save=static function(array $a)use($file):void {
 if(file_put_contents($file,json_encode($a,JSON_THROW_ON_ERROR))===false)
  throw new RuntimeException('F44 token artifact not created');
};
$leases=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f38_fenced.leases'
)->fetchColumn();
$dispatch=static fn():int=>(int)$portalAdmin->query(
 'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
$external=static fn():string=>(string)$externalAdmin->query(
 'SELECT perf_f41_recovery.read_state()'
)->fetchColumn();

switch($phase){
case 'mismatch':
 $check('F43 local remains released_mock epoch 5',$local->state(),'released_mock:5');
 $check('F41 external remains released_mock epoch 4',$external(),'released_mock:4');
 $before=$leases();
 $check('cross-authority claim denies epoch mismatch',
   $cross->claim($owner,$t),'authority_epoch_mismatch_denied');
 $check('cross-authority ACK denies epoch mismatch',
   $cross->acknowledge(1,$owner,1,5,$t),'authority_epoch_mismatch_denied');
 $check('cross-check denial has no lease side effect',$leases(),$before);
 $check('wrong external role cannot check sources',
   $wrong->status(),'wrong_external_role');
 $check('new read-only demonstration for local bypass',
   $writer->reserve('f44-mismatched-direct',$digest),'reserved_synthetic');
 // Expected negative: local role still able to call protected LOCAL SQL directly,
 // which knows nothing about the mismatched external epoch.
 $unprotected=$local->claim($owner,$t);
 $check('EXPECTED BYPASS local SQL accepts claim despite external mismatch',
   str_starts_with($unprotected,'claimed_sql_mock:'),true);
 $check('external epoch still old after local direct bypass',
   $external(),'released_mock:4');
 $check('no event dispatched during mismatch',$dispatch(),0);
 break;
case 'aligned':
 $check('CI operator aligned external epoch with local epoch 5',
    $external(),'released_mock:5');
 $check('local SQL epoch still five',$local->state(),'released_mock:5');
 $check('cross-authority now reports matching release',
    $cross->status(),'released_mock:5');
 $check('create new synthetic read intent for both authorities',
    $writer->reserve('f44-aligned-new',$digest),'reserved_synthetic');
 $a=$parse($cross->claim($owner,$t));
 $check('cross-authority claim binds epoch 5',$a[2],5);
 $check('cross-authority claim yields valid outbox id',$a[0]>0,true);
 $check('cross-authority claim yields valid generation',$a[1]>0,true);
 $check('wrong epoch rejected by local SQL',
    $cross->acknowledge($a[0],$owner,$a[1],4,$t),
    'stale_sql_recovery_epoch_denied');
 $save($a);
 $check('token saved across PHP workers',is_file($file),true);
 $check('outbox remains disabled',$dispatch(),0);
 break;
case 'external_quarantine':
 $check('F41 source has entered quarantine epoch 6',
    $external(),'quarantined:6');
 $check('local SQL is deliberately still released epoch 5',
    $local->state(),'released_mock:5');
 $check('cross-authority claim rejects quarantined external',
    $cross->claim($owner,$t+1),'external_quarantine_denied');
 $a=$load();
 $check('prior token saved with epoch five',$a[2],5);
 $check('cross-authority ACK rejects external quarantine',
    $cross->acknowledge($a[0],$owner,$a[1],5,$t+1),
    'external_quarantine_denied');
 $check('EXPECTED BYPASS direct local SQL ACK ignores external quarantine',
    $local->acknowledge($a[0],$owner,$a[1],5,$t+1),
    'acknowledged_sql_mock');
 $check('external quarantine remains active after direct local bypass',
    $external(),'quarantined:6');
 $check('still no external actions dispatched',$dispatch(),0);
 break;
case 'both_aligned':
 $check('external authority released mock epoch six',
    $external(),'released_mock:6');
 $check('local SQL authority also released mock epoch six',
    $local->state(),'released_mock:6');
 $check('both match before attempting guarded work',
    $cross->status(),'released_mock:6');
 $old=$load();
 $check('old epoch five token denied after both advanced to six',
    $cross->acknowledge($old[0],$owner,$old[1],5,$t+1),
    'stale_sql_recovery_epoch_denied');
 $check('new read-only intent after synchronized mock recovery',
    $writer->reserve('f44-aligned-epoch6',$digest),'reserved_synthetic');
 $new=$parse($cross->claim($owner,$t+31));
 $check('new cross-authority token binds epoch six',$new[2],6);
 $check('new cross-authority token has positive id',$new[0]>0,true);
 $check('valid mock ACK through cross admission',
    $cross->acknowledge($new[0],$owner,$new[1],6,$t+31),
    'acknowledged_cross_mock');
 $check('duplicate mock ACK denied',
    $cross->acknowledge($new[0],$owner,$new[1],6,$t+31),
    'fenced_lease_denied');
 $externalAdmin->exec(
  'REVOKE EXECUTE ON FUNCTION perf_f41_recovery.read_state() FROM perf_f41_guard_reader');
 $check('lost external authority closes cross claim',
    $cross->claim($owner,$t+31),'external_authority_unavailable');
 $check('lost external authority closes ACK',
    $cross->acknowledge($new[0],$owner,$new[1],6,$t+31),
    'external_authority_unavailable');
 $externalAdmin->exec(
  'GRANT EXECUTE ON FUNCTION perf_f41_recovery.read_state() TO perf_f41_guard_reader');
 $check('external role restored, status matches',
    $cross->status(),'released_mock:6');
 $check('no dispatch flag was enabled',$dispatch(),0);
 $check('all intents remain pending synthetic only',
    (int)$portalAdmin->query(
    "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
 putenv('PERF_F44_CROSS_AUTHORITY_LAB=0');
 $check('disabled feature switch closes cross admission',
    $cross->claim($owner,$t+31),'cross_authority_lab_disabled');
 putenv('PERF_F44_CROSS_AUTHORITY_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment closes synthetic admission',
    $cross->claim($owner,$t+31),'cross_authority_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F44 phase={$phase} assertions={$n} cross SQL source demo only".PHP_EOL;
