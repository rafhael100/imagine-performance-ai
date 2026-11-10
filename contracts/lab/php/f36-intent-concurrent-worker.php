<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;

// F1-36: multiple independent PHP CLI processes on disposable PG, no Ads.
$mode=$argv[1]??'';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F36_CONCURRENCY_LAB')!=='1'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!in_array($mode,['race','crash_before_commit'],true)) {
    throw new RuntimeException('F36 accepts race/crash only in CI.');
}
$db=new PDO(
 'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=4',
 'perf_f35_writer','ci-f35-writer-only',
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
);
$hash=hash('sha256','f36-concurrent-synthetic-read-v1');
if($mode==='race') {
    // Every worker uses the same request+manifest, so exactly one should insert.
    $state=(new SyntheticIdempotentIntentGateway($db))
        ->reserve('f36-race-same-key',$hash);
    echo $state.PHP_EOL;
    if(!in_array($state,['reserved_synthetic','already_reserved_synthetic'],true)) {
        exit(2);
    }
    exit(0);
}
$db->beginTransaction();
$statement=$db->prepare('SELECT perf_f35_intents.reserve(?,?,?,?,?,?)');
$statement->execute([
 'perf-demo-a','svc-demo-a','actor-demo-a','f36-crash-before-commit',
 'campaigns:read',$hash
]);
if($statement->fetchColumn()!=='reserved_synthetic') {
    throw new RuntimeException('Expected reservation inside uncommitted transaction.');
}
echo "uncommitted_transaction_exits_without_commit".PHP_EOL;
fflush(STDOUT);
// Closing the PHP process discards this transaction on PostgreSQL.
// This proves the DB rollback, not a real worker crash or Ads recovery.
exit(0);
