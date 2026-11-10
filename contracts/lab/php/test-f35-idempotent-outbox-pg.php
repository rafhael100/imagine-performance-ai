<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F35_INTENT_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
    throw new RuntimeException('F35 disposable Portal PostgreSQL CI only.');
}
$pdo=static fn(string $user,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
    $user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false]);
$writer=$pdo('perf_f35_writer','ci-f35-writer-only');
$other=$pdo('perf_f35_writer','ci-f35-writer-only');
$admin=$pdo('postgres','ci-f08-db-only');
$gate=new SyntheticIdempotentIntentGateway($writer);
$otherGate=new SyntheticIdempotentIntentGateway($other);
$wrongGate=new SyntheticIdempotentIntentGateway($admin);
$manifestA=hash('sha256','synthetic-campaign-read-intent-v1');
$manifestB=hash('sha256','synthetic-campaign-read-intent-v2');
$requests=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM perf_f35_intents.requests'
)->fetchColumn();
$outbox=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM perf_f35_intents.outbox'
)->fetchColumn();
$n=0;
$check=static function(string $name,mixed $got,mixed $want)use(&$n):void {
    if($got!==$want)throw new RuntimeException(
        'FAIL F35 '.$name.' actual '.var_export($got,true)
        .' expected '.var_export($want,true)
    );
    echo "PASS F35 ".++$n." $name".PHP_EOL;
};
$check('initial fixture request count',$requests(),0);
$check('initial fixture outbox count',$outbox(),0);
$check('one safe synthetic read intent reserved',
    $gate->reserve('f35-request-first',$manifestA),'reserved_synthetic');
$check('first committed request count',$requests(),1);
$check('first disabled outbox event committed atomically',$outbox(),1);
$check('same idempotency key and payload does not create another intent',
    $gate->reserve('f35-request-first',$manifestA),'already_reserved_synthetic');
$check('same key in another SQL connection also deduplicates',
    $otherGate->reserve('f35-request-first',$manifestA),'already_reserved_synthetic');
$check('same key with altered manifest refuses without side effect',
    $gate->reserve('f35-request-first',$manifestB),'idempotency_conflict');
$check('first logical command has one request and one outbox event',
    [$requests(),$outbox()],[1,1]);
$check('read-only scope cannot be escalated into Ads mutation',
    $gate->reserve('f35-other-scope',$manifestA,permission:'campaigns:write'),
    'invalid_scope_denied');
$check('tenant B cannot submit through A fixture',
    $gate->reserve('f35-other-tenant',$manifestA,tenant:'perf-demo-b'),
    'invalid_scope_denied');
$check('different Portal service rejected',
    $gate->reserve('f35-other-service',$manifestA,service:'svc-demo-b'),
    'invalid_scope_denied');
$check('other actor rejected',
    $gate->reserve('f35-other-actor',$manifestA,actor:'actor-demo-b'),
    'invalid_scope_denied');
$check('bad request reference rejected',
    $gate->reserve('!invalid',$manifestA),'invalid_scope_denied');
$check('bad manifest rejected',
    $gate->reserve('f35-request-bad','invalid-hash'),'invalid_scope_denied');
$check('invalid attempts never produce outbox records',
    [$requests(),$outbox()],[1,1]);

// A failure after a COMMITTED reservation cannot duplicate the outbox.
try {
    $check('another valid intent committed before mock application exception',
        $gate->reserve('f35-request-failure',$manifestB),'reserved_synthetic');
    throw new RuntimeException('simulated process died after commit');
} catch(RuntimeException $e) {
    $check('simulated exception occurred after commit',
        $e->getMessage(),'simulated process died after commit');
}
$check('retry after simulated process failure is idempotent',
    $otherGate->reserve('f35-request-failure',$manifestB),
    'already_reserved_synthetic');
$check('after retry exactly two pending intents and events',
    [$requests(),$outbox()],[2,2]);

$check('all outbox records are strictly dispatch disabled',
    (int)$admin->query(
        'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled=true'
    )->fetchColumn(),0);
$check('no pending intent has operational status',
    (int)$admin->query(
        "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
$check('outbox has one event for each committed intent',
    (int)$admin->query(
        'SELECT count(DISTINCT intent_id) FROM perf_f35_intents.outbox'
    )->fetchColumn(),2);

$permissionDenied=false;
try{$writer->query('SELECT * FROM perf_f35_intents.requests');}
catch(PDOException $e){$permissionDenied=$e->getCode()==='42501';}
$check('writer cannot directly read private intents table',$permissionDenied,true);
$permissionDenied=false;
try{$writer->exec('DELETE FROM perf_f35_intents.outbox');}
catch(PDOException $e){$permissionDenied=$e->getCode()==='42501';}
$check('writer cannot delete immutable outbox records',$permissionDenied,true);
$check('wrong database role rejected',
    $wrongGate->reserve('f35-request-admin',$manifestA),'wrong_database_or_role');
$writer->beginTransaction();
try {
    $check('cannot nest request reservation within external transaction',
        $gate->reserve('f35-request-nested',$manifestA),
        'nested_transaction_denied');
} finally {$writer->rollBack();}
$check('nested attempt did not create record',[$requests(),$outbox()],[2,2]);
putenv('PERF_F35_INTENT_LAB=0');
$check('closing flag disables gateway',
    $gate->reserve('f35-request-disabled',$manifestA),'intent_lab_disabled');
putenv('PERF_F35_INTENT_LAB=1');
putenv('APP_ENV=production');
$check('production always denied',
    $gate->reserve('f35-request-production',$manifestA),'intent_lab_disabled');
putenv('APP_ENV=testing');
$check('final pending and outbox counts unchanged',[$requests(),$outbox()],[2,2]);
echo "PASS F35 {$n} idempotent synthetic intents and disabled outbox checks".PHP_EOL;
