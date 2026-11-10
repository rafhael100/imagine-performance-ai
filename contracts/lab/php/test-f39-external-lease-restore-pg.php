<?php
declare(strict_types=1);

/**
 * F1-39 three CI phases: highwater survives restoration of Portal's F38
 * generation table; F38 gate alone is still vulnerable to restored ABA.
 */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticFencedLeaseGateway.php';
require_once __DIR__.'/SyntheticIndependentLeaseHighwater.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticFencedLeaseGateway;
use Imagine\Protocol\Lab\SyntheticIndependentLeaseHighwater;

$phase=getenv('PERF_F39_PHASE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
 ||getenv('PERF_F39_EXTERNAL_FENCE_LAB')!=='1'
 ||!in_array($phase,['first','second','restored'],true)
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
    throw new RuntimeException('F39 only runs in three isolated CI phases.');
}
$connect=static fn(string $db,int $port,string $user,string $pass):PDO=>
  new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
    $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_EMULATE_PREPARES=>false]);
$adminPortal=$connect('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$adminExternal=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$intent=new SyntheticIdempotentIntentGateway(
    $connect('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only')
);
$gate=new SyntheticFencedLeaseGateway(
    $connect('portal_perf_auth_lab',5432,'perf_f38_fenced_worker','ci-f38-fenced-only')
);
$external=new SyntheticIndependentLeaseHighwater(
    $connect('perf_f31_nonce_ci',5433,'perf_f39_fence_consumer','ci-f39-fence-only')
);
$manifest=hash('sha256','f39-after-restore-highwater-demo');
$ref='f39-lease-restore';
$owner='worker-f38-a';
$t=1800000000;$n=0;
$check=static function(string $msg,mixed $got,mixed $expected)use(&$n):void {
    if($got!==$expected){
        throw new RuntimeException('FAIL F39 '.$msg.' actual '.var_export($got,true)
            .' expected '.var_export($expected,true));
    }
    echo 'PASS F39 '.++$n.' '.$msg.PHP_EOL;
};
$parse=static function(string $value):array{
    if(!preg_match('/^claimed_mock:([1-9][0-9]*):([1-9][0-9]*)$/D',$value,$m)){
        throw new RuntimeException('Expected test-only lease id+generation: '.$value);
    }
    return [(int)$m[1],(int)$m[2]];
};
$currentId=static function()use($adminPortal,$ref):int {
    $s=$adminPortal->prepare(
        'SELECT o.id FROM perf_f35_intents.outbox o '
        .'JOIN perf_f35_intents.requests r ON r.id=o.intent_id '
        .'WHERE r.request_ref=?'
    );
    $s->execute([$ref]);
    return (int)$s->fetchColumn();
};
$highwater=static fn():int=>(int)$adminExternal->query(
    'SELECT max(highest_generation) FROM perf_f39_fence.generations'
)->fetchColumn();

switch($phase){
case 'first':
    $check('new synthetic intent is reserved',
        $intent->reserve($ref,$manifest),'reserved_synthetic');
    $check('independent fence initially empty',
        (int)$adminExternal->query('SELECT count(*) FROM perf_f39_fence.generations')->fetchColumn(),0);
    [$id,$gen]=$parse($gate->claim($owner,$t));
    $check('first F38 generation begins at one',$gen,1);
    $check('claim is specifically the F39 demo outbox id',$id,$currentId());
    $check('external DB admits generation one',
        $external->observe($id,$gen),'observed_synthetic_generation:1');
    $check('external highwater now one',$highwater(),1);
    $check('same generation is a replay, never a fresh claim',
        $external->observe($id,$gen),'rollback_or_replay_generation_denied');
    break;
case 'second':
    [$id,$gen]=$parse($gate->claim($owner,$t+31));
    $check('lease claims same F39 outbox id after 30 seconds',$id,$currentId());
    $check('generation advanced in Portal DB to two',$gen,2);
    $check('independent highwater also advances to two',
        $external->observe($id,$gen),'observed_synthetic_generation:2');
    $check('independent authority retains generation two',$highwater(),2);
    $check('generation one cannot be presented again',
        $external->observe($id,1),'rollback_or_replay_generation_denied');
    break;
case 'restored':
    $check('backup restoration did not restore external authority',$highwater(),2);
    [$id,$gen]=$parse($gate->claim($owner,$t+62));
    $check('restored Portal F38 ledger reused the SAME id',$id,$currentId());
    $check('EXPECTED DEFECT: restored Portal ledger recycled generation two',$gen,2);
    $check('independent authority refuses recycled generation',
        $external->observe($id,$gen),'rollback_or_replay_generation_denied');
    $check('external highwater remained two',$highwater(),2);
    // This is deliberately performed AFTER the safe fence denial, to prove
    // the old F38 API still bypasses it when called directly.
    $check('EXPECTED BYPASS: calling old F38 ACK directly accepts recycled token',
        $gate->acknowledge($id,$owner,$gen,$t+62),'acknowledged_mock');
    $check('external fence remains unchanged by legacy bypass',$highwater(),2);
    $adminExternal->exec(
        'REVOKE EXECUTE ON FUNCTION perf_f39_fence.advance(bigint,bigint) '
        .'FROM perf_f39_fence_consumer'
    );
    $check('loss of external fence permission denies safely',
        $external->observe($id,3),'external_fence_unavailable');
    $adminExternal->exec(
        'GRANT EXECUTE ON FUNCTION perf_f39_fence.advance(bigint,bigint) '
        .'TO perf_f39_fence_consumer'
    );
    $check('invalid external generation always denied',
        $external->observe($id,0),'invalid_generation_denied');
    $check('restored generation is still blocked after reconnecting',
        $external->observe($id,2),'rollback_or_replay_generation_denied');
    $denied=false;
    try{
        $connect('perf_f31_nonce_ci',5433,'perf_f39_fence_consumer',
            'ci-f39-fence-only')->query(
            'SELECT * FROM perf_f39_fence.generations'
        );
    }catch(PDOException $e){$denied=$e->getCode()==='42501';}
    $check('external consumer cannot SELECT its private highwater table',
        $denied,true);
    $wrong=new SyntheticIndependentLeaseHighwater($adminExternal);
    $check('superuser role cannot impersonate application fence',
        $wrong->observe($id,3),'wrong_external_authority');
    $check('no event was enabled for external dispatch',
        (int)$adminPortal->query(
            'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
        )->fetchColumn(),0);
    putenv('PERF_F39_EXTERNAL_FENCE_LAB=0');
    $check('feature flag disables independent fence',
        $external->observe($id,3),'external_fence_lab_disabled');
    putenv('PERF_F39_EXTERNAL_FENCE_LAB=1');
    putenv('APP_ENV=production');
    $check('production always denies synthetic external fence',
        $external->observe($id,3),'external_fence_lab_disabled');
    putenv('APP_ENV=testing');
    break;
}
echo "PASS F39 phase={$phase} assertions={$n} restore highwater CI only".PHP_EOL;
