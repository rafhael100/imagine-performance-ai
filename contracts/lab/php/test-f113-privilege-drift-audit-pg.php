<?php
declare(strict_types=1);

/**
 * F1-113 audit must detect post-migration drift, including direct,
 * inherited, column-level and PUBLIC/view grants. Audit is read-only.
 *
 * The F112 migration has already been applied in disposable PostgreSQL16.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F113_PRIVILEGE_AUDIT_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F113 requires ephemeral localhost PostgreSQL16 CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
use App\Performance\Security\LegacySqlGrantDriftAudit;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$legacy=new PDO($dsn,'perf_f28_app_reader','ci-f28-app-read-only',$opt);
$reader=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$auditor=new LegacySqlGrantDriftAudit();
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void {
    if(!$ok) throw new RuntimeException('FAIL F113 '.$label);
    ++$n;
};
$hasViolation=static function(array $report,string $role,string $object,
    string $privilege,string $scope):bool {
    foreach($report['unsafe_privileges'] as $v){
        if($v['principal']===$role
            && $v['object']==='public.'.$object
            && $v['privilege']===$privilege
            && $v['scope']===$scope)return true;
    }
    return false;
};
$assertUnsafe=static function(string $label)use($auditor,$db,$check):array{
    $report=$auditor->inspect($db);
    $check($report['audit_complete']===true,$label.' scans all objects');
    $check($report['status']==='privilege_drift_detected',
        $label.' reports unsafe access');
    $check($report['unsafe_privileges']!==[],$label.' supplies evidence');
    $check($report['release_allowed']===false
        && $report['provider_operations_allowed']===false
        && $report['billing_allowed']===false,$label.' still NO-GO');
    $blocked=false;
    try{$auditor->assertClosedInLab($db);}catch(RuntimeException){$blocked=true;}
    $check($blocked,$label.' prevents a false positive security gate');
    return $report;
};
$clean=$auditor->assertClosedInLab($db);
$check($clean['status']==='closed_in_disposable_ci','F112 initial grants closed');
$check($clean['unsafe_privileges']===[],'initial privileges zero');
$check($clean['audited_base_tables']===13,'13 base tables covered');
$check($clean['audited_read_views']===4,'four credential-bound views covered');
$check($clean['audit_complete']===true,'full audit completed');
$check($clean['release_allowed']===false
    && $clean['provider_operations_allowed']===false
    && $clean['billing_allowed']===false,
    'even clean CI lab never authorizes launch, Ads or billing');

// An apparently harmless SELECT on the FORCE-RLS budget base is still a
// privilege regression: the auditor catches permissions, not merely rows.
$db->exec(
    'GRANT SELECT ON public.performance_demo_budget_scenarios TO perf_f28_app_reader'
);
$report=$assertUnsafe('legacy budget SELECT regrant');
$check($hasViolation($report,'perf_f28_app_reader',
    'performance_demo_budget_scenarios','SELECT','table'),
    'direct unsafe legacy budget SELECT pinpointed');
$db->exec(
    'REVOKE SELECT ON public.performance_demo_budget_scenarios FROM perf_f28_app_reader'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'first regression repaired');

// A column grant creates unsafe access even when table privilege is false.
$db->exec(
    'GRANT SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'TO perf_f28_app_reader'
);
$report=$assertUnsafe('column-level regrant');
$check($hasViolation($report,'perf_f28_app_reader',
    'performance_demo_budget_scenarios','SELECT','column'),
    'direct column-only grant detected independently from table SELECT');
$db->exec(
    'REVOKE SELECT(scenario_ref) ON public.performance_demo_budget_scenarios '
    .'FROM perf_f28_app_reader'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'column privilege repaired');

// A role inherits SELECT from an unlisted NOLOGIN group; direct revocation
// from the F28 role is insufficient. The audit must detect effective grants.
$db->exec(
    'CREATE ROLE perf_f113_inherited_grant NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE'
);
$db->exec(
    'GRANT SELECT ON public.performance_campaign_intents TO perf_f113_inherited_grant'
);
$db->exec(
    'GRANT perf_f113_inherited_grant TO perf_f28_app_reader WITH INHERIT TRUE'
);
$report=$assertUnsafe('inherited SQL permission drift');
$check($hasViolation($report,'perf_f28_app_reader',
    'performance_campaign_intents','SELECT','table'),
    'inherited direct table SELECT detected');
$db->exec('REVOKE perf_f113_inherited_grant FROM perf_f28_app_reader');
$db->exec(
    'REVOKE SELECT ON public.performance_campaign_intents FROM perf_f113_inherited_grant'
);
$db->exec('DROP ROLE perf_f113_inherited_grant');
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'inherited grant drift repaired');

// A public grant on a credential-bound read VIEW is not acceptable even
// though the VIEW has a tenant filter; old roles must lack all access.
$db->exec(
    'GRANT SELECT ON public.perf_f106_credential_budget_demo_read TO PUBLIC'
);
$report=$assertUnsafe('PUBLIC view grant drift');
$check($hasViolation($report,'perf_f28_app_reader',
    'perf_f106_credential_budget_demo_read','SELECT','legacy_view_exposure'),
    'PUBLIC grant expands availability to a legacy SQL role');
$db->exec(
    'REVOKE SELECT ON public.perf_f106_credential_budget_demo_read FROM PUBLIC'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'PUBLIC grant drift repaired');

// Privilege drift for supposedly SELECT-only credentialed client role.
$db->exec(
    'GRANT SELECT ON public.performance_demo_budget_scenarios TO perf_f91_reader_a'
);
$report=$assertUnsafe('credentialed viewer base-table exposure');
$check($hasViolation($report,'perf_f91_reader_a',
    'performance_demo_budget_scenarios','SELECT','table'),
    'privileged reader detected on protected base table');
$db->exec(
    'REVOKE SELECT ON public.performance_demo_budget_scenarios FROM perf_f91_reader_a'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'credentialed viewer base access repaired');

// A SELECT-only view must not be directly writable by the authenticated
// reader. Even a grant that cannot produce mutations is a drift signal.
$db->exec(
    'GRANT UPDATE ON public.perf_f106_credential_budget_demo_read TO perf_f91_reader_a'
);
$report=$assertUnsafe('credentialed viewer gained view UPDATE');
$check($hasViolation($report,'perf_f91_reader_a',
    'perf_f106_credential_budget_demo_read','UPDATE','view_write_or_admin'),
    'unexpected VIEW UPDATE privilege detected');
$db->exec(
    'REVOKE UPDATE ON public.perf_f106_credential_budget_demo_read FROM perf_f91_reader_a'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'VIEW write privilege repaired');

// Missing required SELECT prevents a supposedly healthy customer preview.
$db->exec(
    'REVOKE SELECT ON public.perf_f106_credential_budget_demo_read FROM perf_f91_reader_a'
);
$report=$assertUnsafe('protected view SELECT missing');
$check($hasViolation($report,'perf_f91_reader_a',
    'perf_f106_credential_budget_demo_read','SELECT','view_required_missing'),
    'unavailable protected view flagged as incomplete rather than success');
$db->exec(
    'GRANT SELECT ON public.perf_f106_credential_budget_demo_read TO perf_f91_reader_a'
);
$check($auditor->assertClosedInLab($db)['unsafe_privileges']===[],
    'required read-only view restored');

// Reject inspection under a role lacking permission to audit SQL catalog.
$denied=$auditor->inspect($legacy);
$check($denied['status']==='untrusted_catalog_auditor',
    'legacy role cannot claim to have audited privileges');
$check($denied['audit_complete']===false
    && $denied['release_allowed']===false,
    'untrusted catalog auditor fails closed');
$denied=$auditor->inspect($reader);
$check($denied['status']==='untrusted_catalog_auditor',
    'customer database login cannot self-certify permissions');
$check($denied['unsafe_privileges']===[] && $denied['audit_complete']===false,
    'user role cannot obtain authoritative audit record');

putenv('PERF_F113_PRIVILEGE_AUDIT_LAB=0');
$disabled=$auditor->inspect($db);
$check($disabled['status']==='lab_disabled'
    && $disabled['release_allowed']===false,
    'explicit CI audit flag required');
putenv('PERF_F113_PRIVILEGE_AUDIT_LAB=1');
putenv('APP_ENV=production');
$prod=$auditor->inspect($db);
$check($prod['status']==='lab_disabled'
    && $prod['audit_complete']===false,
    'production invocation cannot pretend to be audited');
putenv('APP_ENV=testing');

$final=$auditor->assertClosedInLab($db);
$check($final===$clean,'final catalog audit matches initial closed state');
$manifest=json_decode(
    (string)file_get_contents(dirname(__DIR__,3)
        .'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'),
    true,16,JSON_THROW_ON_ERROR
);
$check($manifest['risk_state']==='open_no_go'
    && $manifest['release_allowed']===false
    && $manifest['direct_sql_client_access_allowed']===false,
    'production GUC and real IAM risk remains OPEN despite lab grant audit');
echo "PASS F113 $n assertions: drift detected for direct/column/inherited/PUBLIC/view grants; CI only, NO-GO\n";
