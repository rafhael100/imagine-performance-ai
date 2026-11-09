<?php
declare(strict_types=1);

/**
 * F1-38 disposable PostgreSQL demonstration: same-owner ABA in F37 and
 * generation-fenced dry-run ACK in F38. No real worker or provider calls.
 */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticDryRunOutboxLeaser.php';
require_once __DIR__.'/SyntheticFencedLeaseGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticDryRunOutboxLeaser;
use Imagine\Protocol\Lab\SyntheticFencedLeaseGateway;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F35_INTENT_LAB')!=='1'
  ||getenv('PERF_F37_LEASE_LAB')!=='1'
  ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
  ||getenv('PERF_F38_UNFENCED_COMPAT_TEST')==='1'
  ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
  ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
  throw new RuntimeException('F38 requires lab with unfenced legacy disabled by default.');
}
$pdo=static fn(string $role,string $password):PDO =>new PDO(
 'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
 $role,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
);
$admin=$pdo('postgres','ci-f08-db-only');
$intent=new SyntheticIdempotentIntentGateway($pdo('perf_f35_writer','ci-f35-writer-only'));
$legacy=new SyntheticDryRunOutboxLeaser($pdo('perf_f37_lease_worker','ci-f37-lease-only'));
$fenced=new SyntheticFencedLeaseGateway($pdo('perf_f38_fenced_worker','ci-f38-fenced-only'));
$fencedOther=new SyntheticFencedLeaseGateway($pdo('perf_f38_fenced_worker','ci-f38-fenced-only'));
$wrong=new SyntheticFencedLeaseGateway($admin);
$clock=1800000000;
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void {
 if($got!==$expected)throw new RuntimeException(
    "FAIL F38 $label got=".var_export($got,true)." expected=".var_export($expected,true)
 );
 echo 'PASS F38 '.++$n.' '.$label.PHP_EOL;
};
$parseOld=static function(string $v):int {
 if(!preg_match('/^claimed_mock:([1-9][0-9]*)$/D',$v,$m))
    throw new RuntimeException('Unexpected legacy claim token: '.$v);
 return (int)$m[1];
};
$parseNew=static function(string $v):array {
 if(!preg_match('/^claimed_mock:([1-9][0-9]*):([1-9][0-9]*)$/D',$v,$m))
    throw new RuntimeException('Unexpected fenced claim token: '.$v);
 return [(int)$m[1],(int)$m[2]];
};
$count=static fn(string $table):int =>(int)$admin->query(
    'SELECT count(*) FROM '.$table
)->fetchColumn();
$manifest=hash('sha256','f38-generation-fencing-only');
$check('before fixture outbox contains four disabled events',
    $count('perf_f35_intents.outbox'),4);
foreach([
    'f38-legacy-aba',
    'f38-fenced-aba',
    'f38-fenced-other'
] as $request){
 $check('synthetic intent reservation '.$request,
    $intent->reserve($request,$manifest),'reserved_synthetic');
}
$check('new intents yield three additional outbox rows',
    $count('perf_f35_intents.outbox'),7);
$check('F37 old unversioned gateway denied by default',
    $legacy->claim('worker-f37-a',$clock),'unfenced_legacy_denied');

putenv('PERF_F38_UNFENCED_COMPAT_TEST=1');
try{
 $oldId=$parseOld($legacy->claim('worker-f37-a',$clock));
 $check('legacy lease admits a previously unclaimed synthetic event',
    $oldId>0,true);
 $check('legacy same owner reacquires after lease expiry',
    $parseOld($legacy->claim('worker-f37-a',$clock+31)),$oldId);
 // The worker has the *old* lease identity but the *same* owner label.
 // Without a generation, it can ACK the replacement: demonstrated flaw.
 $check('EXPECTED DEFECT old owner label ACKs replacement lease (ABA)',
    $legacy->acknowledge($oldId,'worker-f37-a',$clock+31),
    'acknowledged_mock');
} finally{
 putenv('PERF_F38_UNFENCED_COMPAT_TEST=0');
}
$check('legacy gate returns to fail-closed after attack-vector test',
    $legacy->claim('worker-f37-a',$clock+31),'unfenced_legacy_denied');

[$id,$g1]=$parseNew($fenced->claim('worker-f38-a',$clock));
$check('first fenced lease has generation one',$g1,1);
$check('separate F38 ledger stores one leased record',
    $count('perf_f38_fenced.leases'),1);
[$reacquired,$g2]=$parseNew($fencedOther->claim('worker-f38-a',$clock+31));
$check('same owner label can legitimately take over expired fenced lease',
    $reacquired,$id);
$check('reclaim advances lease generation',$g2,$g1+1);
$check('stale worker holding generation one cannot ACK generation two',
    $fenced->acknowledge($id,'worker-f38-a',$g1,$clock+31),
    'fenced_lease_denied');
$check('future invented generation cannot ACK',
    $fenced->acknowledge($id,'worker-f38-a',$g2+5,$clock+31),
    'fenced_lease_denied');
$check('valid current generation may ACK once',
    $fencedOther->acknowledge($id,'worker-f38-a',$g2,$clock+31),
    'acknowledged_mock');
$check('repeated ACK with same generation denied',
    $fencedOther->acknowledge($id,'worker-f38-a',$g2,$clock+31),
    'fenced_lease_denied');
[$id2,$g3]=$parseNew($fenced->claim('worker-f38-a',$clock+31));
$check('another pending event is distinct from prior event',$id2!==$id,true);
$check('independent event starts at generation one',$g3,1);
$check('expired generation cannot acknowledge',
    $fenced->acknowledge($id2,'worker-f38-a',$g3,$clock+62),
    'fenced_lease_denied');
[$sameEvent,$newGen]=$parseNew($fencedOther->claim('worker-f38-b',$clock+62));
$check('new worker reclaims expired second event',$sameEvent,$id2);
$check('new owner receives strictly higher generation',$newGen,$g3+1);
$check('old owner wrong generation cannot ACK after handoff',
    $fenced->acknowledge($id2,'worker-f38-a',$g3,$clock+62),
    'fenced_lease_denied');
$check('new owner can ACK its generation',
    $fencedOther->acknowledge($id2,'worker-f38-b',$newGen,$clock+62),
    'acknowledged_mock');
$check('all selectable F38 events were acknowledged',
    $fenced->claim('worker-f38-a',$clock+100),'none');
$check('invalid owner denied',$fenced->claim('anonymous',$clock),'invalid_claim_denied');
$check('invalid generation rejected',
    $fenced->acknowledge($id,'worker-f38-a',0,$clock+31),
    'invalid_ack_denied');
$check('wrong PDO role fails before claim',
    $wrong->claim('worker-f38-a',$clock),'wrong_database_or_role');
$blocked=false;
try{$pdo('perf_f38_fenced_worker','ci-f38-fenced-only')
    ->query('SELECT * FROM perf_f38_fenced.leases');}
catch(PDOException $e){$blocked=$e->getCode()==='42501';}
$check('consumer cannot SELECT private lease state',$blocked,true);
$check('outbox is still completely dispatch disabled',
    (int)$admin->query('SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled')->fetchColumn(),0);
$check('no intent has operational status',
    (int)$admin->query("SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'")->fetchColumn(),0);
$check('two fenced leases are only acknowledged_mock',
    (int)$admin->query("SELECT count(*) FROM perf_f38_fenced.leases WHERE state='acknowledged_mock'")->fetchColumn(),2);
putenv('PERF_F38_FENCED_LEASE_LAB=0');
$check('feature flag closes fenced gateway',
    $fenced->claim('worker-f38-a',$clock),'lease_lab_disabled');
putenv('PERF_F38_FENCED_LEASE_LAB=1');
putenv('APP_ENV=production');
$check('production refuses all synthetic lease claims',
    $fenced->claim('worker-f38-a',$clock),'lease_lab_disabled');
putenv('APP_ENV=testing');
echo "PASS F38 $n generation-fenced dry-run checks, NO dispatch".PHP_EOL;
