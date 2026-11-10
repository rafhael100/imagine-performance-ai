<?php
declare(strict_types=1);

/**
 * F1-96. Rehearsal: revoke direct base-table grants from ONE obsolete
 * synthetic CI role after F95 proved tenant-GUC spoof.
 * The changes affect only the disposable PostgreSQL16 CI service.
 *
 * NOT a production database migration or a plan to use SQL clients directly.
 */
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F96_GRANT_REVOCATION_LAB')!=='1'
    || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F96 forbids execution outside isolated PostgreSQL CI');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoCampaignReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$legacy=new PDO($dsn,'perf_f90_projection_reader','ci-f90-reader-only',$opt);
$secure=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$n=0;
$check=static function(bool $condition,string $label)use(&$n):void {
    if(!$condition)throw new RuntimeException('FAIL F96 '.$label);
    ++$n;
};
$has=static function(string $role,string $table,string $priv)use($admin):bool{
    $stmt=$admin->query(
        "SELECT has_table_privilege('$role','$table','$priv')"
    );
    return in_array($stmt->fetchColumn(),[true,'t','1',1],true);
};
$tables=[
    'performance_managed_campaigns',
    'performance_ad_account_candidates',
    'performance_campaign_account_links',
];
foreach($tables as $table) {
    $check($has('perf_f90_projection_reader',$table,'SELECT'),
        "precondition: legacy role has SELECT on $table");
}
$legacy->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$before=(int)$legacy->query(
    'SELECT count(*) FROM performance_ad_account_candidates'
)->fetchColumn();
$check($before===1,'legacy GUC-switch visibility confirmed before revoke');

$reader=new CredentialBoundDemoCampaignReader();
$beforeSafe=$reader->read($secure);
$check($beforeSafe['status']==='credential_scoped_demo_preview_only'
    && count($beforeSafe['campaigns'])===1,'secure reader available before revocation');

$admin->exec(
    'REVOKE SELECT ON '
    .'public.performance_managed_campaigns,'
    .'public.performance_ad_account_candidates,'
    .'public.performance_campaign_account_links '
    .'FROM perf_f90_projection_reader'
);
foreach($tables as $table) {
    $check(!$has('perf_f90_projection_reader',$table,'SELECT'),
        "revoke removes SELECT from $table");
    $blocked=false;
    try {
        $legacy->query("SELECT count(*) FROM public.$table")->fetchColumn();
    } catch (PDOException) {
        $blocked=true;
    }
    $check($blocked,"legacy role cannot use GUC to read $table after revoke");
}
$legacy->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['88888888-8888-4888-8888-888888888888']);
foreach($tables as $table) {
    $blocked=false;
    try {
        $legacy->query("SELECT count(*) FROM public.$table")->fetchColumn();
    } catch(PDOException) {
        $blocked=true;
    }
    $check($blocked,"changing GUC back cannot restore direct SELECT on $table");
}
$afterSafe=$reader->read($secure);
$check($afterSafe['campaigns']===$beforeSafe['campaigns'],
    'revoke legacy base grants leaves login-bound F94 reader functional');
foreach(['portal_identity_authenticated','authorization_eligible',
         'ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
    $check($afterSafe[$flag]===false,"F94 continues to deny $flag");
}
$check(!$has('perf_f90_projection_reader','perf_f93_demo_campaign_account_read','SELECT'),
    'legacy role was never granted new view');
$check($has('perf_f91_reader_a','perf_f93_demo_campaign_account_read','SELECT'),
    'scoped credential view remains accessible to authorized test login');

$check($has('perf_f28_app_reader','performance_managed_campaigns','SELECT'),
    'separate legacy F28 reader remains unresolved (do not claim production fix)');

$risk=json_decode((string)file_get_contents(
    $root.'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,12,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    && $risk['release_allowed']===false
    && $risk['direct_sql_client_access_allowed']===false,
    'lab rehearsal MUST NOT clear the global risk or release gate');
echo "PASS F96 $n assertions: revoke direct grants in disposable PG, preserve protected reader; production NO-GO\n";
