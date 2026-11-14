<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/ApprovedOfferCatalog.php';
require_once __DIR__ . '/../portal-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceQuoteLabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalSignedQuoteGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgAtomicSignedCloseLab.php';

use App\Homologation\Commerce\ApprovedOfferCatalog;
use App\Homologation\Commerce\PortalPerformanceQuoteLabIssuer as Issuer;
use Imagine\Performance\Commercial\Lab\PgAtomicSignedCloseLab;

function atomicSame(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) throw new RuntimeException('Atomic lab mismatch: ' . $label);
}
function atomicDeny(callable $f, string $label): void {
    try { $f(); } catch (RuntimeException | InvalidArgumentException) { return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
$dsn = getenv('PERF_LAB_PG_DSN');
$user = getenv('PERF_LAB_PG_USER');
$pass = getenv('PERF_LAB_PG_PASSWORD');
if (!$dsn || $user !== 'perf_fin_lab_app' || !$pass ||
    getenv('APP_ENV') !== 'testing' || getenv('PERF_FIN_ATOMIC_LAB') !== '1' ||
    getenv('PORTAL_PERF_SIGN_LAB') !== '1') {
    throw new RuntimeException('Atomic lab needs ephemeral DB and explicit testing flags.');
}
$db = new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$service = new PgAtomicSignedCloseLab($db);
$catalog = new ApprovedOfferCatalog();
$tenant = 'tenant_atomic_alpha7777';
$keyId = 'k_atomic_lab01';
$secret = str_repeat('T',48);
$now = 1794660000;
$issue = static fn(string $nonce,int $plan,string $month) =>
    Issuer::issue($catalog,['google_ads'=>$plan],$tenant,$keyId,$nonce,$now - 4,$now + 100,$secret,$month);
$resolve = static fn(string $t,string $kid): ?string =>
    $t === 'tenant_atomic_alpha7777' && $kid === 'k_atomic_lab01' ? str_repeat('T',48) : null;
$records = static fn(string $month,int $cents): array => [[
    'channel'=>'google_ads','period'=>$month,'report_id'=>'synthetic_google_001',
    'source'=>'synthetic_fixture','spend_cents'=>$cents,
]];
$first = $issue('nonce_atomic_001_123456abcdef',70000,'2026-11');
atomicDeny(fn()=> $service->stage($first,$tenant,$catalog->version(),'2026-12',$records('2026-12',80000),$now,$resolve),
    'signed month cannot be reused for another competence');
$out = $service->stage($first,$tenant,$catalog->version(),'2026-11',$records('2026-11',80000),$now,$resolve);
atomicSame(true,$out['created'],'first stage created');
atomicSame(false,$out['billable'],'cannot bill');
atomicSame(14000,$out['signed_verification']['preview']['estimated_advance_fee_cents'],'advance');
atomicSame(16000,$out['signed_verification']['preview']['unverified_earned_fee_cents'],'earned');
atomicSame(2000,$out['signed_verification']['preview']['review_only_adjustment_cents'],'adjustment');
atomicDeny(fn()=> $service->stage($first,$tenant,$catalog->version(),'2026-11',$records('2026-11',80000),$now,$resolve),'replay');

$claimCount = static function(PDO $db,string $scope): array {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")
        ->execute(['tenant'=>$scope]);
    $a = (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn();
    $b = (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn();
    $db->rollBack();
    return [$a,$b];
};
atomicSame([1,1],$claimCount($db,$tenant),'one nonce with one drafted close');

$second = $issue('nonce_atomic_002_123456abcdef',80000,'2026-11');
atomicDeny(fn()=> $service->stage($second,$tenant,$catalog->version(),'2026-11',$records('2026-11',80000),$now,$resolve),
    'second distinct draft in same month rejected');
atomicSame([1,1],$claimCount($db,$tenant),'failed draft rolled nonce back');

atomicDeny(fn()=> $service->stage($second,$tenant,$catalog->version(),'2026-12',$records('2026-12',80000),$now,$resolve),
    'even a rolled-back November nonce cannot be applied to December');
atomicSame([1,1],$claimCount($db,$tenant),'rejected cross-month use consumes no nonce');
$december = $issue('nonce_atomic_dec_123456abcdef',80000,'2026-12');
$secondMonth = $service->stage($december,$tenant,$catalog->version(),'2026-12',$records('2026-12',80000),$now,$resolve);
atomicSame(true,$secondMonth['created'],'fresh December quote creates a separate competence');
atomicSame([2,2],$claimCount($db,$tenant),'both monthly closes and nonces committed together');
atomicDeny(fn()=> $service->stage($december,$tenant,$catalog->version(),'2026-12',$records('2026-12',80000),$now,$resolve),
    'completed December cannot be replayed');

$third = $issue('nonce_atomic_003_123456abcdef',70000,'2027-01');
$forged = $third; $forged['billing_period'] = '2026-12';
atomicDeny(fn()=> $service->stage($forged,$tenant,$catalog->version(),'2027-01',
    $records('2027-01',70000),$now,$resolve),'signed month tampered');
$spoof = $records('2027-01',70000);
$spoof[0]['source'] = 'google_verified';
atomicDeny(fn()=> $service->stage($third,$tenant,$catalog->version(),'2027-01',$spoof,$now,$resolve),
    'non-fixture evidence cannot pretend to be verified');
atomicSame([2,2],$claimCount($db,$tenant),'invalid evidence consumes no nonce');

atomicDeny(fn()=> $service->stage($third,'tenant_other_12345678',$catalog->version(),'2027-01',
    $records('2027-01',70000),$now,$resolve),'expected tenant mismatch');
atomicSame([2,2],$claimCount($db,$tenant),'cross-tenant attempt consumes no nonce');

putenv('PERF_FIN_ATOMIC_LAB=0');
atomicDeny(fn()=> $service->stage($third,$tenant,$catalog->version(),'2027-01',
    $records('2027-01',70000),$now,$resolve),'lab cannot run without flag');
echo "OK: PostgreSQL 18 same-transaction nonce+finance draft; failure rolls nonce back; one draft per month, no payment\n";
