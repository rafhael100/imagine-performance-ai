<?php

declare(strict_types=1);

require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalEd25519DurableReplayGate.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use Imagine\Protocol\Lab\SyntheticPortalEd25519DurableReplayGate;
use App\Domain\PerformanceBridge\PortalPerfSqlAuthorityResolver;

$sourceRoot = getenv('F30_PORTAL_SOURCE_ROOT');
$claimPath = getenv('F31_PORTAL_CLAIM_FILE');
$keyPath = getenv('F31_PORTAL_PUBLIC_HEX_FILE');
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F31_ED25519_REPLAY_LAB') !== '1'
    || getenv('PERF_F29_SIGNED_PORTAL_LAB') !== '1'
    || getenv('PORTAL_PERF_PG_AUTH_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'portal_perf_auth_lab'
    || !is_string($sourceRoot) || !is_dir($sourceRoot)
    || !is_string($claimPath) || !is_file($claimPath)
    || !is_string($keyPath) || !is_file($keyPath)) {
    throw new RuntimeException('F1-31 only runs inside pinned Portal CI test.');
}
foreach (['PortalPerfAuthorityFacts.php','PortalPerfSqlAuthorityResolver.php'] as $file) {
    require_once $sourceRoot.'/apps/portal/app/Domain/PerformanceBridge/'.$file;
}
$packet = trim((string)file_get_contents($claimPath));
$pub = trim((string)file_get_contents($keyPath));
$now = 1800000000;
$bearer = 'portal-f08-opaque-session-test-aaaaaaaaaaaaaaaaaaa';
$pdo = static fn(string $db, int $port, string $role, string $pw):PDO =>
    new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
        $role,$pw,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]);
$portal = $pdo('portal_perf_auth_lab',5432,'portal_f08_reader','ci-f08-read-only');
$portalAdmin = $pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$nonce = $pdo('perf_f31_nonce_ci',5433,'perf_f31_consumer','ci-f31-consumer-only');
$nonceAdmin = $pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$resolver = new PortalPerfSqlAuthorityResolver($portal);
$source = static function() use ($resolver,$bearer,$now):?SyntheticCurrentAuthority {
    $facts = $resolver->resolve($bearer,'svc-demo-a',$now);
    if ($facts === null) return null;
    return new SyntheticCurrentAuthority(
        organizationRef:$facts->organizationRef,
        tenantRef:$facts->tenantRef,
        serviceRef:$facts->serviceRef,
        resourceRef:'demo-cam-a1', // A test fixture, NOT resource ownership.
        actorRef:$facts->actorRef,
        sessionRef:$facts->sessionRef,
        permission:$facts->permission,
        sessionActive:true,
        mfaVerified:true,
        membershipActive:true,
        serviceActive:true,
        membershipRevision:$facts->membershipRevision,
        serviceRevision:$facts->serviceRevision,
        sessionRevision:$facts->sessionRevision,
        recoveryEpoch:$facts->recoveryEpoch,
        sourceAvailable:true,
        observedAt:$facts->sourceObservedAt
    );
};
$count = 0;
$check = static function(string $name, mixed $actual, mixed $expected) use (&$count):void {
    if ($actual !== $expected) {
        throw new RuntimeException('FAIL F31 '.$name.' actual '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    echo 'PASS F31 '.++$count.' '.$name.PHP_EOL;
};
$ledger = static fn():int=>(int)$nonceAdmin->query(
    'SELECT count(*) FROM perf_f31_receipts.used_nonces'
)->fetchColumn();
$newGate = static fn():SyntheticPortalEd25519DurableReplayGate=>
    new SyntheticPortalEd25519DurableReplayGate($pub,$nonce);
$portalSystem = (string)$portalAdmin->query(
    'SELECT system_identifier FROM pg_control_system()'
)->fetchColumn();
$nonceSystem = (string)$nonceAdmin->query(
    'SELECT system_identifier FROM pg_control_system()'
)->fetchColumn();
$check('two database systems have distinct physical IDs', $portalSystem!==$nonceSystem, true);
$check('independent nonce ledger initially empty', $ledger(), 0);
$check('Portal authority can independently resolve signed subject',
    $source() instanceof SyntheticCurrentAuthority,true);
$check('first Ed25519 signed Portal claim with SQL source consumed once',
    $newGate()->verifyOnce($packet,$source,$now),'verified_synthetic_once');
$check('signed nonce persisted in separate SQL system', $ledger(),1);
// New verifier instance simulates another worker/process without in-memory state.
$check('new verifier instance denies already consumed Ed25519 claim',
    $newGate()->verifyOnce($packet,$source,$now),'distributed_replay_denied');
$check('replays did not create another nonce ledger row', $ledger(),1);
$check('no performance operational effects were created by nonce check',
    $portalAdmin->query(
        "SELECT to_regclass('public.performance_managed_campaigns') IS NULL"
    )->fetchColumn(),true);

$bogus = json_decode($packet,true,16,JSON_THROW_ON_ERROR);
$bogus['signature'] = str_repeat('A',strlen($bogus['signature']));
$check('tampered Ed25519 signature rejected before nonce store',
    $newGate()->verifyOnce(json_encode($bogus,JSON_THROW_ON_ERROR),$source,$now),
    'invalid_signature');
$check('invalid signature leaves nonce ledger unchanged',$ledger(),1);
$check('different public key rejects claim before nonce store',
    (new SyntheticPortalEd25519DurableReplayGate(
        bin2hex(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())),
        $nonce
    ))->verifyOnce($packet,$source,$now),'invalid_signature');

$portalAdmin->exec(
    "UPDATE portal_perf_authority_services SET service_revision=6
     WHERE organization_ref='org-demo-a'"
);
$check('Portal service revision change denies old signed claim before replay',
    $newGate()->verifyOnce($packet,$source,$now),'authority_revision_mismatch');
$portalAdmin->exec(
    "UPDATE portal_perf_authority_services SET service_revision=5
     WHERE organization_ref='org-demo-a'"
);
$portalAdmin->exec(
    "UPDATE portal_perf_authority_services SET contract_state='revoked_mock'
     WHERE organization_ref='org-demo-a'"
);
$check('Portal source revocation refuses even valid signed claim',
    $newGate()->verifyOnce($packet,$source,$now),'portal_source_unavailable');
$check('source revocation does not affect receipt count',$ledger(),1);
$portalAdmin->exec(
    "UPDATE portal_perf_authority_services SET contract_state='active_mock'
     WHERE organization_ref='org-demo-a'"
);
$check('restoring demo source still cannot replay consumed nonce',
    $newGate()->verifyOnce($packet,$source,$now),'distributed_replay_denied');
$check('independent source failure denies prior to receipt check',
    $newGate()->verifyOnce($packet,static function():never{
        throw new RuntimeException('simulated Portal outage');
    },$now),'portal_source_unavailable');
$check('freshness: expired signed claim cannot consume again',
    $newGate()->verifyOnce($packet,$source,$now+20),'expired_or_future');

$unauthorized = false;
try {
    $nonce->query('SELECT count(*) FROM perf_f31_receipts.used_nonces');
} catch(PDOException $e) {
    $unauthorized = $e->getCode()==='42501';
}
$check('application role cannot read private receipt table',$unauthorized,true);
$unauthorized = false;
try {
    $nonce->exec('DELETE FROM perf_f31_receipts.used_nonces');
} catch(PDOException $e) {
    $unauthorized = $e->getCode()==='42501';
}
$check('application role cannot delete private receipt table',$unauthorized,true);

$nonce->beginTransaction();
try {
    $check('nested authority transaction rejected',
        $newGate()->verifyOnce($packet,$source,$now),
        'nonce_authority_transaction_denied');
} finally {
    $nonce->rollBack();
}
$check('no new ledger row after nested rejection',$ledger(),1);
$wrong = new SyntheticPortalEd25519DurableReplayGate($pub,$nonceAdmin);
$check('superuser connection cannot substitute for restricted authority role',
    $wrong->verifyOnce($packet,$source,$now),'wrong_nonce_authority');

$nonceAdmin->exec(
    'REVOKE EXECUTE ON FUNCTION perf_f31_receipts.consume_validated_nonce('
    .'text,text,text,bigint,bigint) FROM perf_f31_consumer'
);
$check('authority execute permission loss fails closed',
    $newGate()->verifyOnce($packet,$source,$now),
    'nonce_authority_unavailable');
$nonceAdmin->exec(
    'GRANT EXECUTE ON FUNCTION perf_f31_receipts.consume_validated_nonce('
    .'text,text,text,bigint,bigint) TO perf_f31_consumer'
);
$check('restoring permission does not erase used nonce',
    $newGate()->verifyOnce($packet,$source,$now),'distributed_replay_denied');

putenv('PERF_F31_ED25519_REPLAY_LAB=0');
$check('feature gate closed by default',
    $newGate()->verifyOnce($packet,$source,$now),'deployment_gate_closed');
putenv('PERF_F31_ED25519_REPLAY_LAB=1');
putenv('APP_ENV=production');
$check('production environment refuses synthetic grant',
    $newGate()->verifyOnce($packet,$source,$now),'deployment_gate_closed');
putenv('APP_ENV=testing');
$check('ledger persists after all invalid requests',$ledger(),1);
echo "PASS F31 {$count} cross-repository Ed25519 durable nonce checks".PHP_EOL;
