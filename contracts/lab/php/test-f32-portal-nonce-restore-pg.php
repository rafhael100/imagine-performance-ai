<?php
declare(strict_types=1);

/**
 * F1-32 expected-negative CI test: restores can reopen Ed25519 nonce replay.
 * Three sequential phases operate on real disposable PostgreSQL data.
 */
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalEd25519DurableReplayGate.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Domain\PerformanceBridge\PortalPerfSqlAuthorityResolver;
use Imagine\Protocol\Lab\SyntheticPortalEd25519DurableReplayGate;

$phase = getenv('PERF_F32_PHASE');
$root = getenv('F30_PORTAL_SOURCE_ROOT');
$claimFile = getenv('F32_PORTAL_CLAIM_FILE');
$keyFile = getenv('F32_PORTAL_PUBLIC_HEX_FILE');
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F31_ED25519_REPLAY_LAB') !== '1'
    || getenv('PERF_F29_SIGNED_PORTAL_LAB') !== '1'
    || getenv('PERF_F32_RESTORE_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'portal_perf_auth_lab'
    || !in_array($phase,['consume','restored','fenced'],true)
    || !is_string($root) || !is_dir($root)
    || !is_string($claimFile) || !is_file($claimFile)
    || !is_string($keyFile) || !is_file($keyFile)) {
    throw new RuntimeException('F32: unexpected environment or phase.');
}
foreach(['PortalPerfAuthorityFacts.php','PortalPerfSqlAuthorityResolver.php'] as $file) {
    require_once $root.'/apps/portal/app/Domain/PerformanceBridge/'.$file;
}
$packet = trim((string)file_get_contents($claimFile));
$pub = trim((string)file_get_contents($keyFile));
$now = 1800000000;
$pdo = static fn(string $db,int $port,string $role,string $pw):PDO =>
    new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
        $role,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$portal = $pdo('portal_perf_auth_lab',5432,'portal_f08_reader','ci-f08-read-only');
$nonce = $pdo('perf_f31_nonce_ci',5433,'perf_f31_consumer','ci-f31-consumer-only');
$admin = $pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$resolver = new PortalPerfSqlAuthorityResolver($portal);
$source = static function() use($resolver,$now):?SyntheticCurrentAuthority {
    $facts = $resolver->resolve(
        'portal-f08-opaque-session-test-aaaaaaaaaaaaaaaaaaa','svc-demo-a',$now
    );
    if ($facts === null) return null;
    return new SyntheticCurrentAuthority(
        organizationRef:$facts->organizationRef,
        tenantRef:$facts->tenantRef,
        serviceRef:$facts->serviceRef,
        resourceRef:'demo-cam-a1',
        actorRef:$facts->actorRef,
        sessionRef:$facts->sessionRef,
        permission:$facts->permission,
        sessionActive:true,mfaVerified:true,
        membershipActive:true,serviceActive:true,
        membershipRevision:$facts->membershipRevision,
        serviceRevision:$facts->serviceRevision,
        sessionRevision:$facts->sessionRevision,
        recoveryEpoch:$facts->recoveryEpoch,
        sourceAvailable:true,observedAt:$facts->sourceObservedAt
    );
};
$gate = static fn():SyntheticPortalEd25519DurableReplayGate =>
    new SyntheticPortalEd25519DurableReplayGate($pub,$nonce);
$ledger = static fn():int => (int)$admin->query(
    'SELECT count(*) FROM perf_f31_receipts.used_nonces'
)->fetchColumn();
$n=0;
$check = static function(string $label,mixed $actual,mixed $expected) use(&$n):void {
    if($actual!==$expected) {
        throw new RuntimeException('FAIL F32 '.$label.' got '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    echo 'PASS F32 '.++$n.' '.$label.PHP_EOL;
};
$env = json_decode($packet,true,16,JSON_THROW_ON_ERROR);
$raw = base64_decode(strtr($env['payload'],'-_','+/'),true);
$claims = json_decode((string)$raw,true,32,JSON_THROW_ON_ERROR);
$check('signed synthetic Portal profile',$claims['profile'],'portal.capability.synthetic.v1');
$check('signed read-only scope',$claims['permission'],'campaigns:read');
$check('independently queried source',$source() instanceof SyntheticCurrentAuthority,true);
switch($phase) {
case 'consume':
    $check('snapshot before second claim contains baseline F31 receipt',$ledger(),1);
    $check('new signed claim accepted',$gate()->verifyOnce($packet,$source,$now),'verified_synthetic_once');
    $check('new receipt committed independently',$ledger(),2);
    $check('replay denied before restore',$gate()->verifyOnce($packet,$source,$now),'distributed_replay_denied');
    break;
case 'restored':
    $check('old real pg_dump restored baseline receipt only',$ledger(),1);
    $check('EXPECTED VULNERABILITY: old signed claim is accepted after restore',
        $gate()->verifyOnce($packet,$source,$now),'verified_synthetic_once');
    $check('duplicate receipt has been recreated after replay',$ledger(),2);
    $check('second replay again denied after recreated receipt',
        $gate()->verifyOnce($packet,$source,$now),'distributed_replay_denied');
    echo "EXPECTED SECURITY GAP: restoring a nonce ledger reopens signed replay".PHP_EOL;
    break;
case 'fenced':
    $check('restored ledger still has two receipts',$ledger(),2);
    $check('source epoch was advanced outside nonce DB',$source()?->recoveryEpoch,8);
    $check('old signed token rejected due to current source epoch',
        $gate()->verifyOnce($packet,$source,$now),'authority_revision_mismatch');
    $check('epoch mismatch leaves receipt ledger unchanged',$ledger(),2);
    break;
}
echo "PASS F32 phase={$phase} count={$n} expected-negative CI only".PHP_EOL;
