<?php
declare(strict_types=1);

/**
 * F115 real PostgreSQL16 role graph rehearsal after F112/F113/F114.
 * No production DB or operational role is touched.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F115_ROLE_MEMBERSHIP_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F115 requires disposable PG16 lab');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/ProtectedSqlPrincipalMembershipAudit.php';

use App\Performance\Security\ProtectedSqlPrincipalMembershipAudit;
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$user=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$auditor=new ProtectedSqlPrincipalMembershipAudit();
$count=0;
$check=static function(bool $v,string $message)use(&$count):void {
    if(!$v)throw new RuntimeException('FAIL F115 '.$message);
    ++$count;
};
$active=static fn(mixed $v):bool=>in_array($v,[true,'t','1',1],true);
$expectDrift=static function(string $label)use($auditor,$db,$check):array{
    $result=$auditor->inspect($db);
    $check($result['status']==='role_membership_or_attribute_drift',
        "$label detected as SQL security drift");
    $check($result['audit_complete']===true,"$label complete scan");
    $check($result['release_allowed']===false
        && $result['media_execution_allowed']===false
        && $result['billing_allowed']===false,"$label remains no-GO");
    $blocked=false;
    try{$auditor->assertProtectedRolesIsolated($db);}catch(RuntimeException){$blocked=true;}
    $check($blocked,"$label fails strict policy");
    return $result;
};
$edge=static function(array $report,string $granted,string $member):?array{
    foreach($report['unsafe_membership_edges'] as $e) {
        if($e['granted_role']===$granted && $e['member_role']===$member)return $e;
    }
    return null;
};
$attribute=static function(array $report,string $role,string $attr):bool{
    foreach($report['unsafe_role_attributes'] as $e) {
        if($e['principal']===$role && $e['attribute']===$attr)return true;
    }
    return false;
};

$clean=$auditor->assertProtectedRolesIsolated($db);
$check($clean['status']==='protected_roles_isolated_in_ci','baseline isolated role graph');
$check($clean['protected_principal_count']===11,'all 11 protected principals audited');
$check($clean['unsafe_membership_edges']===[],'no baseline membership edges');
$check($clean['unsafe_role_attributes']===[],'no baseline privileged role attrs');
$check($clean['release_allowed']===false
    && $clean['media_execution_allowed']===false
    && $clean['billing_allowed']===false,
    'clean lab must not authorize any release, Ads or billing');

// Previously unknown role granted to a legacy SQL principal:
$db->exec('CREATE ROLE perf_f115_unknown_group NOLOGIN NOSUPERUSER NOBYPASSRLS');
$db->exec(
    'GRANT perf_f115_unknown_group TO perf_f28_app_reader '
    .'WITH INHERIT TRUE, SET TRUE'
);
$r=$expectDrift('F28 inherited + SET unknown role');
$found=$edge($r,'perf_f115_unknown_group','perf_f28_app_reader');
$check(is_array($found),'F28 role graph edge identified');
$check($found['inherit_option']===true,'F28 inherited capability observed');
$check($found['set_option']===true,'F28 SET ROLE capability observed');
$db->exec('REVOKE perf_f115_unknown_group FROM perf_f28_app_reader');
$check($auditor->assertProtectedRolesIsolated($db)['unsafe_membership_edges']===[],
    'grant to legacy principal revoked');

// Unlisted principal can potentially SET ROLE into a protected login.
$db->exec(
    'GRANT perf_f91_reader_a TO perf_f115_unknown_group '
    .'WITH INHERIT FALSE, SET TRUE'
);
$r=$expectDrift('unknown principal could SET protected login');
$found=$edge($r,'perf_f91_reader_a','perf_f115_unknown_group');
$check(is_array($found),'outbound protected reader membership flagged');
$check($found['set_option']===true,'SET ROLE permission captured');
$db->exec('REVOKE perf_f91_reader_a FROM perf_f115_unknown_group');
$check($auditor->assertProtectedRolesIsolated($db)['unsafe_membership_edges']===[],
    'SET ROLE privilege revoked from unknown principal');

// No one other than dedicated NOLOGIN definer should access its identity.
$db->exec(
    'GRANT perf_f104_journal_writer TO perf_f115_unknown_group '
    .'WITH INHERIT TRUE, SET TRUE'
);
$r=$expectDrift('unknown principal inherits journal SECURITY DEFINER owner');
$check(is_array($edge($r,'perf_f104_journal_writer','perf_f115_unknown_group')),
    'F104 protected NOLOGIN owner cannot be granted to another role');
$db->exec('REVOKE perf_f104_journal_writer FROM perf_f115_unknown_group');

// Membership without SET/INHERIT is still an unexpected admin surface.
$db->exec(
    'GRANT perf_f115_unknown_group TO perf_f91_reader_a '
    .'WITH INHERIT FALSE, SET FALSE'
);
$r=$expectDrift('read-only membership still unexpected');
$found=$edge($r,'perf_f115_unknown_group','perf_f91_reader_a');
$check(is_array($found),'even no-SET no-INHERIT membership tracked');
$check($found['inherit_option']===false
    && $found['set_option']===false,'reported options match SQL role edge');
$db->exec('REVOKE perf_f115_unknown_group FROM perf_f91_reader_a');
$db->exec('DROP ROLE perf_f115_unknown_group');
$check($auditor->assertProtectedRolesIsolated($db)['unsafe_membership_edges']===[],
    'unknown test role safely dropped');

// Role flags are checked even with *zero* memberships.
$db->exec('ALTER ROLE perf_f91_reader_a BYPASSRLS');
$r=$expectDrift('reader gained BYPASSRLS');
$check($attribute($r,'perf_f91_reader_a','rolbypassrls'),
    'protected F91 reader BYPASSRLS detected');
$db->exec('ALTER ROLE perf_f91_reader_a NOBYPASSRLS');
$db->exec('ALTER ROLE perf_f104_journal_writer LOGIN');
$r=$expectDrift('journal SECURITY DEFINER role gained LOGIN');
$check($attribute($r,'perf_f104_journal_writer','rolcanlogin'),
    'F104 journal role LOGIN detected');
$db->exec('ALTER ROLE perf_f104_journal_writer NOLOGIN');
$db->exec('ALTER ROLE perf_f104_journal_writer INHERIT');
$r=$expectDrift('journal SECURITY DEFINER role gained INHERIT');
$check($attribute($r,'perf_f104_journal_writer','rolinherit'),
    'F104 journal role INHERIT detected');
$db->exec('ALTER ROLE perf_f104_journal_writer NOINHERIT');

$check($auditor->assertProtectedRolesIsolated($db)===$clean,
    'final role graph exactly matches safe baseline after mutations');

$denied=$auditor->inspect($user);
$check($denied['status']==='untrusted_catalog_principal'
    && $denied['audit_complete']===false,
    'client SQL principal cannot self-certify role membership security');
$check($denied['release_allowed']===false,'self-inspection stays NO-GO');
putenv('PERF_F115_ROLE_MEMBERSHIP_LAB=0');
$denied=$auditor->inspect($db);
$check($denied['status']==='lab_disabled'
    && $denied['audit_complete']===false,'disabled lab audit fails closed');
putenv('PERF_F115_ROLE_MEMBERSHIP_LAB=1');
putenv('APP_ENV=production');
$denied=$auditor->inspect($db);
$check($denied['status']==='lab_disabled'
    && $denied['media_execution_allowed']===false,
    'production cannot use CI role membership attestation');
putenv('APP_ENV=testing');
$manifest=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($manifest['risk_state']==='open_no_go'
    && $manifest['release_allowed']===false
    && $manifest['direct_sql_client_access_allowed']===false,
    'global role/tenant isolation remains unapproved for production');
echo "PASS F115 $count assertions: role membership, SET ROLE, inherited grants and privileged attributes blocked in CI\n";
