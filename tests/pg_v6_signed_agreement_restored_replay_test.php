<?php
declare(strict_types=1);

require_once __DIR__.'/pg_v6_signed_agreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgV6AgreementAtomicCloseLab.php';

use Imagine\Performance\Commercial\Lab\PgV6AgreementAtomicCloseLab;
if (getenv('APP_ENV')!=='testing' ||
    getenv('PERF_PG_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1' ||
    getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
    !getenv('PERF_LAB_RESTORE_DSN')) {
    throw new RuntimeException('Isolated restore replay test forbidden.');
}
$db = new PDO(getenv('PERF_LAB_RESTORE_DSN'),getenv('PERF_LAB_PG_USER'),
    getenv('PERF_LAB_PG_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
if ($db->query('SELECT current_user')->fetchColumn()!=='perf_fin_lab_app') {
    throw new RuntimeException('Restored database must use least-privileged role.');
}
$fixture=V6SignedAgreementCases::prepare();
$finance=new PgV6AgreementAtomicCloseLab($db);
foreach($fixture['cases'] as $case) {
    $rejected=false;
    try {
        $finance->stage(
            $case['signed'],$case['tenant'],$case['agreement'],$case['period'],
            $fixture['catalog_sha256'],$fixture['minima'],$case['records'],
            V6SignedAgreementCases::NOW,[V6SignedAgreementCases::class,'resolveKey']
        );
    } catch(InvalidArgumentException | RuntimeException) {
        $rejected=true;
    }
    if(!$rejected) throw new RuntimeException('Restored signed Portal V6 agreement replay not denied.');
}
foreach([
    V6SignedAgreementCases::ALPHA=>3,
    V6SignedAgreementCases::BETA=>1,
] as $tenant=>$expected) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:scope,true)")
        ->execute(['scope'=>$tenant]);
    $count=(int)$db->query(
        'SELECT count(*) FROM perf_fin_v6_lab.agreement_close_drafts'
    )->fetchColumn();
    $nonces=(int)$db->query(
        'SELECT count(*) FROM perf_fin_lab.signed_quote_nonces'
    )->fetchColumn();
    if ($count!==$expected || $nonces!==$expected) {
        throw new RuntimeException('Restore lost V6 HMAC-bound drafts or nonce durability.');
    }
    $db->rollBack();
}
echo "OK: 4 signed V6 agreement draft replays denied AFTER PG18 dump/restore in fresh database; bindings and nonce durable in isolated CI\n";
