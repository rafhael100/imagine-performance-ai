<?php
declare(strict_types=1);

require_once __DIR__.'/pg_v6_signed_agreement_cases.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV6AgreementSignedGuard.php';
require_once __DIR__.'/../labs/finance-pg/PgV6RoleBoundAgreementCloseLab.php';

use Imagine\Performance\Commercial\Lab\PgV6RoleBoundAgreementCloseLab;

function roleMust(bool $ok,string $description): void {
    if (!$ok) throw new RuntimeException('Role-bound PG18: '.$description);
}
function roleReject(callable $call,string $description):void {
    try { $call(); } catch (InvalidArgumentException|RuntimeException|PDOException) {return;}
    throw new RuntimeException('Role-bound PG18 accepted attack: '.$description);
}
if(getenv('APP_ENV')!=='testing' ||
   getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1' ||
   getenv('PORTAL_PERF_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
   getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
   !getenv('PERF_LAB_PG_DSN') ||
   !getenv('PERF_ROLE_ALPHA_PASS') ||
   !getenv('PERF_ROLE_BETA_PASS')){
    throw new RuntimeException('Role bound CI-only flags missing.');
}
$pdo = static fn(string $name,string $password):PDO => new PDO(
    getenv('PERF_LAB_PG_DSN'),$name,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$a=$pdo('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$b=$pdo('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$cases=V6SignedAgreementCases::prepare();
$alpha=V6SignedAgreementCases::ALPHA;
$beta=V6SignedAgreementCases::BETA;
$catalog=$cases['catalog_sha256'];
$minima=$cases['minima'];
$fa=new PgV6RoleBoundAgreementCloseLab($a);
$fb=new PgV6RoleBoundAgreementCloseLab($b);
$run=static fn(PgV6RoleBoundAgreementCloseLab $service,array $row,string $expectedTenant,
        ?string $agreement=null,?array $reports=null):array=>
    $service->stage(
        $row['signed'],$expectedTenant,$agreement??$row['agreement'],$row['period'],
        $catalog,$minima,$reports??$row['records'],
        V6SignedAgreementCases::NOW,
        [V6SignedAgreementCases::class,'resolveKey']
    );

foreach([[$a,'perf_v6_tenant_alpha',$alpha],
         [$b,'perf_v6_tenant_beta',$beta]] as [$db,$dbRole,$tenant]){
    roleMust($db->query('SELECT session_user')->fetchColumn()===$dbRole,'session user is login');
    roleMust($db->query('SELECT current_user')->fetchColumn()===$dbRole,'current user is same login');
    roleMust($db->query('SELECT perf_fin_role_lab.tenant_for_authenticated_session()')
        ->fetchColumn()===$tenant,'trusted DB mapping resolves login');
    foreach([
        "has_table_privilege(session_user,'perf_fin_role_lab.login_tenant_registry','INSERT')",
        "has_table_privilege(session_user,'perf_fin_role_lab.login_tenant_registry','SELECT')",
        "has_table_privilege(session_user,'perf_fin_role_lab.agreement_close_drafts','UPDATE')",
        "has_table_privilege(session_user,'perf_fin_role_lab.agreement_close_drafts','DELETE')",
        "has_schema_privilege(session_user,'perf_fin_role_lab','CREATE')"
    ] as $privilege){
        $v=$db->query('SELECT '.$privilege)->fetchColumn();
        roleMust(!in_array($v,[true,1,'1','t'],true),'runtime forbidden from admin/UPDATE/CREATE');
    }
}
roleReject(fn()=> $a->exec('SET ROLE perf_v6_tenant_beta'),'alpha impersonating beta by SET ROLE');
roleReject(fn()=> $a->exec('SET SESSION AUTHORIZATION perf_v6_tenant_beta'),
    'alpha changing session authorization to beta');

roleReject(fn()=> $run($fa,$cases['cases'][2],$beta),
    'signed beta tenant cannot be closed by alpha login, even with matching expectedTenant');
roleReject(fn()=> $run($fb,$cases['cases'][0],$alpha),
    'signed alpha tenant cannot be closed by beta login');
roleReject(fn()=> $run($fa,$cases['cases'][0],$alpha,'agreement_alpha_brand_02'),
    'signed agreement cannot be re-labelled');
$altered=$cases['cases'][0];
$altered['signed']['agreement_id']='agreement_alpha_brand_02';
roleReject(fn()=> $run($fa,$altered,$alpha,'agreement_alpha_brand_02'),
    'agreement cannot be changed after HMAC');
$badRecords=$cases['cases'][0]['records'];
$badRecords[0]['source']='official_google_ads_verified';
roleReject(fn()=> $run($fa,$cases['cases'][0],$alpha,null,$badRecords),
    'synthetic report must not assert official source');

// Direct SQL attacker sets old forgeable GUC to BETA. Protected role lookup
// and RLS must still resolve ALPHA from the authenticated session login.
$a->beginTransaction();
$a->prepare("SELECT set_config('app.tenant_id',:fake,true)")->execute(['fake'=>$beta]);
roleMust($a->query('SELECT perf_fin_role_lab.tenant_for_authenticated_session()')
    ->fetchColumn()===$alpha,'forged app.tenant_id cannot change role tenant');
roleMust((int)$a->query('SELECT count(*) FROM perf_fin_role_lab.agreement_close_drafts')
    ->fetchColumn()===0,'GUC spoof cannot reveal other tenant rows');
$a->rollBack();

// Forced WITH CHECK forbids cross-tenant nonce writes even if client forges GUC.
$a->beginTransaction();
$a->prepare("SELECT set_config('app.tenant_id',:fake,true)")->execute(['fake'=>$beta]);
roleReject(static function()use($a,$beta):void {
    $q=$a->prepare('INSERT INTO perf_fin_role_lab.signed_quote_nonces
      (tenant_id,nonce,key_id,expires_at) VALUES (:tenant,:nonce,:key,:expires)');
    $q->execute(['tenant'=>$beta,'nonce'=>'nonce_rogue_beta_1234567890',
        'key'=>'k_v6_multi_lab_01','expires'=>1794660200]);
},'direct cross-tenant nonce insertion rejected by session RLS');
$a->rollBack();

$drafts=[];
foreach($cases['cases'] as $idx=>$item){
    $svc=$item['tenant']===$alpha?$fa:$fb;
    $out=$run($svc,$item,$item['tenant']);
    roleMust($out['created']===true,'valid HMAC creates immutable lab draft '.$idx);
    roleMust($out['tenant_bound_to_authenticated_pg_session_user']===true,
        'statement explicitly identifies SQL role boundary '.$idx);
    roleMust($out['agreement_id_signed_in_v6_envelope']===true,
        'agreement identity HMAC-bound '.$idx);
    roleMust($out['finance_approved']===false && $out['billable']===false &&
        $out['asaas_write_enabled']===false && $out['ads_write_enabled']===false &&
        $out['provider_spend_verified']===false,'not production or billable '.$idx);
    $drafts[]=$out;
}
roleMust(count(array_unique(array_column($drafts,'close_id')))===4,
    'four individual contract close IDs');
roleReject(fn()=> $run($fa,$cases['cases'][0],$alpha),'replay rejected on alpha');
roleReject(fn()=> $run($fb,$cases['cases'][2],$beta),'replay rejected on beta');

// A second forged GUC is still powerless once other tenant has data.
foreach([[$a,$alpha,$beta,3],[$b,$beta,$alpha,1]] as [$db,$real,$fake,$expected]){
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:fake,true)")->execute(['fake'=>$fake]);
    roleMust($db->query('SELECT perf_fin_role_lab.tenant_for_authenticated_session()')
        ->fetchColumn()===$real,'GUC does not override signed-in DB user');
    $count=(int)$db->query('SELECT count(*) FROM perf_fin_role_lab.agreement_close_drafts')
        ->fetchColumn();
    $nonce=(int)$db->query('SELECT count(*) FROM perf_fin_role_lab.signed_quote_nonces')
        ->fetchColumn();
    roleMust($count===$expected && $nonce===$expected,
        'RLS counts only actual login, not spoofed GUC');
    $db->rollBack();
}

$a->beginTransaction();
$q=$a->query("SELECT agreement_id,period FROM perf_fin_role_lab.agreement_close_drafts
                WHERE period='2026-11' ORDER BY agreement_id");
$nov=$q->fetchAll(PDO::FETCH_ASSOC);
roleMust(count($nov)===2 &&
    $nov[0]['agreement_id']==='agreement_alpha_brand_01' &&
    $nov[1]['agreement_id']==='agreement_alpha_brand_02',
    '2 signed agreements of one tenant allowed same month');
$a->rollBack();

putenv('PERF_PG_V6_ROLE_LAB=0');
roleReject(fn()=> $run($fa,$cases['cases'][0],$alpha),'fail closed when lab off');
putenv('PERF_PG_V6_ROLE_LAB=1');
putenv('APP_ENV=production');
roleReject(fn()=> $run($fa,$cases['cases'][0],$alpha),'fail closed in production');

echo "OK: PG18 session_user-bound tenant isolation resists forged GUC, SET ROLE, forged signed agreements; 4 V6 immutable drafts + atomic nonces, no billing\n";
