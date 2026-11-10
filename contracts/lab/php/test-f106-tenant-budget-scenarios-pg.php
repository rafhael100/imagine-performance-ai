<?php
declare(strict_types=1);

/**
 * F1-106 PostgreSQL16 budget-scenario tenancy and non-operational controls.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F106_BUDGET_PG_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F106 runs ONLY with disposable PostgreSQL CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
use App\Performance\Planning\OfflineBudgetScenarioPreview as Planner;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
    if(!$ok) throw new RuntimeException('FAIL F106 '.$msg);
    ++$n;
};

$insertSql='INSERT INTO public.performance_demo_budget_scenarios '
    .'(id,tenant_id,portal_service_ref,scenario_ref,currency,start_date,'
    .'end_date,total_centavos,google_ads_bp,meta_ads_bp,source) '
    .'VALUES(?,?,?,?,?,?,?,?,?,?,?)';
$add=$admin->prepare($insertSql);
$rowA=[
    '88888888-8888-4888-8888-888888888886',$tenantA,'svc-demo-f88',
    'scenario-demo-f106-a','BRL','2028-02-28','2028-03-01',
    10001,5500,4500,'offline_demo_fixture'
];
$rowB=[
    '99999999-9999-4999-8999-999999999996',$tenantB,'svc-demo-f88',
    'scenario-demo-f106-b','BRL','2028-03-01','2028-03-02',
    7002,0,10000,'offline_demo_fixture'
];
$add->execute($rowA);
$add->execute($rowB);
$check((int)$admin->query(
    'SELECT count(*) FROM public.performance_demo_budget_scenarios'
)->fetchColumn()===2,'two synthetic tenant scenarios stored');

$read=static fn(PDO $pdo):array=>$pdo->query(
    'SELECT tenant_id,portal_service_ref,scenario_ref,currency,start_date,'
    .'end_date,total_centavos,google_ads_bp,meta_ads_bp,source,'
    .'provider_connected,media_execution_allowed,billing_allowed,'
    .'advertiser_approval_verified '
    .'FROM public.perf_f106_credential_budget_demo_read ORDER BY scenario_ref'
)->fetchAll(PDO::FETCH_ASSOC);
$rowsA=$read($a);$rowsB=$read($b);
$check(count($rowsA)===1,'login A sees one own DEMO budget scenario');
$check(count($rowsB)===1,'login B sees one own DEMO budget scenario');
$check($rowsA[0]['tenant_id']===$tenantA,'A tenant matches SQL principal');
$check($rowsB[0]['tenant_id']===$tenantB,'B tenant matches SQL principal');
$check($rowsA[0]['scenario_ref']==='scenario-demo-f106-a','A scenario');
$check($rowsB[0]['scenario_ref']==='scenario-demo-f106-b','B scenario');

foreach([$rowsA[0],$rowsB[0]] as $r) {
    $check($r['currency']==='BRL','only BRL projected');
    $check($r['source']==='offline_demo_fixture','only fictitious source');
    $check($r['portal_service_ref']==='svc-demo-f88','owned fictitious Portal service');
    foreach(['provider_connected','media_execution_allowed',
        'billing_allowed','advertiser_approval_verified'] as $flag) {
        $check(in_array($r[$flag],[false,'f','0',0],true),
            "$flag cannot be approved");
    }
    $plan=Planner::calculate([
        'schema_version'=>Planner::SCHEMA,
        'scenario_ref'=>$r['scenario_ref'],
        'currency'=>$r['currency'],
        'start_date'=>$r['start_date'],
        'end_date'=>$r['end_date'],
        'total_centavos'=>(int)$r['total_centavos'],
        'channel_split_bp'=>[
            'google_ads'=>(int)$r['google_ads_bp'],
            'meta_ads'=>(int)$r['meta_ads_bp'],
        ],
    ]);
    $check($plan['total_centavos']===(int)$r['total_centavos'],
        'stored budget equals deterministic pure PHP output');
    $check($plan['media_execution_allowed']===false
        && $plan['billing_allowed']===false
        && $plan['ready_for_production']===false,
        'PHP preview always denies provider actions');
    $check(array_sum(array_column($plan['daily'],'total_centavos'))
        === (int)$r['total_centavos'],'daily rows reconcile to SQL amount');
}
$check($a->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_a',
    'A login authenticated by PG');
$check($b->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_b',
    'B login authenticated by PG');
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantA]);
$check($read($a)===$rowsA,'A changing GUC to B does not change budget row');
$check($read($b)===$rowsB,'B changing GUC to A does not change budget row');
$check((int)$a->query(
    "SELECT count(*) FROM perf_f106_credential_budget_demo_read "
    ."WHERE tenant_id='$tenantB'"
)->fetchColumn()===0,'A explicit B tenant WHERE yields zero');
$check((int)$b->query(
    "SELECT count(*) FROM perf_f106_credential_budget_demo_read "
    ."WHERE tenant_id='$tenantA'"
)->fetchColumn()===0,'B explicit A tenant WHERE yields zero');

$denySql=static function(PDO $conn,string $sql,string $name)use($check):void{
    $blocked=false;
    try{$conn->exec($sql);}catch(PDOException){$blocked=true;}
    $check($blocked,$name);
};
$denySql($a,'SELECT id FROM performance_demo_budget_scenarios',
    'reader A cannot read table directly');
$denySql($b,'SELECT id FROM performance_demo_budget_scenarios',
    'reader B cannot read table directly');
$denySql($a,
    "INSERT INTO perf_f106_credential_budget_demo_read(tenant_id) VALUES(NULL)",
    'reader cannot insert through view');
$denySql($b,
    "UPDATE perf_f106_credential_budget_demo_read SET currency='USD'",
    'reader cannot update view');
$denySql($a,'SET ROLE perf_f91_reader_b','A cannot impersonate B');

$bad=static function(array $replacement,string $name)use($admin,$rowA,$add,$check):void{
    $candidate=array_replace($rowA,$replacement);
    $candidate[0]='88888888-8888-4888-8888-888888888889';
    $candidate[3]='scenario-demo-f106-invalid';
    $blocked=false;
    try{$add->execute($candidate);}catch(PDOException){$blocked=true;}
    $check($blocked,'reject invalid SQL insert: '.$name);
};
$bad([4=>'USD'],'USD');
$bad([7=>0],'zero budget');
$bad([7=>-1],'negative budget');
$bad([7=>2000000001],'over technical cap');
$bad([8=>5000,9=>4999],'shares not exactly 10000');
$bad([8=>-1,9=>10001],'shares out of bounds');
$bad([5=>'2028-03-01',6=>'2028-02-28'],'negative days');
$bad([5=>'2028-01-01',6=>'2029-01-01'],'more than 366 days');
$bad([2=>'svc-demo-unlinked'],'unknown service foreign key');
$bad([10=>'oauth_provider_verified'],'non-DEMO source');
$bad([3=>'x'],'invalid reference');
$duplicate=$rowA;
$duplicate[0]='88888888-8888-4888-8888-888888888889';
$blocked=false;
try{$add->execute($duplicate);}catch(PDOException){$blocked=true;}
$check($blocked,'duplicate tenant/scenario reference rejected');

$baseA="'88888888-8888-4888-8888-888888888886'";
foreach([
    'provider_connected','media_execution_allowed','billing_allowed',
    'advertiser_approval_verified'
] as $flag){
    $denySql($admin,
        "UPDATE public.performance_demo_budget_scenarios SET $flag=true WHERE id=$baseA",
        "SQL CHECK blocks elevating $flag");
}
$denySql($admin,
    "UPDATE public.performance_demo_budget_scenarios SET source='live_ads' WHERE id=$baseA",
    'source cannot be promoted to live');
$denySql($admin,
    "UPDATE public.performance_demo_budget_scenarios SET total_centavos=0 WHERE id=$baseA",
    'budget cannot be edited to zero');

$has=static function(string $role,string $table,string $priv)use($admin):bool{
    $q=$admin->prepare('SELECT has_table_privilege(?,?,?)');
    $q->execute([$role,'public.'.$table,$priv]);
    return in_array($q->fetchColumn(),[true,'t','1',1],true);
};
foreach(['perf_f91_reader_a','perf_f91_reader_b'] as $role) {
    $check(!$has($role,'performance_demo_budget_scenarios','SELECT'),
        "$role has no direct table SELECT");
    $check(!$has($role,'performance_demo_budget_scenarios','INSERT'),
        "$role has no direct table INSERT");
    $check(!$has($role,'performance_demo_budget_scenarios','UPDATE'),
        "$role has no direct table UPDATE");
    $check(!$has($role,'performance_demo_budget_scenarios','DELETE'),
        "$role has no direct table DELETE");
    $check($has($role,'perf_f106_credential_budget_demo_read','SELECT'),
        "$role can only read filtered view");
    $check(!$has($role,'perf_f106_credential_budget_demo_read','UPDATE'),
        "$role cannot change filtered view");
}
$rls=$admin->query(
    "SELECT relrowsecurity,relforcerowsecurity "
    ."FROM pg_catalog.pg_class "
    ."WHERE relname='performance_demo_budget_scenarios'"
)->fetch(PDO::FETCH_ASSOC);
$check(in_array($rls['relrowsecurity'],[true,'t','1',1],true),
    'table RLS enabled');
$check(in_array($rls['relforcerowsecurity'],[true,'t','1',1],true),
    'table FORCE RLS enabled');
$policies=(int)$admin->query(
    "SELECT count(*) FROM pg_catalog.pg_policy "
    ."WHERE polrelid='public.performance_demo_budget_scenarios'::regclass"
)->fetchColumn();
$check($policies===0,'base table has no RLS policies: default deny for non-superusers');
$view=$admin->query(
    "SELECT reloptions FROM pg_catalog.pg_class "
    ."WHERE relname='perf_f106_credential_budget_demo_read'"
)->fetchColumn();
$check(is_string($view)&&str_contains($view,'security_barrier=true'),
    'view is security barrier');
$admin->exec(
    'GRANT SELECT ON public.performance_demo_budget_scenarios TO perf_f91_reader_a'
);
$check((int)$a->query(
    'SELECT count(*) FROM public.performance_demo_budget_scenarios'
)->fetchColumn()===0,'even mistakenly granted SELECT is blocked by default-deny FORCE RLS');
$admin->exec(
    'REVOKE SELECT ON public.performance_demo_budget_scenarios FROM perf_f91_reader_a'
);
$check($read($a)===$rowsA,'safe view still works after temporary base privilege rehearsal');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($read($a)===[],'revoked A immediately reads zero budget scenarios');
$check($read($b)===$rowsB,'B retains budget preview during A revocation');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL '
    .'WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($read($a)===$rowsA,'administrator restores A only');

$forbiddenColumns=(int)$admin->query(
    "SELECT count(*) FROM information_schema.columns "
    ."WHERE table_name='performance_demo_budget_scenarios' "
    ."AND column_name IN ('access_token','oauth_token','refresh_token',"
    ."'provider_account_id','credit_card','advertiser_secret','api_key')"
)->fetchColumn();
$check($forbiddenColumns===0,'no advertising credentials or payment data stored');

echo "PASS F106 $n PostgreSQL budget tenant, zero-spend constraints, GUC spoofing and planner parity assertions\n";
