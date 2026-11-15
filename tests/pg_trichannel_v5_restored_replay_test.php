<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceTriChannelV5LabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgTriChannelV5CandidateCloseLab.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Catalog;
use App\Homologation\Commerce\PortalPerformanceTriChannelV5LabIssuer as Issuer;
use Imagine\Performance\Commercial\Lab\PgTriChannelV5CandidateCloseLab;

if (getenv('APP_ENV')!=='testing' || getenv('PERF_PG_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PERF_PORTAL_TRICHANNEL_V5_LAB')!=='1') {
    throw new RuntimeException('Restore replay laboratory not enabled.');
}
$db=new PDO(getenv('PERF_LAB_RESTORE_DSN'),getenv('PERF_LAB_PG_USER'),getenv('PERF_LAB_PG_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
]);
$tenant='tenant_v5_finance_alpha1234';
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")->execute(['tenant'=>$tenant]);
$counts=[
 (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(),
 (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn()
];
$db->rollBack();
if ($counts!==[1,1]) {
    throw new RuntimeException('V5 restore lost nonce or finance draft.');
}
$catalog=new Catalog();
$now=1794660000;
$signed=Issuer::issue($catalog,
 ['meta_ads'=>70000,'chatgpt_ads'=>120000,'google_ads'=>100000],
 $tenant,'2026-11','k_perf_pg_v5_test','nonce_pg_v5_original_12345678',
 $now-3,$now+120,str_repeat('U',48));
$records=[
 ['channel'=>'chatgpt_ads','period'=>'2026-11','report_id'=>'accountset_chat_12345','source'=>'synthetic_fixture','spend_cents'=>90000],
 ['channel'=>'google_ads','period'=>'2026-11','report_id'=>'accountset_google_12345','source'=>'synthetic_fixture','spend_cents'=>150000],
 ['channel'=>'meta_ads','period'=>'2026-11','report_id'=>'accountset_meta_12345','source'=>'synthetic_fixture','spend_cents'=>50000],
];
$resolver=static fn(string $t,string $k):?string=>
    $t==='tenant_v5_finance_alpha1234' && $k==='k_perf_pg_v5_test' ? str_repeat('U',48) : null;
$rejected=false;
try {
    (new PgTriChannelV5CandidateCloseLab($db))->stage(
        $signed,$tenant,'2026-11',$catalog->version(),$catalog->minima(),$records,$now,$resolver
    );
} catch (RuntimeException | InvalidArgumentException) {
    $rejected=true;
}
if (!$rejected) throw new RuntimeException('Restored V5 nonce was reused.');
$db->beginTransaction();
$db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")->execute(['tenant'=>$tenant]);
$after=[
 (int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn(),
 (int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn()
];
$db->rollBack();
if ($after!==[1,1]) {
    throw new RuntimeException('Replayed V5 quote changed restored records.');
}
echo "OK: V5 HMAC nonce and unbillable draft survived PG18 backup/restore; restored replay rejected\n";
