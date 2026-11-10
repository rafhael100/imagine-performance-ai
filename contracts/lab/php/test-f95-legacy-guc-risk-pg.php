<?php
declare(strict_types=1);

/**
 * F1-95 — negative-control regression proving that the legacy FORCE RLS
 * predicate with a caller-settable GUC does NOT authenticate a tenant.
 *
 * This is intentionally a KNOWN VULNERABILITY test with synthetic data,
 * NOT a penetration test on a real environment or an authorization mechanism.
 */
if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F95_LEGACY_GUC_RISK_LAB')!=='1'
    || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F95 requires isolated disposable PostgreSQL CI');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoCampaignReader;

$manifest=json_decode(
    (string)file_get_contents($root.'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'),
    true,12,JSON_THROW_ON_ERROR
);
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$legacy=new PDO($dsn,'perf_f90_projection_reader','ci-f90-reader-only',$opts);
$secure=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$a='88888888-8888-4888-8888-888888888888';
$b='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $okay,string $label)use(&$n):void{
    if(!$okay)throw new RuntimeException('FAIL F95 '.$label);
    ++$n;
};
$check($manifest['schema_version']==='perf.security.legacy-guc-risk.lab.v1',
    'manifest version pinned');
$check($manifest['risk_state']==='open_no_go','risk explicitly open');
$check($manifest['release_allowed']===false
    && $manifest['direct_sql_client_access_allowed']===false,
    'manifest cannot permit release or client DB logins');
$check($manifest['role_under_test']==='perf_f90_projection_reader',
    'test role named in manifest');

$asLegacy=static function() use ($legacy): array {
    $accounts=(int)$legacy->query(
        'SELECT count(*) FROM performance_ad_account_candidates'
    )->fetchColumn();
    $links=(int)$legacy->query(
        'SELECT count(*) FROM performance_campaign_account_links'
    )->fetchColumn();
    $campaigns=(int)$legacy->query(
        'SELECT count(*) FROM performance_managed_campaigns'
    )->fetchColumn();
    return [$accounts,$links,$campaigns];
};
$check($legacy->query('SELECT session_user')->fetchColumn()==='perf_f90_projection_reader',
    'legacy reader login is real PostgreSQL session_user');
$check($asLegacy()===[0,0,0],'without tenant GUC legacy role reads zero rows');
$set=static function(string $tenant)use($legacy):void {
    $stmt=$legacy->prepare("SELECT set_config('app.performance_tenant_id',?,false)");
    $stmt->execute([$tenant]);
};
$set($a);
$first=$asLegacy();
$check($first===[2,1,1],'caller-selected GUC A reveals A synthetic records');
$set($b);
$second=$asLegacy();
$check($second===[1,1,1],'same SQL login can switch GUC and read B synthetic records');
$check($first!==$second,'tenant switch changes visible data under same login');
$check($manifest['legacy_guc_spoof_reproduced']===true,
    'risk flag describes reproducible failure');

$secureReader=new CredentialBoundDemoCampaignReader();
$baseline=$secureReader->read($secure);
$check($baseline['status']==='credential_scoped_demo_preview_only'
       && count($baseline['campaigns'])===1,
    'new credential-bound campaign reader has its own company');
$secure->prepare("SELECT set_config('app.performance_tenant_id',?,false)")->execute([$b]);
$afterSpoof=$secureReader->read($secure);
$check($afterSpoof['campaigns']===$baseline['campaigns'],
    'the same tenant GUC spoof does not expand F94 safe view');
foreach(['authorization_eligible','dispatch_enabled','billing_enabled',
         'ready_for_production','portal_identity_authenticated'] as $field) {
    $check($baseline[$field]===false && $afterSpoof[$field]===false,
        "F94 never authorizes $field");
}
$check((bool)$legacy->query(
    "SELECT has_table_privilege(current_user,'performance_ad_account_candidates','SELECT')"
)->fetchColumn(),'CI legacy role can read base table when GUC is set');
$check((bool)$legacy->query(
    "SELECT has_table_privilege(current_user,'performance_campaign_account_links','SELECT')"
)->fetchColumn(),'CI legacy role can read join table when GUC is set');

$blocked=false;
try {
    $legacy->query('SELECT * FROM public.perf_f93_demo_campaign_account_read');
} catch(PDOException) {
    $blocked=true;
}
$check($blocked,'legacy direct table reader not granted new credential-bound VIEW');
echo "PASS F95 $n assertions: legacy GUC spoof reproduced (NO-GO); credential-bound F94 remains scoped\n";
