<?php
declare(strict_types=1);

/**
 * F1-117: PostgreSQL16 read path after privileged VIEW ownership cutover.
 *
 * This deliberately runs AFTER all F22–F116 CI regressions, on a disposable
 * PostgreSQL service. The new roles are NOLOGIN, with SELECT only on the
 * minimal underlying tables, RLS bound to SQL session_user via F91.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F117_LIMITED_OWNER_LAB') !== '1'
    || getenv('PERF_F107_BUDGET_READER_LAB') !== '1'
    || getenv('PERF_F92_CREDENTIAL_READER_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F117 executes only with ephemeral CI PostgreSQL16');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoAccountReader;
use App\Performance\ReadSide\CredentialBoundDemoBudgetReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$tables=[
    'performance_ad_account_candidates',
    'performance_managed_campaigns',
    'performance_campaign_account_links',
    'performance_campaign_intents',
    'performance_intent_review_journal',
    'performance_demo_budget_scenarios',
];
$views=[
    'perf_f91_demo_account_read',
    'perf_f93_demo_campaign_account_read',
    'perf_f97_demo_review_read',
    'perf_f106_credential_budget_demo_read',
];
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F117 '.$label);
    ++$n;
};
$truthy=static fn(mixed $x):bool=>in_array($x,[true,'t',1,'1'],true);
$has=$admin->prepare('SELECT has_table_privilege(?,?,?)');
$can=static function(string $principal,string $object,string $priv)use($has,$truthy):bool {
    $has->execute([$principal,'public.'.$object,$priv]);
    return $truthy($has->fetchColumn());
};
$read=static function(PDO $db,string $view):array {
    $rows=$db->query(
        'SELECT * FROM public.'.$view.' ORDER BY 1,2,3,4'
    )->fetchAll(PDO::FETCH_ASSOC);
    if(!is_array($rows))throw new RuntimeException('F117 query failed for '.$view);
    return $rows;
};
$baseline=[];
foreach(['A'=>$a,'B'=>$b] as $label=>$connection){
    foreach($views as $view){
        $baseline[$label][$view]=$read($connection,$view);
        $check(is_array($baseline[$label][$view]),"$label baseline $view");
        $check(count($baseline[$label][$view])<=15,"$label baseline DEMO rows bounded");
    }
}
$check(count($baseline['A']['perf_f91_demo_account_read'])===2,
    'A has two DEMO advertiser candidates');
$check(count($baseline['B']['perf_f91_demo_account_read'])===1,
    'B has one DEMO advertiser candidate');
$check(count($baseline['A']['perf_f106_credential_budget_demo_read'])===1
    &&count($baseline['B']['perf_f106_credential_budget_demo_read'])===1,
    'both tenants have independent synthetic budget previews');

$command='php artisan migrate --path=database/lab-migrations '
    .'--force --no-interaction 2>&1';
$output=[];$code=-1;
exec($command,$output,$code);
$check($code===0,
    'F117 limited owner cutover migration succeeded: '.implode(' | ',$output));
$check((int)$admin->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000007_limit_demo_view_function_owners'"
)->fetchColumn()===1,'F117 recorded as a versioned migration in disposable CI');

$roles=['perf_f117_demo_view_owner','perf_f117_binding_definer_owner'];
foreach($roles as $role){
    $q=$admin->prepare(
        'SELECT rolcanlogin,rolsuper,rolcreatedb,rolcreaterole,'
        .'rolinherit,rolbypassrls,rolreplication '
        .'FROM pg_catalog.pg_roles WHERE rolname=?'
    );
    $q->execute([$role]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $check(is_array($row),'dedicated '.$role.' role exists');
    foreach($row as $name=>$value){
        $check(!$truthy($value),$role.' '.$name.' is disabled');
    }
    $memberships=$admin->prepare(
        'SELECT count(*) FROM pg_catalog.pg_auth_members m '
        .'WHERE m.roleid=?::regrole OR m.member=?::regrole'
    );
    $memberships->execute([$role,$role]);
    $check((int)$memberships->fetchColumn()===0,$role.' has zero membership edges');
}

$viewOwner='perf_f117_demo_view_owner';
$bindingOwner='perf_f117_binding_definer_owner';
foreach($views as $view){
    $q=$admin->prepare(
        'SELECT pg_catalog.pg_get_userbyid(c.relowner) AS owner_name,'
        .'c.reloptions AS options,c.relkind AS kind '
        .'FROM pg_catalog.pg_class c WHERE c.oid=?::regclass'
    );
    $q->execute(['public.'.$view]);
    $result=$q->fetch(PDO::FETCH_ASSOC);
    $check($result['owner_name']===$viewOwner,'VIEW '.$view.' uses NOLOGIN owner');
    $check($result['kind']==='v','VIEW '.$view.' remains ordinary view');
    $check(is_string($result['options'])
        &&str_contains($result['options'],'security_barrier=true'),
        'VIEW '.$view.' remains security_barrier');
    $check($can('perf_f91_reader_a',$view,'SELECT'),
        'A retains only authorized reader VIEW '.$view);
    $check(!$can('perf_f91_reader_a',$view,'UPDATE'),
        'A cannot UPDATE '.$view);
}
$f=$admin->query(
    "SELECT pg_catalog.pg_get_userbyid(p.proowner) AS owner_name,"
    ."p.prosecdef,p.proconfig "
    ."FROM pg_catalog.pg_proc p WHERE p.oid='public.perf_f91_tenant_for_login()'::regprocedure"
)->fetch(PDO::FETCH_ASSOC);
$check($f['owner_name']===$bindingOwner,'F91 function has different dedicated owner');
$check($truthy($f['prosecdef']),'F91 remains SECURITY DEFINER');
$check(is_string($f['proconfig'])
    &&str_contains($f['proconfig'],'search_path=pg_catalog'),
    'F91 helper has catalog-only search path');
$check($can($bindingOwner,'performance_db_principal_tenant_bindings','SELECT'),
    'F91 definer owner may SELECT only principal binding registry');
$check(!$can($bindingOwner,'performance_demo_budget_scenarios','SELECT'),
    'binding owner cannot read advertiser budget base');
$check(!$can($bindingOwner,'performance_campaign_intents','SELECT'),
    'binding owner cannot read campaign intents');

$fx=$admin->prepare('SELECT has_function_privilege(?,?,?)');
$fx->execute([$viewOwner,'public.perf_f91_tenant_for_login()','EXECUTE']);
$check($truthy($fx->fetchColumn()),'VIEW owner may call scoped F91 definer');
$fx->execute([$bindingOwner,'public.perf_f27_capture_review_transition()','EXECUTE']);
$check(!$truthy($fx->fetchColumn()),'binding owner may not execute unrelated review trigger');
foreach($tables as $table){
    $check($can($viewOwner,$table,'SELECT'),'VIEW owner has minimum SELECT '.$table);
    foreach(['INSERT','UPDATE','DELETE','TRUNCATE'] as $priv){
        $check(!$can($viewOwner,$table,$priv),
            'VIEW owner lacks '.$priv.' on '.$table);
    }
    $policy=$admin->prepare(
        'SELECT polname,polpermissive,polcmd,'
        .'pg_catalog.pg_get_expr(polqual,polrelid) AS predicate '
        .'FROM pg_catalog.pg_policy WHERE polrelid=?::regclass '
        ."AND polname LIKE 'perf_f117_owner_login_%' ORDER BY polname"
    );
    $policy->execute(['public.'.$table]);
    $found=$policy->fetchAll(PDO::FETCH_ASSOC);
    $check(count($found)===2,$table.' has two separate owner-only RLS policies');
    $state=[];
    foreach($found as $p){
        $state[$p['polname']]=$truthy($p['polpermissive']);
        $check($p['polcmd']==='r',$table.' F117 policy SELECT-only');
        $check(str_contains((string)$p['predicate'],'perf_f91_tenant_for_login'),
            $table.' predicate uses SQL login instead of GUC');
    }
    $check(($state['perf_f117_owner_login_select']??null)===true,
        $table.' permissive login-bound SELECT');
    $check(($state['perf_f117_owner_login_fence']??null)===false,
        $table.' restrictive login-bound fence');
}
foreach(['A'=>$a,'B'=>$b] as $label=>$db){
    foreach($views as $view){
        $now=$read($db,$view);
        $check($now===$baseline[$label][$view],
            "$label sees identical $view rows after NOLOGIN ownership cutover");
    }
}
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantA]);
foreach(['A'=>$a,'B'=>$b] as $label=>$db){
    foreach($views as $view){
        $check($read($db,$view)===$baseline[$label][$view],
            "$label GUC spoof leaves $view unchanged after RLS owner policy");
    }
}
$accounts=new CredentialBoundDemoAccountReader();
$budgets=new CredentialBoundDemoBudgetReader();
$check(count($accounts->read($a)['accounts'])===2,
    'Laravel A account read still works after owner cutover');
$check(count($accounts->read($b)['accounts'])===1,
    'Laravel B account read still works after owner cutover');
$pa=$budgets->read($a);$pb=$budgets->read($b);
$check($pa['status']==='credential_scoped_demo_budget_preview_only'
    &&$pb['status']==='credential_scoped_demo_budget_preview_only',
    'Laravel budget readers remain scoped');
$check($pa['scenarios'][0]['total_centavos']===10001
    &&$pb['scenarios'][0]['total_centavos']===7002,
    'precise per-tenant centavos preserved');
$check(!$pa['billing_allowed'] && !$pa['media_execution_allowed']
    &&!$pb['billing_allowed'],'no Ads/billing exposed');

$admin->exec('BEGIN');
try{
    $admin->exec('SET LOCAL ROLE perf_f117_demo_view_owner');
    $admin->exec("SELECT set_config('app.performance_tenant_id','$tenantA',true)");
    foreach($tables as $table){
        $cnt=(int)$admin->query('SELECT count(*) FROM public.'.$table)->fetchColumn();
        $check($cnt===0,'NOLOGIN VIEW role with session_user postgres reads zero '.$table);
    }
}finally{$admin->exec('ROLLBACK');}
$check($admin->query('SELECT session_user')->fetchColumn()==='postgres',
    'admin session restored after temporary role switch');

$deny=static function(PDO $c,string $sql,string $label)use($check):void{
    $rejected=false;
    try{$c->exec($sql);}catch(PDOException){$rejected=true;}
    $check($rejected,$label);
};
$deny($a,'SELECT scenario_ref FROM public.performance_demo_budget_scenarios',
    'tenant reader still denied direct base budget SELECT');
$deny($b,'SELECT account_ref FROM public.performance_ad_account_candidates',
    'tenant reader still denied direct base account SELECT');
$deny($a,'SET ROLE perf_f117_demo_view_owner',
    'A cannot SET ROLE to NOLOGIN VIEW owner');
$deny($b,'SET ROLE perf_f117_binding_definer_owner',
    'B cannot SET ROLE to NOLOGIN F91 helper owner');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
foreach($views as $view){
    $check($read($a,$view)===[],'revoked A reads no '.$view);
    $check($read($b,$view)===$baseline['B'][$view],
        'B remains isolated when A revoked for '.$view);
}
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
foreach($views as $view){
    $check($read($a,$view)===$baseline['A'][$view],
        'restored A sees only own '.$view);
}
$out2=[];$code2=-1;
exec($command,$out2,$code2);
$check($code2===0,'F117 migration command remains idempotent');
$check((int)$admin->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000007_limit_demo_view_function_owners'"
)->fetchColumn()===1,'exactly one durable F117 migration record in CI');

$manifest=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($manifest['risk_state']==='open_no_go'
    &&$manifest['release_allowed']===false
    &&$manifest['direct_sql_client_access_allowed']===false,
    'SQL lab owner migration never clears production NO-GO');

echo "PASS F117 $n assertions: F91 helper and 4 DEMO VIEWs moved to two NOLOGIN owners; tenant RLS/isolation and centavos intact, NO-GO\n";
