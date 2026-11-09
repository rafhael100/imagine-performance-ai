<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticIndependentReplayFence.php';

use Imagine\Protocol\Lab\SyntheticIndependentReplayFence;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F29_INDEPENDENT_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F1-29 requires disposable two-cluster CI.');
}
$key = (string)getenv('PERF_F26_ISSUER_KEY');
if (!preg_match('/^[0-9a-f]{64}$/D', $key)) {
    throw new RuntimeException('Missing CI signing key.');
}
$A = '11111111-1111-4111-8111-111111111111';
$B = '22222222-2222-4222-8222-222222222222';
$sign = static function(string $tenant, int $expiry, string $nonce) use($key):string {
    $data = implode('|', [$tenant, (string)$expiry, $nonce, 'perf-f26-ci']);
    return $data.'|'.hash_hmac('sha256', $data, hex2bin($key));
};
$pdo = static fn(string $db, int $port, string $user, string $pw): PDO => new PDO(
    'pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
    $user, $pw, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$p = $pdo('perf_test_campaign_registry',5432,'perf_campaign_test_user','ci-f22-role-only');
$pa = $pdo('perf_test_campaign_registry',5432,'postgres','ci-f22-db-only');
$a = $pdo('perf_f29_authority_ci',5433,'perf_f29_consumer','ci-f29-consumer');
$aa = $pdo('perf_f29_authority_ci',5433,'postgres','ci-f29-auth-db-only');
$uow = new SyntheticIndependentReplayFence($p, $a);
$i=0;
$check=static function(string $name, mixed $actual, mixed $wanted) use(&$i): void {
    if ($actual !== $wanted) {
        throw new RuntimeException("FAIL F29 $name: actual="
            .var_export($actual,true).' expected='.var_export($wanted,true));
    }
    echo "PASS F29 ".++$i." $name".PHP_EOL;
};
$read = static fn(PDO $db):int => (int)$db->query(
    'SELECT count(*) FROM performance_managed_campaigns'
)->fetchColumn();
$ledger = static fn():int => (int)$aa->query(
    'SELECT count(*) FROM perf_f29_authority.consumed_nonce'
)->fetchColumn();
$fp = (string)$pa->query('SELECT system_identifier FROM pg_control_system()')->fetchColumn();
$fa = (string)$aa->query('SELECT system_identifier FROM pg_control_system()')->fetchColumn();
$check('two physical postgres cluster IDs are distinct',$fp!==$fa,true);
$check('independent authority ledger begins empty',$ledger(),0);
$check('source database does not contain authority schema',
    $pa->query("SELECT to_regclass('perf_f29_authority.consumed_nonce') IS NULL")->fetchColumn(),true);
$check('role cannot access tenants without signed claim',$read($p),0);
$expiry=time()+150;
$abort = $sign($A,$expiry,'f29-aborted-transaction');
$failed = $uow->execute($abort,static function(PDO $db):never {
    $db->query('SELECT count(*) FROM performance_managed_campaigns');
    throw new RuntimeException('Rollback local simulated');
});
$check('valid token consumed but local callback rolled back',
    $failed['state'],'performance_rolled_back_nonce_retained');
$check('authority nonce survived local rollback',$ledger(),1);
$check('same token cannot be replayed after rollback',
    $uow->execute($abort,$read)['state'],'authority_denied_or_replay');
$check('modified expiration with same nonce remains denied',
    $uow->execute($sign($A,$expiry+1,'f29-aborted-transaction'),$read)['state'],
    'authority_denied_or_replay');
$check('replays do not add ledger rows',$ledger(),1);
$check('rollback cleared Performance SQL claim',$read($p),0);

$good=$sign($A,$expiry,'f29-fresh-transaction');
$success=$uow->execute($good,$read);
$check('fresh authorized nonce allows isolated read',
    $success['state'],'allowed_synthetic_only');
$check('read sees exactly two tenant A campaigns',$success['value'],2);
$check('second committed nonce exists in authority',$ledger(),2);
$check('successful token replay now denied',
    $uow->execute($good,$read)['state'],'authority_denied_or_replay');
$check('cross-tenant signed B refused',
    $uow->execute($sign($B,$expiry,'f29-tenant-b'),$read)['state'],
    'authority_denied_or_replay');
$check('expired A signed token refused',
    $uow->execute($sign($A,time()-5,'f29-expired-a'),$read)['state'],
    'authority_denied_or_replay');
$tampered=explode('|',$sign($A,$expiry,'f29-forged-a'));
$tampered[0]=$B;
$check('tampered A-to-B token refused',
    $uow->execute(implode('|',$tampered),$read)['state'],
    'authority_denied_or_replay');
$check('invalid attempts never alter independent ledger',$ledger(),2);

$badRole=new SyntheticIndependentReplayFence($p,$aa);
$check('admin authority PDO cannot be used by application',
    $badRole->execute($sign($A,$expiry,'f29-admin-ref'),$read)['state'],
    'wrong_database_or_role');
$denied=false;
try {
    $a->query('SELECT count(*) FROM perf_f29_authority.consumed_nonce');
} catch(PDOException $e) {
    $denied=$e->getCode()==='42501';
}
$check('consumer role cannot read nonce ledger',$denied,true);
$denied=false;
try {
    $a->query('SELECT secret FROM perf_f29_authority.issuer');
} catch(PDOException $e) {
    $denied=$e->getCode()==='42501';
}
$check('consumer role cannot read HMAC issuer secret',$denied,true);
$p->beginTransaction();
try {
    $check('nested local tx never consumes nonce',
        $uow->execute($sign($A,$expiry,'f29-nested'),$read)['state'],
        'nested_transaction_denied');
} finally {
    $p->rollBack();
}
$check('nested tx left ledger unchanged',$ledger(),2);
$check('campaigns still cannot dispatch Ads',
    (int)$pa->query(
        "SELECT count(*) FROM performance_managed_campaigns WHERE dispatch_state<>'disabled'"
    )->fetchColumn(),0);
putenv('PERF_F29_INDEPENDENT_LAB=0');
$check('lab gate closes fail-closed',
    $uow->execute($sign($A,$expiry,'f29-disabled'),$read)['state'],
    'lab_disabled');
$check('connection stays devoid of signed context',$read($p),0);
echo "PASS F29 {$i} separate authority nonce-fence assertions".PHP_EOL;
