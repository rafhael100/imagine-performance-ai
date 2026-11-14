<?php
declare(strict_types=1);
require_once __DIR__.'/pg_v6_signed_agreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgV6RoleBoundAgreementCloseLab.php';

use Imagine\Performance\Commercial\Lab\PgV6RoleBoundAgreementCloseLab;

if(getenv('APP_ENV')!=='testing' ||
   getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
   getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
   !getenv('PERF_LAB_RESTORE_DSN')){
    throw new RuntimeException('PG18 role-isolated restore not authorized.');
}
$connect=static fn(string $role,string $password):PDO=> new PDO(
    getenv('PERF_LAB_RESTORE_DSN'),$role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$a=$connect('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$b=$connect('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$fixture=V6SignedAgreementCases::prepare();
$alpha=V6SignedAgreementCases::ALPHA;
$beta=V6SignedAgreementCases::BETA;
foreach([[$a,$alpha,3],[$b,$beta,1]] as [$db,$tenant,$expected]){
    if($db->query('SELECT perf_fin_role_lab.tenant_for_authenticated_session()')
        ->fetchColumn()!==$tenant) {
        throw new RuntimeException('Restored role binding changed.');
    }
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:other,true)")
        ->execute(['other'=>$tenant===$alpha?$beta:$alpha]);
    foreach(['agreement_close_drafts','signed_quote_nonces'] as $table) {
        $count=(int)$db->query('SELECT count(*) FROM perf_fin_role_lab.'.$table)
            ->fetchColumn();
        if($count!==$expected) {
            throw new RuntimeException('Restored session-user RLS, nonces or drafts incomplete.');
        }
    }
    $db->RollBack();
}
$svcA=new PgV6RoleBoundAgreementCloseLab($a);
$svcB=new PgV6RoleBoundAgreementCloseLab($b);
foreach($fixture['cases'] as $case) {
    $svc=$case['tenant']===$alpha?$svcA:$svcB;
    $denied=false;
    try {
        $svc->stage(
            $case['signed'],$case['tenant'],$case['agreement'],$case['period'],
            $fixture['catalog_sha256'],$fixture['minima'],$case['records'],
            V6SignedAgreementCases::NOW,
            [V6SignedAgreementCases::class,'resolveKey']
        );
    } catch(InvalidArgumentException|RuntimeException) {$denied=true;}
    if(!$denied) throw new RuntimeException('Restored quote HMAC/nonce replay accepted.');
}
echo "OK: Postgres 18 restore preserves login-to-tenant RLS and 4 V6 nonce claims; session forged app.tenant_id has no effect\n";
