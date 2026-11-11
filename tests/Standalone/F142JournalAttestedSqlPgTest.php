<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F142JournalAttestedRegistration.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F142JournalAttestedRegistration;

if (getenv('APP_ENV')!=='testing'||getenv('PERF_F142_LAB')!=='1'
    ||getenv('PERF_F141_LAB')!=='1'
    ||getenv('PGDATABASE')!=='perf_f132_ci'
    ||getenv('WITNESS_DB')!=='perf_f132_witness_ci'
    ||getenv('ANCHOR_DB')!=='perf_f138_anchor_ci') {
    throw new RuntimeException('F142 disposable three-Postgres CI only');
}
function db142(int $port,string $dbname,string $role,string $password):PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$dbname}",$role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>4,
    ]);
}
$root=(string)getenv('RUNNER_TEMP').'/f142-authority';
if (!is_dir($root) && !mkdir($root,0700,true)) {
    throw new RuntimeException('F142 cannot create CI authority directory');
}
$keyPath=$root.'/ci-ed25519-identity.json';
$logPath=$root.'/signed-journal.jsonl';
$fixture=$root.'/captured-b-journal-mac.json';
$phase=$argv[1]??'';
if ($phase==='seed') {
    if(is_file($keyPath))throw new RuntimeException('F142 CI identity unexpectedly preexisting');
    $kp=sodium_crypto_sign_keypair();
    file_put_contents($keyPath,json_encode([
        'secret'=>base64_encode(sodium_crypto_sign_secretkey($kp)),
        'public'=>base64_encode(sodium_crypto_sign_publickey($kp)),
    ],JSON_THROW_ON_ERROR));
    chmod($keyPath,0600);
}
$keys=json_decode((string)file_get_contents($keyPath),true,512,JSON_THROW_ON_ERROR);
$secret=base64_decode((string)$keys['secret'],true);
$public=base64_decode((string)$keys['public'],true);
$macKey=hex2bin((string)getenv('PERF_F142_CI_KEY'));
if($secret===false||$public===false||$macKey===false||strlen($macKey)!==32)
    throw new RuntimeException('F142 missing ephemeral signing/MAC keys');
$pa=db142(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$wa=db142(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$aa=db142(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$primary=db142(5432,'perf_f132_ci','perf_f142_primary_bridge',(string)getenv('PERF_F142_PRIMARY_PASSWORD'));
$witness=db142(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$anchor=db142(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$legacy=db142(5432,'perf_f132_ci','perf_f139_primary_bridge',(string)getenv('PERF_F139_PRIMARY_PASSWORD'));
$authority=new F141SignedJournalAuthority($logPath,$secret,$public);
$sut=new F142JournalAttestedRegistration($primary,$witness,$anchor,$authority,$macKey);
$fp=static fn(string $s):string=>hash('sha256','F142-ci-'.$s);
$pc=static fn():int=>(int)$pa->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc=static fn():int=>(int)$wa->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$pe=static fn():int=>(int)$pa->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$we=static fn():int=>(int)$wa->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ae=static fn():int=>(int)$aa->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$n=0;
$check=static function(bool $condition,string $label)use(&$n):void{
    ++$n;if(!$condition)throw new RuntimeException('F142 FAILED: '.$label);
};
$denied=static function(callable $fn):bool{
    try{$fn();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$receipt=static function(string $op,string $tenant,string $fingerprint,int $epoch,
                          int $timestamp,int $sequence,string $head)use($macKey):array{
    $payload=implode('|',[
        'F142-v1',$op,$tenant,$fingerprint,(string)$epoch,
        (string)$timestamp,(string)$sequence,$head,
    ]);
    return [
        'journal_issued_at'=>$timestamp,
        'journal_sequence'=>$sequence,
        'journal_head'=>$head,
        'journal_mac'=>hash_hmac('sha256',$payload,$macKey),
    ];
};
$call=static function(string $op,string $tenant,string $fingerprint,int $epoch,
                       int $wtime,string $wsig,array $a)use($primary):bool{
    $q=$primary->prepare(
        'SELECT CASE WHEN perf_f142_primary.record_with_journal_attestation(?,?,?,?,?,?,?,?,?,?) THEN 1 ELSE 0 END'
    );
    $q->execute([
        $op,$tenant,$fingerprint,$epoch,$wtime,$wsig,
        $a['journal_issued_at'],$a['journal_sequence'],
        $a['journal_head'],$a['journal_mac'],
    ]);
    return (int)$q->fetchColumn()===1;
};
$mint=static function(string $op,string $tenant,string $fingerprint,int $epoch)use($witness):array{
    $q=$witness->prepare('SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)');
    $q->execute([$op,$tenant,$fingerprint,$epoch]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r))throw new RuntimeException('F142 unexpected witness mint denial');
    return $r;
};

if($phase==='seed'){
    $authority->bootstrap();
    $check($pe()===8&&$we()===8&&$ae()===8,'three SQL floors start generation8');
    $check($authority->inspect()['epoch']===8,'external signed journal start generation8');
    $check($pc()===0&&$wc()===0,'SQL ledgers initially empty');
    $check($denied(static fn()=>$legacy->query(
        "SELECT perf_f139_primary.record_with_locked_anchor('operation_1420','tenant_demo_A','"
          .$fp('bogus')."',8,1,'".str_repeat('0',64)."')")),
        'old F139 SQL login cannot bypass attested registrar');
    $check($denied(static fn()=>$primary->query(
        'SELECT * FROM perf_f142_primary.test_mac_key')),
        'F142 SQL login cannot read MAC key');
    $check($denied(static fn()=>$primary->query(
        'SELECT * FROM perf_f136_primary.remote_issued')),
        'F142 login has no raw witness foreign-table access');
    $invalid=['journal_issued_at'=>time(),'journal_sequence'=>2,
       'journal_head'=>str_repeat('a',64),'journal_mac'=>str_repeat('0',64)];
    $check(!$call('operation_1421','tenant_demo_A',$fp('bogus'),8,time(),
        str_repeat('0',64),$invalid),'direct F142 SQL without journal-proof denied');
    $check($pc()===0&&$wc()===0,'forged proof did not modify ledgers');
    $bad=$sut->register('invalid','tenant_demo_A',$fp('bad'));
    $check($bad['status']==='invalid_inputs','invalid identifier rejected before journal');
    $a=$sut->register('operation_1421','tenant_demo_A',$fp('A'));
    $check($a['status']==='registered_lab'&&$a['registered'],'journal attested registration A succeeds');
    $check(!$a['action_authorized'],'A does not authorize external actions');
    $check($pc()===1&&$wc()===1&&$rc()===1,'A redeemed only once in SQL witnesses');
    $check(isset($authority->inspect()['seen'][$fp('A')]),'external journal saw A');
    $check($sut->register('operation_1421','tenant_demo_A',$fp('A'))['status']
       ==='journal_replay_or_epoch_denied','external journal refuses replay of A');
}elseif($phase==='after-snapshot'){
    $check($pc()===1&&$wc()===1,'snapshot A baseline');
    $check($authority->advance(9),'external signed journal advanced to generation9');
    $check($sut->register('operation_1422','tenant_demo_B',$fp('B'))['status']
       ==='journal_epoch_mismatch','external floor blocks stale local databases');
    $check($wc()===1,'epoch mismatch does not burn new witness permit');
    $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
    $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
    $check($pe()===9&&$we()===9&&$ae()===9,'all three SQL floors aligned to9');
    $b=$sut->register('operation_1422','tenant_demo_B',$fp('B'));
    $check($b['status']==='registered_lab'&&$b['registered'],'B accepted by F142 at epoch9');
    $check($pc()===2&&$wc()===2&&$rc()===2,'B persisted exactly once in each ledger');
    $state=$authority->inspect();
    $proof=$receipt('operation_1422','tenant_demo_B',$fp('B'),9,
        time(),$state['events'],$state['head']);
    file_put_contents($fixture,json_encode($proof,JSON_THROW_ON_ERROR));
    $check(is_file($fixture),'captured short-lived B journal attestation for CI negative case');
    $check($sut->register('operation_1422','tenant_demo_B',$fp('B'))['status']
       ==='journal_replay_or_epoch_denied','external journal refuses repeat of B');
    $check($authority->inspect()['epoch']===9,'file journal remains on higher generation');
}elseif($phase==='after-triple-restore'){
    $check($pe()===8&&$we()===8&&$ae()===8,'three database floors rolled back to8');
    $check($pc()===1&&$wc()===1&&$rc()===1,'B history lost in three databases');
    $check($authority->inspect()['epoch']===9,'signed file journal outside DB backups stayed at9');
    $stale=$sut->register('operation_1422','tenant_demo_B',$fp('B'));
    $check($stale['status']==='journal_epoch_mismatch','guarded gateway blocks B after triple restore');
    $check($sut->register('operation_1423','tenant_demo_A',$fp('fresh'))['status']
       ==='journal_epoch_mismatch','guarded gateway blocks fresh operation after rollback');
    $check($pc()===1&&$wc()===1,'gateway denial consumes neither SQL ledger');

    // Manual alignment only in CI; this is NOT a recovery protocol.
    $aa->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
    $wa->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $pa->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
    $check($pe()===9&&$we()===9&&$ae()===9,'lab admin aligned all three restored DBs');
    $check($sut->register('operation_1422','tenant_demo_B',$fp('B'))['status']
       ==='journal_replay_or_epoch_denied','journal remembers B even with all DB floors9');
    $forgery=['journal_issued_at'=>time(),'journal_sequence'=>4,
        'journal_head'=>str_repeat('b',64),'journal_mac'=>str_repeat('0',64)];
    $check(!$call('operation_1424','tenant_demo_A',$fp('forgery'),9,time(),
       str_repeat('0',64),$forgery),'forged HMAC rejected directly inside SQL');
    $check($pc()===1&&$rc()===1,'forged HMAC changes no SQL row');
    $oldProof=$receipt('operation_1425','tenant_demo_A',$fp('expired'),9,
        time()-55,5,str_repeat('f',64));
    $check(!$call('operation_1425','tenant_demo_A',$fp('expired'),9,time(),
       str_repeat('0',64),$oldProof),'even valid MAC with expired timestamp rejected');

    // EXPECTED RESIDUAL VULNERABILITY: a captured valid MAC + ability to reissue
    // F136 witness permits can replay B after ALL SQL DBs were restored and
    // manually re-aligned. The MAC is not checked against live journal state.
    $captured=json_decode((string)file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
    $newWitnessPermit=$mint('operation_1422','tenant_demo_B',$fp('B'),9);
    $check($wc()===2&&$rc()===1,'witness nonce B reissued after triple restore');
    $check($call('operation_1422','tenant_demo_B',$fp('B'),9,
      (int)$newWitnessPermit['issued_at'],(string)$newWitnessPermit['signature'],$captured),
      'EXPECTED GAP: captured F142 MAC permits direct SQL replay after triple restore');
    $check($pc()===2&&$wc()===2&&$rc()===2,
      'bypass generated fresh B SQL rows without new signed external event');
    $check($sut->register('operation_1422','tenant_demo_B',$fp('B'))['status']
       ==='journal_replay_or_epoch_denied','gateway still refuses B after SQL bypass');
    $check(!$stale['action_authorized'],'no advertising or billing authorization');
}else throw new RuntimeException('F142 phases seed|after-snapshot|after-triple-restore');

echo "F142 {$phase}: {$n} PASS / 0 FAIL — signed SQL attestation; residual replay NO-GO\n";
