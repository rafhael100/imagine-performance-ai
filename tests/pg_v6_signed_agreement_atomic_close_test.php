<?php
declare(strict_types=1);

require_once __DIR__ . '/pg_v6_signed_agreement_cases.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgV6AgreementAtomicCloseLab.php';

use Imagine\Performance\Commercial\Lab\PgV6AgreementAtomicCloseLab;

function multiSame(mixed $expected,mixed $actual,string $name): void {
    if ($expected !== $actual) {
        throw new RuntimeException($name . ': expected ' .
            var_export($expected,true) . ' got ' . var_export($actual,true));
    }
}
function multiDeny(callable $fn,string $name): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Multiagreement finance accepted invalid scenario: '.$name);
}
if (getenv('APP_ENV')!=='testing' ||
    getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PERF_PG_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PERF_LAB_PG_USER')!=='perf_fin_lab_app' ||
    !getenv('PERF_LAB_PG_DSN') ||
    !getenv('PERF_LAB_PG_PASSWORD')) {
    throw new RuntimeException('Multiagreement CI runtime safety gates missing.');
}
$db = new PDO(getenv('PERF_LAB_PG_DSN'), getenv('PERF_LAB_PG_USER'),
    getenv('PERF_LAB_PG_PASSWORD'), [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
multiSame('perf_fin_lab_app',$db->query('SELECT current_user')->fetchColumn(),'CI role');
$canUpdateDraft = $db->query(
    "SELECT has_table_privilege(current_user, 'perf_fin_v6_lab.agreement_close_drafts', 'UPDATE')"
)->fetchColumn();
multiSame(false,in_array($canUpdateDraft,[true,1,'1','t'],true),'app cannot alter immutable drafts');
$fixture=V6SignedAgreementCases::prepare();
$cases=$fixture['cases'];
$fin=new PgV6AgreementAtomicCloseLab($db);
$call=static function(array $row,?string $agreementOverride=null,
        ?string $tenantOverride=null,?array $reportOverride=null) use($fin,$fixture):array {
    return $fin->stage(
        $row['signed'],$tenantOverride??$row['tenant'],
        $agreementOverride??$row['agreement'],$row['period'],
        $fixture['catalog_sha256'],$fixture['minima'],
        $reportOverride??$row['records'],
        V6SignedAgreementCases::NOW,
        [V6SignedAgreementCases::class,'resolveKey']
    );
};

// Failing an independent registry lookup must rollback the consumed nonce.
multiDeny(fn()=> $call($cases[0],'agreement_alpha_brand_02'),
    'signed quote of Brand 01 cannot close Brand 02');
multiDeny(fn()=> $call($cases[0],'agreement_unknown_123456'),
    'no synthetic agreement binding exists');
multiDeny(fn()=> $call($cases[0],null,V6SignedAgreementCases::BETA),
    'foreign tenant must not take the quote');
$bad=$cases[0]['records'];$bad[0]['source']='verified_official_google';
multiDeny(fn()=> $call($cases[0],null,null,$bad),
    'synthetic record cannot assert official spend');
$bad=$cases[0]['records'];unset($bad[1]);$bad=array_values($bad);
multiDeny(fn()=> $call($cases[0],null,null,$bad),
    'missing channel report cannot silently become zero');

// After failures, the original nonce is still free and exact original binding works.
$closed=[];
foreach ($cases as $i=>$case) {
    $result=$call($case);
    multiSame(true,$result['created'],'created case '.$i);
    multiSame($case['agreement'],$result['agreement_id'],'agreement id case '.$i);
    multiSame($case['period'],$result['period'],'period case '.$i);
    multiSame($case['quote_hash'],$result['quote_claim_sha256'],'signed HMAC claim case '.$i);
    multiSame(true,$result['agreement_id_signed_in_v6_envelope'],'V6 signs immutable agreement id');
    multiSame(false,$result['agreement_binding_proven_by_real_portal_iam'],'fixture not real IAM');
    multiSame(false,$result['billable'],'no invoices '.$i);
    multiSame(false,$result['finance_approved'],'no finance approval '.$i);
    multiSame(false,$result['provider_spend_verified'],'no real Ads proof '.$i);
    multiSame(false,$result['asaas_write_enabled'],'no Asaas '.$i);
    multiSame(false,$result['ads_write_enabled'],'no Ads mutation '.$i);
    $closed[]=$result;
}
multiSame(4,count(array_unique(array_column($closed,'close_id'))),'different immutable close ids');
multiSame(34000,$closed[0]['signed_verification']['estimated_advance_fee_cents'],
    'brand 01 planned R1700 @20%');
multiSame(26000,$closed[0]['signed_verification']['unverified_earned_fee_cents'],
    'brand 01 unverified R1300 @20%');
multiSame(-8000,$closed[0]['signed_verification']['review_only_adjustment_cents'],
    'brand 01 review-only adjustment');
multiSame(24000,$closed[1]['signed_verification']['estimated_advance_fee_cents'],
    'brand 02 ChatGPT R1200 advance @20%');
multiSame(0,$closed[1]['signed_verification']['unverified_earned_fee_cents'],
    'ChatGPT zero reported means zero hypothetically earned');
multiSame(-24000,$closed[1]['signed_verification']['review_only_adjustment_cents'],
    'no automatic billing or refund');
multiSame(38000,$closed[2]['signed_verification']['estimated_advance_fee_cents'],
    'different tenant with same agreement identifier');
multiSame(32000,$closed[2]['signed_verification']['unverified_earned_fee_cents'],
    'different tenant actual R1600');
multiSame(20000,$closed[3]['signed_verification']['estimated_advance_fee_cents'],
    'same agreement in a later billing month is possible');
multiSame(0,$closed[3]['signed_verification']['unverified_earned_fee_cents'],
    'later month explicit zero');

multiDeny(fn()=> $call($cases[0]),'nonce replay after transaction commit');
multiDeny(fn()=> $call($cases[1]),'second agreement nonce replay after transaction commit');
$changed=$cases[0]['records'];$changed[0]['spend_cents']+=1000;
multiDeny(fn()=> $call($cases[0],null,null,$changed),'changed spend cannot overwrite immutable draft');
$attack=$cases[0];
$attack['signed']['quote']['estimated_management_fee_cents']+=1;
multiDeny(fn()=> $call($attack),'tamper signed fee');

foreach ([V6SignedAgreementCases::ALPHA=>[3,3],
          V6SignedAgreementCases::BETA=>[1,1]] as $tenant=>$counts) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:scope,true)")
        ->execute(['scope'=>$tenant]);
    $drafts=(int)$db->query('SELECT count(*) FROM perf_fin_v6_lab.agreement_close_drafts')->fetchColumn();
    $nonce=(int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn();
    multiSame($counts[0],$drafts,'RLS multiagreement rows for '.$tenant);
    multiSame($counts[1],$nonce,'nonce rollback prevents orphan claims for '.$tenant);
    $db->rollBack();
}
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id',:scope,true)")
    ->execute(['scope'=>V6SignedAgreementCases::ALPHA]);
$stmt=$db->query(
    "SELECT agreement_id,period,planned_media_cents,unverified_actual_media_cents,
            advance_fee_cents,unverified_earned_fee_cents,adjustment_cents,
            status,billable,finance_approved,source_verified
       FROM perf_fin_v6_lab.agreement_close_drafts
      WHERE period='2026-11' ORDER BY agreement_id"
);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
multiSame(2,count($rows),'TWO distinct contracts of SAME tenant in SAME month');
multiSame('agreement_alpha_brand_01',$rows[0]['agreement_id'],'brand 01');
multiSame('agreement_alpha_brand_02',$rows[1]['agreement_id'],'brand 02');
foreach($rows as $row) {
    multiSame('unverified_multiagreement_lab_draft',$row['status'],'nonbillable status');
    foreach(['billable','finance_approved','source_verified'] as $f) {
        multiSame(false,in_array($row[$f],[true,1,'1','t'],true),'draft permission denied '.$f);
    }
    multiSame(
        (int)$row['unverified_earned_fee_cents']-(int)$row['advance_fee_cents'],
        (int)$row['adjustment_cents'],'DB financial invariant'
    );
}
$db->rollBack();

putenv('PERF_PG_V6_AGREEMENT_LAB=0');
multiDeny(fn()=> $call($cases[3]),'lab disabled');
putenv('PERF_PG_V6_AGREEMENT_LAB=1');
putenv('APP_ENV=production');
multiDeny(fn()=> $call($cases[3]),'production fails closed');

echo "OK: PG18 per-tenant/per-agreement/month independent V6 HMAC-bound drafts; two contracts same tenant/month; signed agreement ID, nonce atomic rollback, RLS CI, no payments\n";
