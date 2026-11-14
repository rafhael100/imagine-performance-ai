<?php
declare(strict_types=1);

require_once __DIR__.'/pg_v5_multiagreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgMultiAgreementV5CloseLab.php';

use Imagine\Performance\Commercial\Lab\PgMultiAgreementV5CloseLab;
if (getenv('APP_ENV')!=='testing' ||
    getenv('PERF_PG_MULTIAGREEMENT_V5_LAB')!=='1' ||
    getenv('PERF_PORTAL_TRICHANNEL_V5_LAB')!=='1' ||
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
$fixture=V5MultiAgreementCases::prepare();
$finance=new PgMultiAgreementV5CloseLab($db);
foreach($fixture['cases'] as $case) {
    $rejected=false;
    try {
        $finance->stage(
            $case['signed'],$case['tenant'],$case['agreement'],$case['period'],
            $fixture['catalog_sha256'],$fixture['minima'],$case['records'],
            V5MultiAgreementCases::NOW,[V5MultiAgreementCases::class,'resolveKey']
        );
    } catch(InvalidArgumentException | RuntimeException) {
        $rejected=true;
    }
    if(!$rejected) throw new RuntimeException('Restored signed Portal V5 agreement replay not denied.');
}
foreach([
    V5MultiAgreementCases::ALPHA=>3,
    V5MultiAgreementCases::BETA=>1,
] as $tenant=>$expected) {
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:scope,true)")
        ->execute(['scope'=>$tenant]);
    $count=(int)$db->query(
        'SELECT count(*) FROM perf_fin_multi_lab.agreement_close_drafts'
    )->fetchColumn();
    $bindings=(int)$db->query(
        'SELECT count(*) FROM perf_fin_multi_lab.agreement_quote_bindings'
    )->fetchColumn();
    $nonces=(int)$db->query(
        'SELECT count(*) FROM perf_fin_lab.signed_quote_nonces'
    )->fetchColumn();
    if ($count!==$expected || $bindings!==$expected || $nonces!==$expected) {
        throw new RuntimeException('Restore lost agreement registry, draft isolation or nonce durability.');
    }
    $db->rollBack();
}
echo "OK: 4 signed V5 agreement draft replays denied AFTER PG18 dump/restore in fresh database; bindings and nonce durable in isolated CI\n";
