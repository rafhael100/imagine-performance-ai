<?php
declare(strict_types=1);

// This test runs AFTER pg_portal_v7_acceptance_revocation_nonce_e2e_test.php
// in the SAME disposable PostgreSQL18 database. The prior test consumed a
// legitimate BETA signed-plan nonce under synthetic, versioned approval.
// Here we simulate the later action boundary and inspect current authority.
require_once __DIR__.'/../labs/finance-pg/PgPortalV7AcceptanceFinalReviewLab.php';

use Imagine\Performance\Commercial\Lab\PgPortalV7AcceptanceFinalReviewLab as FinalCheck;

function finalMust(bool $ok,string $label): void
{
    if (!$ok) throw new RuntimeException('V7 final review CI: '.$label);
}
foreach ([
    'APP_ENV'=>'testing',
    'PERF_PG_V6_ROLE_LAB'=>'1',
    'PERF_PG_V7_ACCEPTANCE_LAB'=>'1',
    'PERF_PG_V7_FINAL_REVIEW_LAB'=>'1',
] as $key=>$value) {
    if (getenv($key)!==$value) throw new RuntimeException('Missing CI gate '.$key);
}
$create=static fn(string $user,string $pass):PDO=>new PDO(
    getenv('PERF_LAB_PG_DSN'),$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$admin=$create('postgres',getenv('PERF_PG_ADMIN_PASS'));
$alpha=$create('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$create('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$tenantA='tenant_v6multi_alpha_123456';
$tenantB='tenant_v6multi_beta_567890';
$agreement='agreement_acceptance_beta_1234567';
$period='2026-11';
$now=(new DateTimeImmutable('2026-11-14T21:05:00Z'))->getTimestamp();
$latest=$beta->query(
    "SELECT event_revision,plan_sha256,terms_sha256,state
       FROM perf_fin_role_lab.portal_v7_acceptance_events
      WHERE tenant_id='tenant_v6multi_beta_567890'
        AND agreement_id='agreement_acceptance_beta_1234567'
        AND period='2026-11'
      ORDER BY event_revision DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
finalMust(is_array($latest) && (int)$latest['event_revision']===1 &&
    $latest['state']==='synthetic_accepted',
    'must start from an independently accepted plan and consumed nonce fixture');
$hash=$latest['plan_sha256'];
$terms=$latest['terms_sha256'];
$reviewB=new FinalCheck($beta);
$reviewA=new FinalCheck($alpha);
$check=static fn(FinalCheck $review,int $revision,string $digest,string $expectedTerms,
    ?string $tenant=null,?string $agreementRef=null,int $seconds=0):bool=>
    $review->currentlyMatches(
        $tenant??$tenantB,$agreementRef??$agreement,$period,
        $revision,$digest,$expectedTerms,$seconds?:$now
    );
finalMust($check($reviewB,1,$hash,$terms),
    'after consuming valid signed plan nonce, current acceptance still matches');
finalMust(!$check($reviewA,1,$hash,$terms),
    'same agreement cannot be used under different authenticated tenant login');
finalMust(!$check($reviewB,1,$hash,$terms,$tenantA),
    'expected foreign tenant is denied even if own SQL login is valid');
finalMust(!$check($reviewB,1,str_repeat('0',64),$terms),
    'altered plan digest is denied before mock action');
finalMust(!$check($reviewB,1,$hash,str_repeat('f',64)),
    'altered terms digest is denied before mock action');
finalMust(!$check($reviewB,2,$hash,$terms),
    'unproven revision must not be assumed');
$beta->exec("SET app.tenant_id='tenant_v6multi_alpha_123456'");
finalMust($check($reviewB,1,$hash,$terms),
    'client-controlled GUC cannot replace actual PostgreSQL login identity');

// NOW append a committed revocation after the nonce claim already succeeded.
// This is the gap a one-time nonce alone cannot protect.
$append=$admin->prepare(
    'INSERT INTO perf_fin_role_lab.portal_v7_acceptance_events
       (tenant_id,agreement_id,period,event_revision,state,
        plan_sha256,terms_sha256,synthetic_actor_ref,
        mfa_snapshot_verified,full_month_reviewed,event_at,expires_at)
     VALUES (:tenant,:agreement,:period,:revision,:state,
        :plan,:terms,:actor,:mfa,:full,:at,:expires)'
);
$event=static function(int $revision,string $state,string $digest,
    int $eventAt,bool $mfa,bool $full) use(
    $append,$tenantB,$agreement,$period,$terms,$now
):void {
    $append->execute([
        'tenant'=>$tenantB,'agreement'=>$agreement,'period'=>$period,
        'revision'=>$revision,'state'=>$state,
        'plan'=>$digest,'terms'=>$terms,
        'actor'=>'actor_lab_postclaim_check_123',
        'mfa'=>$mfa?'true':'false',
        'full'=>$full?'true':'false',
        'at'=>$eventAt,'expires'=>$now+3600,
    ]);
};
$event(2,'synthetic_revoked',$hash,$now-10,false,false);
finalMust(!$check($reviewB,1,$hash,$terms),
    'revocation committed AFTER successful nonce claim blocks action-time review');
finalMust(!$check($reviewB,2,$hash,$terms),
    'latest revoked revision cannot pass either');

$event(3,'synthetic_accepted',$hash,$now-9,true,true);
finalMust(!$check($reviewB,1,$hash,$terms),
    'same plan reaccepted at revision 3 never silently revives old revision 1');
finalMust($check($reviewB,3,$hash,$terms),
    'only independently expected latest accepted revision can pass');

$newDigest=hash('sha256','changed-plan-after-acceptance');
$event(4,'synthetic_accepted',$newDigest,$now-8,true,true);
finalMust(!$check($reviewB,3,$hash,$terms),
    'changed plan state/revision blocks stale review even when both accepted');
finalMust(!$check($reviewB,4,$hash,$terms),
    'matching latest revision cannot override a changed plan fingerprint');
finalMust($check($reviewB,4,$newDigest,$terms),
    'revised plan requires its own independent fingerprint and revision');

$event(5,'synthetic_accepted',$newDigest,$now+1,true,true);
finalMust(!$check($reviewB,5,$newDigest,$terms),
    'future effective acceptance cannot authorize at current instant');
finalMust(!$check($reviewB,5,$newDigest,$terms,$tenantB,$agreement,$now+3700),
    'expired future acceptance cannot later authorize');

$nonceUsed=$beta->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn();
finalMust((int)$nonceUsed===1,
    'all the above reviews and revocations were read-only, no new nonce consumed');
finalMust(!FinalCheck::limits()['real_customer_acceptance_proven']
    && !FinalCheck::limits()['external_actions_authorized']
    && !FinalCheck::limits()['prevents_revocation_after_check'],
    'test-only final check must never claim operational authority');

putenv('PERF_PG_V7_FINAL_REVIEW_LAB=0');
finalMust(!$check($reviewB,5,$newDigest,$terms),
    'flag disables final review');
putenv('PERF_PG_V7_FINAL_REVIEW_LAB=1');
putenv('APP_ENV=production');
finalMust(!$check($reviewB,5,$newDigest,$terms),
    'production forcibly closed');
echo "PASS V7: committed synthetic revocation after nonce claim blocks next action-time SQL read; new revision and changed terms denied; zero external actions.\n";
