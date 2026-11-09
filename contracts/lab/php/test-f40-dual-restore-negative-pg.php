<?php
declare(strict_types=1);

/**
 * F1-40 expected-negative CI: restoring BOTH SQL authorities permits the
 * same generation to be issued and admitted again. This proves a gap, not
 * a mitigation or operational safety of the external fence.
 */
require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticFencedLeaseGateway.php';
require_once __DIR__.'/SyntheticIndependentLeaseHighwater.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticFencedLeaseGateway;
use Imagine\Protocol\Lab\SyntheticIndependentLeaseHighwater;

$phase=getenv('PERF_F40_PHASE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
 ||getenv('PERF_F39_EXTERNAL_FENCE_LAB')!=='1'
 ||getenv('PERF_F40_DUAL_RESTORE_LAB')!=='1'
 ||!in_array($phase,['first','second','restored'],true)
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
    throw new RuntimeException('F40 only runs in explicit disposable dual-restore CI.');
}
$connect=static fn(string $db,int $port,string $user,string $pass):PDO=>
    new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
        $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false]);
$portalAdmin=$connect('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$fenceAdmin=$connect('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$intent=new SyntheticIdempotentIntentGateway(
    $connect('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only'));
$lease=new SyntheticFencedLeaseGateway(
    $connect('portal_perf_auth_lab',5432,'perf_f38_fenced_worker','ci-f38-fenced-only'));
$outside=new SyntheticIndependentLeaseHighwater(
    $connect('perf_f31_nonce_ci',5433,'perf_f39_fence_consumer','ci-f39-fence-only'));
$t=1800000000;
$owner='worker-f38-a';
$request='f40-dual-restore';
$manifest=hash('sha256','f40-dual-restore-negative-demo');
$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected){
        throw new RuntimeException('FAIL F40 '.$name.' got '
            .var_export($got,true).' expected '.var_export($expected,true));
    }
    echo 'PASS F40 '.++$n.' '.$name.PHP_EOL;
};
$token=static function(string $claim):array{
    if(!preg_match('/^claimed_mock:([1-9][0-9]*):([1-9][0-9]*)$/D',$claim,$m)){
        throw new RuntimeException('Invalid mock token '.$claim);
    }
    return [(int)$m[1],(int)$m[2]];
};
$idForRequest=static function()use($portalAdmin,$request):int{
    $s=$portalAdmin->prepare(
        'SELECT o.id FROM perf_f35_intents.outbox o '
        .'INNER JOIN perf_f35_intents.requests r ON r.id=o.intent_id '
        .'WHERE r.request_ref=?'
    );
    $s->execute([$request]);
    return (int)$s->fetchColumn();
};
$highest=static function(int $id)use($fenceAdmin):int {
    $q=$fenceAdmin->prepare(
        'SELECT highest_generation FROM perf_f39_fence.generations WHERE outbox_id=?'
    );
    $q->execute([$id]);
    return (int)$q->fetchColumn();
};

switch($phase) {
case 'first':
    $check('new read-only demo intent safely reserved',
        $intent->reserve($request,$manifest),'reserved_synthetic');
    [$id,$g1]=$token($lease->claim($owner,$t));
    $check('first lease is for exact F40 demo outbox',$id,$idForRequest());
    $check('first generation begins at 1',$g1,1);
    $check('independent authority accepts first generation',
        $outside->observe($id,$g1),'observed_synthetic_generation:1');
    $check('external highwater recorded 1',$highest($id),1);
    break;

case 'second':
    [$id,$g2]=$token($lease->claim($owner,$t+31));
    $check('first lease expires and can be reacquired',$id,$idForRequest());
    $check('second generation issued before dual restore',$g2,2);
    $check('external highwater advances to 2',
        $outside->observe($id,$g2),'observed_synthetic_generation:2');
    $check('external authority remembers generation 2',$highest($id),2);
    $check('repeated generation 2 denied before restoring either DB',
        $outside->observe($id,$g2),'rollback_or_replay_generation_denied');
    break;

case 'restored':
    $id=$idForRequest();
    $check('after BOTH backups restored, highwater also rolled back',
        $highest($id),1);
    [$recycledId,$recycledGen]=$token($lease->claim($owner,$t+62));
    $check('restored Portal lease reissues the same outbox id',
        $recycledId,$id);
    $check('EXPECTED VULNERABILITY: recycled generation two',
        $recycledGen,2);
    $check('EXPECTED VULNERABILITY: restored external DB accepts recycled gen2',
        $outside->observe($id,$recycledGen),'observed_synthetic_generation:2');
    $check('external highwater is now 2 again',$highest($id),2);
    $check('second replay after regenerated record is denied',
        $outside->observe($id,$recycledGen),'rollback_or_replay_generation_denied');
    $check('no external dispatch flag enabled',
        (int)$portalAdmin->query(
            'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
        )->fetchColumn(),0);
    $check('all intents remain synthetic pending',
        (int)$portalAdmin->query(
            "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
        )->fetchColumn(),0);
    $check('no operational ads/providing action was dispatched',
        $portalAdmin->query(
            "SELECT to_regclass('public.performance_managed_campaigns') IS NULL"
        )->fetchColumn(),true);
    putenv('PERF_F40_DUAL_RESTORE_LAB=0');
    $check('dual-restore harness flag turned off',
        getenv('PERF_F40_DUAL_RESTORE_LAB'),'0');
    putenv('PERF_F40_DUAL_RESTORE_LAB=1');
    putenv('APP_ENV=production');
    $check('existing external fence closes in production',
        $outside->observe($id,3),'external_fence_lab_disabled');
    $check('existing Portal lease closes in production',
        $lease->claim($owner,$t+100),'lease_lab_disabled');
    putenv('APP_ENV=testing');
    break;
}
echo "PASS F40 phase={$phase} assertions={$n} expected-negative dual-restore only".PHP_EOL;
