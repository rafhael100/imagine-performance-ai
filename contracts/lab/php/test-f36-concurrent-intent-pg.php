<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;

$phase=$argv[1]??'';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F36_CONCURRENCY_LAB')!=='1'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!in_array($phase,['before','after_crash','after_race'],true)) {
    throw new RuntimeException('F36 CI-only validation phases.');
}
$admin=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
    'postgres','ci-f08-db-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_EMULATE_PREPARES=>false]);
$writer=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
    'perf_f35_writer','ci-f35-writer-only',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$gateway=new SyntheticIdempotentIntentGateway($writer);
$hash=hash('sha256','f36-concurrent-synthetic-read-v1');
$count=static fn(string $table):int => (int)$admin->query('SELECT count(*) FROM '.$table)->fetchColumn();
$requests=static fn():int=>$count('perf_f35_intents.requests');
$events=static fn():int=>$count('perf_f35_intents.outbox');
$has=static function(string $request)use($admin):int {
    $q=$admin->prepare('SELECT count(*) FROM perf_f35_intents.requests WHERE request_ref=?');
    $q->execute([$request]);
    return (int)$q->fetchColumn();
};
$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void {
    if($got!==$expected){
        throw new RuntimeException("FAIL F36 $phase $name got="
            .var_export($got,true)." expected=".var_export($expected,true));
    }
    echo "PASS F36 ".++$n." $name".PHP_EOL;
};
switch($phase){
case 'before':
    $check('F35 baseline committed requests',$requests(),2);
    $check('F35 baseline committed disabled outbox events',$events(),2);
    $check('race key does not exist before concurrency',$has('f36-race-same-key'),0);
    $check('crash key does not exist before uncommitted worker',$has('f36-crash-before-commit'),0);
    break;
case 'after_crash':
    $check('aborted worker transaction left no committed request',$has('f36-crash-before-commit'),0);
    $check('aborted worker transaction left total pending unchanged',$requests(),2);
    $check('aborted worker transaction left outbox unchanged',$events(),2);
    $check('next worker may legitimately reserve this not-committed key',
        $gateway->reserve('f36-crash-before-commit',$hash),'reserved_synthetic');
    $check('retried key now durable in requests',$has('f36-crash-before-commit'),1);
    $check('retried key created exactly one outbox event',[$requests(),$events()],[3,3]);
    $check('second retry cannot duplicate entry',
        $gateway->reserve('f36-crash-before-commit',$hash),'already_reserved_synthetic');
    break;
case 'after_race':
    $check('all racing requests produced exactly one row',$has('f36-race-same-key'),1);
    $check('total unique requests includes one race and one recovered crash',$requests(),4);
    $check('one event per committed reservation',$events(),4);
    $check('a later independent connection sees the same reserved state',
        $gateway->reserve('f36-race-same-key',$hash),'already_reserved_synthetic');
    $check('altering the racing manifest is a conflict',
        $gateway->reserve('f36-race-same-key',hash('sha256','f36-injected-change')),
        'idempotency_conflict');
    $check('no event is dispatch-enabled',
        (int)$admin->query('SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled')->fetchColumn(),0);
    $check('all records remain pending synthetic only',
        (int)$admin->query("SELECT count(*) FROM perf_f35_intents.requests WHERE state <> 'pending_synthetic'")->fetchColumn(),0);
    $check('distinct outbox intents equals committed requests',
        (int)$admin->query('SELECT count(DISTINCT intent_id) FROM perf_f35_intents.outbox')->fetchColumn(),4);
    break;
}
echo "PASS F36 phase={$phase} count={$n} synthetic process race only".PHP_EOL;
