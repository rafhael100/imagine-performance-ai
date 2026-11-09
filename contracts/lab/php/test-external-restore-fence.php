<?php

declare(strict_types=1);

require_once __DIR__.'/../SyntheticS2SProtocol.php';
require_once __DIR__.'/SyntheticIngressBridge.php';
require_once __DIR__.'/SyntheticExternalRestoreFence.php';

use Imagine\Protocol\Lab\SyntheticExternalRestoreFence;
use Imagine\Protocol\Lab\SyntheticS2SProtocol;

$phase = getenv('PERF_RESTORE_PHASE');
$mainName = getenv('PGDATABASE');
$authorityName = getenv('PERF_FENCE_DB');
$fixturePath = getenv('PERF_SNAPSHOT_FIXTURE');
if (getenv('PGHOST') !== '127.0.0.1' || getenv('PGPORT') !== '5432'
    || $mainName !== 'perf_test_restore_main'
    || $authorityName !== 'perf_test_fence_authority'
    || !is_string($fixturePath) || !is_file($fixturePath)) {
    throw new RuntimeException('Refusing non-disposable main or fence laboratory databases');
}
$contents = file_get_contents($fixturePath);
if (!is_string($contents) || $contents === '' || strlen($contents) > 32768) {
    throw new RuntimeException('Invalid synthetic test fixture');
}
$digest = hash('sha256', $contents);
$testKey = random_bytes(32);
$clock = 1800000000;
$password = getenv('PGPASSWORD');
if (!is_string($password) || $password === '') {
    throw new RuntimeException('CI test-only password missing');
}
$connect = static fn (string $database, string $user, string $pass): PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$database.';connect_timeout=5',
    $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$mainAdmin = $connect($mainName, 'postgres', $password);
$mainRuntime = $connect($mainName, 'perf_s2s_lab_caller', 'ci-test-only-s2s-role');
$fenceAdmin = $connect($authorityName, 'postgres', $password);
$fenceReader = $connect($authorityName, 'perf_test_fence_reader', 'ci-only-fence-reader');
$bridge = new SyntheticExternalRestoreFence($mainRuntime, $fenceReader);
$mint = static fn (
    string $request,
    string $nonce,
    int $revision = 3,
    string $org = 'org-demo-a',
    string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a'
): array => SyntheticS2SProtocol::mintForLab(
    $org, $tenant, $service, $request, $nonce,
    $clock - 60, $clock + 60, $revision, $digest, $testKey
);
$run = static fn (
    mixed $msg,
    int $revision = 3,
    string $org = 'org-demo-a',
    string $tenant = 'perf-demo-a',
    string $service = 'svc-demo-a'
): string => $bridge->accept($msg, $testKey, $org, $tenant, $service,
    $digest, $revision, $clock);
$n = 0;
$check = static function (string $label, mixed $actual, mixed $expected) use (&$n, $phase): void {
    if ($actual !== $expected) {
        throw new RuntimeException("FAIL [$label], got ".var_export($actual, true)
            .' expected '.var_export($expected, true));
    }
    ++$n;
    echo "PASS {$phase}-{$n} {$label}\n";
};
$mainCount = static fn (): int => (int) $mainAdmin->query(
    'SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts'
)->fetchColumn();
$primary = static fn (): array => $mainAdmin->query(
    "SELECT active,grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
)->fetch(PDO::FETCH_ASSOC);
$authority = static fn (): array => $fenceAdmin->query(
    "SELECT active,min_revision FROM perf_external_lab.mock_authority_fences WHERE service_ref='svc-demo-a'"
)->fetch(PDO::FETCH_ASSOC);
$bool = static fn (mixed $x): bool => $x === true || $x === 't' || $x === '1';

switch ($phase) {
    case 'baseline':
        $check('01 initial fence active', $bool($authority()['active']), true);
        $check('02 matching initial revision', (int) $authority()['min_revision'], 3);
        $check('03 baseline signed claim accepted', $run($mint('req-demo-baseline', 'nonce-demo-baseline')), 'accepted_synthetic_only');
        $check('04 repeat denied by durable primary DB', $run($mint('req-demo-baseline', 'nonce-demo-other')), 'mock_replay_denied');
        $check('05 one receipt in main DB', $mainCount(), 1);
        $check('06 B signature cannot be used for A', $run($mint('req-demo-bad', 'nonce-demo-bad', 1, 'org-demo-b','perf-demo-b','svc-demo-b')), 'mock_binding_denied');
        $check('07 unknown cross-org fence not usable', $run(
            $mint('req-demo-mismatch','nonce-demo-mismatch'), 3, 'org-demo-missing'
        ), 'mock_binding_denied');
        try {
            $fenceReader->exec("UPDATE perf_external_lab.mock_authority_fences SET active=false WHERE service_ref='svc-demo-a'");
            throw new RuntimeException('External read-only role mutated authority');
        } catch (PDOException) {
            $check('08 fence reader cannot mutate external authority', true, true);
        }
        break;

    case 'pre_revoke_extra':
        $check('09 claim accepted after backup snapshot', $run($mint('req-demo-lost', 'nonce-demo-lost')), 'accepted_synthetic_only');
        $check('10 both receipts now present', $mainCount(), 2);
        break;

    case 'revoked':
        $check('11 external authority revocation survived', $bool($authority()['active']), false);
        $check('12 external revision increased', (int) $authority()['min_revision'], 4);
        $check('13 revoked service denies old signed revision', $run($mint('req-demo-revoked', 'nonce-demo-revoked')), 'external_fence_revoked');
        $check('14 revoked service denies newer matching revision', $run($mint('req-demo-revoked4', 'nonce-demo-revoked4',4), 4), 'external_fence_revoked');
        $check('15 rejected messages did not create receipts', $mainCount(), 2);
        break;

    case 'after_restore':
        $check('16 main DB restored old active grant', $bool($primary()['active']), true);
        $check('17 main DB rolled back to revision 3', (int) $primary()['grant_revision'], 3);
        $check('18 main DB lost post-snapshot receipt', $mainCount(), 1);
        $check('19 independent DB is still revoked', $bool($authority()['active']), false);
        $check('20 independent revision did not roll back', (int) $authority()['min_revision'], 4);
        $check('21 restored old valid signature fails closed', $run($mint('req-demo-lost','nonce-demo-lost')), 'external_fence_revoked');
        $check('22 restored snapshot cannot accept brand-new A message', $run($mint('req-demo-new-after-restore','nonce-demo-new-after-restore')), 'external_fence_revoked');
        $check('23 denied restored claims do not recreate receipts', $mainCount(), 1);
        break;

    case 'after_reactivate':
        $check('24 independent authority advanced to revision 5', (int) $authority()['min_revision'], 5);
        $check('25 independent authority reactivated in lab', $bool($authority()['active']), true);
        $check('26 old signed revision remains denied', $run($mint('req-demo-oldrev','nonce-demo-oldrev')), 'external_fence_revision_stale');
        $check('27 reauthorized revision 5 accepted synthetically', $run($mint('req-demo-rev5','nonce-demo-rev5', 5), 5), 'accepted_synthetic_only');
        $check('28 replay of new grant revision denied', $run($mint('req-demo-rev5','nonce-demo-rev5b', 5), 5), 'mock_replay_denied');
        break;

    case 'second_restore':
        $check('29 main restore again reset revision to 3', (int) $primary()['grant_revision'], 3);
        $check('30 external revision remains 5', (int) $authority()['min_revision'], 5);
        $check('31 signed old revision rejected externally', $run($mint('req-demo-old-2','nonce-demo-old-2')), 'external_fence_revision_stale');
        $check('32 even signed revision 5 denied by stale main DB', $run($mint('req-demo-new-2','nonce-demo-new-2',5), 5), 'mock_grant_revision_stale');
        $check('33 main replay receipts reset to snapshot again', $mainCount(), 1);
        break;

    case 'external_unavailable':
        $check('34 denied without SELECT on external authority', $run($mint('req-demo-unavailable','nonce-demo-unavailable',5), 5), 'external_fence_unavailable');
        $check('35 no fail-open to restored main grant', $mainCount(), 1);
        break;

    default:
        throw new RuntimeException('Unknown test phase. No real operations permitted.');
}
echo "PASS $n F1-10 synthetic restore-fence assertions in phase $phase\n";
