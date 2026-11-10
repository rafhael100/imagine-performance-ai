<?php

declare(strict_types=1);

/**
 * F1-27: actual PostgreSQL journal and rollback assertions in disposable CI.
 * This journal records SQL facts, not authenticated human identity.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('RUN_PERF_F22_PG_LAB') !== '1'
    || getenv('PERF_F27_JOURNAL_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F27 refuses non-disposable PostgreSQL.');
}
$connect=static fn(string $user,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$admin=$connect('postgres','ci-f22-db-only');
$reader=$connect('perf_f27_journal_reader','ci-f27-reader-only');
$writer=$connect('perf_campaign_test_user','ci-f22-role-only');
$A='11111111-1111-4111-8111-111111111111';
$B='22222222-2222-4222-8222-222222222222';
$campaignA='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$campaignB='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$n=0;
$check=static function(string $label,mixed $got,mixed $want) use(&$n):void {
    if($got!==$want){
        throw new RuntimeException(
            'FAIL F27 '.$label.' got '.var_export($got,true)
            .' expected '.var_export($want,true)
        );
    }
    ++$n;
    echo "PASS F27 $n $label\n";
};
$scalar=static fn(string $sql):mixed=>$admin->query($sql)->fetchColumn();
$count=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM performance_intent_review_journal'
)->fetchColumn();
$state=static function(string $id) use($admin):string {
    $q=$admin->prepare(
        'SELECT review_state FROM performance_campaign_intents WHERE id=?'
    );
    $q->execute([$id]);
    return (string)$q->fetchColumn();
};
$journalFor=static function(string $id) use($admin):?array {
    $q=$admin->prepare(
        'SELECT tenant_id,intent_id,review_state,reviewer_actor_ref,
                manifest_version,payload_sha256,source,synthetic_only,
                request_ref,portal_service_ref
         FROM performance_intent_review_journal WHERE intent_id=?'
    );
    $q->execute([$id]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    return is_array($row)?$row:null;
};
$deny=static function(string $label,callable $cmd,string $stateCode,string $text)
    use($check):void {
    try{
        $cmd();
    }catch(PDOException $e){
        $state=$e->errorInfo[0]??$e->getCode();
        if ($state!==$stateCode
            || ($text!=='' && !str_contains($e->getMessage(),$text))) {
            throw new RuntimeException(
                'FAIL F27 '.$label.' unexpected '.$state.' '.$e->getMessage()
            );
        }
        $check($label,true,true);
        return;
    }
    throw new RuntimeException('FAIL F27 mutation was accepted: '.$label);
};
$setTenant=static function(string $uuid) use($reader):void {
    $q=$reader->prepare(
        "SELECT set_config('app.performance_tenant_id', ?, false)"
    );
    $q->execute([$uuid]);
};

$check('01 journal is FORCE RLS',
    $scalar("SELECT relrowsecurity AND relforcerowsecurity
      FROM pg_class WHERE oid='performance_intent_review_journal'::regclass"),true);
$check('02 terminal capture trigger is enabled',
    $scalar("SELECT tgenabled FROM pg_trigger
      WHERE tgname='perf_f27_after_review_transition'"),'O');
$check('03 journal append-only trigger enabled',
    $scalar("SELECT tgenabled FROM pg_trigger
      WHERE tgname='perf_f27_journal_append_only'"),'O');
$check('04 capture function has fixed SECURITY DEFINER privilege',
    $scalar("SELECT prosecdef FROM pg_proc
      WHERE proname='perf_f27_capture_review_transition'"),true);
$check('05 table has one unique record per intent',
    (int)$scalar("SELECT count(*) FROM pg_constraint
      WHERE conname='perf_f27_one_terminal_review_per_intent'"),1);

$originalFinalized=(int)$scalar(
    "SELECT count(*) FROM performance_campaign_intents
     WHERE review_state IN ('approved_internal','rejected')"
);
$check('06 five final decisions came from F23/F26 fixtures',
    $originalFinalized,5);
$check('07 five journal entries from those SQL transitions',
    $count(),5);
$check('08 no terminal intent lacks corresponding journal row',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents i
         LEFT JOIN performance_intent_review_journal j ON j.intent_id=i.id
         WHERE i.review_state IN ('approved_internal','rejected')
           AND j.intent_id IS NULL"
    ),0);
$check('09 no journal decision differs from source intent state',
    (int)$scalar(
        "SELECT count(*) FROM performance_intent_review_journal j
         JOIN performance_campaign_intents i ON i.id=j.intent_id
         WHERE j.review_state<>i.review_state"
    ),0);
$check('10 no journal tenant differs from intent tenant',
    (int)$scalar(
        "SELECT count(*) FROM performance_intent_review_journal j
         JOIN performance_campaign_intents i ON i.id=j.intent_id
         WHERE j.tenant_id<>i.tenant_id"
    ),0);
$check('11 no journal service differs from the intent service',
    (int)$scalar(
        "SELECT count(*) FROM performance_intent_review_journal j
         JOIN performance_campaign_intents i ON i.id=j.intent_id
         WHERE j.portal_service_ref<>i.portal_service_ref"
    ),0);
$check('12 all journal records carry explicit synthetic provenance',
    (int)$scalar(
        "SELECT count(*) FROM performance_intent_review_journal
         WHERE source <> 'synthetic_postgresql_only' OR synthetic_only<>true"
    ),0);
$check('13 no journal entry for the proposal while still pending',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents i
         JOIN performance_intent_review_journal j ON j.intent_id=i.id
         WHERE i.review_state='awaiting_approval'"
    ),0);
$check('14 no real dispatch in journal schema',
    (int)$scalar(
        "SELECT count(*) FROM information_schema.columns
         WHERE table_name='performance_intent_review_journal'
           AND column_name IN ('access_token','provider_account_id','media_spend')"
    ),0);

$check('15 before context GUC, read-only journal role sees nothing',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),0);
$setTenant($A);
$check('16 tenant A sees only its five decisions',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),5);
$setTenant($B);
$check('17 tenant B initially sees no A review',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),0);
$check('18 journal reader cannot INSERT',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_f27_journal_reader','performance_intent_review_journal','INSERT')"
    ),false);
$check('19 journal reader cannot UPDATE',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_f27_journal_reader','performance_intent_review_journal','UPDATE')"
    ),false);
$check('20 journal reader cannot DELETE',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_f27_journal_reader','performance_intent_review_journal','DELETE')"
    ),false);
$check('21 campaign-writer role cannot read review journal',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_campaign_test_user','performance_intent_review_journal','SELECT')"
    ),false);
$check('22 campaign-writer role cannot directly INSERT journal entry',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_campaign_test_user','performance_intent_review_journal','INSERT')"
    ),false);
$check('23 journal reader cannot read member identities table',
    (bool)$scalar(
        "SELECT has_table_privilege(
          'perf_f27_journal_reader','operational_memberships','SELECT')"
    ),false);
$check('24 journal reader is not PostgreSQL superuser',
    $scalar(
        "SELECT rolsuper FROM pg_roles
         WHERE rolname='perf_f27_journal_reader'"
    ),false);

$deny('25 even admin SQL cannot silently UPDATE journal while trigger enabled',
    static fn()=>$admin->exec(
        "UPDATE performance_intent_review_journal
         SET request_ref='forged' WHERE review_state='approved_internal'"
    ),'P0001','perf_f27_journal_is_append_only');
$deny('26 even admin SQL cannot silently DELETE journal while trigger enabled',
    static fn()=>$admin->exec(
        'DELETE FROM performance_intent_review_journal'
    ),'P0001','perf_f27_journal_is_append_only');
$check('27 blocked manipulation preserved five records',
    $count(),5);

// A second fictional tenant appears in its own RLS partition.
$bStatement=$admin->prepare(
    "INSERT INTO performance_campaign_intents
      (id,tenant_id,campaign_id,portal_service_ref,operation,
       proposer_actor_ref,manifest_version,request_ref,payload_sha256,
       review_state,dispatch_state,created_at,updated_at)
     VALUES(gen_random_uuid(),?,?,?,'campaign.pause',
            'actor-demo-b','manifest-demo-v1','req-demo-f27-b',?,
            'awaiting_approval','disabled',clock_timestamp(),clock_timestamp())
     RETURNING id"
);
$bStatement->execute([$B,$campaignB,'svc-demo-b',str_repeat('b',64)]);
$bId=(string)$bStatement->fetchColumn();
$check('28 B test proposal is initially pending',
    $state($bId),'awaiting_approval');
$check('29 B pending request has no journal row',
    $journalFor($bId),null);
$admin->prepare(
    "UPDATE performance_campaign_intents SET
       review_state='rejected',reviewer_actor_ref='reviewer-demo-b',
       updated_at=clock_timestamp()
     WHERE id=?"
)->execute([$bId]);
$check('30 B review transitioned to rejected',
    $state($bId),'rejected');
$check('31 DB automatically recorded B journal',
    $journalFor($bId)['review_state']??null,'rejected');
$check('32 B reviewer is attributed as a fictitious name only',
    $journalFor($bId)['reviewer_actor_ref']??null,'reviewer-demo-b');
$check('33 B status required no application-level journal INSERT',
    $count(),6);
$setTenant($B);
$check('34 B reader sees exactly one B journal',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),1);
$setTenant($A);
$check('35 A reader never sees B journal',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),5);

// The journal write must be in the same transaction as the decision.
// Deliberately make journal insertion impossible, then restore the schema.
$prepared=$admin->prepare(
    "INSERT INTO performance_campaign_intents
     (id,tenant_id,campaign_id,portal_service_ref,operation,
      proposer_actor_ref,manifest_version,request_ref,payload_sha256,
      review_state,dispatch_state,created_at,updated_at)
     VALUES(gen_random_uuid(),?,?,?,'campaign.pause',
            'actor-demo-a','manifest-demo-v1','req-demo-f27-atomic',?,
            'awaiting_approval','disabled',clock_timestamp(),clock_timestamp())
     RETURNING id"
);
$prepared->execute([$A,$campaignA,'svc-demo-a',str_repeat('a',64)]);
$atomicId=(string)$prepared->fetchColumn();
$check('36 atomic fixture starts pending',
    $state($atomicId),'awaiting_approval');
$check('37 atomic pending fixture has no journal yet',
    $journalFor($atomicId),null);
$admin->exec(
    'ALTER TABLE performance_intent_review_journal '
    . 'ADD CONSTRAINT perf_f27_test_insertion_failure_ck CHECK(false) NOT VALID'
);
$deny('38 journal CHECK failure cancels review transition itself',
    static fn()=>$admin->prepare(
        "UPDATE performance_campaign_intents SET
          review_state='approved_internal',
          reviewer_actor_ref='reviewer-demo-a',
          updated_at=clock_timestamp() WHERE id=?"
    )->execute([$atomicId]),
    '23514','perf_f27_test_insertion_failure_ck');
$check('39 atomic review remains pending after journal write fails',
    $state($atomicId),'awaiting_approval');
$check('40 no journal created from failed review',
    $journalFor($atomicId),null);
$admin->exec(
    'ALTER TABLE performance_intent_review_journal '
    . 'DROP CONSTRAINT perf_f27_test_insertion_failure_ck'
);
$check('41 resubmitted review advances after audit storage recovery',
    $admin->prepare(
        "UPDATE performance_campaign_intents SET
          review_state='approved_internal',
          reviewer_actor_ref='reviewer-demo-a',
          updated_at=clock_timestamp() WHERE id=?"
    )->execute([$atomicId]),true);
$check('42 after recovery, decision was committed',
    $state($atomicId),'approved_internal');
$check('43 exactly one journal row exists for retried review',
    (int)$scalar(
        "SELECT count(*) FROM performance_intent_review_journal
         WHERE intent_id='{$atomicId}'"
    ),1);
$check('44 journal state matches recovered decision',
    $journalFor($atomicId)['review_state']??null,'approved_internal');
$check('45 journal request reference stays a synthetic ID',
    $journalFor($atomicId)['request_ref']??null,'req-demo-f27-atomic');
$check('46 journal manifest digest has fixed 64 hex length',
    strlen((string)($journalFor($atomicId)['payload_sha256']??'')),64);
$check('47 no final state is missing journal after retry',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents i
         LEFT JOIN performance_intent_review_journal j ON j.intent_id=i.id
         WHERE i.review_state IN ('approved_internal','rejected')
           AND j.intent_id IS NULL"
    ),0);
$check('48 total terminal journal entries after B and retry',
    $count(),7);
$check('49 provider dispatch still disabled on every intent',
    (int)$scalar(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE dispatch_state <> 'disabled'"
    ),0);
$check('50 campaigns remain without active provider state',
    (int)$scalar(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE dispatch_state <> 'disabled'"
    ),0);
echo "PASS F27 $n PostgreSQL journal/RLS/rollback checks in isolated CI\n";
