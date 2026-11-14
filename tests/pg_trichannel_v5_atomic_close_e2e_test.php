<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceTriChannelV5LabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgTriChannelV5CandidateCloseLab.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Catalog;
use App\Homologation\Commerce\PortalPerformanceTriChannelV5LabIssuer as Issuer;
use Imagine\Performance\Commercial\Lab\PgTriChannelV5CandidateCloseLab;

function samePgV5(mixed $want, mixed $actual, string $label):void {
    if ($want!==$actual) throw new RuntimeException(
        $label.': expected '.var_export($want,true).' actual '.var_export($actual,true));
}
function denyPgV5(callable $f,string $label):void {
    try { $f(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Invalid candidate close accepted: '.$label);
}
foreach (['PERF_LAB_PG_DSN','PERF_LAB_PG_USER','PERF_LAB_PG_PASSWORD'] as $field) {
    if (!getenv($field)) throw new RuntimeException('Missing ephemeral database config.');
}
if (getenv('APP_ENV')!=='testing' ||
    getenv('PERF_PG_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PERF_PORTAL_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1') {
    throw new RuntimeException('V5 finance lab must be explicitly enabled.');
}
$db=new PDO(getenv('PERF_LAB_PG_DSN'),getenv('PERF_LAB_PG_USER'),getenv('PERF_LAB_PG_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
samePgV5('perf_fin_lab_app',$db->query('SELECT current_user')->fetchColumn(),'least privileged role');

$catalog=new Catalog();
$finance=new PgTriChannelV5CandidateCloseLab($db);
$tenant='tenant_v5_finance_alpha1234';
$tenantB='tenant_v5_finance_beta5678';
$keyId='k_perf_pg_v5_test';
$secret=str_repeat('U',48);
$now=1794660000;
$month='2026-11';
$resolve=static fn(string $t,string $k):?string=>
    in_array($t,['tenant_v5_finance_alpha1234','tenant_v5_finance_beta5678'],true) &&
    $k==='k_perf_pg_v5_test' ? str_repeat('U',48) : null;
$budget=['meta_ads'=>70000,'chatgpt_ads'=>120000,'google_ads'=>100000];
$issue=static fn(string $who,string $nonce,string $period='2026-11',array $plan=[]) =>
    Issuer::issue($catalog,$plan ?: $budget,$who,$period,$keyId,$nonce,$now-3,$now+120,$secret);

$records=[
 ['channel'=>'chatgpt_ads','period'=>$month,'report_id'=>'accountset_chat_12345','source'=>'synthetic_fixture','spend_cents'=>90000],
 ['channel'=>'google_ads','period'=>$month,'report_id'=>'accountset_google_12345','source'=>'synthetic_fixture','spend_cents'=>150000],
 ['channel'=>'meta_ads','period'=>$month,'report_id'=>'accountset_meta_12345','source'=>'synthetic_fixture','spend_cents'=>50000],
];
$signed=$issue($tenant,'nonce_pg_v5_original_12345678');
$malformed=$signed;$malformed['quote']['amount_now_cents']++;
denyPgV5(fn()=> $finance->stage($malformed,$tenant,$month,$catalog->version(),$catalog->minima(),$records,$now,$resolve),
    'altered fee must be denied BEFORE SQL nonce claim');
$bad=$records;$bad[0]['source']='verified_official_chatgpt';
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$bad,$now,$resolve),
    'synthetic source spoofed');
$bad=$records;unset($bad[0]);$bad=array_values($bad);
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$bad,$now,$resolve),
    'missing ChatGPT account expense cannot be zero');
$bad=$records;$bad[2]['channel']='other_ads';
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$bad,$now,$resolve),
    'unknown extra provider');
$bad=$records;$bad[2]['spend_cents']=12.6;
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$bad,$now,$resolve),
    'fractional cents');
$bad=$records;$bad[2]['period']='2026-12';
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$bad,$now,$resolve),
    'different billing period');

$first=$finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$records,$now,$resolve);
samePgV5(true,$first['created'],'one V5 candidate close created');
samePgV5('unverified_v5_candidate_pg18_draft',$first['state'],'review only');
samePgV5(290000,$first['signed_verification']['planned_media_cents'],'all planned media');
samePgV5(290000,$first['signed_verification']['unverified_actual_media_cents'],'synthetic media sum');
samePgV5(58000,$first['signed_verification']['estimated_advance_fee_cents'],'20 percent planned advance');
samePgV5(58000,$first['signed_verification']['unverified_earned_fee_cents'],'20 percent hypothetical expense');
samePgV5(false,$first['finance_approved'],'finance disabled');
samePgV5(false,$first['billable'],'no bill');
samePgV5(false,$first['asaas_write_enabled'],'Asaas disabled');
samePgV5(false,$first['ads_write_enabled'],'Ads writes disabled');
samePgV5(64,strlen($first['synthetic_spend_fingerprint_sha256_not_proof']),'SHA only not provider proof');
denyPgV5(fn()=> $finance->stage($signed,$tenant,$month,$catalog->version(),$catalog->minima(),$records,$now,$resolve),
    'nonce replay after commit');

$conflict=$issue($tenant,'nonce_pg_v5_conflict_1234567890');
denyPgV5(fn()=> $finance->stage($conflict,$tenant,$month,$catalog->version(),$catalog->minima(),$records,$now,$resolve),
    'same tenant/month second quote must fail with no revision policy');
$changed=$records;$changed[0]['spend_cents']=80000;$changed[1]['spend_cents']=160000;
$next=$issue($tenant,'nonce_pg_v5_changed_1234567890');
denyPgV5(fn()=> $finance->stage($next,$tenant,$month,$catalog->version(),$catalog->minima(),$changed,$now,$resolve),
    'redistribution of account-reported amounts cannot overwrite stored close');
denyPgV5(fn()=> $finance->stage($signed,$tenantB,$month,$catalog->version(),$catalog->minima(),$records,$now,$resolve),
    'trusted tenant cross company');

$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")->execute(['tenant'=>$tenant]);
$count=(int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn();
$nonceCount=(int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn();
samePgV5(1,$count,'one immutable November close for tenant');
samePgV5(1,$nonceCount,'conflicted quotes rolled back nonce');
$stored=$db->query('SELECT planned_media_cents,unverified_actual_media_cents,advance_fee_cents,unverified_earned_fee_cents,adjustment_cents,billable,finance_approved,source_verified FROM perf_fin_lab.reconciliation_drafts')->fetch(PDO::FETCH_ASSOC);
samePgV5(290000,(int)$stored['planned_media_cents'],'stored media');
samePgV5(58000,(int)$stored['advance_fee_cents'],'stored fee');
samePgV5(false,$stored['billable'],'PG boolean billable false');
samePgV5(false,$stored['finance_approved'],'PG approved false');
samePgV5(false,$stored['source_verified'],'no spend source auth');
$db->rollBack();

$zero=[
 ['channel'=>'chatgpt_ads','period'=>$month,'report_id'=>'zero_chat','source'=>'synthetic_fixture','spend_cents'=>0],
 ['channel'=>'google_ads','period'=>$month,'report_id'=>'zero_google','source'=>'synthetic_fixture','spend_cents'=>0],
 ['channel'=>'meta_ads','period'=>$month,'report_id'=>'zero_meta','source'=>'synthetic_fixture','spend_cents'=>0],
];
$otherSigned=$issue($tenantB,'nonce_pg_v5_beta_1234567890');
$other=$finance->stage($otherSigned,$tenantB,$month,$catalog->version(),$catalog->minima(),$zero,$now,$resolve);
samePgV5(true,$other['created'],'different tenant can have independent draft');
samePgV5(0,$other['signed_verification']['unverified_earned_fee_cents'],'zero actually invested earns zero management');
samePgV5(-58000,$other['signed_verification']['review_only_adjustment_cents'],'refund review only');

foreach ([$tenant,$tenantB] as $scopeTenant) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")->execute(['tenant'=>$scopeTenant]);
    samePgV5(1,(int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn(),'RLS tenant drafts');
    samePgV5(1,(int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(),'RLS tenant nonce');
    $db->rollBack();
}
putenv('PERF_PG_TRICHANNEL_V5_LAB=0');
denyPgV5(fn()=> $finance->stage($issue($tenantB,'nonce_pg_v5_gateoff_12345678'),$tenantB,'2026-12',$catalog->version(),$catalog->minima(),$zero,$now,$resolve),
    'disabled PG finance lab');
echo "OK: Portal real V5 issuer + 3-channel synthetic spend + atomic PG18 nonce/immutable close, rollback/replay/RLS and no bill\n";
