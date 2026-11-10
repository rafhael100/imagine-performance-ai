<?php

declare(strict_types=1);

require_once __DIR__.'/../SyntheticS2SProtocol.php';
require_once __DIR__.'/SyntheticIngressBridge.php';
require_once __DIR__.'/SyntheticExternalRestoreFence.php';
require_once __DIR__.'/SyntheticRevocationRelay.php';

use Imagine\Protocol\Lab\SyntheticExternalRestoreFence;
use Imagine\Protocol\Lab\SyntheticRevocationRelay;
use Imagine\Protocol\Lab\SyntheticS2SProtocol;

$phase = getenv('PERF_RELAY_PHASE');
if (!is_string($phase) || !in_array($phase, [
    'baseline', 'pending', 'delivered', 'reactivated',
    'second_revoke', 'restored', 'authority_unavailable',
], true)) {
    throw new RuntimeException('Unknown F1-11 disposable laboratory phase.');
}
if (getenv('PGHOST') !== '127.0.0.1' || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PERF_FENCE_DB') !== 'perf_test_relay_authority') {
    throw new RuntimeException('Refusing to connect outside the F1-11 disposable databases.');
}
$fixture = getenv('PERF_SNAPSHOT_FIXTURE');
if (!is_string($fixture) || !is_file($fixture)) {
    throw new RuntimeException('Missing F1-11 synthetic snapshot.');
}
$raw = file_get_contents($fixture);
if (!is_string($raw) || strlen($raw) > 32768 || strlen($raw) < 50) {
    throw new RuntimeException('Invalid F1-11 fixture length.');
}
$digest = hash('sha256', $raw);
$key = random_bytes(32);
$clock = 1800000000;
$password = getenv('PGPASSWORD');
if (!is_string($password) || $password === '') {
    throw new RuntimeException('Missing disposable test-only role password.');
}
$connect = static fn (string $name, string $user, string $pass): PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$name.';connect_timeout=5',
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$adminMain = $connect('perf_test_relay_main', 'postgres', $password);
$adminAuthority = $connect('perf_test_relay_authority', 'postgres', $password);
$mainRuntime = $connect('perf_test_relay_main', 'perf_s2s_lab_caller', 'ci-test-only-s2s-role');
$mainUpdater = $connect('perf_test_relay_main', 'perf_test_main_revoker', 'ci-only-main-revoker');
$authorityReader = $connect('perf_test_relay_authority', 'perf_test_fence_reader', 'ci-only-fence-reader');
$authorityAcker = $connect('perf_test_relay_authority', 'perf_test_outbox_acker', 'ci-only-outbox-acker');
$relay = new SyntheticRevocationRelay($authorityReader, $mainUpdater, $authorityAcker);
$guard = new SyntheticExternalRestoreFence($mainRuntime, $authorityReader);

$mint = static fn (
    string $request,
    string $nonce,
    int $revision = 3,
    string $organization = 'org-demo-a',
    string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a'
): array => SyntheticS2SProtocol::mintForLab(
    $organization, $tenant, $service, $request, $nonce,
    $clock - 60, $clock + 60, $revision, $digest, $key
);
$accept = static fn (array $message, int $revision = 3,
    string $org = 'org-demo-a', string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a'): string => $guard->accept(
        $message, $key, $org, $tenant, $service, $digest, $revision, $clock
    );
$grantMain = static function (string $service) use ($adminMain): array {
    $stmt = $adminMain->prepare(
        'SELECT organization_ref, tenant_ref, active, grant_revision
         FROM perf_s2s_lab.mock_service_grants WHERE service_ref=?'
    );
    $stmt->execute([$service]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($v)) {
        throw new RuntimeException('Missing main lab grant.');
    }
    return $v;
};
$grantAuthority = static function (string $service) use ($adminAuthority): array {
    $stmt = $adminAuthority->prepare(
        'SELECT organization_ref, tenant_ref, active, min_revision
         FROM perf_external_lab.mock_authority_fences WHERE service_ref=?'
    );
    $stmt->execute([$service]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($v)) {
        throw new RuntimeException('Missing authority lab grant.');
    }
    return $v;
};
$event = static function (string $id) use ($adminAuthority): array {
    $stmt = $adminAuthority->prepare(
        'SELECT event_id, status, attempts, source_revision
         FROM perf_external_lab.mock_revocation_outbox WHERE event_id=?'
    );
    $stmt->execute([$id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($v)) {
        throw new RuntimeException('Missing outbox event '.$id);
    }
    return $v;
};
$receipts = static fn (): int => (int) $adminMain->query(
    'SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts'
)->fetchColumn();
$isActive = static fn (array $row): bool => in_array($row['active'], [true, 't', '1', 1], true);
$n = 0;
$check = static function (string $name, mixed $actual, mixed $expected) use (&$n, $phase): void {
    if ($actual !== $expected) {
        throw new RuntimeException('FAIL '.$phase.' '.$name.' got '
            .var_export($actual, true).' expected '.var_export($expected, true));
    }
    ++$n;
    echo 'PASS '.$phase.'-'.$n.' '.$name."\n";
};

switch ($phase) {
    case 'baseline':
        $check('01 A main grant active', $isActive($grantMain('svc-demo-a')), true);
        $check('02 A authority grant active', $isActive($grantAuthority('svc-demo-a')), true);
        $check('03 A authority revision 3', (int) $grantAuthority('svc-demo-a')['min_revision'], 3);
        $check('04 signed baseline claim accepted', $accept($mint('req-demo-base', 'nonce-demo-base')), 'accepted_synthetic_only');
        $check('05 replay denied by main', $accept($mint('req-demo-base', 'nonce-demo-new')), 'mock_replay_denied');
        $check('06 one baseline receipt before backup', $receipts(), 1);
        $check('07 B retains own active grant', $isActive($grantMain('svc-demo-b')), true);
        break;

    case 'pending':
        $check('08 source revocation committed before relay', $isActive($grantAuthority('svc-demo-a')), false);
        $check('09 main is stale active before relay', $isActive($grantMain('svc-demo-a')), true);
        $check('10 outbox pending', $event('evt-demo-revoke-4')['status'], 'pending');
        $check('11 link outage defers delivery', $relay->deliver('evt-demo-revoke-4', false), 'relay_deferred_demo');
        $check('12 outage does not advance main', (int) $grantMain('svc-demo-a')['grant_revision'], 3);
        $check('13 stale active main cannot pass independent fence',
            $accept($mint('req-demo-pending', 'nonce-demo-pending')), 'external_fence_revoked');
        $check('14 malformed relay event denied', $relay->deliver('../../etc/passwd'), 'invalid_lab_event_id');
        $check('15 unknown relay event denied', $relay->deliver('evt-demo-missing'), 'relay_unknown_event');
        // Privileged lab adversary inserts an event with foreign tenant for A.
        $adminAuthority->exec(
            "INSERT INTO perf_external_lab.mock_revocation_outbox
             (event_id,service_ref,organization_ref,tenant_ref,source_revision,event_type)
             VALUES ('evt-demo-forged','svc-demo-a','org-demo-b','perf-demo-b',2,
                     'service.authorization_changed.synthetic')"
        );
        $check('16 forged cross-tenant event rejected', $relay->deliver('evt-demo-forged'), 'relay_untrusted_binding');
        $check('17 forged event remains unacked', $event('evt-demo-forged')['status'], 'pending');
        $check('18 no new main receipt on outage', $receipts(), 1);
        break;

    case 'delivered':
        $check('19 first sync simulated lost ACK',
            $relay->deliver('evt-demo-revoke-4', true, true), 'relay_ack_deferred_demo');
        $check('20 main grant now revoked', $isActive($grantMain('svc-demo-a')), false);
        $check('21 main revision advanced to 4', (int) $grantMain('svc-demo-a')['grant_revision'], 4);
        $check('22 lost ACK keeps event pending', $event('evt-demo-revoke-4')['status'], 'pending');
        $check('23 retry idempotently finishes ACK',
            $relay->deliver('evt-demo-revoke-4'), 'relay_already_synced_demo');
        $check('24 event now acknowledged', $event('evt-demo-revoke-4')['status'], 'acked');
        $check('25 acknowledged again without mutation',
            $relay->deliver('evt-demo-revoke-4'), 'relay_already_synced_demo');
        $check('26 only one ACK status transition', (int) $event('evt-demo-revoke-4')['attempts'], 1);
        $check('27 old claim stays blocked', $accept($mint('req-demo-revoked', 'nonce-demo-revoked')), 'external_fence_revoked');
        $check('28 B main grant unaffected', (int) $grantMain('svc-demo-b')['grant_revision'], 1);
        break;

    case 'reactivated':
        $check('29 new revision 5 exists only at authority',
            (int) $grantAuthority('svc-demo-a')['min_revision'], 5);
        $check('30 reactivation explicitly set in source', $isActive($grantAuthority('svc-demo-a')), true);
        $check('31 main remains revision 4 until sync', (int) $grantMain('svc-demo-a')['grant_revision'], 4);
        $check('32 new signed message blocked by stale main',
            $accept($mint('req-demo-v5-presync', 'nonce-demo-v5-presync', 5), 5), 'mock_grant_revoked');
        $check('33 old signed revision denied by external authority',
            $accept($mint('req-demo-v3-stale', 'nonce-demo-v3-stale')), 'external_fence_revision_stale');
        // Old revoke event only wakes reconciler: current authority revision 5
        // explicitly reactivated; event 4 itself has NO power to reactivate.
        $check('34 old event reconciles CURRENT authority only',
            $relay->deliver('evt-demo-revoke-4'), 'relay_synced_demo');
        $check('35 main is now revision 5', (int) $grantMain('svc-demo-a')['grant_revision'], 5);
        $check('36 main is active only because source revision 5 active',
            $isActive($grantMain('svc-demo-a')), true);
        $check('37 reactivation event can be ACKed idempotently',
            $relay->deliver('evt-demo-activate-5'), 'relay_already_synced_demo');
        $check('38 reactivation outbox ACK recorded',
            $event('evt-demo-activate-5')['status'], 'acked');
        $check('39 new signed grant accepted synthetically',
            $accept($mint('req-demo-v5', 'nonce-demo-v5', 5), 5), 'accepted_synthetic_only');
        $check('40 duplicate new signed grant denied',
            $accept($mint('req-demo-v5', 'nonce-demo-v5b', 5), 5), 'mock_replay_denied');
        break;

    case 'second_revoke':
        $check('41 independent authority revision 6',
            (int) $grantAuthority('svc-demo-a')['min_revision'], 6);
        $check('42 independent authority revoked again', $isActive($grantAuthority('svc-demo-a')), false);
        $check('43 main still active at previous revision', (int) $grantMain('svc-demo-a')['grant_revision'], 5);
        $check('44 external authority rejects old valid claim',
            $accept($mint('req-demo-old-v5', 'nonce-demo-old-v5', 5), 5), 'external_fence_revoked');
        // Deliver an OLD reactivation event out of order: read current source
        // revocation revision 6, never re-enable from stale event content.
        $check('45 delayed older event applies current REVOKED state',
            $relay->deliver('evt-demo-activate-5'), 'relay_synced_demo');
        $check('46 main revision advanced directly to 6',
            (int) $grantMain('svc-demo-a')['grant_revision'], 6);
        $check('47 old event never reopened revoked service',
            $isActive($grantMain('svc-demo-a')), false);
        $check('48 latest revocation event ACK idempotently',
            $relay->deliver('evt-demo-revoke-6'), 'relay_already_synced_demo');
        $check('49 latest revocation outbox ACKed', $event('evt-demo-revoke-6')['status'], 'acked');
        $check('50 unrelated B grant never revoked', $isActive($grantMain('svc-demo-b')), true);
        break;

    case 'restored':
        $check('51 historical main restored active=true', $isActive($grantMain('svc-demo-a')), true);
        $check('52 historical main restored revision=3', (int) $grantMain('svc-demo-a')['grant_revision'], 3);
        $check('53 main backup erased post-backup receipt', $receipts(), 1);
        $check('54 source authority still revision 6', (int) $grantAuthority('svc-demo-a')['min_revision'], 6);
        $check('55 source authority remains revoked', $isActive($grantAuthority('svc-demo-a')), false);
        $check('56 signed old grant blocked despite old main restore',
            $accept($mint('req-demo-postrestore', 'nonce-demo-postrestore')), 'external_fence_revoked');
        $check('57 already ACKed revocation event reconciles restored main',
            $relay->deliver('evt-demo-revoke-6'), 'relay_synced_demo');
        $check('58 restored main caught back up to revision 6',
            (int) $grantMain('svc-demo-a')['grant_revision'], 6);
        $check('59 restored main active false after reconciliation', $isActive($grantMain('svc-demo-a')), false);
        $check('60 acked event stays ACKed across main restore',
            $event('evt-demo-revoke-6')['status'], 'acked');
        $check('61 second reconciliation idempotent',
            $relay->deliver('evt-demo-revoke-6'), 'relay_already_synced_demo');
        break;

    case 'authority_unavailable':
        $check('62 relay denies unreadable external authority',
            $relay->deliver('evt-demo-revoke-6'), 'relay_authority_unavailable');
        $check('63 claim fails closed while authority unreachable',
            $accept($mint('req-demo-authority-down', 'nonce-demo-authority-down', 6), 6),
            'external_fence_unavailable');
        $check('64 no main rollback or reactivation on authority failure',
            (int) $grantMain('svc-demo-a')['grant_revision'], 6);
        $check('65 no new receipts after authority failure', $receipts(), 1);
        break;
}

echo "PASS F1-11 phase {$phase}, {$n} real PostgreSQL synthetic assertions\n";
