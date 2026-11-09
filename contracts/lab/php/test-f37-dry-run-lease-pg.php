<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticDryRunOutboxLeaser.php';

use Imagine\Protocol\Lab\SyntheticDryRunOutboxLeaser;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F37_LEASE_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
    throw new RuntimeException('F37 dry-run only in disposable CI.');
}
$pdo=static fn(string $role,string $pw)=>new PDO(
 'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
 $role,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$worker=$pdo('perf_f37_lease_worker','ci-f37-lease-only');
$other=$pdo('perf_f37_lease_worker','ci-f37-lease-only');
$admin=$pdo('postgres','ci-f08-db-only');
$gate=new SyntheticDryRunOutboxLeaser($worker);
$gate2=new SyntheticDryRunOutboxLeaser($other);
$wrong=new SyntheticDryRunOutboxLeaser($admin);
$t=1800000000;$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void{
 if($got!==$expected){
  throw new RuntimeException("FAIL F37 $name got=".var_export($got,true)
   ." expected=".var_export($expected,true));
 }
 echo "PASS F37 ".++$n." $name".PHP_EOL;
};
$count=static fn(string $table):int =>
 (int)$admin->query('SELECT count(*) FROM '.$table)->fetchColumn();
$parse=static function(string $state):int{
 if(!preg_match('/^claimed_mock:([1-9][0-9]*)$/D',$state,$m))
  throw new RuntimeException('Expected purely fictional claimed id, got '.$state);
 return (int)$m[1];
};
$check('F35/F36 created four disabled outbox records',
 $count('perf_f35_intents.outbox'),4);
$check('dry-run lease table starts empty',
 $count('perf_f37_dry_run.leases'),0);
$first=$parse($gate->claim('worker-f37-a',$t));
$second=$parse($gate2->claim('worker-f37-b',$t));
$check('two independent connections select distinct outbox IDs',
 $first!==$second,true);
$check('two rows are only leased, never dispatched',
 $count('perf_f37_dry_run.leases'),2);
$check('original A cannot acknowledge after 30-second lease expired',
 $gate->acknowledge($first,'worker-f37-a',$t+31),
 'stale_lease_or_wrong_owner');
$check('another worker may safely dry-run reclaim expired lease',
 $parse($gate2->claim('worker-f37-b',$t+31)),$first);
$check('stale original owner cannot acknowledge after replacement',
 $gate->acknowledge($first,'worker-f37-a',$t+31),
 'stale_lease_or_wrong_owner');
$check('replacement owner may acknowledge only demo lease',
 $gate2->acknowledge($first,'worker-f37-b',$t+31),
 'acknowledged_mock');
$check('duplicate acknowledgement cannot repeat',
 $gate2->acknowledge($first,'worker-f37-b',$t+31),
 'stale_lease_or_wrong_owner');
$check('second original worker lease now expired',
 $gate2->acknowledge($second,'worker-f37-b',$t+31),
 'stale_lease_or_wrong_owner');
$check('third worker reclaims second expired lease',
 $parse($gate->claim('worker-f37-c',$t+31)),$second);
$check('third worker demo-acks second lease',
 $gate->acknowledge($second,'worker-f37-c',$t+31),
 'acknowledged_mock');
$third=$parse($gate->claim('worker-f37-c',$t+31));
$check('new dry-run worker claims previously unclaimed event',
 !in_array($third,[$first,$second],true),true);
$check('demo acknowledgement of third event',
 $gate->acknowledge($third,'worker-f37-c',$t+31),
 'acknowledged_mock');
$fourth=$parse($gate->claim('worker-f37-c',$t+31));
$check('fourth event distinct from all other claims',
 !in_array($fourth,[$first,$second,$third],true),true);
$check('demo acknowledgement of fourth event',
 $gate->acknowledge($fourth,'worker-f37-c',$t+31),
 'acknowledged_mock');
$check('no more dry-run events available',
 $gate->claim('worker-f37-c',$t+31),'none');
$check('all four claims acknowledged only as demo',
 (int)$admin->query("SELECT count(*) FROM perf_f37_dry_run.leases WHERE state='acknowledged_mock'")->fetchColumn(),4);
$check('no re-claim after ACK even when lease clock advances',
 $gate2->claim('worker-f37-b',$t+500),'none');
$check('user cannot request unauthorized owner name',
 $gate->claim('attacker',$t),'invalid_claim_denied');
$check('invalid simulated clock refused',
 $gate->claim('worker-f37-a',0),'invalid_claim_denied');
$check('wrong owner cannot ACK',
 $gate2->acknowledge($first,'worker-f37-a',$t+32),'stale_lease_or_wrong_owner');
$check('malformed ACK id rejected',
 $gate2->acknowledge(0,'worker-f37-a',$t+32),'invalid_ack_denied');
$check('superuser PDO cannot be the app lease actor',
 $wrong->claim('worker-f37-a',$t),'wrong_database_or_role');
$denied=false;
try{$worker->query('SELECT * FROM perf_f37_dry_run.leases');}
catch(PDOException $e){$denied=$e->getCode()==='42501';}
$check('worker cannot SELECT private lease table',$denied,true);
$denied=false;
try{$worker->exec('DELETE FROM perf_f35_intents.outbox');}
catch(PDOException $e){$denied=$e->getCode()==='42501';}
$check('worker cannot delete disabled outbox',$denied,true);
$check('every actual outbox still disabled',
 (int)$admin->query('SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled=true')->fetchColumn(),0);
$check('no operational status was recorded',
 (int)$admin->query("SELECT count(*) FROM perf_f35_intents.requests WHERE state <> 'pending_synthetic'")->fetchColumn(),0);
$worker->beginTransaction();
try{
 $check('nested transaction denied by facade',
  $gate->claim('worker-f37-a',$t),'nested_transaction_denied');
}finally{$worker->rollBack();}
putenv('PERF_F37_LEASE_LAB=0');
$check('lab feature switch denies all claims',
 $gate->claim('worker-f37-a',$t),'lease_lab_disabled');
putenv('PERF_F37_LEASE_LAB=1');
putenv('APP_ENV=production');
$check('production cannot claim',
 $gate->claim('worker-f37-a',$t),'lease_lab_disabled');
putenv('APP_ENV=testing');
echo "PASS F37 {$n} dry-run lease/reclaim/ACK tests, NO dispatch".PHP_EOL;
