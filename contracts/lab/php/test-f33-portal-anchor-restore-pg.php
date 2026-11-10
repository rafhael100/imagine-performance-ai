<?php
declare(strict_types=1);

/** F1-33 two-phase positive/negative disposable PostgreSQL proof only. */
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalEd25519DurableReplayGate.php';
require_once __DIR__.'/SyntheticPortalAnchoredReplayGate.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Domain\PerformanceBridge\PortalPerfSqlAuthorityResolver;
use Imagine\Protocol\Lab\SyntheticPortalAnchoredReplayGate;
use Imagine\Protocol\Lab\SyntheticPortalEd25519DurableReplayGate;

$phase=getenv('PERF_F33_PHASE');
$root=getenv('F30_PORTAL_SOURCE_ROOT');
$claimFile=getenv('F33_PORTAL_CLAIM_FILE');
$keyFile=getenv('F33_PORTAL_PUBLIC_HEX_FILE');
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F31_ED25519_REPLAY_LAB')!=='1'
    || getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
    || getenv('PERF_F33_PORTAL_ANCHOR_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='portal_perf_auth_lab'
    || !in_array($phase,['consume','restored'],true)
    || !is_string($root) || !is_dir($root)
    || !is_string($claimFile) || !is_file($claimFile)
    || !is_string($keyFile) || !is_file($keyFile)) {
    throw new RuntimeException('F33 runs only inside disposable CI phases.');
}
foreach(['PortalPerfAuthorityFacts.php','PortalPerfSqlAuthorityResolver.php'] as $f) {
    require_once $root.'/apps/portal/app/Domain/PerformanceBridge/'.$f;
}
$packet=trim((string)file_get_contents($claimFile));
$pub=trim((string)file_get_contents($keyFile));
$now=1800000000;
$pdo=static fn(string $db,int $port,string $u,string $p):PDO=>
    new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
        $u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$portal=$pdo('portal_perf_auth_lab',5432,'portal_f08_reader','ci-f08-read-only');
$anchor=$pdo('portal_perf_auth_lab',5432,'perf_f33_anchor_consumer','ci-f33-anchor-only');
$portalAdmin=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$nonce=$pdo('perf_f31_nonce_ci',5433,'perf_f31_consumer','ci-f31-consumer-only');
$nonceAdmin=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$resolver=new PortalPerfSqlAuthorityResolver($portal);
$source=static function()use($resolver,$now):?SyntheticCurrentAuthority {
    $facts=$resolver->resolve(
        'portal-f08-opaque-session-test-aaaaaaaaaaaaaaaaaaa','svc-demo-a',$now
    );
    if($facts===null)return null;
    return new SyntheticCurrentAuthority(
        organizationRef:$facts->organizationRef,
        tenantRef:$facts->tenantRef,
        serviceRef:$facts->serviceRef,
        resourceRef:'demo-cam-a1',
        actorRef:$facts->actorRef,
        sessionRef:$facts->sessionRef,
        permission:$facts->permission,
        sessionActive:true,mfaVerified:true,membershipActive:true,serviceActive:true,
        membershipRevision:$facts->membershipRevision,
        serviceRevision:$facts->serviceRevision,
        sessionRevision:$facts->sessionRevision,
        recoveryEpoch:$facts->recoveryEpoch,
        sourceAvailable:true,observedAt:$facts->sourceObservedAt
    );
};
$gate=static fn():SyntheticPortalAnchoredReplayGate=>
    new SyntheticPortalAnchoredReplayGate($pub,$anchor,$nonce);
$localOnly=static fn():SyntheticPortalEd25519DurableReplayGate=>
    new SyntheticPortalEd25519DurableReplayGate($pub,$nonce);
$anchorCount=static fn():int=>(int)$portalAdmin->query(
    'SELECT count(*) FROM perf_f33_anchor.used_nonces'
)->fetchColumn();
$localCount=static fn():int=>(int)$nonceAdmin->query(
    'SELECT count(*) FROM perf_f31_receipts.used_nonces'
)->fetchColumn();
$n=0;
$check=static function(string $msg,mixed $actual,mixed $expected)use(&$n):void{
    if($actual!==$expected){
        throw new RuntimeException('FAIL F33 '.$msg.': '
            .var_export($actual,true).' != '.var_export($expected,true));
    }
    echo 'PASS F33 '.++$n.' '.$msg.PHP_EOL;
};
$envelope=json_decode($packet,true,16,JSON_THROW_ON_ERROR);
$claim=json_decode((string)base64_decode(
    strtr($envelope['payload'],'-_','+/'),true),true,32,JSON_THROW_ON_ERROR);
$check('signed synthetic claim has read-only permission',$claim['permission'],'campaigns:read');
$check('new Portal SQL source has recovery epoch 8',$source()?->recoveryEpoch,8);
$check('source revision matches new signed epoch',$claim['recovery_epoch'],8);
$check('Portal and nonce authority are physically different',
    $portalAdmin->query('SELECT system_identifier FROM pg_control_system()')->fetchColumn()
    !==$nonceAdmin->query('SELECT system_identifier FROM pg_control_system()')->fetchColumn(),true);
switch($phase) {
case 'consume':
    $check('new Portal anchor starts empty',$anchorCount(),0);
    $check('nonce DB after F32 has two synthetic baseline receipts',$localCount(),2);
    $check('valid Ed25519 claim consumes Portal anchor first, then local nonce',
        $gate()->verifyOnce($packet,$source,$now),'verified_synthetic_anchored_once');
    $check('Portal independent anchor persists signed nonce',$anchorCount(),1);
    $check('nonce DB records new nonce as well',$localCount(),3);
    $check('fresh gate refuses second presentation through Portal anchor',
        $gate()->verifyOnce($packet,$source,$now),'portal_anchor_replay_denied');
    $check('anchor replay leaves both receipt totals unchanged',
        [$anchorCount(),$localCount()],[1,3]);
    break;
case 'restored':
    $check('Portal anchor retained its nonce after restore of other DB',
        $anchorCount(),1);
    $check('nonce DB backup lost third receipt',$localCount(),2);
    $check('same signed packet blocked by external Portal anchor',
        $gate()->verifyOnce($packet,$source,$now),'portal_anchor_replay_denied');
    $check('safe path never writes to restored nonce DB',
        $localCount(),2);
    // Direct old F31 use is still possible in the CLI lab; documenting why
    // ALL operational entrypoints must enforce the external anchor gate.
    $check('KNOWN BYPASS: calling old unanchored gate after restore reopens replay',
        $localOnly()->verifyOnce($packet,$source,$now),'verified_synthetic_once');
    $check('unanchored bypass creates a new local receipt',$localCount(),3);
    $check('Portal external anchor remains unchanged',$anchorCount(),1);
    $check('anchored path still rejects replay even after old code bypass',
        $gate()->verifyOnce($packet,$source,$now),'portal_anchor_replay_denied');
    $denied=false;
    try {$anchor->query('SELECT * FROM perf_f33_anchor.used_nonces');}
    catch(PDOException $e){$denied=$e->getCode()==='42501';}
    $check('anchor consumer cannot read private table',$denied,true);
    break;
}
echo "PASS F33 phase={$phase} count={$n} (CI proof only, no release)".PHP_EOL;
