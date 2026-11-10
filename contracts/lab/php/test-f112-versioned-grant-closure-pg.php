<?php
declare(strict_types=1);

/**
 * F112: executable lab migration is durable (Laravel migrations table),
 * fails atomically on residual column GRANT, then closes legacy direct SQL.
 * All connections use ephemeral localhost PG16, never an operational DB.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F112_LEGACY_GRANT_MIGRATION_LAB')!=='1'
    ||getenv('PERF_F107_BUDGET_READER_LAB')!=='1'
    ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F112 verification requires isolated PostgreSQL CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoBudgetReader;
use App\Performance\ReadSide\CredentialBoundDemoAccountReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$f28=new PDO($dsn,'perf_f28_app_reader','ci-f28-app-read-only',$options);
$f23=new PDO($dsn,'perf_f23_writer','ci-f23-ledger-only',$options);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$options);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$options);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok) throw new RuntimeException('FAIL F112 '.$label);
    ++$n;
};
$roles=[
    'perf_campaign_test_user','perf_f23_writer','perf_f24_reader',
    'perf_f27_journal_reader','perf_f28_app_reader','perf_f88_registrar',
    'perf_f89_link_registrar','perf_f90_projection_reader',
];
$tables=[
    'performance_tenants','portal_service_links','operational_memberships',
    'service_eligibility_snapshots','audit_events','idempotency_receipts',
    'performance_managed_campaigns','performance_campaign_intents',
    'performance_intent_review_journal','performance_ad_account_candidates',
    'performance_campaign_account_links','performance_db_principal_tenant_bindings',
    'performance_demo_budget_scenarios',
];
$privs=['SELECT','INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'];
$can=$db->prepare('SELECT has_table_privilege(?,?,?)');
$has=static function(string $role,string $table,string $priv)use($can):bool {
    $can->execute([$role,'public.'.$table,$priv]);
    return in_array($can->fetchColumn(),[true,'t','1',1],true);
};

$check($db->query('SELECT current_database()')->fetchColumn()==='perf_test_campaign_registry',
    'disposable SQL database');
$check($db->query('SELECT session_user')->fetchColumn()==='postgres',
    'migration-run privilege audit role');

// F99 ran and removed old direct grants; deliberate regression here models
// a DBA/automation regranting legacy privileges before the real migration.
$check(!$has('perf_f28_app_reader','performance_campaign_intents','SELECT'),
    'F99 already removed old F28 SELECT');
$db->exec(
    'GRANT SELECT ON public.performance_campaign_intents TO perf_f28_app_reader'
);
$db->exec(
    'GRANT INSERT ON public.performance_campaign_intents TO perf_f23_writer'
);
$db->exec(
    'GRANT SELECT ON public.performance_demo_budget_scenarios TO perf_f28_app_reader'
);
$check($has('perf_f28_app_reader','performance_campaign_intents','SELECT'),
    'unsafe prior F28 direct SELECT reprovisioned');
$check($has('perf_f23_writer','performance_campaign_intents','INSERT'),
    'unsafe prior F23 INSERT reprovisioned');
$check($has('perf_f28_app_reader','performance_demo_budget_scenarios','SELECT'),
    'unsafe new budget base SELECT reprovisioned');

// Column-grant attack: REVOKE ALL ON TABLE does NOT revoke column grants.
// The migration must FAIL and roll back ALL revokes until a DBA first clears
// every column permission, then a clean retry must succeed.
$db->exec(
    'GRANT SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'TO perf_f28_app_reader'
);
$col=$db->prepare(
    'SELECT has_column_privilege(?,?,?,?)'
);
$col->execute([
    'perf_f28_app_reader','public.performance_demo_budget_scenarios',
    'scenario_ref','SELECT'
]);
$check(in_array($col->fetchColumn(),[true,'t','1',1],true),
    'explicit column-level SELECT grant exists');

$command='php artisan migrate --path=database/lab-migrations '
    .'--force --no-interaction 2>&1';
$failOutput=[];
$failCode=-1;
exec($command,$failOutput,$failCode);
if ($failCode===0) {
    $col->execute([
        'perf_f28_app_reader','public.performance_demo_budget_scenarios',
        'scenario_ref','SELECT'
    ]);
    $still=in_array($col->fetchColumn(),[true,'t','1',1],true);
    throw new RuntimeException(
        'FAIL F112 expected column-level grant guard to reject migration; '
        .'column grant remains='.($still?'yes':'no')
        .'; artisan output='.implode(' | ',$failOutput)
    );
}
$check($failCode!==0,'migration refuses residual column-level privilege');
$check(str_contains(implode("\n",$failOutput),'residual column grant'),
    'migration failure identifies security-specific reason');
$check($has('perf_f28_app_reader','performance_campaign_intents','SELECT'),
    'PostgreSQL transactional failure restores earlier direct grant');
$check($has('perf_f23_writer','performance_campaign_intents','INSERT'),
    'transaction rollback keeps all grants on failed migration');
$check((int)$db->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000006_close_legacy_direct_sql_grants'"
)->fetchColumn()===0,'failed migration is not recorded as successful');

$db->exec(
    'REVOKE SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'FROM perf_f28_app_reader'
);
$successOutput=[];
$successCode=-1;
exec($command,$successOutput,$successCode);
$check($successCode===0,'versioned migration succeeds after column grant closure');
$check((int)$db->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000006_close_legacy_direct_sql_grants'"
)->fetchColumn()===1,'closure registered in Laravel migrations table');

foreach($roles as $role) {
    foreach($tables as $table) {
        foreach($privs as $priv) {
            $check(!$has($role,$table,$priv),
                'no effective '.$role.'/'.$table.'/'.$priv);
        }
    }
}
$check(!$has('perf_f28_app_reader','performance_campaign_intents','SELECT'),
    'F28 old SQL exposure removed by versioned migration');
$check(!$has('perf_f23_writer','performance_campaign_intents','INSERT'),
    'F23 legacy write capability removed by versioned migration');
$check(!$has('perf_f28_app_reader','performance_demo_budget_scenarios','SELECT'),
    'F28 budget base table denied after versioned migration');
$deny=static function(PDO $db,string $sql,string $why)use($check):void {
    $blocked=false;
    try{$db->exec($sql);}catch(PDOException){$blocked=true;}
    $check($blocked,$why);
};
$f28->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$deny($f28,
    'SELECT id FROM public.performance_campaign_intents',
    'F28 cannot read another tenant by spoofing GUC');
$deny($f28,
    'SELECT scenario_ref FROM public.performance_demo_budget_scenarios',
    'F28 cannot read budget base by spoofing GUC');
$deny($f23,
    'SELECT id FROM public.performance_campaign_intents',
    'F23 cannot access base intents after grant closure');
foreach(['perf_f91_reader_a','perf_f91_reader_b'] as $principal) {
    foreach([
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ] as $view) {
        $check($has($principal,$view,'SELECT'),
            "protected $principal retains $view SELECT");
        $check(!$has($principal,$view,'UPDATE'),
            "protected $principal cannot UPDATE view $view");
        $check(!$has($principal,$view,'INSERT'),
            "protected $principal cannot INSERT view $view");
    }
}
$accounts=new CredentialBoundDemoAccountReader();
$budgets=new CredentialBoundDemoBudgetReader();
$check(count($accounts->read($a)['accounts'])===2,'A protected account reader still works');
$check(count($accounts->read($b)['accounts'])===1,'B protected account reader still works');
$pa=$budgets->read($a);$pb=$budgets->read($b);
$check($pa['status']==='credential_scoped_demo_budget_preview_only',
    'A budget reader works after migration');
$check($pb['status']==='credential_scoped_demo_budget_preview_only',
    'B budget reader works after migration');
$check(count($pa['scenarios'])===1 && count($pb['scenarios'])===1,
    'budget reader remains isolated by database principal');
$check($pa['scenarios'][0]['total_centavos']===10001,
    'exact A BRL budget remains 10001 centavos');
$check($pb['scenarios'][0]['total_centavos']===7002,
    'exact B BRL budget remains 7002 centavos');
$check(!$pa['media_execution_allowed'] && !$pa['billing_allowed'],
    'no provider execution or payments after grant migration');
$check(!$pb['media_execution_allowed'] && !$pb['billing_allowed'],
    'no provider execution or payments in other tenant');

$second=[];$secondCode=-1;
exec($command,$second,$secondCode);
$check($secondCode===0,'re-running migration command is idempotent');
$check((int)$db->query(
    "SELECT count(*) FROM migrations WHERE migration="
    ."'2026_11_10_000006_close_legacy_direct_sql_grants'"
)->fetchColumn()===1,'exactly one versioned migration record');

$risk=json_decode(
    (string)file_get_contents(
        dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
    ),true,16,JSON_THROW_ON_ERROR
);
$check($risk['risk_state']==='open_no_go'
    && $risk['release_allowed']===false
    && $risk['direct_sql_client_access_allowed']===false,
    'production RLS/GUC and IAM risk still recorded as OPEN/NO-GO');

echo "PASS F112 $n assertions: versioned CI grant migration, transactional rollback on column grants, safe readers, NO-GO\n";
