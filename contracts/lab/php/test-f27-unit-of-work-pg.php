<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticSignedTenantUnitOfWork.php';

use Imagine\Protocol\Lab\SyntheticSignedTenantUnitOfWork;

if (PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F27_UNIT_OF_WORK_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F27 only runs inside disposable PostgreSQL CI.');
}
$secret=(string)getenv('PERF_F26_ISSUER_KEY');
if (!preg_match('/^[a-f0-9]{64}$/D',$secret)) {
    throw new RuntimeException('CI ephemeral test signing key missing.');
}
$A='11111111-1111-4111-8111-111111111111';
$B='22222222-2222-4222-8222-222222222222';
$sign=static function(string $tenant,int $expires,string $nonce) use($secret):string {
    $payload=implode('|',[$tenant,(string)$expires,$nonce,'perf-f26-ci']);
    return $payload.'|'.hash_hmac('sha256',$payload,hex2bin($secret));
};
$validA=$sign($A,time()+120,'nonce-f27-aaaa');
$validB=$sign($B,time()+120,'nonce-f27-bbbb');
$expired=$sign($A,time()-2,'nonce-f27-expire');
$pdo=static fn(string $role,string $pass):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $role,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$conn=$pdo('perf_campaign_test_user','ci-f22-role-only');
$admin=$pdo('postgres','ci-f22-db-only');
$unit=new SyntheticSignedTenantUnitOfWork($conn);
$n=0;
$check=static function(string $name,mixed $actual,mixed $expected) use(&$n):void {
    if ($actual!==$expected) {
        throw new RuntimeException('FAIL F27 '.$name.' got '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F27 $n $name\n";
};
$get=static fn(string $table) => static fn(PDO $db):int =>
    (int)$db->query('SELECT count(*) FROM '.$table)->fetchColumn();
$noContext=static function() use($conn):int {
    return (int)$conn->query(
        'SELECT count(*) FROM performance_managed_campaigns'
    )->fetchColumn();
};

$check('01 new pooled connection starts with no tenant', $noContext(),0);
$ok=$unit->execute($validA,$get('performance_managed_campaigns'));
$check('02 valid A claim succeeds within transaction',$ok['state'],'allowed_synthetic_only');
$check('03 valid A claim sees only A two demo campaigns',$ok['value'],2);
$check('04 after successful commit same SQL connection has no tenant',$noContext(),0);
$check('05 verified_tenant cleared on same reused connection',
    $conn->query('SELECT perf_f26_identity.verified_tenant()')->fetchColumn(),null);
$ok=$unit->execute($validA,$get('performance_campaign_intents'));
$check('06 same connection safe to reuse with fresh signed A context',
    $ok['state'],'allowed_synthetic_only');
$check('07 A sees exactly four synthetic request rows',$ok['value'],4);
$check('08 after second commit claim remains absent',$noContext(),0);

$wrong=$unit->execute($validB,$get('performance_managed_campaigns'));
$check('09 valid B token is denied by bound A unit of work',
    $wrong['state'],'signed_tenant_denied');
$check('10 B claim denial never invokes callback',$wrong['value'],null);
$check('11 B signed claim cannot linger on pooled connection',$noContext(),0);
$forged=explode('|',$validA);$forged[0]=$B;
$check('12 forged A-to-B claim rejected',
    $unit->execute(implode('|',$forged),$get('performance_managed_campaigns'))['state'],
    'signed_tenant_denied');
$check('13 expired signed A claim refused',
    $unit->execute($expired,$get('performance_managed_campaigns'))['state'],
    'signed_tenant_denied');
$check('14 empty claim refused',
    $unit->execute('',$get('performance_managed_campaigns'))['state'],
    'unit_of_work_disabled');
$check('15 oversized claim refused',
    $unit->execute(str_repeat('x',301),$get('performance_managed_campaigns'))['state'],
    'unit_of_work_disabled');
$check('16 invalid claim does not persist on connection',$noContext(),0);

$conn->beginTransaction();
try {
    $check('17 nested caller transaction cannot be entered by unit of work',
        $unit->execute($validA,$get('performance_managed_campaigns'))['state'],
        'nested_transaction_denied');
} finally {
    $conn->rollBack();
}
$check('18 after nested denial no claim remains',$noContext(),0);

$forced=$unit->execute($validA,static function(PDO $db):never {
    $db->exec("SELECT set_config('app.performance_tenant_id',
        '22222222-2222-4222-8222-222222222222',true)");
    throw new RuntimeException('synthetic failure after tampered GUC');
});
$check('19 exception forces rollback',$forced['state'],'transaction_rolled_back');
$check('20 exception does not leave signed claim on same connection',$noContext(),0);

$stillA=$unit->execute($validA,static function(PDO $db):int {
    $s=$db->prepare("SELECT set_config('app.performance_tenant_id', ?, true)");
    $s->execute(['22222222-2222-4222-8222-222222222222']);
    return (int)$db->query(
        "SELECT count(*) FROM performance_managed_campaigns"
    )->fetchColumn();
});
$check('21 arbitrary B GUC inside signed A unit does not override HMAC tenant',
    $stillA['value'],2);
$check('22 no signed context persists after altered GUC transaction',$noContext(),0);

$attack=$unit->execute($validA,static function(PDO $db) use($B):void {
    $p=$db->prepare(
        "INSERT INTO performance_managed_campaigns
         (id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,
          created_at,updated_at)
         VALUES (?,?,?,?,?,?,clock_timestamp(),clock_timestamp())"
    );
    $p->execute([
        'f7777777-7777-4777-8777-777777777777',$B,'svc-demo-b','meta',
        'cam-f27-cross','Cross-tenant denied'
    ]);
});
$check('23 attempted B INSERT rolls back with fail-closed transaction',
    $attack['state'],'transaction_rolled_back');
$check('24 B campaign total unchanged after attempted cross-tenant INSERT',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$B}'"
    )->fetchColumn(),1);
$check('25 A campaign total unchanged too',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$A}'"
    )->fetchColumn(),2);
$check('26 rollback clears signed context after attempted cross-tenant write',
    $noContext(),0);

$adminRole=new SyntheticSignedTenantUnitOfWork($admin);
$check('27 superuser cannot be substituted as lab transaction actor',
    $adminRole->execute($validA,$get('performance_managed_campaigns'))['state'],
    'wrong_database_role');

putenv('PERF_F27_UNIT_OF_WORK_LAB=0');
$check('28 disabled lab flag refuses existing instance',
    $unit->execute($validA,$get('performance_managed_campaigns'))['state'],
    'unit_of_work_disabled');
putenv('PERF_F27_UNIT_OF_WORK_LAB=1');
$check('29 re-enabled lab can read A with fresh claim',
    $unit->execute($validA,$get('performance_managed_campaigns'))['value'],2);
$check('30 no claim remains after final successful operation',$noContext(),0);
$check('31 SQL application role is not superuser',
    $admin->query(
        "SELECT rolsuper FROM pg_roles WHERE rolname='perf_campaign_test_user'"
    )->fetchColumn(),false);
$check('32 all campaigns remain dispatch disabled',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE dispatch_state<>'disabled'"
    )->fetchColumn(),0);
echo "PASS F27 {$n} pooled-connection transaction-scoped signed tenant tests\n";
