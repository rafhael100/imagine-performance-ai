<?php

declare(strict_types=1);

require_once __DIR__.'/SyntheticPortalRestoreReconciler.php';
require_once __DIR__.'/SyntheticExternalRestoreFence.php';
require_once __DIR__.'/SyntheticIngressBridge.php';
require_once __DIR__.'/../SyntheticS2SProtocol.php';

use Imagine\Protocol\Lab\SyntheticPortalRestoreReconciler;
use Imagine\Protocol\Lab\SyntheticExternalRestoreFence;
use Imagine\Protocol\Lab\SyntheticS2SProtocol;

if (PHP_SAPI !== 'cli'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab'
    || getenv('PERF_F16_RESTORE_LAB') !== '1') {
    throw new RuntimeException('F1-16 requires disposable PostgreSQL CI.');
}
$pass = getenv('PGPASSWORD');
if (!is_string($pass) || $pass !== 'local-ci-only') {
    throw new RuntimeException('F1-16 expected test-only admin pass.');
}
$connect = static fn (string $db,string $user,string $password):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$db.';connect_timeout=3',
    $user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$sourceAdmin=$connect('portal_lab','postgres',$pass);
$mainAdmin=$connect('perf_test_relay_main','postgres',$pass);
$portalReader=$connect('portal_lab','portal_perf_source_reader','ci-only-portal-perf-reader');
$perfWriter=$connect('perf_test_relay_main','perf_test_wire_ingress','ci-only-direct-wire-ingress');
$reconciler=new SyntheticPortalRestoreReconciler($portalReader,$perfWriter);

$getMain = static function (string $ref) use ($mainAdmin):array {
    $st=$mainAdmin->prepare(
        'SELECT organization_ref,tenant_ref,active,grant_revision
         FROM perf_s2s_lab.mock_service_grants WHERE service_ref=?'
    );
    $st->execute([$ref]);
    return $st->fetch(PDO::FETCH_ASSOC);
};
$getSource = static function () use ($sourceAdmin):array {
    return $sourceAdmin->query(
        "SELECT service_ref,organization_ref,tenant_ref,active,min_revision
         FROM lab_portal_perf_authority WHERE service_ref='svc-demo-b'"
    )->fetch(PDO::FETCH_ASSOC);
};
$isActive = static fn (mixed $v):bool => in_array($v,[true,'t','1',1],true);
$n=0;
$check = static function(string $label,mixed $got,mixed $expected) use (&$n):void {
    if($got!==$expected){
        throw new RuntimeException('FAIL F16 '.$label.' got '.var_export($got,true)
            .' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F16 $n $label\n";
};

$check('01 backup really restored B active', $isActive($getMain('svc-demo-b')['active']), true);
$check('02 backup restored B revision 1', (int)$getMain('svc-demo-b')['grant_revision'],1);
$check('03 backup restored A to earlier revision 3',
    (int)$getMain('svc-demo-a')['grant_revision'],3);
$check('04 Portal authority independently retained revoked B state',
    $isActive($getSource()['active']),false);
$check('05 Portal authority retained revocation revision 2',
    (int)$getSource()['min_revision'],2);
$check('06 Portal source outbox was already ACKed',
    $sourceAdmin->query(
        "SELECT status FROM lab_portal_perf_revocation_outbox
         WHERE service_ref='svc-demo-b'"
    )->fetchColumn(),'acked');
$check('07 no pending outbox event exists to resend',
    (int)$sourceAdmin->query(
        "SELECT count(*) FROM lab_portal_perf_revocation_outbox
         WHERE status='pending' AND service_ref='svc-demo-b'"
    )->fetchColumn(),0);
$check('08 restored motor lost previous wire replay receipt',
    (int)$mainAdmin->query(
        'SELECT count(*) FROM perf_s2s_lab.mock_direct_wire_receipts'
    )->fetchColumn(),0);

$sourceAdmin->exec(
    'REVOKE SELECT ON lab_portal_perf_authority FROM portal_perf_source_reader'
);
$check('09 unavailable source rejects reconciliation',
    $reconciler->reconcileServiceB(),'restore_source_unavailable');
$check('10 fail closed leaves local revision unchanged',
    (int)$getMain('svc-demo-b')['grant_revision'],1);
$sourceAdmin->exec(
    'GRANT SELECT ON lab_portal_perf_authority TO portal_perf_source_reader'
);
$check('11 current authority reconciles restored B grant',
    $reconciler->reconcileServiceB(),'restore_reconciled_demo');
$check('12 B is inactive after reconciliation',
    $isActive($getMain('svc-demo-b')['active']),false);
$check('13 B advanced monotonically to revision 2',
    (int)$getMain('svc-demo-b')['grant_revision'],2);
$check('14 repeat reconciliation is idempotent',
    $reconciler->reconcileServiceB(),'restore_already_reconciled_demo');
$check('15 ACK remains ACKed, no redelivery required',
    $sourceAdmin->query(
        "SELECT status FROM lab_portal_perf_revocation_outbox
         WHERE service_ref='svc-demo-b'"
    )->fetchColumn(),'acked');
$check('16 no extra wire receipt was forged by reconciliation',
    (int)$mainAdmin->query(
        'SELECT count(*) FROM perf_s2s_lab.mock_direct_wire_receipts'
    )->fetchColumn(),0);
$check('17 A remains at restore revision (F16 cannot touch it)',
    (int)$getMain('svc-demo-a')['grant_revision'],3);
$check('18 direct SQL role still cannot see A under RLS',
    (int)$perfWriter->query(
        "SELECT count(*) FROM perf_s2s_lab.mock_service_grants
         WHERE service_ref='svc-demo-a'"
    )->fetchColumn(),0);

// Separate F1-10 authority in another DB (same test cluster) still has the
// A revocation, while the restored main A row appears active.
$mainRuntime=$connect('perf_test_relay_main','perf_s2s_lab_caller','ci-test-only-s2s-role');
$externalReader=$connect('perf_test_relay_authority','perf_test_fence_reader','ci-only-fence-reader');
$bytes=file_get_contents((string)getenv('PERF_SNAPSHOT_FIXTURE'));
if(!is_string($bytes)){
    throw new RuntimeException('Synthetic fixture unavailable for A check');
}
$key=random_bytes(32);
$signed=SyntheticS2SProtocol::mintForLab(
    'org-demo-a','perf-demo-a','svc-demo-a',
    'req-demo-f16-a','nonce-demo-f16-a',
    1799999940,1800000060,3,hash('sha256',$bytes),$key
);
$guard=new SyntheticExternalRestoreFence($mainRuntime,$externalReader);
$check('19 restored A active locally but blocked by separate lab fence',
    $guard->accept($signed,$key,'org-demo-a','perf-demo-a','svc-demo-a',
        hash('sha256',$bytes),3,1800000000),
    'external_fence_revoked');
$check('20 none of the tests had a commercial side effect',
    array_key_exists('payment_state',$signed) || array_key_exists('ads_account',$signed),
    false);
echo "PASS F16 $n restore recovery tests against current Portal synthetic authority\n";
