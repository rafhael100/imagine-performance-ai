<?php
declare(strict_types=1);

/**
 * F1-119: positive verification of PostgreSQL RLS policy cutover.
 * Disposable PostgreSQL16 only; no customers, VPS or Ads integrations.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F119_DROP_GUC_RLS_LAB')!=='1'
    ||getenv('PERF_F107_BUDGET_READER_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F119 only runs in isolated PG16 CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
use App\Performance\ReadSide\CredentialBoundDemoAccountReader;
use App\Performance\ReadSide\CredentialBoundDemoBudgetReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$legacy=[
    'performance_tenants'=>'perf_tenant_boundary',
    'portal_service_links'=>'perf_tenant_boundary',
    'operational_memberships'=>'perf_tenant_boundary',
    'service_eligibility_snapshots'=>'perf_tenant_boundary',
    'audit_events'=>'perf_tenant_boundary',
    'idempotency_receipts'=>'perf_tenant_boundary',
    'performance_managed_campaigns'=>'perf_campaign_tenant_boundary',
    'performance_campaign_intents'=>'perf_campaign_tenant_boundary',
    'performance_intent_review_journal'=>'perf_f27_journal_tenant_read',
    'performance_ad_account_candidates'=>'perf_f88_tenant_boundary',
    'performance_campaign_account_links'=>'perf_f89_tenant_boundary',
];
$views=[
    'perf_f91_demo_account_read',
    'perf_f93_demo_campaign_account_read',
    'perf_f97_demo_review_read',
    'perf_f106_credential_budget_demo_read',
];
$n=0;
$check=static function(bool $v,string $label)use(&$n):void{
    if(!$v)throw new RuntimeException('FAIL F119 '.$label);
    ++$n;
};
$read=static fn(PDO $db,string $view):array=>
    $db->query('SELECT * FROM public.'.$view.' ORDER BY 1,2,3,4')
        ->fetchAll(PDO::FETCH_ASSOC);
$baseline=[];
foreach(['A'=>$a,'B'=>$b] as $label=>$db){
    foreach($views as $view){
        $baseline[$label][$view]=$read($db,$view);
        $check(is_array($baseline[$label][$view]),
            "$label $view baseline is readable");
    }
}
$check(count($baseline['A']['perf_f91_demo_account_read'])===2,
    'A has two DEMO account candidates');
$check(count($baseline['B']['perf_f91_demo_account_read'])===1,
    'B has one DEMO account candidate');
$check(count($baseline['A']['perf_f106_credential_budget_demo_read'])===1
    &&count($baseline['B']['perf_f106_credential_budget_demo_read'])===1,
    'both companies have their own DEMO budget preview');
$getPolicy=$admin->prepare(
    'SELECT polname,pg_catalog.pg_get_expr(polqual,polrelid) AS predicate '
    .'FROM pg_catalog.pg_policy '
    .'WHERE polrelid=?::regclass AND polname=?'
);
foreach($legacy as $table=>$name){
    $getPolicy->execute(['public.'.$table,$name]);
    $row=$getPolicy->fetch(PDO::FETCH_ASSOC);
    $check(is_array($row)
        &&str_contains((string)$row['predicate'],'app.performance_tenant_id'),
        'legacy GUC policy catalogued before removal: '.$table);
}

$cmd='php artisan migrate --path=database/f119-guc-cutover-lab-migrations '
    .'--force --no-interaction 2>&1';
$lines=[];$exit=-1;exec($cmd,$lines,$exit);
$check($exit===0,'F119 isolated migration succeeded: '.implode(' | ',$lines));
$check((int)$admin->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000008_remove_legacy_tenant_guc_rls'"
)->fetchColumn()===1,'F119 registered as versioned Laravel migration');

foreach($legacy as $table=>$name){
    $getPolicy->execute(['public.'.$table,$name]);
    $check($getPolicy->fetch(PDO::FETCH_ASSOC)===false,
        'GUC policy no longer exists: '.$table);
    $q=$admin->prepare(
        'SELECT relrowsecurity,relforcerowsecurity FROM pg_catalog.pg_class '
        .'WHERE oid=?::regclass'
    );
    $q->execute(['public.'.$table]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    $check(in_array($r['relrowsecurity'],[true,'t','1',1],true)
        &&in_array($r['relforcerowsecurity'],[true,'t','1',1],true),
        'FORCE RLS preserved: '.$table);
}
$remaining=(int)$admin->query(
    "SELECT count(*) FROM pg_catalog.pg_policy p "
    ."JOIN pg_catalog.pg_class c ON c.oid=p.polrelid "
    ."JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
    ."WHERE n.nspname='public' "
    ."AND (coalesce(pg_catalog.pg_get_expr(p.polqual,p.polrelid),'') "
    ."LIKE '%app.performance_tenant_id%' "
    ."OR coalesce(pg_catalog.pg_get_expr(p.polwithcheck,p.polrelid),'') "
    ."LIKE '%app.performance_tenant_id%')"
)->fetchColumn();
$check($remaining===0,
    'no active RLS policy in public schema reads legacy tenant GUC');

$journal=(int)$admin->query(
    "SELECT count(*) FROM pg_catalog.pg_policy "
    ."WHERE polrelid='public.performance_intent_review_journal'::regclass "
    ."AND polname='perf_f104_writer_synthetic_insert'"
)->fetchColumn();
$check($journal===1,'F104 synthetic journal writer policy unaffected');

foreach(['A'=>$a,'B'=>$b] as $label=>$db){
    foreach($views as $view){
        $check($read($db,$view)===$baseline[$label][$view],
            "$label VIEW unchanged after policy cutover: $view");
    }
}
$readerA=new CredentialBoundDemoAccountReader();
$readerB=new CredentialBoundDemoBudgetReader();
$check(count($readerA->read($a)['accounts'])===2,'Laravel accounts A still readable');
$check(count($readerA->read($b)['accounts'])===1,'Laravel accounts B still readable');
$budgetA=$readerB->read($a);
$budgetB=$readerB->read($b);
$check($budgetA['status']==='credential_scoped_demo_budget_preview_only'
    &&$budgetB['status']==='credential_scoped_demo_budget_preview_only',
    'Laravel budgets still scoped after GUC removal');
$check($budgetA['scenarios'][0]['total_centavos']===10001
    &&$budgetB['scenarios'][0]['total_centavos']===7002,
    'synthetic BRL budgets unchanged in integer centavos');
$check($budgetA['media_execution_allowed']===false
    &&$budgetA['billing_allowed']===false
    &&$budgetA['ready_for_production']===false,
    'no Ads execution, billing or release enabled');

$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
foreach($views as $view){
    $check($read($a,$view)===[],
        'A revocation hides '.$view);
    $check($read($b,$view)===$baseline['B'][$view],
        'B unchanged after A revocation: '.$view);
}
$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
foreach($views as $view){
    $check($read($a,$view)===$baseline['A'][$view],
        'restored A sees own '.$view);
}

$again=[];$code=-1;exec($cmd,$again,$code);
$check($code===0,'F119 migration idempotent on rerun');
$check((int)$admin->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000008_remove_legacy_tenant_guc_rls'"
)->fetchColumn()===1,'F119 remains registered only once');

$out=getenv('PERF_F119_BUDGET_JSON_FILE');
if(is_string($out)&&$out!==''){
    $check(str_starts_with($out,'/tmp/')
        &&!file_exists($out)&&!is_link($out),
        'local new CI-only JSON artifact');
    $data=json_encode($budgetA,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    $check(file_put_contents($out,$data."\n",LOCK_EX)!==false,
        'export DEMO Laravel budget for pinned Portal contract tests');
}
$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'CI GUC policy removal never changes external NO-GO status');

echo "PASS F119 $n assertions: 11 legacy tenant-GUC policies removed in ephemeral PG16, login-bound Laravel/Portal data intact, NO-GO\n";
