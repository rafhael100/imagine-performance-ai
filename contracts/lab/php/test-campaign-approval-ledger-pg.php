<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/vendor/autoload.php';
require_once __DIR__.'/SyntheticCampaignApprovalLedger.php';

use Imagine\Protocol\Lab\SyntheticCampaignApprovalLedger;

if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('RUN_PERF_F22_PG_LAB') !== '1'
    || getenv('PERF_F23_LEDGER_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F23 ledger tests refuse non-disposable database.');
}
$connect=static fn (string $role,string $pass):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $role,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$admin=$connect('postgres','ci-f22-db-only');
$writer=$connect('perf_f23_writer','ci-f23-ledger-only');
$ledger=new SyntheticCampaignApprovalLedger($writer);
$A='11111111-1111-4111-8111-111111111111';
$B='22222222-2222-4222-8222-222222222222';
$campaign='88888888-8888-4888-8888-888888888888';
$actor='actor-demo-a';
$reviewer='reviewer-demo-a';
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected) use (&$n):void {
    if ($actual!==$expected) {
        throw new RuntimeException("FAIL F23 $label got ".var_export($actual,true)
            .' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F23 $n $label\n";
};
$one=static function(string $sql) use($admin):mixed=> $admin->query($sql)->fetchColumn();
$state=static function(string $request) use($admin):?array{
    $s=$admin->prepare(
        'SELECT tenant_id,review_state,reviewer_actor_ref,dispatch_state
         FROM performance_campaign_intents WHERE request_ref=?'
    );
    $s->execute([$request]);
    $r=$s->fetch(PDO::FETCH_ASSOC);
    return is_array($r)?$r:null;
};
$auditCount=static function(string $request) use($admin):int{
    $s=$admin->prepare('SELECT count(*) FROM audit_events WHERE correlation_ref=?');
    $s->execute([$request]);
    return (int)$s->fetchColumn();
};
$admin->prepare(
    "INSERT INTO performance_managed_campaigns
     (id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,
      created_at,updated_at)
     VALUES (?,?,?,'google_ads','cam-f23-demo','F23 test draft',
             clock_timestamp(),clock_timestamp())"
)->execute([$campaign,$A,'svc-demo-a']);
$admin->prepare(
    "INSERT INTO operational_memberships
     (id,tenant_id,actor_ref,role,status,scopes,expires_at,created_at,updated_at)
     VALUES (gen_random_uuid(),?,?,?,'active',?::jsonb,NULL,
             clock_timestamp(),clock_timestamp())"
)->execute([$A,$reviewer,'approver','["proposals:approve"]']);

$check('01 non-superuser only',
    $one("SELECT rolname FROM pg_roles WHERE rolname='perf_f23_writer'"),
    'perf_f23_writer');
$check('02 membership scoped to A',
    $one("SELECT tenant_id::text FROM operational_memberships
         WHERE actor_ref='reviewer-demo-a'"),$A);
$check('03 F23 operation stored without dispatch',
    $one("SELECT dispatch_state FROM performance_managed_campaigns
        WHERE id='{$campaign}'"),'disabled');
$check('04 no initial F23 audit',
    $auditCount('req-demo-f23-first'),0);

putenv('PERF_F23_LEDGER_LAB=0');
$check('05 flag removal closes proposer',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-first',$actor),'ledger_lab_disabled');
putenv('PERF_F23_LEDGER_LAB=1');

$check('06 cannot propose for foreign tenant',
    $ledger->propose($B,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-first',$actor),'proposal_scope_denied');
$check('07 cannot propose for foreign service',
    $ledger->propose($A,$campaign,'svc-demo-b','campaign.pause',
        'req-demo-f23-first',$actor),'proposal_scope_denied');
$check('08 cannot propose as foreign actor',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-first','actor-demo-b'),'proposal_scope_denied');
$check('09 malformed request key refused',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        '../../bad',$actor),'proposal_scope_denied');
$check('10 unsupported operation refused',
    $ledger->propose($A,$campaign,'svc-demo-a','billing.charge',
        'req-demo-f23-first',$actor),'proposal_scope_denied');
$check('11 nonexisting campaign cannot be proposed',
    $ledger->propose($A,'ffffffff-ffff-4fff-8fff-ffffffffffff','svc-demo-a',
        'campaign.pause','req-demo-f23-missing',$actor),
    'proposal_campaign_denied');
$check('12 missing campaign does not leave intent',
    $state('req-demo-f23-missing'),null);

$check('13 new request persists pending and audit atomically',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-first',$actor),'proposal_pending_demo');
$check('14 stored state pending',
    $state('req-demo-f23-first')['review_state'],'awaiting_approval');
$check('15 stored dispatch always disabled',
    $state('req-demo-f23-first')['dispatch_state'],'disabled');
$check('16 original proposal adds one audit',
    $auditCount('req-demo-f23-first'),1);
$check('17 exact replay idempotent',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-first',$actor),'already_proposed_demo');
$check('18 changed operation on same request_ref denied',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.replace',
        'req-demo-f23-first',$actor),'request_key_conflict');
$check('19 repeated request never duplicates audit',
    $auditCount('req-demo-f23-first'),1);
$check('20 request key exists exactly once',
    (int)$one("SELECT count(*) FROM performance_campaign_intents
                 WHERE request_ref='req-demo-f23-first'"),1);

$check('21 reviewer from another tenant refused',
    $ledger->review($B,'req-demo-f23-first',$reviewer,true),
    'review_scope_denied');
$check('22 proposer cannot approve own request',
    $ledger->review($A,'req-demo-f23-first',$actor,true),
    'review_scope_denied');
$check('23 reviewer outside fixed synthetic identity refused',
    $ledger->review($A,'req-demo-f23-first','other-reviewer',true),
    'review_scope_denied');
$check('24 reviewer cannot review absent request',
    $ledger->review($A,'req-demo-f23-missing',$reviewer,true),
    'review_intent_denied');

$admin->exec(
    "UPDATE operational_memberships SET status='inactive'
     WHERE actor_ref='reviewer-demo-a'"
);
$check('25 inactive membership cannot approve',
    $ledger->review($A,'req-demo-f23-first',$reviewer,true),
    'review_membership_denied');
$admin->exec(
    "UPDATE operational_memberships
     SET status='active',scopes='[\"reports:read\"]'::jsonb
     WHERE actor_ref='reviewer-demo-a'"
);
$check('26 wrong scope cannot approve',
    $ledger->review($A,'req-demo-f23-first',$reviewer,true),
    'review_membership_denied');
$admin->exec(
    "UPDATE operational_memberships
     SET scopes='[\"proposals:approve\"]'::jsonb,
         expires_at=clock_timestamp()-interval '1 minute'
     WHERE actor_ref='reviewer-demo-a'"
);
$check('27 expired membership cannot approve',
    $ledger->review($A,'req-demo-f23-first',$reviewer,true),
    'review_membership_denied');
$admin->exec(
    "UPDATE operational_memberships SET expires_at=NULL
     WHERE actor_ref='reviewer-demo-a'"
);
$check('28 review now succeeds only as internal approval',
    $ledger->review($A,'req-demo-f23-first',$reviewer,true),
    'review_approved_internal_demo');
$check('29 approved state stored',
    $state('req-demo-f23-first')['review_state'],'approved_internal');
$check('30 separate reviewer stored',
    $state('req-demo-f23-first')['reviewer_actor_ref'],$reviewer);
$check('31 approval still cannot dispatch',
    $state('req-demo-f23-first')['dispatch_state'],'disabled');
$check('32 review has exactly two audit records',
    $auditCount('req-demo-f23-first'),2);
$check('33 idempotent review returns already reviewed',
    $ledger->review($A,'req-demo-f23-first',$reviewer,true),
    'already_reviewed_demo');
$check('34 conflicting review refuses overwriting',
    $ledger->review($A,'req-demo-f23-first',$reviewer,false),
    'review_conflict');
$check('35 idempotent and conflicting reviews do not add audit',
    $auditCount('req-demo-f23-first'),2);

$check('36 independent proposal for rejection persists',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.replace',
        'req-demo-f23-rejected',$actor),'proposal_pending_demo');
$check('37 second reviewer can reject',
    $ledger->review($A,'req-demo-f23-rejected',$reviewer,false),
    'review_rejected_demo');
$check('38 rejected never dispatches',
    $state('req-demo-f23-rejected')['dispatch_state'],'disabled');
$check('39 rejected replay returns idempotently',
    $ledger->review($A,'req-demo-f23-rejected',$reviewer,false),
    'already_reviewed_demo');
$check('40 cannot approve an already-rejected request',
    $ledger->review($A,'req-demo-f23-rejected',$reviewer,true),
    'review_conflict');

// Force the SECOND write (audit) to fail after the intent insert.
// Its entire transaction must roll back, including the new intent row.
$admin->exec('REVOKE INSERT ON audit_events FROM perf_f23_writer');
$check('41 failed audit insert rolls back pending intent',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-failed-audit',$actor),'proposal_transaction_denied');
$check('42 failed transaction leaves no orphan intent',
    $state('req-demo-f23-failed-audit'),null);
$check('43 failed transaction leaves no audit',
    $auditCount('req-demo-f23-failed-audit'),0);
$admin->exec('GRANT INSERT ON audit_events TO perf_f23_writer');
$check('44 once audit role restored, exact request can be persisted',
    $ledger->propose($A,$campaign,'svc-demo-a','campaign.pause',
        'req-demo-f23-failed-audit',$actor),'proposal_pending_demo');

$admin->exec('REVOKE INSERT ON audit_events FROM perf_f23_writer');
$check('45 failed review audit rolls back approval update',
    $ledger->review($A,'req-demo-f23-failed-audit',$reviewer,true),
    'review_transaction_denied');
$check('46 previous pending review state remains',
    $state('req-demo-f23-failed-audit')['review_state'],'awaiting_approval');
$check('47 no separate review audit from failed transaction',
    $auditCount('req-demo-f23-failed-audit'),1);
$admin->exec('GRANT INSERT ON audit_events TO perf_f23_writer');
$check('48 review retries successfully after permission restored',
    $ledger->review($A,'req-demo-f23-failed-audit',$reviewer,true),
    'review_approved_internal_demo');

$writer->beginTransaction();
try {
    $tenantStmt=$writer->prepare(
        "SELECT set_config('app.performance_tenant_id', ?, true)"
    );
    $tenantStmt->execute([$A]);
    $check('49 writer cannot see B campaign',
        (int)$one("SELECT count(*) FROM performance_managed_campaigns
                    WHERE tenant_id='{$B}' AND campaign_ref='cam-b1'"),1);
    $check('50 restricted writer reads only own campaigns',
        (int)$writer->query(
            "SELECT count(*) FROM performance_managed_campaigns
             WHERE tenant_id='{$B}'"
        )->fetchColumn(),0);
    $check('51 restricted writer cannot see B intent',
        (int)$writer->query(
            "SELECT count(*) FROM performance_campaign_intents
             WHERE tenant_id='{$B}'"
        )->fetchColumn(),0);
} finally {
    $writer->rollBack();
}
$check('52 no account has used Ads dispatch state',
    (int)$one("SELECT count(*) FROM performance_campaign_intents
                WHERE dispatch_state<>'disabled'"),0);
$check('53 campaign state untouched by all approval transactions',
    $one("SELECT lifecycle_state FROM performance_managed_campaigns
        WHERE id='{$campaign}'"),'draft');
$check('54 all F23 requests remain same tenant',
    (int)$one("SELECT count(*) FROM performance_campaign_intents
        WHERE request_ref LIKE 'req-demo-f23-%'
          AND tenant_id<>'{$A}'"),0);
$check('55 no real provider IDs/access tokens in approval ledger schema',
    (int)$one("SELECT count(*) FROM information_schema.columns
        WHERE table_name='performance_campaign_intents'
        AND column_name IN ('provider_access_token','provider_account_id')"),0);
$check('56 this ledger role is not a superuser',
    $one("SELECT rolsuper FROM pg_roles WHERE rolname='perf_f23_writer'"),false);
echo "PASS F23 {$n} synthetic proposal/review, idempotency, audit, rollback and tenant RLS checks\n";
