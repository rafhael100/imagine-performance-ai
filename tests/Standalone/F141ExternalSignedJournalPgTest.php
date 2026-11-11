<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Lab/F141JournalGatedRegistration.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F141JournalGatedRegistration;

if (getenv('APP_ENV')!=='testing'
    || getenv('PERF_F141_LAB')!=='1'
    || getenv('PERF_F139_LAB')!=='1'
    || getenv('PGDATABASE')!=='perf_f132_ci'
    || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
    || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci') {
    throw new RuntimeException('F141: forbidden outside ephemeral CI');
}
function f141db(int $port,string $db,string $role,string $pwd):PDO {
    return new PDO("pgsql:host=127.0.0.1;port={$port};dbname={$db}",$role,$pwd,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>4,
    ]);
}
$root=(string)getenv('RUNNER_TEMP').'/f141-authority';
if (!is_dir($root) && !mkdir($root,0700,true)) {
    throw new RuntimeException('F141 test-only journal directory cannot be created');
}
$keyPath=$root.'/test-signing-key.json';
$journalPath=$root.'/separate-signed-authority.jsonl';
if (($argv[1]??'')==='seed') {
    if (file_exists($keyPath)) throw new RuntimeException('F141 test-only keys must start new');
    $keypair=sodium_crypto_sign_keypair();
    $stored=[
        'secret'=>base64_encode(sodium_crypto_sign_secretkey($keypair)),
        'public'=>base64_encode(sodium_crypto_sign_publickey($keypair)),
    ];
    if (file_put_contents($keyPath,json_encode($stored,JSON_THROW_ON_ERROR))===false) {
        throw new RuntimeException('F141 test-only keys not persisted');
    }
    chmod($keyPath,0600);
}
if (!is_file($keyPath)) throw new RuntimeException('F141 missing ephemeral signing identity');
$keys=json_decode((string)file_get_contents($keyPath),true,512,JSON_THROW_ON_ERROR);
$secret=base64_decode((string)$keys['secret'],true);
$public=base64_decode((string)$keys['public'],true);
if ($secret===false || $public===false) throw new RuntimeException('invalid F141 signing identity');

$adminP=f141db(5432,'perf_f132_ci','postgres',(string)getenv('PGPASSWORD'));
$adminW=f141db(5433,'perf_f132_witness_ci','postgres',(string)getenv('WITNESS_PASSWORD'));
$adminA=f141db(5434,'perf_f138_anchor_ci','postgres',(string)getenv('ANCHOR_PASSWORD'));
$primary=f141db(5432,'perf_f132_ci','perf_f139_primary_bridge',(string)getenv('PERF_F139_PRIMARY_PASSWORD'));
$witness=f141db(5433,'perf_f132_witness_ci','perf_f136_issuer',(string)getenv('PERF_F136_ISSUER_PASSWORD'));
$anchor=f141db(5434,'perf_f138_anchor_ci','perf_f138_anchor_reader',(string)getenv('PERF_F138_ANCHOR_READER_PASSWORD'));
$authority=new F141SignedJournalAuthority($journalPath,$secret,$public);
$gateway=new F141JournalGatedRegistration($primary,$witness,$anchor,$authority);
$fp=static fn(string $id):string=>hash('sha256','F141-disposable-'.$id);
$pc=static fn():int=>(int)$adminP->query('SELECT count(*) FROM perf_f129_lab.receipts')->fetchColumn();
$wc=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f136_witness.issued')->fetchColumn();
$rc=static fn():int=>(int)$adminW->query('SELECT count(*) FROM perf_f136_witness.issued WHERE redeemed')->fetchColumn();
$ep=static fn():int=>(int)$adminP->query('SELECT generation FROM perf_f132_primary.local_generation')->fetchColumn();
$ew=static fn():int=>(int)$adminW->query('SELECT generation FROM perf_f132_witness.epoch_floor')->fetchColumn();
$ea=static fn():int=>(int)$adminA->query('SELECT generation FROM perf_f138_anchor.epoch_floor')->fetchColumn();
$n=0;
$check=static function(bool $ok,string $message)use(&$n):void {
    ++$n;
    if (!$ok) throw new RuntimeException('F141 test failure: '.$message);
};
$phase=$argv[1]??'';
if ($phase==='seed') {
    $authority->bootstrap();
    $s=$authority->inspect();
    $check($s['epoch']===8 && $s['events']===1,'signed independent genesis epoch 8');
    $check($ep()===8 && $ew()===8 && $ea()===8,'three database floors at 8');
    $check($pc()===0 && $wc()===0,'three-db ledgers initially empty');
    $check($gateway->register('bad','tenant_demo_A',$fp('bad'))['status']==='invalid_inputs',
      'invalid input denied before journal event');
    $check($authority->inspect()['events']===1,'invalid input did not burn a signed event');
    $r=$gateway->register('operation_1411','tenant_demo_A',$fp('A'));
    $check($r['status']==='registered_lab' && $r['registered'],'A accepted via journal and three DBs');
    $check(!$r['action_authorized'],'no external media authorization');
    $check($pc()===1 && $wc()===1 && $rc()===1,'A recorded in SQL ledgers');
    $s=$authority->inspect();
    $check($s['events']===2 && isset($s['seen'][$fp('A')]),'A durably reserved in external signed file');
    $check($gateway->register('operation_1411','tenant_demo_A',$fp('A'))['status']
       ==='external_replay_or_epoch_denied','external authority denies A replay');
} elseif ($phase==='after-snapshot') {
    $check($ep()===8 && $ew()===8 && $ea()===8,'snapshots captured old epoch 8');
    $check($pc()===1 && $wc()===1,'snapshot baseline holds A');
    $check($authority->advance(9),'independent authority advanced to 9 before databases');
    $check(!$authority->advance(8),'signed authority cannot decrement epoch via API');
    $early=$gateway->register('operation_1412','tenant_demo_B',$fp('B'));
    $check($early['status']==='external_epoch_mismatch',
       'external floor 9 refuses old database epoch 8');
    $check($pc()===1 && $wc()===1,'epoch mismatch consumes no SQL nonce');
    $adminA->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
    $adminW->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $adminP->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
    $check($ep()===9 && $ew()===9 && $ea()===9,'SQL floors manually aligned in disposable CI');
    $accepted=$gateway->register('operation_1412','tenant_demo_B',$fp('B'));
    $check($accepted['status']==='registered_lab' && !$accepted['action_authorized'],
      'B admitted at epoch 9 only after all floors match');
    $check($pc()===2 && $wc()===2 && $rc()===2,'B journalled and redeemed exactly once');
    $check(isset($authority->inspect()['seen'][$fp('B')]),'external signer remembers B');
    $check($gateway->register('operation_1412','tenant_demo_B',$fp('B'))['status']
      ==='external_replay_or_epoch_denied','external signer refuses B duplicate');

    // Unknown SQL outcome burns journal identity without any automatic retry.
    $adminP->exec(
      'REVOKE EXECUTE ON FUNCTION perf_f139_primary.record_with_locked_anchor(text,text,text,bigint,bigint,text) FROM perf_f139_primary_bridge'
    );
    $failed=$gateway->register('operation_1413','tenant_demo_A',$fp('E'));
    $check($failed['status']==='downstream_denied_external_spent',
      'SQL permission failure is fail-closed and external nonce spent');
    $check($pc()===2 && $wc()===3,'failed SQL did not insert primary; witness may be spent');
    $adminP->exec(
      'GRANT EXECUTE ON FUNCTION perf_f139_primary.record_with_locked_anchor(text,text,text,bigint,bigint,text) TO perf_f139_primary_bridge'
    );
    $check($gateway->register('operation_1413','tenant_demo_A',$fp('E'))['status']
      ==='external_replay_or_epoch_denied','uncertain operation cannot auto-retry external reservation');
    $check($authority->inspect()['epoch']===9,'signed ledger remains at generation 9');
} elseif ($phase==='after-triple-restore') {
    $check($ep()===8 && $ew()===8 && $ea()===8,'three SQL databases all restored to epoch 8');
    $check($pc()===1 && $wc()===1 && $rc()===1,'all SQL databases lost B history');
    $check($authority->inspect()['epoch']===9,
      'external signed file stayed at generation 9 outside three SQL backups');
    $old=$gateway->register('operation_1412','tenant_demo_B',$fp('B'));
    $check($old['status']==='external_epoch_mismatch','gateway denies B replay while restored DBs stale');
    $fresh=$gateway->register('operation_1414','tenant_demo_A',$fp('D'));
    $check($fresh['status']==='external_epoch_mismatch','gateway denies unseen D after triple rollback');
    $check($pc()===1 && $wc()===1,'external denial consumes no SQL entries');

    // Demonstrate IMPORTANT KNOWN BYPASS: a direct SQL principal still holding
    // F139 execute privilege can avoid the PHP file authority entirely.
    $issued=$witness->prepare(
      'SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,8)'
    );
    $issued->execute(['operation_1415','tenant_demo_A',$fp('direct')]);
    $permit=$issued->fetch(PDO::FETCH_ASSOC);
    $check(is_array($permit),'direct SQL attacker can obtain witness lab permit');
    $call=$primary->prepare(
      'SELECT CASE WHEN perf_f139_primary.record_with_locked_anchor(?,?,?,?,?,?) THEN 1 ELSE 0 END'
    );
    $call->execute([
      'operation_1415','tenant_demo_A',$fp('direct'),8,
      (int)$permit['issued_at'],(string)$permit['signature'],
    ]);
    $check((int)$call->fetchColumn()===1,
      'EXPECTED BYPASS: SQL bridge directly admits despite external signed authority floor 9');
    $check($pc()===2 && $wc()===2 && $rc()===2,
      'direct SQL bypass changes ledgers but does not create external signed event');
    $check(!isset($authority->inspect()['seen'][$fp('direct')]),
      'external signed ledger never approved direct SQL bypass');
    $check($gateway->register('operation_1415','tenant_demo_A',$fp('direct'))['status']
      ==='external_epoch_mismatch','guarded path still refuses direct-bypass identity');

    // The signed journal detects in-place modification, not a valid-prefix
    // truncation. This is why a file on the same runner is NOT a WORM anchor.
    $tamperPath=$root.'/tampered.jsonl';
    $original=(string)file_get_contents($journalPath);
    $rows=explode("\n",trim($original));
    $check(count($rows)>=4,'journal has genesis, advance and reservations');
    $mutated=json_decode($rows[1],true,512,JSON_THROW_ON_ERROR);
    $mutated['payload']['epoch']=42;
    $rows[1]=json_encode($mutated,JSON_THROW_ON_ERROR);
    file_put_contents($tamperPath,implode("\n",$rows)."\n");
    $tampered=new F141SignedJournalAuthority($tamperPath,$secret,$public);
    $rejected=false;
    try{$tampered->inspect();}catch(RuntimeException $e){$rejected=true;}
    $check($rejected,'Ed25519 and hash-chain verify detects in-place alteration');

    $prefixPath=$root.'/truncated-valid-prefix.jsonl';
    file_put_contents($prefixPath,explode("\n",$original)[0]."\n");
    $prefix=new F141SignedJournalAuthority($prefixPath,$secret,$public);
    $check($prefix->inspect()['epoch']===8,
      'EXPECTED LIMITATION: valid-prefix log truncation evades signature-only verification');

    $check($authority->inspect()['epoch']===9,'original journal remained uncompromised');

    // Manual CI epoch alignment is not a real recovery protocol. The
    // immutable-looking signed file still prevents B duplication through
    // the gateway after DB generations are aligned again.
    $adminA->query('SELECT perf_f138_anchor.advance_floor(9)')->fetchColumn();
    $adminW->query('SELECT perf_f132_witness.advance_floor(9)')->fetchColumn();
    $adminP->exec('UPDATE perf_f132_primary.local_generation SET generation=9 WHERE singleton=1');
    $check($ep()===9 && $ew()===9 && $ea()===9,'lab-only admin realignment to epoch 9');
    $retry=$gateway->register('operation_1412','tenant_demo_B',$fp('B'));
    $check($retry['status']==='external_replay_or_epoch_denied',
      'even after SQL realignment, signed external history denies replay of B');
    $check($gateway->register('operation_1412','tenant_demo_B',$fp('different'))['status']
      ==='external_replay_or_epoch_denied','same operation with different fingerprint also denied');
    $check($pc()===2 && !$retry['action_authorized'],
      'no primary replay from guarded code');
} else throw new RuntimeException('F141 requires seed|after-snapshot|after-triple-restore');

echo "F141 {$phase}: {$n} PASS / 0 FAIL — LAB ONLY / direct-SQL bypass NO-GO\n";
