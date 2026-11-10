<?php
declare(strict_types=1);

/** F107: PostgreSQL F106 → Laravel budget read-side → F105 exact money. */
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F107_BUDGET_READER_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F107 requires disposable PostgreSQL16 CI');
}
require_once dirname(__DIR__, 3).'/vendor/autoload.php';
use App\Performance\ReadSide\CredentialBoundDemoBudgetReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$old=new PDO($dsn,'perf_f28_app_reader','ci-f28-app-read-only',$opt);
$reader=new CredentialBoundDemoBudgetReader();
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F107 '.$label);
    ++$n;
};
$denies=static function(array $data,string $name)use($check):void{
    $check($data['simulation_only']===true,"$name is synthetic");
    foreach([
        'portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','media_execution_allowed',
        'billing_allowed','ready_for_production'
    ] as $flag) {
        $check(($data[$flag]??null)===false,"$name always denies $flag");
    }
};

$one=$reader->read($a);
$two=$reader->read($b);
$check($one['status']==='credential_scoped_demo_budget_preview_only','A scoped');
$check($two['status']==='credential_scoped_demo_budget_preview_only','B scoped');
$check($one['credential_binding_confirmed']===true,'A authenticated DB login mapping');
$check($two['credential_binding_confirmed']===true,'B authenticated DB login mapping');
$check(count($one['scenarios'])===1,'A has one budget scenario');
$check(count($two['scenarios'])===1,'B has one budget scenario');
$check($one['schema_version']==='perf.portal.credential-bound-budgets.synthetic.v1',
    'versioned F107 DTO');
$denies($one,'A');
$denies($two,'B');
$planA=$one['scenarios'][0];
$planB=$two['scenarios'][0];
$check($planA['scenario_ref']==='scenario-demo-f106-a','A scenario ref');
$check($planB['scenario_ref']==='scenario-demo-f106-b','B scenario ref');
$check($planA['currency']==='BRL' && $planB['currency']==='BRL','BRL currency');
$check($planA['total_centavos']===10001,'A exact stored amount');
$check($planB['total_centavos']===7002,'B exact stored amount');
$check($planA['number_of_days']===3,'A leap calendar');
$check($planB['number_of_days']===2,'B two day interval');
$check($planA['channel_totals_centavos']===[
    'google_ads'=>5500,'meta_ads'=>4501,
], 'A exact 55/45 split including remainder');
$check($planB['channel_totals_centavos']===[
    'google_ads'=>0,'meta_ads'=>7002,
], 'B zero Google + all Meta supported');
$check(array_column($planA['daily'],'date')===[
    '2028-02-28','2028-02-29','2028-03-01'
], 'A includes leap day');
$check(array_sum(array_column($planA['daily'],'total_centavos'))===10001,
    'A daily rows sum exactly');
$check(array_sum(array_column($planB['daily'],'total_centavos'))===7002,
    'B daily rows sum exactly');
foreach($one['scenarios'] as $plan) {
    $check($plan['simulation_only']===true,'subscenario always simulated');
    foreach(['advertiser_approval_verified','provider_connected',
        'media_execution_allowed','billing_allowed','ready_for_production'] as $flag) {
        $check($plan[$flag]===false,'subscenario never authorizes '.$flag);
    }
}

$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['88888888-8888-4888-8888-888888888888']);
$check($reader->read($a)['scenarios']===$one['scenarios'],
    'A forged GUC cannot reveal B budget');
$check($reader->read($b)['scenarios']===$two['scenarios'],
    'B forged GUC cannot reveal A budget');

$root=$reader->read($admin);
$check($root['status']==='untrusted_database_login'
    && $root['scenarios']===[],'admin is not a customer principal');
$denies($root,'admin');
$legacy=$reader->read($old);
$check(in_array($legacy['status'],['untrusted_database_login','read_unavailable'],true)
    && $legacy['scenarios']===[],'old F28 role cannot use new budget reader');
$denies($legacy,'legacy');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$revoked=$reader->read($a);
$check($revoked['status']==='database_login_revoked'
    && $revoked['scenarios']===[],
    'revoked login A sees no budget scenarios');
$check($revoked['credential_binding_confirmed']===false,'no binding after revocation');
$denies($revoked,'revoked');
$check($reader->read($b)['scenarios']===$two['scenarios'],
    'B unaffected by A revocation');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL '
    .'WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($reader->read($a)['scenarios']===$one['scenarios'],
    'restore administratively returns only A scenarios');

putenv('PERF_F107_BUDGET_READER_LAB=0');
$disabled=$reader->read($a);
$check($disabled['status']==='lab_disabled' && $disabled['scenarios']===[],
    'lab flag required');
$denies($disabled,'disabled flag');
putenv('PERF_F107_BUDGET_READER_LAB=1');
putenv('APP_ENV=production');
$prod=$reader->read($a);
$check($prod['status']==='lab_disabled' && $prod['scenarios']===[],
    'production access denied');
$denies($prod,'production');
putenv('APP_ENV=testing');

$serialized=json_encode($one,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
foreach([
    '88888888-8888-4888-8888-888888888888','ci-f91-only-a',
    'org-demo-a','svc-demo-f88','access_token','refresh_token',
    'provider_account_id','actor-reviewer-demo'
] as $secret) {
    $check(!str_contains($serialized,$secret),'no tenant UUID, service, actors or secrets');
}
$out=getenv('PERF_F107_BUDGET_JSON_FILE');
if(is_string($out)&&$out!==''){
    $check(str_starts_with($out,'/tmp/')&&!file_exists($out)&&!is_link($out),
        'fresh /tmp JSON artifact only');
    $check(file_put_contents($out,$serialized."\n",LOCK_EX)!==false,
        'save purely synthetic JSON for Portal contract integration');
}
echo "PASS F107 $n credential-scoped budget reader, real cents reconciliation, spoof and revocation assertions\n";
