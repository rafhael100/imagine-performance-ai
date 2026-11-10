<?php

declare(strict_types=1);

/**
 * F1-26 executable PostgreSQL constraints, not a real human approval/auth
 * system. Runs after F1-25 preview snapshot parity to avoid fixture drift.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('RUN_PERF_F22_PG_LAB') !== '1'
    || getenv('PERF_F26_INTEGRITY_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F1-26 test refuses non-disposable database.');
}
$connect=static fn(string $user,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$db=$connect('perf_campaign_test_user','ci-f22-role-only');
$admin=$connect('postgres','ci-f22-db-only');
$A='11111111-1111-4111-8111-111111111111';
$B='22222222-2222-4222-8222-222222222222';
$campaignA='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$intentA='cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$actor='actor-demo-a';
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected) use (&$n):void {
    if($actual!==$expected){
        throw new RuntimeException('FAIL F26 '.$label.' actual='.var_export($actual,true)
            .' expected='.var_export($expected,true));
    }
    ++$n;
    echo "PASS F26 $n $label\n";
};
$scalar=static fn(string $sql):mixed=>$db->query($sql)->fetchColumn();
$adminScalar=static fn(string $sql):mixed=>$admin->query($sql)->fetchColumn();
$reject=static function(string $label,callable $cmd,string $expectedError) use ($check):void{
    try{
        $cmd();
    } catch(PDOException $e){
        $state=$e->errorInfo[0]??$e->getCode();
        if($state!=='P0001' || !str_contains($e->getMessage(),$expectedError)){
            throw new RuntimeException(
                'FAIL F26 '.$label.' unexpected SQLSTATE/error '
                .$state.' '.$e->getMessage()
            );
        }
        $check($label,true,true);
        return;
    }
    throw new RuntimeException('FAIL F26 '.$label.' was allowed');
};
$change=static function(string $id,string $set) use($db):int{
    $sql='UPDATE performance_campaign_intents SET '.$set
        ." WHERE id='".$id."'";
    return $db->exec($sql);
};
$pending=static function(string $id) use($db):array{
    $stmt=$db->prepare(
        'SELECT id,tenant_id,campaign_id,portal_service_ref,operation,
                proposer_actor_ref,reviewer_actor_ref,manifest_version,
                request_ref,payload_sha256,dispatch_state,review_state,
                created_at,updated_at
         FROM performance_campaign_intents WHERE id=?'
    );
    $stmt->execute([$id]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)){
        throw new RuntimeException('F26 fixture missing '.$id);
    }
    return $row;
};
$makePending=static function(string $requestRef) use($db,$A,$campaignA,$actor):string {
    $stmt=$db->prepare(
        "INSERT INTO performance_campaign_intents
         (id,tenant_id,campaign_id,portal_service_ref,operation,
          proposer_actor_ref,manifest_version,request_ref,payload_sha256,
          review_state,dispatch_state,created_at,updated_at)
         VALUES (gen_random_uuid(),?,?,?,'campaign.pause',
                 ?,'manifest-demo-v1',?,?,'awaiting_approval','disabled',
                 clock_timestamp(),clock_timestamp()) RETURNING id"
    );
    $stmt->execute([
        $A,$campaignA,'svc-demo-a',$actor,$requestRef,str_repeat('a',64)
    ]);
    return (string)$stmt->fetchColumn();
};
$db->prepare(
    "SELECT set_config('app.performance_tenant_id', ?, false)"
)->execute([$A]);

$check('01 F26 row trigger exists',
    (int)$adminScalar(
        "SELECT count(*) FROM pg_trigger WHERE tgname='perf_f26_intent_row_guard'
         AND tgrelid='performance_campaign_intents'::regclass AND NOT tgisinternal"
    ),1);
$check('02 F26 trigger enabled for all callers',
    $adminScalar(
        "SELECT tgenabled FROM pg_trigger
         WHERE tgname='perf_f26_intent_row_guard'"
    ),'O');
$check('03 F26 function is not SECURITY DEFINER',
    $adminScalar(
        "SELECT prosecdef FROM pg_proc
         WHERE proname='perf_f26_guard_intent_row'"
    ),false);
$check('04 test SQL role cannot disable trigger without table ownership',
    $adminScalar(
        "SELECT rolsuper FROM pg_roles WHERE rolname='perf_campaign_test_user'"
    ),false);
$check('05 original F22 request remains pending',
    $pending($intentA)['review_state'],'awaiting_approval');
$check('06 original F22 reviewer remains null',
    $pending($intentA)['reviewer_actor_ref'],null);
$check('07 original F22 dispatch remains disabled',
    $pending($intentA)['dispatch_state'],'disabled');

$target=$intentA;
$reject('08 cannot change tenant after creation',
    static fn()=> $change($target,
        "tenant_id='22222222-2222-4222-8222-222222222222'"),
    'perf_intent_immutable_fields');
$reject('09 cannot switch campaign after creation',
    static fn()=> $change($target,
        "campaign_id='88888888-8888-4888-8888-888888888888'"),
    'perf_intent_immutable_fields');
$reject('10 cannot change linked Portal service',
    static fn()=> $change($target,"portal_service_ref='svc-demo-a2'"),
    'perf_intent_immutable_fields');
$reject('11 cannot change operation after creation',
    static fn()=> $change($target,"operation='campaign.replace'"),
    'perf_intent_immutable_fields');
$reject('12 cannot change proposal actor',
    static fn()=> $change($target,"proposer_actor_ref='rogue-demo-actor'"),
    'perf_intent_immutable_fields');
$reject('13 cannot replace approved manifest version',
    static fn()=> $change($target,"manifest_version='manifest-demo-v0'"),
    'perf_intent_immutable_fields');
$reject('14 cannot rebind request idempotency key',
    static fn()=> $change($target,"request_ref='request-demo-f26-rogue'"),
    'perf_intent_immutable_fields');
$reject('15 cannot replace request digest',
    static fn()=> $change($target,"payload_sha256='".str_repeat('b',64)."'"),
    'perf_intent_immutable_fields');
$reject('16 cannot falsify original creation timestamp',
    static fn()=> $change($target,"created_at=created_at+interval '2 days'"),
    'perf_intent_immutable_fields');
$reject('17 cannot replace intent UUID',
    static fn()=> $change($target,
        "id='ffffffff-ffff-4fff-8fff-ffffffffffff'"),
    'perf_intent_immutable_fields');
$reject('18 cannot force dispatch state on existing request',
    static fn()=> $change($target,"dispatch_state='queued'"),
    'perf_intent_immutable_fields');
$reject('19 cannot attach reviewer while still pending',
    static fn()=> $change($target,"reviewer_actor_ref='reviewer-demo-1'"),
    'perf_intent_illegal_transition');
$reject('20 cannot modify timestamp without final decision',
    static fn()=> $change($target,"updated_at=clock_timestamp()"),
    'perf_intent_illegal_transition');
$reject('21 cannot set terminal state without a reviewer',
    static fn()=> $change($target,"review_state='rejected'"),
    'perf_intent_independent_reviewer_required');
$reject('22 cannot self-review by matching proposer',
    static fn()=> $change($target,
        "review_state='approved_internal',reviewer_actor_ref='actor-demo-a'"),
    'perf_intent_independent_reviewer_required');

$insertApproved=static function(string $state,?string $reviewer)
    use($db,$A,$campaignA,$actor):void{
    $sql= "INSERT INTO performance_campaign_intents
        (id,tenant_id,campaign_id,portal_service_ref,operation,
         proposer_actor_ref,reviewer_actor_ref,manifest_version,request_ref,
         payload_sha256,review_state,dispatch_state,created_at,updated_at)
         VALUES(gen_random_uuid(),?,?,?,'campaign.pause',?,?,
                'manifest-demo-v1','request-demo-f26-insert-rogue',?,
                ?,'disabled',clock_timestamp(),clock_timestamp())";
    $db->prepare($sql)->execute([
        $A,$campaignA,'svc-demo-a',$actor,$reviewer,str_repeat('a',64),$state
    ]);
};
$reject('23 cannot pre-create approved review with separate name',
    static fn()=> $insertApproved('approved_internal','reviewer-demo-b'),
    'perf_intent_must_start_pending');
$reject('24 cannot pre-create rejected review with separate name',
    static fn()=> $insertApproved('rejected','reviewer-demo-b'),
    'perf_intent_must_start_pending');
$reject('25 cannot create awaiting review with reviewer already set',
    static fn()=> $insertApproved('awaiting_approval','reviewer-demo-b'),
    'perf_intent_must_start_pending');
$check('26 forged insert request not present',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE request_ref='request-demo-f26-insert-rogue'"
    ),0);
$reject('27 direct SQL deletion is forbidden in this pre-release migration',
    static fn()=> $db->exec(
        "DELETE FROM performance_campaign_intents WHERE id='{$target}'"
    ),
    'perf_intent_delete_blocked');
$check('28 blocked delete leaves pending request unchanged',
    $pending($target)['review_state'],'awaiting_approval');

// This tests *data integrity only*: the actor name is not authenticated.
// A production review must be routed through verified membership and audit.
$check('29 valid transition to internal approved is allowed by DB invariant',
    $change($target,
        "review_state='approved_internal',
         reviewer_actor_ref='reviewer-demo-other',
         updated_at=clock_timestamp()"
    ),1);
$check('30 final internal state stored',
    $pending($target)['review_state'],'approved_internal');
$check('31 reviewer name distinct from proposer',
    $pending($target)['reviewer_actor_ref'],'reviewer-demo-other');
$check('32 dispatch stays disabled after review',
    $pending($target)['dispatch_state'],'disabled');
$reject('33 cannot reopen internally approved request',
    static fn()=> $change($target,
        "review_state='awaiting_approval',
         reviewer_actor_ref=NULL"),
    'perf_intent_terminal_state_immutable');
$reject('34 cannot change approved state to rejected',
    static fn()=> $change($target,
        "review_state='rejected',reviewer_actor_ref='reviewer-2'"),
    'perf_intent_terminal_state_immutable');
$reject('35 cannot replace original reviewer once terminal',
    static fn()=> $change($target,
        "reviewer_actor_ref='reviewer-2'"),
    'perf_intent_terminal_state_immutable');
$reject('36 cannot modify approved timestamp after decision',
    static fn()=> $change($target,
        "updated_at=updated_at+interval '1 minute'"),
    'perf_intent_terminal_state_immutable');
$reject('37 cannot delete already approved row',
    static fn()=> $db->exec(
        "DELETE FROM performance_campaign_intents WHERE id='{$target}'"
    ),
    'perf_intent_delete_blocked');

$newId=$makePending('request-demo-f26-legal-reject');
$check('38 new pending request inserted with no reviewer',
    $pending($newId)['reviewer_actor_ref'],null);
$check('39 initial status of new request',
    $pending($newId)['review_state'],'awaiting_approval');
$check('40 pending -> rejected with distinct reviewer',
    $change($newId,
        "review_state='rejected',reviewer_actor_ref='reviewer-demo-other'"),
    1);
$check('41 rejected state is terminal',
    $pending($newId)['review_state'],'rejected');
$reject('42 cannot approve after rejection',
    static fn()=> $change($newId,"review_state='approved_internal'"),
    'perf_intent_terminal_state_immutable');
$reject('43 cannot switch reviewer after rejection',
    static fn()=> $change($newId,
        "reviewer_actor_ref='reviewer-demo-third'"),
    'perf_intent_terminal_state_immutable');
$check('44 source campaign dispatch still disabled',
    $adminScalar(
        "SELECT dispatch_state FROM performance_managed_campaigns
         WHERE id='{$campaignA}'"
    ),'disabled');
$check('45 no row can have non-disabled dispatch',
    (int)$adminScalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE dispatch_state<>'disabled'"
    ),0);
$check('46 foreign tenant B intent invisible through RLS',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE tenant_id='{$B}'"
    ),0);
$check('47 no F26 operation changed campaign lifecycle',
    $adminScalar(
        "SELECT lifecycle_state FROM performance_managed_campaigns
         WHERE id='{$campaignA}'"
    ),'paused');
$check('48 F26 direct SQL tests did not create provider execution',
    (int)$adminScalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE review_state='approved_internal' AND dispatch_state<>'disabled'"
    ),0);
$check('49 preserved F23 rejected request remains rejected',
    $adminScalar(
        "SELECT review_state FROM performance_campaign_intents
         WHERE request_ref='req-demo-f23-rejected'"
    ),'rejected');
$check('50 all new test approvals belong to expected demo tenant',
    (int)$adminScalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE request_ref LIKE 'request-demo-f26-%'
           AND tenant_id<>'{$A}'"
    ),0);

echo "PASS F26 $n PostgreSQL intent immutability and one-way transition checks\n";
