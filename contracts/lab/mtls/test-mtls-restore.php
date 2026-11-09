<?php

declare(strict_types=1);

/**
 * F1-21 — mTLS over localhost with two isolated PostgreSQL16 containers.
 * All certificates, roles and grants are fabricated and discarded by CI.
 */
require_once __DIR__.'/SyntheticMtlsPortalFence.php';

use Imagine\Protocol\Lab\SyntheticMtlsPortalFence;

$phase = getenv('PERF_F21_PHASE');
if (PHP_SAPI !== 'cli'
    || !in_array($phase, ['baseline','revoked','delivered','restored','outage'], true)
    || getenv('PERF_F21_MTLS_LAB') !== '1'
    || getenv('PORTAL_F20_PORT') !== '5432'
    || getenv('PERF_F20_PORT') !== '5433'
    || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('F21 must run in disposable two-container CI.');
}
$ca = getenv('PERF_F21_CA');
$cert = getenv('PERF_F21_CLIENT_CERT');
$key = getenv('PERF_F21_CLIENT_KEY');
$badCert = getenv('PERF_F21_ROGUE_CLIENT_CERT');
$badKey = getenv('PERF_F21_ROGUE_CLIENT_KEY');
$wrongCa = getenv('PERF_F21_UNKNOWN_CA');
foreach ([$ca,$cert,$key,$badCert,$badKey,$wrongCa] as $file) {
    if (!is_string($file) || !is_file($file)) {
        throw new RuntimeException('Missing ephemeral TLS fixture.');
    }
}
$sourcePort = 5432;
$targetPort = 5433;
$connect = static fn (int $port,string $db,string $user,string $pass):PDO => new PDO(
    "pgsql:host=127.0.0.1;port={$port};dbname={$db};connect_timeout=3",
    $user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$srcAdmin=$connect($sourcePort,'portal_f20_test','postgres','portal-ci-f20-only');
$targetAdmin=$connect($targetPort,'perf_f20_test','postgres','performance-ci-f20-only');
$target=$connect($targetPort,'perf_f20_test','perf_f20_writer','ci-only-motor-writer-f20');
$fence=new SyntheticMtlsPortalFence($target,$ca,$cert,$key);

$source = static fn ():array => $srcAdmin->query(
    "SELECT active,current_revision,recovery_epoch FROM portal_f20.mock_authority
     WHERE service_ref='svc-demo-b'"
)->fetch(PDO::FETCH_ASSOC);
$targetValue = static fn ():array => $targetAdmin->query(
    "SELECT active,grant_revision,recovery_epoch FROM perf_f20.mock_grants
     WHERE service_ref='svc-demo-b'"
)->fetch(PDO::FETCH_ASSOC);
$receipts = static fn ():int => (int)$targetAdmin->query(
    'SELECT count(*) FROM perf_f20.mock_receipts'
)->fetchColumn();
$active=static fn ($v):bool => in_array($v,[true,'t','1',1],true);
$n=0;
$check=static function(string $name,mixed $actual,mixed $expected) use(&$n,$phase):void {
    if($actual!==$expected){
        throw new RuntimeException('FAIL F21 '.$phase.' '.$name.' actual='
            .var_export($actual,true).' expected='.var_export($expected,true));
    }
    ++$n;
    echo "PASS F21 {$phase} {$n} {$name}\n";
};

switch ($phase) {
    case 'baseline':
        $check('01 TLS accepted for correct temporary CA and client',
            $fence->admission(),'admitted_synthetic_only');
        $rogue=new SyntheticMtlsPortalFence($target,$ca,$badCert,$badKey);
        $check('02 wrong signed client identity denied',
            $rogue->admission(),'mtls_authority_unavailable');
        $unknownCa=new SyntheticMtlsPortalFence($target,$wrongCa,$cert,$key);
        $check('03 unrelated server trust CA denied',
            $unknownCa->admission(),'mtls_authority_unavailable');
        $noClient=new SyntheticMtlsPortalFence($target,$ca,'/nonexistent-f21.pem','/nonexistent-key.pem');
        $check('04 missing client cert denied',
            $noClient->admission(),'mtls_authority_unavailable');
        $wrongKey=new SyntheticMtlsPortalFence($target,$ca,$cert,$badKey);
        $check('05 certificate/private-key mismatch denied',
            $wrongKey->admission(),'mtls_authority_unavailable');
        $check('06 no revocation introduced by TLS negatives',
            $active($targetValue()['active']),true);
        $check('07 source uses separate PG cluster revision 1',
            (int)$source()['current_revision'],1);
        $check('08 target uses separate PG cluster revision 1',
            (int)$targetValue()['grant_revision'],1);
        $check('09 target RLS denies selecting tenant A',
            (int)$target->query(
                "SELECT count(*) FROM perf_f20.mock_grants WHERE service_ref='svc-demo-a'"
            )->fetchColumn(),0);
        $check('10 no receipt during baseline',$receipts(),0);
        $check('11 live source with active service rejects reconciliation',
            $fence->reconcileRevoked(),'mtls_authority_not_revoked');
        putenv('PERF_F21_MTLS_LAB=0');
        $check('12 policy disabled outside lab flag',
            $fence->admission(),'mtls_lab_disabled');
        putenv('PERF_F21_MTLS_LAB=1');
        break;

    case 'revoked':
        $check('13 source actually revoked',
            $active($source()['active']),false);
        $check('14 source new revision 2',
            (int)$source()['current_revision'],2);
        $check('15 source new recovery epoch 2',
            (int)$source()['recovery_epoch'],2);
        $check('16 restored authority source denies stale motor over mTLS',
            $fence->admission(),'mtls_revision_mismatch');
        $check('17 destination still active prior to delivery',
            $active($targetValue()['active']),true);
        break;

    case 'delivered':
        $check('18 destination updated to revoke',
            $active($targetValue()['active']),false);
        $check('19 destination revision 2',
            (int)$targetValue()['grant_revision'],2);
        $check('20 destination epoch 2',
            (int)$targetValue()['recovery_epoch'],2);
        $check('21 revocation remains denied across valid TLS',
            $fence->admission(),'mtls_source_revoked');
        $check('22 source trusted but not approving operations',
            $fence->reconcileRevoked(),'mtls_already_reconciled_demo');
        $check('23 one receipt before restore',$receipts(),1);
        break;

    case 'restored':
        $check('24 independent Portal source retained revoke',
            $active($source()['active']),false);
        $check('25 independent Portal source remains revision 2',
            (int)$source()['current_revision'],2);
        $check('26 destination historical backup restored active',
            $active($targetValue()['active']),true);
        $check('27 destination historical revision 1',
            (int)$targetValue()['grant_revision'],1);
        $check('28 destination previous replay receipts lost',$receipts(),0);
        $check('29 healthy TLS still denies stale destination',
            $fence->admission(),'mtls_revision_mismatch');
        $check('30 mTLS source state reconciles B',
            $fence->reconcileRevoked(),'mtls_reconciled_demo');
        $check('31 destination B revoked after reconciliation',
            $active($targetValue()['active']),false);
        $check('32 destination B now revision 2',
            (int)$targetValue()['grant_revision'],2);
        $check('33 epoch current after reconciliation',
            (int)$targetValue()['recovery_epoch'],2);
        $check('34 now aligned but revoked remains denied',
            $fence->admission(),'mtls_source_revoked');
        $check('35 repeat TLS reconcile idempotent',
            $fence->reconcileRevoked(),'mtls_already_reconciled_demo');
        $check('36 destination A remains revision 7',
            (int)$targetAdmin->query(
                "SELECT grant_revision FROM perf_f20.mock_grants
                 WHERE service_ref='svc-demo-a'"
            )->fetchColumn(),7);
        $check('37 no receipt invented by reconciliation',$receipts(),0);
        break;

    case 'outage':
        $check('38 source server offline causes default deny',
            $fence->admission(),'mtls_authority_unavailable');
        $check('39 source outage prevents reconciliation',
            $fence->reconcileRevoked(),'mtls_authority_unavailable');
        $check('40 local revoked state preserved',
            $active($targetValue()['active']),false);
        $check('41 destination revision unchanged',
            (int)$targetValue()['grant_revision'],2);
        $check('42 destination A remains isolated and untouched',
            (int)$targetAdmin->query(
                "SELECT grant_revision FROM perf_f20.mock_grants
                 WHERE service_ref='svc-demo-a'"
            )->fetchColumn(),7);
        break;
}
echo "PASS F21 {$n} mTLS dual-PG checks for phase {$phase}\n";
