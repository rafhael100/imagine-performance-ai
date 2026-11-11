<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F143LiveOracleRegistration.php';
require dirname(__DIR__,2).'/src/Lab/F145IndependentDispatchBoundary.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F143LiveOracleRegistration;
use Imagine\Performance\Lab\F145IndependentDispatchBoundary;

if(getenv('APP_ENV')!=='testing'||getenv('PERF_F145_LAB')!=='1'
 ||getenv('PERF_F143_LAB')!=='1'||getenv('PERF_F141_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f132_ci'
 ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
 ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
 ||getenv('ORACLE_DB')!=='perf_f143_oracle_ci') {
 throw new RuntimeException('F145 requires four disposable CI PostgreSQL databases');
}
function db144(int $port,string $dbname,string $role,string $password):PDO {
 return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$dbname}",$role,$password,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>4,
 ]);
}
$root=(string)getenv('RUNNER_TEMP').'/f145-authority';
if(!is_dir($root)&&!mkdir($root,0700,true))throw new RuntimeException('F145 no runner directory');
$keyPath=$root.'/ci-key.json';
$proofPath=$root.'/captured-b-proof.json';
$phase=$argv[1]??'';
if($phase==='seed'){
 if(file_exists($keyPath))throw new RuntimeException('F145 lab must seed once');
 $kp=sodium_crypto_sign_keypair();
 file_put_contents($keyPath,json_encode([
  'secret'=>base64_encode(sodium_crypto_sign_secretkey($kp)),
  'public'=>base64_encode(sodium_crypto_sign_publickey($kp)),
 ],JSON_THROW_ON_ERROR));
 chmod($keyPath,0600);
 foreach (['dispatch-key.json','issuer-key.json'] as $basename) {
   $separate=sodium_crypto_sign_keypair();
   $path=$root.'/'.$basename;
   if(file_exists($path))throw new RuntimeException('F145 distinct key already exists');
   file_put_contents($path,json_encode([
     'secret'=>base64_encode(sodium_crypto_sign_secretkey($separate)),
     'public'=>base64_encode(sodium_crypto_sign_publickey($separate)),
   ],JSON_THROW_ON_ERROR));
   chmod($path,0600);
 }
}
$keys=json_decode((string)file_get_contents($keyPath),true,512,JSON_THROW_ON_ERROR);
$secret=base64_decode((string)$keys['secret'],true);
$public=base64_decode((string)$keys['public'],true);
$key=hex2bin((string)getenv('PERF_F142_CI_KEY'));
if($secret===false||$public===false||$key===false||strlen($key)!==32)
 throw new RuntimeException('F145 CI synthetic key missing');
$pa=db144(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wa=db144(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aa=db144(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$oa=db144(5435,'perf_f143_oracle_ci','postgres',(string)getenv('ORACLE_PASSWORD'));
$p=db144(5432,'perf_f132_ci','perf_f143_primary_bridge',(string)getenv('PERF_F143_PRIMARY_PASSWORD'));
$w=db144(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$a=db144(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$o=db144(5435,'perf_f143_oracle_ci','perf_f143_oracle_issuer',(string)getenv('PERF_F143_ISSUER_PASSWORD'));
$journal=new F141SignedJournalAuthority($root.'/signed.jsonl',$secret,$public);
$bridge=new F143LiveOracleRegistration($p,$w,$a,$o,$journal,$key);
$dispatchKeys=json_decode((string)file_get_contents($root.'/dispatch-key.json'),true,512,JSON_THROW_ON_ERROR);
$issuerKeys=json_decode((string)file_get_contents($root.'/issuer-key.json'),true,512,JSON_THROW_ON_ERROR);
$dispatchSecret=base64_decode((string)$dispatchKeys['secret'],true);
$dispatchPublic=base64_decode((string)$dispatchKeys['public'],true);
$issuerSecret=base64_decode((string)$issuerKeys['secret'],true);
$issuerPublic=base64_decode((string)$issuerKeys['public'],true);
if($dispatchSecret===false||$dispatchPublic===false||$issuerSecret===false||$issuerPublic===false)
 throw new RuntimeException('F145 independent test issuer identity unavailable');
$dispatchLogPath=$root.'/terminal-dispatch.jsonl';
$checkpointPath=$root.'/terminal-high-watermark.json';
$dispatchAuthority=new F141SignedJournalAuthority($dispatchLogPath,$dispatchSecret,$dispatchPublic);
$gate=new F145IndependentDispatchBoundary($dispatchAuthority,$checkpointPath,$issuerPublic);
$labRegistered=['status'=>'registered_lab','registered'=>true,'action_authorized'=>false];
$issuerTicket=static function(string $op,string $tenant,string $fp,int $epoch,?int $issued=null)use($issuerSecret):array{
 $stamp=$issued??time();
 $data=implode('|',['F145-v1',$op,$tenant,$fp,(string)$epoch,(string)$stamp]);
 return ['issued_at'=>$stamp,
   'signature'=>base64_encode(sodium_crypto_sign_detached($data,$issuerSecret))];
};


$fp=static fn(string $s):string=>hash('sha256','F145-only-'.$s);
$pc=static fn():int=>(int)$pa->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$oc=static fn():int=>(int)$oa->query('SELECT count(*) FROM perf_f143_oracle.permits')->fetchColumn();
$orc=static fn():int=>(int)$oa->query('SELECT count(*) FROM perf_f143_oracle.permits WHERE redeemed')->fetchColumn();
$pe=static fn():int=>(int)$pa->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wa->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ae=static fn():int=>(int)$aa->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$oe=static fn():int=>(int)$oa->query('SELECT generation FROM perf_f143_oracle.floor')->fetchColumn();
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void {
 ++$n;if(!$ok)throw new RuntimeException('F145 FAILED: '.$why);
};
$oracleIssue=static function(array $captured)use($o,$fp):bool {
 $q=$o->prepare('SELECT perf_f143_oracle.issue_if_current(?,?,?,?,?,?,?,?)');
 $q->execute([
  'operation_1452','tenant_demo_B',$fp('B'),9,
  (int)$captured['journal_issued_at'],(int)$captured['journal_sequence'],
  (string)$captured['journal_head'],(string)$captured['journal_mac'],
 ]);
 return in_array($q->fetchColumn(),[true,'t','1',1],true);
};
$witnessIssue=static function()use($w,$fp):array {
 $q=$w->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)');
 $q->execute(['operation_1452','tenant_demo_B',$fp('B'),9]);
 $p=$q->fetch(PDO::FETCH_ASSOC);
 if(!is_array($p))throw new RuntimeException('F145 witness unexpectedly denied');
 return $p;
};
$directWrite=static function(array $attestation,array $witness)use($p,$fp):bool {
 $q=$p->prepare('SELECT CASE WHEN perf_f143_primary.redeem_online_oracle(?,?,?,?,?,?,?,?,?,?) THEN 1 ELSE 0 END');
 $q->execute([
  'operation_1452','tenant_demo_B',$fp('B'),9,
  (int)$witness['issued_at'],(string)$witness['signature'],
  (int)$attestation['journal_issued_at'],(int)$attestation['journal_sequence'],
  (string)$attestation['journal_head'],(string)$attestation['journal_mac'],
 ]);
 return (int)$q->fetchColumn()===1;
};
if($phase==='seed'){
 $journal->bootstrap();
 $gate->bootstrap();
 $check($pe()===8&&$we()===8&&$ae()===8&&$oe()===8,
   'all 4 authorities initial generation8');
 $check($pc()===0&&$wc()===0&&$oc()===0,'all ledgers start empty');
 $aResult=$bridge->register('operation_1451','tenant_demo_A',$fp('A'));
 $check($aResult['status']==='registered_lab'&&$aResult['registered'],
   'A registered in four-DB path');
 $check(!$aResult['action_authorized'],'no real Ads or billing permission');
 $check($pc()===1&&$wc()===1&&$oc()===1&&$orc()===1,
   'A durable exactly once in each ledger');
 $ticketA=$issuerTicket('operation_1451','tenant_demo_A',$fp('A'),8);
 $first=$gate->decide('operation_1451','tenant_demo_A',$fp('A'),8,$aResult,$ticketA);
 $check($first['status']==='mock_dispatch_recorded'&&$first['lab_delivered'],
   'independent mock dispatch reserves A once after trusted issuer approval');
 $check($first['action_authorized']===false,'terminal gate never authorizes real Ads');
 $check($gate->decide('operation_1451','tenant_demo_A',$fp('A'),8,$labRegistered,$ticketA)['status']
   ==='dispatch_replay_denied','terminal authority refuses a duplicate A despite forged SQL success flag');
 $faked=['issued_at'=>time(),'signature'=>base64_encode(random_bytes(64))];
 $check($gate->decide('operation_1456','tenant_demo_A',$fp('unauthorized'),8,$labRegistered,$faked)['status']
   ==='issuer_signature_denied','forged independent issuer signature cannot pass');
 $expired=$issuerTicket('operation_1457','tenant_demo_A',$fp('expired'),8,time()-60);
 $check($gate->decide('operation_1457','tenant_demo_A',$fp('expired'),8,$labRegistered,$expired)['status']
   ==='issuer_attestation_missing_or_expired','old issuer attestation is rejected');
 $ticketMismatch=$issuerTicket('operation_1458','tenant_demo_A',$fp('mismatch'),8);
 $check($gate->decide('operation_1458','tenant_demo_B',$fp('mismatch'),8,$labRegistered,$ticketMismatch)['status']
   ==='issuer_signature_denied','issuer signature binds tenant identity');
 $check($gate->decide('operation_1458','tenant_demo_A',$fp('mismatch'),8,
    ['status'=>'denied','registered'=>false,'action_authorized'=>false],$ticketMismatch)['status']
   ==='sql_result_untrusted_or_denied','independent gate denies explicit rejected SQL result');

}elseif($phase==='after-snapshot'){
 $check($pc()===1&&$wc()===1&&$oc()===1&&$orc()===1,
   'old backups were taken with only A');
 $check($journal->advance(9),'signed journal advanced to9 outside backups');
 $check($gate->advance(9),'terminal authority separately advances to generation9');
 $oa->query('SELECT perf_f143_oracle.advance_floor(9)')->fetchColumn();
 $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($pe()===9&&$we()===9&&$ae()===9&&$oe()===9,
   'all 4 SQL floors advanced to9');
 $b=$bridge->register('operation_1452','tenant_demo_B',$fp('B'));
 $check($b['status']==='registered_lab'&&$b['registered'],
   'B registered legitimately after four old backups');
 $check(!$b['action_authorized'],'B still not a live Ads approval');
 $check($pc()===2&&$wc()===2&&$oc()===2&&$orc()===2,
   'B durable and oracle B redeemed in all new data');
 $ticketB=$issuerTicket('operation_1452','tenant_demo_B',$fp('B'),9);
 $dispatchB=$gate->decide('operation_1452','tenant_demo_B',$fp('B'),9,$b,$ticketB);
 $check($dispatchB['status']==='mock_dispatch_recorded'&&$dispatchB['lab_delivered'],
   'B observed once by independent terminal boundary');
 $check(!$dispatchB['action_authorized'],'B terminal record does not dispatch real campaigns');
 $check($gate->decide('operation_1452','tenant_demo_B',$fp('B'),9,$labRegistered,$ticketB)['status']
   ==='dispatch_replay_denied','B cannot be mock-dispatched twice with same signed issuer proof');

 $q=$oa->prepare('SELECT journal_issued_at,journal_sequence,journal_head,journal_mac FROM perf_f143_oracle.permits WHERE operation_id=?');
 $q->execute(['operation_1452']);$captured=$q->fetch(PDO::FETCH_ASSOC);
 $check(is_array($captured)&&strlen((string)$captured['journal_mac'])===64,
   'real signed state-based B MAC captured for negative test');
 file_put_contents($proofPath,json_encode($captured,JSON_THROW_ON_ERROR));
 $check($bridge->register('operation_1452','tenant_demo_B',$fp('B'))['status']
  ==='journal_replay_or_epoch_denied','while intact, gateway rejects B repeat');
}elseif($phase==='after-quadruple-restore'){
 $check($pe()===8&&$we()===8&&$ae()===8&&$oe()===8,
   'EXPECTED GAP: all four SQL floors restored to older epoch8');
 $check($pc()===1&&$wc()===1&&$oc()===1&&$orc()===1,
   'EXPECTED GAP: four old ledgers lose B history and redemption');
 $check($journal->inspect()['epoch']===9,
   'signed file remains at9 outside the four SQL backups');
 $check($dispatchAuthority->inspect()['epoch']===9,
   'separate terminal journal survives four database restores');
 $check($gate->decide('operation_1452','tenant_demo_B',$fp('B'),9,$labRegistered,
   $issuerTicket('operation_1452','tenant_demo_B',$fp('B'),9))['status']
   ==='dispatch_replay_denied','terminal boundary refuses restored-B SQL success from old backups');

 $check($bridge->register('operation_1452','tenant_demo_B',$fp('B'))['status']
   ==='journal_epoch_mismatch','guarded gateway blocks B because un-restored signed journal retains epoch9');

 // This is a LAB-ADMIN reset only, not a recovery protocol.
 $oa->query('SELECT perf_f143_oracle.advance_floor(9)')->fetchColumn();
 $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
 $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
 $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
 $check($pe()===9&&$we()===9&&$ae()===9&&$oe()===9,
   'admin brings all restored floors back to9');
 $check($bridge->register('operation_1452','tenant_demo_B',$fp('B'))['status']
  ==='journal_replay_or_epoch_denied','signed file gateway still denies B');

 // Simulate direct SQL/issuer credential combined: a valid captured MAC is
 // still within 30s and FOURTH oracle has forgotten its redeemed B row.
 $captured=json_decode((string)file_get_contents($proofPath),true,512,JSON_THROW_ON_ERROR);
 $check($oracleIssue($captured),
   'EXPECTED GAP: oracle with restored history re-issues an old B signed permit');
 $check($oc()===2&&$orc()===1,'oracle has a reissued but not redeemed B row');
 $freshWitness=$witnessIssue();
 $check($wc()===2,'restored witness independently re-issues B nonce');
 $check($directWrite($captured,$freshWitness),
   'EXPECTED VULNERABILITY: direct SQL with captured MAC replays B after FOUR DB restores');
 $check($pc()===2&&$wc()===2&&$oc()===2&&$orc()===2,
   'B replay added fresh receipts despite signed file maintaining B history');
 $check(!$directWrite($captured,$freshWitness),
   'immediate duplicate is denied once restored oracle B is redeemed again');
 $check($journal->inspect()['epoch']===9
  &&isset($journal->inspect()['seen'][$fp('B')]),
   'external signed journal never approved this second B');
 $check($gate->decide('operation_1452','tenant_demo_B',$fp('B'),9,$labRegistered,
   $issuerTicket('operation_1452','tenant_demo_B',$fp('B'),9))['status']
   ==='dispatch_replay_denied','direct SQL replay cannot bypass separate synthetic terminal boundary');

 $full=(string)file_get_contents($dispatchLogPath);
 $firstLine=explode("\n",trim($full))[0]."\n";
 file_put_contents($dispatchLogPath,$firstLine);
 $check($gate->decide('operation_1459','tenant_demo_A',$fp('new'),9,$labRegistered,
   $issuerTicket('operation_1459','tenant_demo_A',$fp('new'),9))['status']
   ==='independent_dispatch_authority_unknown',
   'valid signed-prefix truncation is caught by independent high-watermark mismatch');
 file_put_contents($dispatchLogPath,$full);
 rename($checkpointPath,$checkpointPath.'.absent');
 $check($gate->decide('operation_1459','tenant_demo_A',$fp('new'),9,$labRegistered,
   $issuerTicket('operation_1459','tenant_demo_A',$fp('new'),9))['status']
   ==='independent_dispatch_authority_unknown','missing checkpoint fails closed');
 rename($checkpointPath.'.absent',$checkpointPath);
 $check($gate->decide('operation_1452','tenant_demo_B',$fp('B'),9,$labRegistered,
   $issuerTicket('operation_1452','tenant_demo_B',$fp('B'),9))['status']
   ==='dispatch_replay_denied','restored intact terminal journal still refuses B');

}else throw new RuntimeException('F145 phase seed|after-snapshot|after-quadruple-restore');
echo "F145 {$phase}: {$n} PASS / 0 FAIL — expected negative FOUR-DB restore, NO-GO\n";
