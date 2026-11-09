<?php

declare(strict_types=1);

require_once __DIR__.'/IndependentClusterSyntheticFence.php';

use Imagine\Protocol\Lab\IndependentClusterSyntheticFence;

$phase=getenv('PERF_F20_PHASE');
if (PHP_SAPI !== 'cli'
    || !in_array($phase,['baseline','revoked','delivered','restored','source_outage'],true)
    || getenv('PERF_F20_DUAL_CLUSTER_LAB') !== '1'
    || getenv('PORTAL_F20_PORT') !== '5432'
    || getenv('PERF_F20_PORT') !== '5433'
    || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('F20 requires two isolated CI PostgreSQL instances.');
}
$srcAdminPass=getenv('PORTAL_F20_ADMIN_PASS');
$dstAdminPass=getenv('PERF_F20_ADMIN_PASS');
if ($srcAdminPass!=='portal-ci-f20-only'
    || $dstAdminPass!=='performance-ci-f20-only') {
    throw new RuntimeException('Unexpected disposable database passwords.');
}
$connect=static fn (int $port,string $db,string $role,string $password):PDO => new PDO(
    'pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
    $role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$src=$connect(5432,'portal_f20_test','portal_f20_reader','ci-only-source-reader-f20');
$dst=$connect(5433,'perf_f20_test','perf_f20_writer','ci-only-motor-writer-f20');
$srcAdmin=$connect(5432,'portal_f20_test','postgres',$srcAdminPass);
$dstAdmin=$connect(5433,'perf_f20_test','postgres',$dstAdminPass);
$fence=new IndependentClusterSyntheticFence($src,$dst);
$source=static fn (string $s):array => $srcAdmin->query(
    "SELECT service_ref,organization_ref,tenant_ref,active,
            current_revision,recovery_epoch
     FROM portal_f20.mock_authority WHERE service_ref='".$s."'"
)->fetch(PDO::FETCH_ASSOC);
$main=static fn (string $s):array => $dstAdmin->query(
    "SELECT service_ref,organization_ref,tenant_ref,active,
            grant_revision,recovery_epoch
     FROM perf_f20.mock_grants WHERE service_ref='".$s."'"
)->fetch(PDO::FETCH_ASSOC);
$receipts=static fn ():int => (int)$dstAdmin->query(
    'SELECT count(*) FROM perf_f20.mock_receipts'
)->fetchColumn();
$active=static fn (mixed $x):bool => in_array($x,[true,'t','1',1],true);
$n=0;
$check=static function(string $label,mixed $got,mixed $want) use(&$n,$phase):void {
    if($got!==$want){
        throw new RuntimeException('FAIL F20 '.$phase.' '.$label.' got '
            .var_export($got,true).' expected '.var_export($want,true));
    }
    ++$n;
    echo "PASS F20 $phase $n $label\n";
};

switch ($phase) {
    case 'baseline':
        $check('01 initial source grant B active', $active($source('svc-demo-b')['active']),true);
        $check('02 initial destination grant B active', $active($main('svc-demo-b')['active']),true);
        $check('03 both initial grant revisions are 1',
            [(int)$source('svc-demo-b')['current_revision'],
                (int)$main('svc-demo-b')['grant_revision']],[1,1]);
        $check('04 exact initial epoch 1 in both clusters',
            [(int)$source('svc-demo-b')['recovery_epoch'],
                (int)$main('svc-demo-b')['recovery_epoch']],[1,1]);
        $check('05 domain-only synthetic admission before test revoke',
            $fence->admission(),'admitted_synthetic_only');
        $check('06 source reader cannot see tenant A',
            (int)$src->query(
                "SELECT count(*) FROM portal_f20.mock_authority WHERE service_ref='svc-demo-a'"
            )->fetchColumn(),0);
        $check('07 destination writer cannot see tenant A',
            (int)$dst->query(
                "SELECT count(*) FROM perf_f20.mock_grants WHERE service_ref='svc-demo-a'"
            )->fetchColumn(),0);
        $check('08 initial durable receipts empty',$receipts(),0);
        break;

    case 'revoked':
        $check('09 source revocation committed', $active($source('svc-demo-b')['active']),false);
        $check('10 source revision advanced to 2',
            (int)$source('svc-demo-b')['current_revision'],2);
        $check('11 source epoch independently advanced to 2',
            (int)$source('svc-demo-b')['recovery_epoch'],2);
        $check('12 destination remains stale active',
            $active($main('svc-demo-b')['active']),true);
        $check('13 stale destination cannot admit',
            $fence->admission(),'cluster_revision_mismatch');
        $check('14 source role cannot mutate authority',
            (int)$src->query(
                "SELECT count(*) FROM portal_f20.mock_authority WHERE service_ref='svc-demo-b'"
            )->fetchColumn(),1);
        break;

    case 'delivered':
        $check('15 source still revoked', $active($source('svc-demo-b')['active']),false);
        $check('16 destination now revoked',
            $active($main('svc-demo-b')['active']),false);
        $check('17 destination revision advanced', (int)$main('svc-demo-b')['grant_revision'],2);
        $check('18 destination epoch advanced', (int)$main('svc-demo-b')['recovery_epoch'],2);
        $check('19 receipt committed after revocation', $receipts(),1);
        $check('20 source revocation blocks admission even when revisions match',
            $fence->admission(),'cluster_source_revoked');
        break;

    case 'restored':
        $check('21 source distinct container survived motor restore',
            $active($source('svc-demo-b')['active']),false);
        $check('22 source revision retained 2',
            (int)$source('svc-demo-b')['current_revision'],2);
        $check('23 source epoch retained 2',
            (int)$source('svc-demo-b')['recovery_epoch'],2);
        $check('24 old motor snapshot restored active B',
            $active($main('svc-demo-b')['active']),true);
        $check('25 old motor snapshot restored revision 1',
            (int)$main('svc-demo-b')['grant_revision'],1);
        $check('26 old motor snapshot lost replay receipt', $receipts(),0);
        $check('27 old local grant is denied by separate source',
            $fence->admission(),'cluster_revision_mismatch');
        $check('28 reconciler reads revocation from separate instance',
            $fence->reconcileRevokedB(),'cluster_reconciled_synthetic_only');
        $check('29 local B is revoked after recovery',
            $active($main('svc-demo-b')['active']),false);
        $check('30 local B restored to revision 2',
            (int)$main('svc-demo-b')['grant_revision'],2);
        $check('31 local epoch restored to source epoch 2',
            (int)$main('svc-demo-b')['recovery_epoch'],2);
        $check('32 local admission still denied for revoked source',
            $fence->admission(),'cluster_source_revoked');
        $check('33 repeat reconciliation is idempotent',
            $fence->reconcileRevokedB(),'cluster_already_reconciled_demo');
        $check('34 source tenant A remains independently revoked',
            $active($source('svc-demo-a')['active']),false);
        $check('35 motor tenant A retains its restore revision 7',
            (int)$main('svc-demo-a')['grant_revision'],7);
        $check('36 reconcile does not forge a replay receipt',$receipts(),0);
        break;

    case 'source_outage':
        $check('37 admin still observes the independent revocation',
            $active($source('svc-demo-b')['active']),false);
        $check('38 reader SELECT outage causes fail-closed admission',
            $fence->admission(),'cluster_source_unavailable');
        $check('39 source outage disallows reconciliation',
            $fence->reconcileRevokedB(),'cluster_source_unavailable');
        $check('40 local revision not modified during source outage',
            (int)$main('svc-demo-b')['grant_revision'],2);
        $check('41 local remains revoked during source outage',
            $active($main('svc-demo-b')['active']),false);
        $check('42 no fallback to old receipt or client cache',$receipts(),0);
        break;
}
echo "PASS F20 {$n} assertions in phase {$phase}; two PG16 containers on distinct ports\n";
