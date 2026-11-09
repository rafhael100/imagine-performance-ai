<?php
declare(strict_types=1);

// Disposable F1-34 CI: legacy bypass is denied by default but anchored path works.
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalEd25519DurableReplayGate.php';
require_once __DIR__.'/SyntheticPortalAnchoredReplayGate.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Domain\PerformanceBridge\PortalPerfSqlAuthorityResolver;
use Imagine\Protocol\Lab\SyntheticPortalAnchoredReplayGate;
use Imagine\Protocol\Lab\SyntheticPortalEd25519DurableReplayGate;

$root=getenv('F30_PORTAL_SOURCE_ROOT');
$packetFile=getenv('F34_PORTAL_PACKET');
$keyFile=getenv('F34_PORTAL_KEY');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F34_GUARD_LAB')!=='1'
 ||getenv('PERF_F34_LEGACY_COMPAT_TEST')==='1'
 ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
 ||getenv('PERF_F31_ED25519_REPLAY_LAB')!=='1'
 ||getenv('PERF_F33_PORTAL_ANCHOR_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($root)||!is_dir($root)
 ||!is_string($packetFile)||!is_file($packetFile)
 ||!is_string($keyFile)||!is_file($keyFile)) {
    throw new RuntimeException('F34 requires isolated CI; legacy compatibility must be disabled.');
}
foreach(['PortalPerfAuthorityFacts.php','PortalPerfSqlAuthorityResolver.php'] as $file){
 require_once $root.'/apps/portal/app/Domain/PerformanceBridge/'.$file;
}
$pdo=static fn(string $db,int $port,string $role,string $password):PDO =>
 new PDO("pgsql:host=127.0.0.1;port=$port;dbname=$db;connect_timeout=3",
    $role,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false]);
$reader=$pdo('portal_perf_auth_lab',5432,'portal_f08_reader','ci-f08-read-only');
$anchor=$pdo('portal_perf_auth_lab',5432,'perf_f33_anchor_consumer','ci-f33-anchor-only');
$local=$pdo('perf_f31_nonce_ci',5433,'perf_f31_consumer','ci-f31-consumer-only');
$adminA=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$adminL=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$resolver=new PortalPerfSqlAuthorityResolver($reader);
$now=1800000000;
$current=static function()use($resolver,$now):?SyntheticCurrentAuthority {
 $f=$resolver->resolve('portal-f08-opaque-session-test-aaaaaaaaaaaaaaaaaaa','svc-demo-a',$now);
 if(!$f)return null;
 return new SyntheticCurrentAuthority(
 organizationRef:$f->organizationRef,tenantRef:$f->tenantRef,
 serviceRef:$f->serviceRef,resourceRef:'demo-cam-a1',
 actorRef:$f->actorRef,sessionRef:$f->sessionRef,permission:$f->permission,
 sessionActive:true,mfaVerified:true,membershipActive:true,serviceActive:true,
 membershipRevision:$f->membershipRevision,serviceRevision:$f->serviceRevision,
 sessionRevision:$f->sessionRevision,recoveryEpoch:$f->recoveryEpoch,
 sourceAvailable:true,observedAt:$f->sourceObservedAt);
};
$packet=trim((string)file_get_contents($packetFile));
$pub=trim((string)file_get_contents($keyFile));
$old=static fn()=>new SyntheticPortalEd25519DurableReplayGate($pub,$local);
$new=static fn()=>new SyntheticPortalAnchoredReplayGate($pub,$anchor,$local);
$countA=static fn()=>(int)$adminA->query('SELECT count(*) FROM perf_f33_anchor.used_nonces')->fetchColumn();
$countL=static fn()=>(int)$adminL->query('SELECT count(*) FROM perf_f31_receipts.used_nonces')->fetchColumn();
$n=0;
$check=static function(string $name,mixed $actual,mixed $expected)use(&$n):void {
 if($actual!==$expected)throw new RuntimeException("FAIL F34 $name actual=".var_export($actual,true)." expected=".var_export($expected,true));
 echo "PASS F34 ".++$n." $name".PHP_EOL;
};
$a=$countA();$l=$countL();
$check('source epoch advanced from F32',$current()?->recoveryEpoch,8);
$check('legacy direct gate denies before touching ledger',$old()->verifyOnce($packet,$current,$now),'legacy_unanchored_denied');
$check('direct denial has no side effects',[$countA(),$countL()],[$a,$l]);
$check('anchored canonical path succeeds with new signed claim',$new()->verifyOnce($packet,$current,$now),'verified_synthetic_anchored_once');
$check('both receipts commit under canonical path',[$countA(),$countL()],[$a+1,$l+1]);
$check('repeat anchored claim denied',$new()->verifyOnce($packet,$current,$now),'portal_anchor_replay_denied');
$check('direct old verifier still closed',$old()->verifyOnce($packet,$current,$now),'legacy_unanchored_denied');
$check('replay leaves both ledgers unchanged',[$countA(),$countL()],[$a+1,$l+1]);
putenv('APP_ENV=production');
$check('canonical gate closed in production',$new()->verifyOnce($packet,$current,$now),'deployment_gate_closed');
$check('old gate closed in production',$old()->verifyOnce($packet,$current,$now),'deployment_gate_closed');
putenv('APP_ENV=testing');
putenv('PERF_F33_PORTAL_ANCHOR_LAB=0');
$check('missing anchor flag denies canonical gate',$new()->verifyOnce($packet,$current,$now),'deployment_gate_closed');
putenv('PERF_F33_PORTAL_ANCHOR_LAB=1');
$check('no operational Ads tables populated',
 $adminA->query("SELECT to_regclass('public.performance_managed_campaigns') IS NULL")->fetchColumn(),true);
echo "PASS F34 $n legacy denial and anchored lab assertions".PHP_EOL;
