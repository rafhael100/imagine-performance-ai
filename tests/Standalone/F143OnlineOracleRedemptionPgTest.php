<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F143LiveOracleRegistration.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F143LiveOracleRegistration;

if(getenv('APP_ENV')!=='testing'||getenv('PERF_F143_LAB')!=='1'
 ||getenv('PERF_F141_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f132_ci'
 ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
 ||getenv('ORACLE_DB')!=='perf_f143_oracle_ci') {
 throw new RuntimeException('F143 requires four disposable CI PostgreSQL databases');
}
function f143db(int $port,string $db,string $role,string $secret):PDO {
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$role,$secret,[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
   PDO::ATTR_EMULATE_PREPARES=>false,
   PDO::ATTR_TIMEOUT=>4,
 ]);
}
$root=(string)getenv('RUNNER_TEMP').'/f143-authority';
if(!is_dir($root)&&!mkdir($root,0700,true))throw new RuntimeException('F143 no runner directory');
$keyPath=$root.'/ci-ed25519.json';
$fixture=$root.'/b-oracle-attestation.json';
$phase=$argv[1]??'';
if($phase==='seed'){
 $keyPair=sodium_crypto_sign_keypair();
 file_put_contents($keyPath,json_encode([
   'secret'=>base64_encode(sodium_crypto_sign_secretkey($keyPair)),
   'public'=>base64_encode(sodium_crypto_sign_publickey($keyPair)),
 ],JSON_THROW_ON_ERROR));
 chmod($keyPath,0600);
}
$keys=json_decode((string)file_get_contents($keyPath),true,512,JSON_THROW_ON_ERROR);
$secret=base64_decode((string)$keys['secret'],true);
$public=base64_decode((string)$keys['public'],true);
$key=hex2bin((string)getenv('PERF_F142_CI_KEY'));
if($secret===false||$public===false||$key===false||strlen($key)!==32)
 throw new RuntimeException('F143 synthetic key setup failed');

$pa=f143db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wa=f143db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aa=f143db(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$oa=f143db(5435,'perf_f143_oracle_ci','postgres',(string)getenv('ORACLE_PASSWORD'));
$p=f143db(5432,'perf_f132_ci','perf_f143_primary_bridge',(string)getenv('PERF_F143_PRIMARY_PASSWORD'));
$w=f143db(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$a=f143db(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$o=f143db(5435,'perf_f143_oracle_ci','perf_f143_oracle_issuer',(string)getenv('PERF_F143_ISSUER_PASSWORD'));
$old=f143db(5432,'perf_f132_ci','perf_f142_primary_bridge',(string)getenv('PERF_F142_PRIMARY_PASSWORD'));
$journal=new F141SignedJournalAuthority($root.'/separate.jsonl',$secret,$public);
$sut=new F143LiveOracleRegistration($p,$w,$a,$o,$journal,$key);
$fp=static fn(string $s):string=>hash('sha256','F143-ci-'.$s);
$pc=static fn():int=>(int)$pa->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$oc=static fn():int=>(int)$oa->query('SELECT count(*) FROM perf_f143_oracle.permits')->fetchColumn();
$or=static fn():int=>(int)$oa->query('SELECT count(*) FROM perf_f143_oracle.permits WHERE redeemed')->fetchColumn();
$pe=static fn():int=>(int)$pa->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wa->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ae=static fn():int=>(int)$aa->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$oe=static fn():int=>(int)$oa->query('SELECT generation FROM perf_f143_oracle.floor')->fetchColumn();
$n=0;
$check=static function(bool $valid,string $message)use(&$n):void{
 ++$n; if(!$valid)throw new RuntimeException('F143 FAIL: '.$message);
};
$denied=static function(callable $fn):bool{
 try{$fn();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$issueWitness=static function(string $op,string $tenant,string $finger,int $epoch)use($w):array{
 $q=$w->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)');
 $q->execute([$op,$tenant,$finger,$epoch]);
 $r=$q->fetch(PDO::FETCH_ASSOC);
 if(!is_array($r))throw new RuntimeException('F143 witness denied unexpected synthetic case');
 return $r;
};
$write=static function(string $op,string $tenant,string $finger,int $epoch,array $witness,
 array $attestation)use($p):bool {
 $q=$p->prepare(
  'SELECT CASE WHEN perf_f143_primary.redeem_online_oracle(?,?,?,?,?,?,?,?,?,?) THEN 1 ELSE 0 END'
 );
 $q->execute([
  $op,$tenant,$finger,$epoch,(int)$witness['issued_at'],(string)$witness['signature'],
  (int)$attestation['journal_issued_at'],(int)$attestation['journal_sequence'],
  (string)$attestation['journal_head'],(string)$attestation['journal_mac'],
 ]);
 return (int)$q->fetchColumn()===1;
};
if($phase==='seed'){
 $journal->bootstrap();
 $check($pe()===8&&$we()===8&&$ae()===8&&$oe()===8,
    'all four database floors start generation8');
 $check($pc()===0&&$wc()===0&&$oc()===0,'all SQL journals initially empty');
 $check($denied(static fn()=>$old->query(
  "SELECT perf_f142_primary.record_with_journal_attestation('operation_1430','tenant_demo_A','"
  .$fp('fake')."',8,1,'".str_repeat('a',64)."',1,2,'".str_repeat('b',64)."','".str_repeat('0',64)."')"
 )),'old F142 SQL credential cannot bypass live oracle');
 $check($denied(static fn()=>$p->query('SELECT * FROM perf_f143_primary.remote_permits')),
   'F143 SQL app role cannot read raw remote oracle table');
 $check($denied(static fn()=>$o->query('SELECT * FROM perf_f143_oracle.permits')),
   'oracle issuer cannot read or modify permits directly');
 $macUnknown=$o->prepare('SELECT perf_f143_oracle.issue_if_current(?,?,?,?,?,?,?,?)');
 $macUnknown->execute(['operation_1430','tenant_demo_A',$fp('bogus'),8,
   time(),2,str_repeat('a',64),str_repeat('0',64)]);
 $check(!in_array($macUnknown->fetchColumn(),[true,'t','1',1],true),
   'oracle rejects unverified MAC even for syntactically valid claims');
 $check($oc()===0,'forged oracle issuance writes no permit');
 $check($sut->register('bad','tenant_demo_A',$fp('bad'))['status']==='invalid_inputs',
   'invalid user input rejected');
 $aResult=$sut->register('operation_1431','tenant_demo_A',$fp('A'));
 $check($aResult['status']==='registered_lab'&&$aResult['registered'],
   'A succeeds with journal and online oracle consumption');
 $check(!$aResult['action_authorized'],'no Ads or billing authorization');
 $check($pc()===1&&$wc()===1&&$rc()===1&&$oc()===1&&$or()===1,
   'A once in primary/witness/oracle; remote permit redeemed');
 $check($sut->register('operation_1431','tenant_demo_A',$fp('A'))['status']
  ==='journal_replay_or_epoch_denied','A cannot be retried through gateway');
}elseif($phase==='after-snapshot'){
 $check($pc()===1&&$wc()===1&&$oc()===1,'snapshot taken at generation8');
 $check($journal->advance(9),'file journal advances to9');
 $oa->query('SELECT perf_f143_oracle.advance_floor(9)')->fetchColumn();
 $check($oe()===9&&$pe()===8,'online oracle floor already advanced');
 $check($sut->register('operation_1432','tenant_demo_B',$fp('B'))['status']
  ==='sql_epoch_mismatch','oracle prevents stale epoch without spending journal');
 $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($pe()===9&&$we()===9&&$ae()===9&&$oe()===9,'four DB floors align to9');
 $bResult=$sut->register('operation_1432','tenant_demo_B',$fp('B'));
 $check($bResult['status']==='registered_lab'&&$bResult['registered'],
   'B admitted with online redemption');
 $check($pc()===2&&$wc()===2&&$rc()===2&&$oc()===2&&$or()===2,
   'B recorded once and oracle permit consumed');
 $q=$oa->prepare(
  'SELECT journal_issued_at,journal_sequence,journal_head,journal_mac
   FROM perf_f143_oracle.permits WHERE operation_id=?'
 );
 $q->execute(['operation_1432']);$captured=$q->fetch(PDO::FETCH_ASSOC);
 $check(is_array($captured)&&strlen((string)$captured['journal_mac'])===64,
  'capture exact legitimate previously consumed oracle attestation');
 file_put_contents($fixture,json_encode($captured,JSON_THROW_ON_ERROR));
 $check($sut->register('operation_1432','tenant_demo_B',$fp('B'))['status']
  ==='journal_replay_or_epoch_denied','file signer denies B retry');
}elseif($phase==='after-triple-restore'){
 $check($pe()===8&&$we()===8&&$ae()===8,'three DBs restored to old epoch8');
 $check($oe()===9&&$journal->inspect()['epoch']===9,
  'unrestored oracle and signed file retain epoch9');
 $check($pc()===1&&$wc()===1&&$oc()===2&&$or()===2,
  'oracle retains redeemed B while primary/witness lost it');
 $check($sut->register('operation_1432','tenant_demo_B',$fp('B'))['status']
   ==='sql_epoch_mismatch','gateway denies B while three DBs stale');
 $oa->exec('REVOKE SELECT ON perf_f143_oracle.floor FROM perf_f143_oracle_issuer');
 $check($sut->register('operation_1434','tenant_demo_A',$fp('D'))['status']
   ==='online_oracle_or_database_unavailable','unavailable oracle fails closed');
 $oa->exec('GRANT SELECT ON perf_f143_oracle.floor TO perf_f143_oracle_issuer');
 $check($oc()===2&&$pc()===1,'oracle outage does not create new permits');

 // Synthetic manual alignment of three rolled-back databases, NOT recovery.
 $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($pe()===9&&$we()===9&&$ae()===9&&$oe()===9,
  'all DB generations match9 after manual CI lab realignment');
 $check($sut->register('operation_1432','tenant_demo_B',$fp('B'))['status']
  ==='journal_replay_or_epoch_denied','gateway still denies B due to signed journal history');

 // F142 negative test previously showed that a captured valid MAC plus
 // freshly minted witness nonce could replay B. F143 SQL now additionally
 // requires a still-unredeemed row in the nonrestored fourth oracle.
 $freshWitness=$issueWitness('operation_1432','tenant_demo_B',$fp('B'),9);
 $att=json_decode((string)file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
 $check($wc()===2&&$rc()===1,'reissued witness nonce for B after triple rollback');
 $check(!$write('operation_1432','tenant_demo_B',$fp('B'),9,$freshWitness,$att),
  'F143 SQL denies exact captured valid B MAC because oracle B was redeemed');
 $check($pc()===1&&$or()===2&&$oc()===2,'replay did not write primary or oracle');
 $forgery=[
  'journal_issued_at'=>time(),'journal_sequence'=>4,
  'journal_head'=>str_repeat('b',64),'journal_mac'=>str_repeat('0',64),
 ];
 $check(!$write('operation_1435','tenant_demo_A',$fp('forged'),9,$freshWitness,$forgery),
  'direct F143 call with no issued oracle row is denied');
 $check($pc()===1&&$or()===2,'forged or absent permits cannot change primary');
 $check($journal->inspect()['epoch']===9,'external file still remembers generation9');
}else throw new RuntimeException('F143 phase seed|after-snapshot|after-triple-restore');

echo "F143 {$phase}: {$n} PASS / 0 FAIL — four-DB CI lab / NO-GO\n";
