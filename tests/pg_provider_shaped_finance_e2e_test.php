<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementReviewHtml.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../src/Domain/Commercial/SyntheticAdsAccountSpendContract.php';
require_once __DIR__ . '/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__ . '/../labs/finance-pg/PgAtomicSignedCloseLab.php';
require_once __DIR__ . '/../labs/finance-pg/PgSignedCloseReview.php';
require_once __DIR__ . '/../labs/finance-pg/PgProviderShapedFinanceLab.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer as PortalIssuer;
use Imagine\Performance\Commercial\Lab\PgAtomicSignedCloseLab;
use Imagine\Performance\Commercial\Lab\PgSignedCloseReview;
use Imagine\Performance\Commercial\Lab\PgProviderShapedFinanceLab;

function expectProvider(bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException('Provider-shaped finance test: ' . $reason);
}
function rejectProvider(callable $task, string $reason): void {
    try { $task(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Invalid provider-shaped finance input allowed: ' . $reason);
}
$dsn=getenv('PERF_LAB_PG_DSN');
$user=getenv('PERF_LAB_PG_USER');
$pass=getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || $user !== 'perf_fin_lab_app' || !$pass ||
    getenv('APP_ENV') !== 'testing' ||
    getenv('PORTAL_PERF_SIGN_LAB') !== '1' ||
    getenv('PERF_FIN_ATOMIC_LAB') !== '1' ||
    getenv('PERF_ADS_PROVIDER_FIN_LAB') !== '1' ||
    getenv('PERF_FIN_SIGNED_HTML_LAB') !== '1') {
    throw new RuntimeException('Missing isolated laboratory requirements.');
}
$db=new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$period='2026-11';
$tenant='tenant_provider_pg_7777777';
$catalog=new ApprovedOfferCatalog();
$key=str_repeat('F',48);
$now=1794660000;
$nonce='nonce_provider_pg_1234567890abcdef';
$signed=PortalIssuer::issue(
    $catalog, ['google_ads'=>70000,'meta_ads'=>70000],
    $tenant,'k_provider_lab01',$nonce,$now-3,$now+120,$key,$period
);
$resolve=static fn(string $t,string $kid): ?string =>
    $t==='tenant_provider_pg_7777777' && $kid==='k_provider_lab01'
        ? str_repeat('F',48) : null;

$scopes=[
    'google_ads'=>[
        '1234567890'=>'America/Sao_Paulo',
        '2345678901'=>'America/Sao_Paulo',
    ],
    'meta_ads'=>['act_987654321'=>'America/Sao_Paulo'],
];
$googleFixture=static fn(string $id,string $micros): array => [
    'customer_id'=>$id,'currency_code'=>'BRL',
    'time_zone'=>'America/Sao_Paulo','complete'=>true,
    'batches'=>[['results'=>[[
        'segments'=>['date'=>'2026-11-01'],
        'metrics'=>['costMicros'=>$micros],
    ]]]],
];
$metaFixture=static fn(string $spend): array => [
    'account_id'=>'act_987654321','currency'=>'BRL',
    'time_zone'=>'America/Sao_Paulo','complete'=>true,'next'=>null,
    'data'=>[[
        'account_id'=>'987654321',
        'account_currency'=>'BRL',
        'date_start'=>'2026-11-01',
        'date_stop'=>'2026-11-01',
        'spend'=>$spend,
    ]],
];
$payloads=[
    'google_ads'=>[
        '1234567890'=>$googleFixture('1234567890','400000000'),
        '2345678901'=>$googleFixture('2345678901','100000000'),
    ],
    'meta_ads'=>['act_987654321'=>$metaFixture('1000.00')],
];

$prepared=PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$payloads);
expectProvider(
    $prepared['synthetic_account_summary']['unverified_spend_by_channel_cents'] ===
    ['google_ads'=>50000,'meta_ads'=>100000],
    'two Google accounts aggregated correctly and Meta account isolated'
);
expectProvider($prepared['provider_spend_verified']===false,'no authoritative spend');
expectProvider(count($prepared['synthetic_finance_records'])===2,'two canonical channel records');

$changed=$payloads;
$changed['google_ads']['1234567890']=$googleFixture('1234567890','450000000');
$changed['google_ads']['2345678901']=$googleFixture('2345678901','50000000');
$changedPrep=PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$changed);
expectProvider(
    $changedPrep['synthetic_account_summary']['unverified_spend_by_channel_cents'] ===
    $prepared['synthetic_account_summary']['unverified_spend_by_channel_cents'],
    'reallocation preserves same Google/Meta totals'
);
expectProvider(
    $changedPrep['account_fingerprint_sha256_not_provider_proof'] !==
    $prepared['account_fingerprint_sha256_not_provider_proof'],
    'account-level change modifies provenance hash despite equal channel total'
);
expectProvider(
    $changedPrep['synthetic_finance_records'][0]['report_id'] !==
    $prepared['synthetic_finance_records'][0]['report_id'],
    'canonical finance evidence hash binds which accounts contributed'
);
expectProvider(
    $changedPrep['synthetic_finance_records'][1]['report_id'] ===
    $prepared['synthetic_finance_records'][1]['report_id'],
    'unchanged Meta source does not need new fingerprint'
);
$reversedScopes=['meta_ads'=>$scopes['meta_ads'],'google_ads'=>array_reverse($scopes['google_ads'],true)];
$reversedFixtures=['meta_ads'=>$payloads['meta_ads'],'google_ads'=>array_reverse($payloads['google_ads'],true)];
$reversed=PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$reversedScopes,$reversedFixtures);
expectProvider(
    $reversed['synthetic_finance_records'] === $prepared['synthetic_finance_records'],
    'input order does not change finance evidence hash'
);
expectProvider(
    $reversed['account_fingerprint_sha256_not_provider_proof'] ===
    $prepared['account_fingerprint_sha256_not_provider_proof'],
    'account-level hash deterministic'
);

$bridge=new PgProviderShapedFinanceLab(new PgAtomicSignedCloseLab($db));
$staged=$bridge->stage($signed,$tenant,$catalog->version(),$period,$scopes,$payloads,$now,$resolve);
expectProvider($staged['atomic_close']['created']===true,'finance ledger inserted once');
expectProvider($staged['atomic_close']['billable']===false,'never billable');
expectProvider($staged['atomic_close']['finance_approved']===false,'no finance approval');
expectProvider(
    $staged['atomic_close']['signed_verification']['billing_period_bound']===true,
    'month-bound HMAC verified'
);
$actual=$staged['atomic_close']['signed_verification']['preview'];
expectProvider($actual['estimated_advance_fee_cents']===28000,'20% planned advance');
expectProvider($actual['unverified_earned_fee_cents']===30000,'20% simulated real spend');
expectProvider($actual['review_only_adjustment_cents']===2000,'review-only positive adjustment');

$reader=new PgSignedCloseReview($db);
$html=$reader->render(
    $tenant,$signed,$staged['atomic_close']['signed_verification'],
    $staged['synthetic_finance_records']
);
foreach(['DEMONSTRAÇÃO NÃO HOMOLOGADA','R$ 280,00','R$ 300,00','+R$ 20,00',
         'R$ 1.500,00','Google Ads','Meta Ads'] as $term) {
    expectProvider(str_contains($html,$term),'read-only HTML shows '.$term);
}
rejectProvider(
    fn()=> $reader->render($tenant,$signed,$staged['atomic_close']['signed_verification'],
        $changedPrep['synthetic_finance_records']),
    'different account attribution must invalidate stored finance evidence hash'
);
rejectProvider(
    fn()=> $bridge->stage($signed,$tenant,$catalog->version(),$period,
        $scopes,$payloads,$now,$resolve),
    'replay of same signed month blocked'
);

$missing=$payloads;
unset($missing['google_ads']['2345678901']);
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$missing),
    'missing Google account'
);
$foreign=$payloads;
$foreign['google_ads']['9999999999']=$googleFixture('9999999999','10000');
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$foreign),
    'extra uncontracted account'
);
$fake=$payloads;
$fake['meta_ads']['act_987654321']['complete']=false;
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$fake),
    'partial provider reply'
);
$fake=$payloads;
$fake['google_ads']['1234567890']['provider_verified']=true;
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$fake),
    'spoofed attestation field'
);
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare(
        'tenant_wrong_77777777',$period,$signed,$scopes,$payloads),
    'cross tenant provider response'
);
putenv('PERF_ADS_PROVIDER_FIN_LAB=0');
rejectProvider(
    fn()=> PgProviderShapedFinanceLab::prepare($tenant,$period,$signed,$scopes,$payloads),
    'lab off by default'
);
echo "OK: real Portal v2 code, synthetic Google+Meta account totals, account-bound claim hash, atomic PG18 close and read-only HTML; no Ads/PSP operations\n";
