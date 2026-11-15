<?php
declare(strict_types=1);

require_once __DIR__.'/pg_v6_signed_agreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgV6SpendCorrectionReviewLab.php';

use Imagine\Performance\Commercial\Lab\PgV6SpendCorrectionReviewLab;

function correctionSame(mixed $expected,mixed $received,string $what):void {
    if($expected!==$received) throw new RuntimeException(
        'Correction review: '.$what.' expected '.var_export($expected,true).
        ' received '.var_export($received,true));
}
function correctionDeny(callable $fn,string $what):void {
    try {$fn();} catch(InvalidArgumentException|RuntimeException|PDOException){return;}
    throw new RuntimeException('Correction allowed invalid: '.$what);
}
if(getenv('APP_ENV')!=='testing' ||
   getenv('PERF_PG_V6_CORRECTION_LAB')!=='1' ||
   getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
   getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1') {
    throw new RuntimeException('Correction lab disabled.');
}
$pdo=static fn(string $role,string $password):PDO=>new PDO(
    getenv('PERF_LAB_PG_DSN'),$role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$dbA=$pdo('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$dbB=$pdo('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$svcA=new PgV6SpendCorrectionReviewLab($dbA);
$svcB=new PgV6SpendCorrectionReviewLab($dbB);
$fx=V6SignedAgreementCases::prepare();
$alpha=V6SignedAgreementCases::ALPHA;
$beta=V6SignedAgreementCases::BETA;
$c=$fx['cases'];
$correct=static fn(PgV6SpendCorrectionReviewLab $svc,array $case,
    array $reports,int $revision,string $reason,
    ?string $who=null,?string $agreement=null):array=>
    $svc->propose(
        $case['signed'],$who??$case['tenant'],$agreement??$case['agreement'],
        $case['period'],$fx['catalog_sha256'],$fx['minima'],
        $reports,$revision,$reason,V6SignedAgreementCases::NOW,
        [V6SignedAgreementCases::class,'resolveKey']
    );

$r1=$c[0]['records'];
$r1[0]['spend_cents']=100000; // historical synthetic Google +R200 relative to base
correctionDeny(fn()=> $correct($svcB,$c[0],$r1,1,'late_spend_fixture'),
    'other authenticated database login cannot amend alpha contract');
correctionDeny(fn()=> $correct($svcA,$c[2],$c[2]['records'],1,'late_spend_fixture'),
    'alpha role cannot use beta signed quote');
correctionDeny(fn()=> $correct($svcA,$c[0],$r1,1,'late_spend_fixture',
    $alpha,'agreement_alpha_brand_02'),'quote cannot be moved to another agreement');
$bad=$r1;$bad[0]['source']='official_verified_google';
correctionDeny(fn()=> $correct($svcA,$c[0],$bad,1,'late_spend_fixture'),
    'official source claim forbidden');
correctionDeny(fn()=> $correct($svcA,$c[0],$c[0]['records'],1,'late_spend_fixture'),
    'exact same original evidence is not a new correction');
correctionDeny(fn()=> $correct($svcA,$c[0],$r1,2,'late_spend_fixture'),
    'cannot skip revision 1');
correctionDeny(fn()=> $correct($svcA,$c[0],$r1,1,'manual_approved_credit'),
    'no client-controlled approval event types');

$event1=$correct($svcA,$c[0],$r1,1,'late_spend_fixture');
correctionSame(1,$event1['revision_index'],'first revision');
correctionSame(150000,$event1['unverified_new_spend_cents'],'new synthetic spend');
correctionSame(30000,$event1['unverified_new_earned_fee_cents'],'20 percent of new spend');
correctionSame(4000,$event1['delta_earned_fee_cents'],'fee revision is +R40 only');
correctionSame(false,$event1['billable'],'correction cannot bill');
correctionSame(false,$event1['finance_approved'],'correction not approved');
correctionSame(false,$event1['automated_refund_or_credit_enabled'],'no automatic credit');

$r2=$r1;
$r2[0]['report_id']='g_alpha_nov_corrected_id'; // same amount; different evidence
$event2=$correct($svcA,$c[0],$r2,2,'account_allocation_fixture');
correctionSame(2,$event2['revision_index'],'second revision');
correctionSame($event1['event_sha256'],$event2['previous_event_sha256'],
    'hash chain points to previous correction');
correctionSame(0,$event2['delta_earned_fee_cents'],
    'changed original evidence, same total, no new fee');
if($event1['event_sha256']===$event2['event_sha256'] ||
   $event1['new_spend_claim_sha256']===$event2['new_spend_claim_sha256']) {
    throw new RuntimeException('Evidence reallocation not reflected in correction chain');
}
correctionDeny(fn()=> $correct($svcA,$c[0],$r2,2,'account_allocation_fixture'),
    'same correction replay denied');
correctionDeny(fn()=> $correct($svcA,$c[0],$r2,3,'account_allocation_fixture'),
    'same evidence cannot create redundant correction');

$chat=$c[1]['records'];
$chat[0]['spend_cents']=10000;
$event3=$correct($svcA,$c[1],$chat,1,'late_spend_fixture');
correctionSame('agreement_alpha_brand_02',$event3['agreement_id'],'second agreement isolated');
correctionSame(2000,$event3['delta_earned_fee_cents'],'ChatGPT 20 percent of mock R100');
correctionSame(false,$event3['asaas_write_enabled'],'Asaas still disabled');
correctionDeny(fn()=> $correct($svcB,$c[1],$chat,2,'late_spend_fixture'),
    'beta cannot append to alpha agreement even with next index');

// RLS tied to the database session: setting stale app.tenant_id to someone
// else's tenant cannot read or write their correction history.
foreach([[$dbA,$beta,3],[$dbB,$alpha,0]] as [$db,$fake,$expectedCount]) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:fake,true)")
       ->execute(['fake'=>$fake]);
    $count=(int)$db->query(
       'SELECT count(*) FROM perf_fin_role_lab.spend_correction_events'
    )->fetchColumn();
    correctionSame($expectedCount,$count,'forged GUC has no influence on correction RLS');
    $db->rollBack();
}
$canUpdate=$dbA->query(
    "SELECT has_table_privilege(session_user,
      'perf_fin_role_lab.spend_correction_events','UPDATE')"
)->fetchColumn();
correctionSame(false,in_array($canUpdate,[true,1,'1','t'],true),
    'app role has no UPDATE privilege for correction events');
$dbA->beginTransaction();
$old=$dbA->query(
    "SELECT quote_claim_sha256,unverified_earned_fee_cents,spend_claim_sha256
       FROM perf_fin_role_lab.agreement_close_drafts
      WHERE agreement_id='agreement_alpha_brand_01' AND period='2026-11'"
)->fetch(PDO::FETCH_ASSOC);
correctionSame($c[0]['quote_hash'],$old['quote_claim_sha256'],
    'original HMAC base quote immutable');
correctionSame(26000,(int)$old['unverified_earned_fee_cents'],
    'original fee 20% on initial synthetic R1300 unchanged');
$dbA->rollBack();

putenv('PERF_PG_V6_CORRECTION_LAB=0');
correctionDeny(fn()=> $correct($svcA,$c[0],$r2,3,'late_spend_fixture'),
    'correction lab disabled');
putenv('PERF_PG_V6_CORRECTION_LAB=1');
putenv('APP_ENV=production');
correctionDeny(fn()=> $correct($svcA,$c[0],$r2,3,'late_spend_fixture'),
    'no production corrections');

echo "OK: append-only synthetic spend corrections with V6 signed agreement, role RLS, hash chain, immutable base, no invoice/credit/PSP\n";
