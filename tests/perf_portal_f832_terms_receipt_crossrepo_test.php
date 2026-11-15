<?php
declare(strict_types=1);

// F832 cross-repo CI proof. The pinned Portal real-shaped PG18 fixture has
// already executed F831 accept/revoke and F832 receipt-backed reacceptance.
// It exported only the synthetic F419/V7 commercial HMAC, no bearer or OTP.
require_once __DIR__.'/../src/Domain/Commercial/PortalF419SignedActivationReviewV7LabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV7SignedPlanAgreementLabVerifier.php';
require_once __DIR__.'/../portal-f832-reference/apps/portal/app/Labs/PerformanceV7Pg18/PortalV7SqlTermsPresentationReceiptLab.php';

use Imagine\Performance\Commercial\PortalV7SignedPlanAgreementLabVerifier as Verify;
use App\Labs\PerformanceV7Pg18\PortalV7SqlTermsPresentationReceiptLab as Terms;

function f832Must(bool $yes,string $message):void {
    if(!$yes)throw new RuntimeException('Portal F832 ↔ Performance: '.$message);
}
foreach([
    'APP_ENV'=>'testing',
    'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
    'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
    'PORTAL_PERF_V7_PG18_IDENTITY_LAB'=>'1',
    'PORTAL_PERF_V7_EXPRESS_CONSENT_LAB'=>'1',
    'PORTAL_PERF_V7_TERMS_PRESENTATION_LAB'=>'1',
] as $env=>$value){
    if(getenv($env)!==$value)throw new RuntimeException('CI-only flag absent '.$env);
}
$path='/tmp/portal-f829-signed-plan-ci.json';
if(!is_file($path) || filesize($path)<100 || filesize($path)>65536)
    throw new RuntimeException('Expected redacted Portal V7 CI payload missing.');
$data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
f832Must(is_array($data)
    && ($data['source']??null)==='portal_f829_pg18_synthetic_review'
    && ($data['actual_customer_acceptance']??null)===false,
    'signed review is synthetic only; no customer is authenticated');
$expect=$data['expected'];
$tenant=$expect['tenant_id'];
$agreement=$expect['agreement_id'];
$period=$expect['period'];
$planned=$data['planned'];
$planHash=$data['stable_plan_sha256'];
$termsHash=$data['terms_sha256'];
$claimCount=0;
$key=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_f827_plan_01'
        ?str_repeat('Y',48):null;
$verified=Verify::verify(
    $data['signed_plan'],$data['activation'],$expect,
    $data['channels'],$planned,$data['review_now_utc'],
    str_repeat('X',48),$key,
    static function(string $t,string $a,string $nonce,int $expires)
    use(&$claimCount,$tenant,$agreement):bool{
        ++$claimCount;
        return $claimCount===1 && $t===$tenant && $a===$agreement
            && $expires>0 && strlen($nonce)>=16;
    }
);
f832Must($verified['hmac_verified_in_lab']===true
    && $verified['planned_media_total_cents']===170000
    && $verified['activation_review_cents']===98000
    && $verified['estimated_management_fee_cents']===37000
    && $verified['minimum_management_fee_per_channel_cents']===17000
    && $claimCount===1,
    'independent HMAC/activation/management fee verification remains valid');
foreach([
    'customer_acceptance_proven','real_customer_contract_authenticated',
    'portal_iam_authenticated','billable','ads_write_enabled',
    'asaas_write_enabled','c6_write_enabled','crm_write_enabled'
] as $flag)f832Must(($verified[$flag]??null)===false,
    'commercial HMAC cannot override legal/financial NO-GO '.$flag);

$db=static fn(string $user,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=portal_identity_lab;connect_timeout=3',
    $user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$admin=$db('postgres',getenv('F66_PG_ADMIN_PASSWORD'));
$reader=$db('portal_v7_lab_reader',getenv('PORTAL_V7_PG_READER_PASSWORD'));
$terms=new Terms($reader);
$bearer='PORTAL-V7-PG18-CI-PRIVATE-SESSION-00000001';
$stmt=$admin->prepare(
    'SELECT e.event_revision,e.state,
      l.presentation_challenge,p.session_sha256,p.actor_id,p.organization_id,
      p.plan_sha256,p.terms_sha256,p.agreement_revision,
      p.previous_consent_revision,p.registered_at,p.expires_at,l.linked_at
     FROM public.portal_v7_consent_events_lab e
     LEFT JOIN public.portal_v7_terms_acceptance_link_lab l
       ON l.tenant_id=e.tenant_id AND l.agreement_id=e.agreement_id
      AND l.period=e.period AND l.consent_revision=e.event_revision
     LEFT JOIN public.portal_v7_terms_presentation_lab p
       ON p.challenge=l.presentation_challenge
     WHERE e.tenant_id=:tenant AND e.agreement_id=:agreement
       AND e.period=:period
     ORDER BY e.event_revision'
);
$stmt->execute(['tenant'=>$tenant,'agreement'=>$agreement,'period'=>$period]);
$rows=$stmt->fetchAll();
f832Must(count($rows)===7,
    'real F831 + F832 PostgreSQL18 event history contains seven ordered revisions');
$states=[
    'synthetic_accepted','synthetic_revoked','synthetic_accepted',
    'synthetic_revoked','synthetic_accepted','synthetic_revoked','synthetic_accepted',
];
foreach($rows as $index=>$row){
    $rev=$index+1;
    f832Must((int)$row['event_revision']===$rev && $row['state']===$states[$index],
        'append-only revisions and revoked states survive F829 regressions: '.$rev);
    if(in_array($rev,[5,7],true)){
        f832Must(is_string($row['presentation_challenge']) &&
            $row['session_sha256']===hash('sha256',$bearer) &&
            $row['actor_id']==='e1111111-1111-4111-8111-111111111111' &&
            $row['organization_id']===$expect['organization_ref'] &&
            $row['plan_sha256']===$planHash &&
            $row['terms_sha256']===$termsHash &&
            (int)$row['agreement_revision']===1 &&
            (int)$row['previous_consent_revision']===$rev-1 &&
            $row['registered_at']<=$row['linked_at'] &&
            $row['linked_at']<$row['expires_at'],
            'current signed plan has a session/actor/terms-bound prior receipt at revision '.$rev);
    }else{
        f832Must($row['presentation_challenge']===null,
            'legacy accepted/revoked consent has no invented presentation link '.$rev);
    }
}
f832Must($rows[4]['presentation_challenge']!==$rows[6]['presentation_challenge'],
    'reacceptance after revocation cannot reuse old terms presentation');

f832Must(!$terms->isCurrent($bearer,$tenant,$agreement,$period,
    $planHash,$termsHash,6,7),
    'F829 logout after F832 acceptance denies current review despite intact HMAC');
f832Must(!$terms->isCurrent($bearer,$tenant,$agreement,$period,
    $planHash,$termsHash,1,7),
    'stale agreement approval revision also denies old receipt');
f832Must(!$terms->isCurrent($bearer,$tenant,$agreement,$period,
    $planHash,$termsHash,6,5),
    'older accepted event is not current even though it had a valid receipt');
f832Must(!$terms->isCurrent($bearer,$tenant,$agreement,$period,
    $planHash,str_repeat('f',64),6,7),
    'changing terms after acceptance denies');
f832Must(!$terms->isCurrent($bearer,$tenant,$agreement,$period,
    str_repeat('a',64),$termsHash,6,7),
    'changing signed media plan after acceptance denies');
f832Must(!$terms->isCurrent(
    'PORTAL-F832-NOT-ISSUED-SESSION-000000000000',
    $tenant,$agreement,$period,$planHash,$termsHash,6,7
), 'another session cannot inherit receipt-backed approval');
f832Must(!$terms->isCurrent($bearer,'tenant_different_12345678',
    $agreement,$period,$planHash,$termsHash,6,7),
    'different tenant cannot reuse consent receipt');

foreach(['portal_v7_terms_presentation_lab',
         'portal_v7_terms_acceptance_link_lab'] as $table){
    foreach(['SELECT','INSERT','UPDATE','DELETE'] as $priv){
        $allowed=$reader->query(
            "SELECT has_table_privilege(session_user,'public.$table','$priv')"
        )->fetchColumn();
        f832Must(!in_array($allowed,[true,1,'1','t'],true),
            'SQL login cannot directly '.$priv.' '.$table);
    }
}
f832Must(Terms::limits()['actual_html_rendering_proven']===false
    && Terms::limits()['actual_customer_approval_proven']===false
    && Terms::limits()['billable']===false,
    'database receipt does not prove actual browser display or legally binding consent');
echo "PASS Portal F832 -> Performance: same F419/V7 HMAC and money but SQL session revoked; 2 receipt-linked acceptances prove only synthetic presentation chronology, no live authority.\n";
