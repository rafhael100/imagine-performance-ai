<?php
declare(strict_types=1);

/**
 * FAST-PERF F1-51: disposable SQL credential lockdown after the complete
 * adversarial F30-F50 suite. CI deliberately REVOKES old EXECUTE privileges
 * rather than allowing any permit observation/issuance without a real
 * anti-rollback epoch and enforced external authority.
 *
 * This does not configure production IAM, GitHub branch protection or
 * prevent superusers from granting rights again. No ads or deployment.
 */
require_once __DIR__.'/SyntheticRestoreSentinelAdmission.php';
require_once __DIR__.'/SyntheticExternalAtomicPermitObserver.php';
require_once __DIR__.'/SyntheticServerClockPermitGateway.php';
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';

use Imagine\Protocol\Lab\SyntheticRestoreSentinelAdmission;
use Imagine\Protocol\Lab\SyntheticExternalAtomicPermitObserver;
use Imagine\Protocol\Lab\SyntheticServerClockPermitGateway;
use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('GITHUB_ACTIONS')!=='true'
 ||getenv('PERF_F51_LOCKDOWN_LAB')!=='1'
 ||getenv('PERF_F50_RESTORE_SENTINEL_LAB')!=='1'
 ||getenv('PERF_F47_EXTERNAL_ATOMIC_LAB')!=='1'
 ||getenv('PERF_F46_SERVER_CLOCK_LAB')!=='1'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
 throw new RuntimeException('F51 lockdown must run ONLY inside three disposable CI SQL stores');
}
$connect=static fn(string $db,int $port,string $user,string $password):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
  $user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_EMULATE_PREPARES=>false]);
$a=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$b=$connect('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$c=$connect('perf_f50_sentinel_ci',5434,'postgres','ci-f50-sentinel-db-only');

$oldObserve=$connect('perf_f31_nonce_ci',5433,'perf_f47_observer','ci-f47-observer-only');
$oldMint=$connect('perf_f31_nonce_ci',5433,'perf_f46_issuer','ci-f46-issuer-only');
$oldConsume=$connect('portal_perf_auth_lab',5432,'perf_f46_consumer','ci-f46-consumer-only');
$oldReserve=$connect('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only');
$oldSentinel=$connect('perf_f50_sentinel_ci',5434,'perf_f50_observer','ci-f50-observer-only');

$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void{
 if($got!==$expected)throw new RuntimeException(
   'FAIL F51 '.$name.' got='.var_export($got,true)
     .' expected='.var_export($expected,true)
 );
 echo 'PASS F51 '.++$n.' '.$name.PHP_EOL;
};
$functionAllowed=static function(PDO $admin,string $role,string $f):bool{
 $q=$admin->prepare(
   'SELECT has_function_privilege(?, ?, ?)'
 );
 $q->execute([$role,$f,'EXECUTE']);
 $result=$q->fetchColumn();
 return in_array($result,[true,'t','1',1],true);
};
$expectPermissionDenied=static function(PDO $db,string $sql):bool{
 try{$db->query($sql);return false;}
 catch(PDOException $e){return $e->getCode()==='42501';}
};
$check('source still shows mock release epoch twelve',
  (string)$a->query('SELECT perf_f41_recovery.read_state()')->fetchColumn(),
  'released_mock:12');
$check('independent sentinel still floors observations at epoch twelve',
  (int)$c->query('SELECT minimum_epoch FROM perf_f50_sentinel.floor')->fetchColumn(),12);

$grants=[
 [$a,'perf_f47_observer','perf_f47_authority.observe(text)','direct F47 observation'],
 [$a,'perf_f46_issuer','perf_f46_issuer.mint_server(text,bigint,text,bigint,text,bigint)','old F46 issuer'],
 [$a,'perf_f45_issuer','perf_f45_issuer.mint(text,bigint,text,bigint,bigint,text)','old F45 issuer'],
 [$b,'perf_f46_consumer','perf_f46_admission.consume_server(text,bigint)','old local F46 consumer'],
 [$b,'perf_f45_consumer','perf_f45_admission.consume(text,bigint)','old F45 consumer'],
 [$b,'perf_f43_sql_consumer','perf_f43_sql_guard.claim(text,bigint)','old F43 direct claim'],
 [$b,'perf_f42_admission','perf_f38_fenced.claim(text,bigint)','old F42 raw claim'],
 [$b,'perf_f38_fenced_worker','perf_f38_fenced.claim(text,bigint)','old F38 raw claim'],
 [$b,'perf_f35_writer','perf_f35_intents.reserve(text,text,text,text,text,text)','F35 synthetic reservation'],
 [$c,'perf_f50_observer','perf_f50_sentinel.check_and_reserve(bigint,text)','F50 sentinel admission']
];
foreach($grants as [$admin,$role,$func,$label]){
 $check('no EXECUTE privilege for '.$label,
    $functionAllowed($admin,$role,$func),false);
}
$check('direct F47 function denied by SQL engine',
    $expectPermissionDenied($oldObserve,
     "SELECT perf_f47_authority.observe('f45|fake')"),true);
$check('direct F46 mint denied by SQL engine',
    $expectPermissionDenied($oldMint,
     "SELECT perf_f46_issuer.mint_server('claim',0,'worker-f38-a',0,"
      ."'f45-f51-disabled-aaaa',1000000000)"),true);
$check('direct F50 sentinel admission denied by SQL engine',
    $expectPermissionDenied($oldSentinel,
     "SELECT perf_f50_sentinel.check_and_reserve("
      ."12,'f45-f51-disabled-aaaa')"),true);
$check('synthetic writer can no longer reserve an intent',
    $expectPermissionDenied($oldReserve,
     "SELECT perf_f35_intents.reserve("
      ."'perf-demo-a','svc-demo-a','actor-demo-a',"
      ."'f51-test-disabled','campaigns:read',"
      ."'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')"),
    true);

$observer=new SyntheticExternalAtomicPermitObserver($oldObserve);
$check('legacy F47 PHP observer fails closed after role revoke',
    $observer->observe('malformed'),'external_atomic_unavailable');
$signed=new SyntheticServerClockPermitGateway($oldMint,$oldConsume);
$check('legacy F46 PHP issuer fails closed after role revoke',
    $signed->mint('claim',0,'worker-f38-a',0,
      'f45-f51-old-issuer-aaaa',1000000000),
    'server_issuer_unavailable');
$check('legacy F46 PHP consumer fails closed after role revoke',
    $signed->consume('malformed',1000000000),
    'server_admission_unavailable');
$sentinel=new SyntheticRestoreSentinelAdmission($oldObserve,$oldSentinel);
$fakeToken='f45|claim|0|worker-f38-a|0|12|1800000000|1800000030|'
          .'f45-f51-disabled-aaaa|'.str_repeat('a',64);
$check('F50 facade fails closed when sentinel EXECUTE revoked',
    $sentinel->observe($fakeToken),'sentinel_unavailable');

$check('real SQL outbox check constraint still forbids dispatch',
    (int)$b->query(
       'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
    )->fetchColumn(),0);
$check('synthetic requests never transitioned to operation',
    (int)$b->query(
      "SELECT count(*) FROM perf_f35_intents.requests "
      ."WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
$check('one-way third domain highwater remains intact after lockdown',
    (int)$c->query(
     'SELECT minimum_epoch FROM perf_f50_sentinel.floor'
    )->fetchColumn(),12);
$check('F47 receipts were not erased during lockdown',
    (int)$a->query(
     'SELECT count(*) FROM perf_f47_authority.observed'
    )->fetchColumn()>0,true);

putenv('APP_ENV=production');
$check('production still refuses synthetic restore sentinel',
   $sentinel->observe($fakeToken),'restore_sentinel_lab_disabled');
$check('production still refuses synthetic F47 observer',
   $observer->observe($fakeToken),'external_atomic_lab_disabled');
putenv('APP_ENV=testing');
echo "PASS F51 {$n} SQL privilege lockdown assertions: NO-GO remains in effect".PHP_EOL;
