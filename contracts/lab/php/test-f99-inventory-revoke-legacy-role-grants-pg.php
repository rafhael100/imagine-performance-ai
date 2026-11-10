<?php
declare(strict_types=1);

/**
 * FAST-PERF F1-99 — final CI-only grant review/remediation rehearsal.
 *
 * Only the ephemeral PostgreSQL16 database is mutated. This must NEVER be
 * invoked on a production, staging, Leaseweb or shared development database.
 * Revokes the broad table privileges of ALL KNOWN LEGACY CI ROLES after
 * F22–F98 regressions have completed.
 */
if(PHP_SAPI!=='cli'
   ||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F99_ROLE_GRANTS_LAB')!=='1'
   ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
   ||getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
   ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
   ||getenv('PGHOST')!=='127.0.0.1'
   ||getenv('PGPORT')!=='5432'
   ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F99 refuses all non-ephemeral PostgreSQL environments');
}

$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoAccountReader;
use App\Performance\ReadSide\CredentialBoundDemoCampaignReader;
use App\Performance\ReadSide\CredentialBoundDemoReviewReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$checkCount=0;
$check=static function(bool $ok,string $label)use(&$checkCount):void {
    if(!$ok) throw new RuntimeException('FAIL F99 '.$label);
    ++$checkCount;
};
$check($db->query('SELECT current_database()')->fetchColumn()==='perf_test_campaign_registry',
    'connected only to ephemeral PostgreSQL CI');
$check($db->query('SELECT session_user')->fetchColumn()==='postgres',
    'CI superuser review role');

// These are exact, pre-existing synthetic CI login roles, not any users of
// the production database. Known names only; no caller-provided identifiers.
$legacyRoles=[
    'perf_campaign_test_user',
    'perf_f23_writer',
    'perf_f24_reader',
    'perf_f27_journal_reader',
    'perf_f28_app_reader',
    'perf_f88_registrar',
    'perf_f89_link_registrar',
    'perf_f90_projection_reader',
];
$sensitiveTables=[
    'performance_tenants',
    'portal_service_links',
    'operational_memberships',
    'service_eligibility_snapshots',
    'audit_events',
    'idempotency_receipts',
    'performance_managed_campaigns',
    'performance_campaign_intents',
    'performance_intent_review_journal',
    'performance_ad_account_candidates',
    'performance_campaign_account_links',
    'performance_db_principal_tenant_bindings',
];
$privileges=['SELECT','INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'];
$has=$db->prepare('SELECT has_table_privilege(?,?,?)');
$can=static function(string $role,string $table,string $permission)use($has):bool {
    $has->execute([$role,'public.'.$table,$permission]);
    return in_array($has->fetchColumn(),[true,'t','1',1],true);
};
foreach($legacyRoles as $role) {
    $q=$db->prepare('SELECT rolsuper,rolcreaterole,rolcreatedb,rolcanlogin FROM pg_roles WHERE rolname=?');
    $q->execute([$role]);
    $info=$q->fetch(PDO::FETCH_ASSOC);
    $check(is_array($info),'expected legacy test role exists '.$role);
    foreach(['rolsuper','rolcreaterole','rolcreatedb'] as $flag) {
        $check(in_array($info[$flag],[false,'f','0',0],true),
            'legacy test role not privileged '.$role.'/'.$flag);
    }
    $check(in_array($info['rolcanlogin'],[true,'t','1',1],true),'legacy role is CI login '.$role);
}
$expected=[
    ['perf_campaign_test_user','performance_campaign_intents','UPDATE'],
    ['perf_f23_writer','performance_campaign_intents','INSERT'],
    ['perf_f24_reader','performance_managed_campaigns','SELECT'],
    ['perf_f27_journal_reader','performance_intent_review_journal','SELECT'],
    ['perf_f28_app_reader','portal_service_links','SELECT'],
    ['perf_f88_registrar','performance_ad_account_candidates','INSERT'],
    ['perf_f89_link_registrar','performance_campaign_account_links','INSERT'],
];
foreach($expected as [$role,$table,$permission]) {
    $check($can($role,$table,$permission),
        'reconciliation precondition '.$role.'/'.$table.'/'.$permission);
}
$check(!$can('perf_f90_projection_reader','performance_managed_campaigns','SELECT'),
    'F96 already revoked F90 campaign SELECT');

// No roles gain privileges during this rehearsal. Inventory the prior grants
// from the database catalog (not handwritten assumptions).
$grantsBefore=[];
foreach($legacyRoles as $role){
    foreach($sensitiveTables as $table){
        foreach($privileges as $priv){
            if($can($role,$table,$priv)){
                $grantsBefore[]="$role:$table:$priv";
            }
        }
    }
}
$check(count($grantsBefore)>=10,'multiple legacy grants found for removal');

// Revoke only these exact synthetic CI principals on exact allowlisted tables.
foreach($sensitiveTables as $table) {
    $db->exec('REVOKE ALL PRIVILEGES ON TABLE public.'.$table
        .' FROM '.implode(',',$legacyRoles));
}
$removed=0;
foreach($legacyRoles as $role) {
    foreach($sensitiveTables as $table) {
        foreach($privileges as $priv){
            $check(!$can($role,$table,$priv),
                'no legacy direct '.$priv.' on '.$table.' for '.$role);
        }
    }
}
$removed=count($grantsBefore);
$check($removed>=10,'at least ten known unsafe direct grants were revoked');

// Both the legacy and newly introduced views must remain inaccessible to the
// old direct-SQL principals; only credential-bound test logins can see them.
foreach($legacyRoles as $role){
    foreach(['perf_f91_demo_account_read',
             'perf_f93_demo_campaign_account_read',
             'perf_f97_demo_review_read'] as $view){
        $check(!$can($role,$view,'SELECT'),
            'old role '.$role.' cannot read credential-bound '.$view);
    }
}
foreach(['perf_f91_reader_a','perf_f91_reader_b'] as $role) {
    foreach(['perf_f91_demo_account_read',
             'perf_f93_demo_campaign_account_read',
             'perf_f97_demo_review_read'] as $view){
        $check($can($role,$view,'SELECT'),
            'trusted lab login '.$role.' retains read-only view '.$view);
        $check(!$can($role,$view,'UPDATE'),
            'trusted lab login cannot update '.$view);
        $check(!$can($role,$view,'INSERT'),
            'trusted lab login cannot insert into '.$view);
    }
}

$f28=new PDO($dsn,'perf_f28_app_reader','ci-f28-app-read-only',$options);
$f28->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['11111111-1111-4111-8111-111111111111']);
foreach(['portal_service_links','performance_managed_campaigns',
         'performance_campaign_intents','performance_intent_review_journal'] as $table){
    $denied=false;
    try{$f28->query('SELECT count(*) FROM public.'.$table)->fetchColumn();}
    catch(PDOException){$denied=true;}
    $check($denied,'old F28 reader cannot use forged GUC to read '.$table);
}
$f23=new PDO($dsn,'perf_f23_writer','ci-f23-ledger-only',$options);
$f23->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['11111111-1111-4111-8111-111111111111']);
$denied=false;
try{$f23->query('SELECT count(*) FROM public.performance_campaign_intents')->fetchColumn();}
catch(PDOException){$denied=true;}
$check($denied,'old F23 writer can no longer query base intents with forged GUC');

$loginA=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$options);
$loginB=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$options);
foreach([
    ['account',new CredentialBoundDemoAccountReader(),'accounts',2,1],
    ['campaign',new CredentialBoundDemoCampaignReader(),'campaigns',1,1],
    ['review',new CredentialBoundDemoReviewReader(),'reviews',1,1]
] as [$label,$reader,$key,$countA,$countB]){
    $a=$reader->read($loginA);
    $b=$reader->read($loginB);
    $check(count($a[$key])===$countA,'credential reader A '.$label.' preserved');
    $check(count($b[$key])===$countB,'credential reader B '.$label.' preserved');
    $check($a['credential_binding_confirmed']===true,
        'credential binding A intact for '.$label);
    $check($b['credential_binding_confirmed']===true,
        'credential binding B intact for '.$label);
    foreach(['portal_identity_authenticated','authorization_eligible',
             'ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
        $check($a[$flag]===false && $b[$flag]===false,
            'no-go for '.$label.'/'.$flag);
    }
}
$risk=json_decode(
    (string)file_get_contents($root.'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'),
    true,12,JSON_THROW_ON_ERROR
);
$check($risk['risk_state']==='open_no_go'
    && $risk['release_allowed']===false
    && $risk['direct_sql_client_access_allowed']===false,
    'global legacy/production risk never declared remediated');

// The test reports only role names and grant counts, never CI passwords.
echo "PASS F99 {$checkCount} assertions: {$removed} legacy direct CI privileges revoked; "
    ."three credential-bound readers remain safe; release NO-GO\n";
