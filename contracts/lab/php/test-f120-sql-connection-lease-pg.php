<?php
declare(strict_types=1);

/**
 * F120: exercise pooled-connection reuse, login change expectation, tenant
 * rebind/revoke and mid-query race AFTER the F119 GUC RLS removal.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F120_SQL_LEASE_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F120 runs only in disposable PostgreSQL CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/SqlTenantConnectionLease.php';

use App\Performance\Security\SqlTenantConnectionLease;

final class F120InterleavedPdo extends PDO
{
    public ?Closure $duringRead = null;

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        $statement = parent::query($query);
        if ($statement !== false
            && $this->duringRead !== null
            && str_contains($query, 'FROM public.perf_f106_credential_budget_demo_read')
        ) {
            $f = $this->duringRead;
            $this->duringRead = null;
            $f();
        }
        return $statement;
    }
}

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$options);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$options);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$loginA='perf_f91_reader_a';
$loginB='perf_f91_reader_b';
$views=[
    'perf_f91_demo_account_read',
    'perf_f93_demo_campaign_account_read',
    'perf_f97_demo_review_read',
    'perf_f106_credential_budget_demo_read',
];
$n=0;
$check=static function(bool $yes,string $name)use(&$n):void {
    if (!$yes) throw new RuntimeException('FAIL F120 '.$name);
    $n++;
};
$revoke=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$restore=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);

$leaseA=SqlTenantConnectionLease::capture($a,$loginA,$tenantA);
$leaseB=SqlTenantConnectionLease::capture($b,$loginB,$tenantB);
$check($leaseA instanceof SqlTenantConnectionLease,'real A login+tenant lease acquired');
$check($leaseB instanceof SqlTenantConnectionLease,'real B login+tenant lease acquired');
$check(SqlTenantConnectionLease::capture($a,$loginB,$tenantB)===null,
    'A connection cannot be repurposed for B login/tenant');
$check(SqlTenantConnectionLease::capture($a,$loginA,$tenantB)===null,
    'A connection cannot be asserted to belong to B tenant');
$check(SqlTenantConnectionLease::capture($b,$loginA,$tenantA)===null,
    'B connection cannot masquerade as A');
$check(SqlTenantConnectionLease::capture($admin,$loginA,$tenantA)===null,
    'administrator SQL session cannot obtain DEMO login lease');
$check(SqlTenantConnectionLease::capture($a,'random_login',$tenantA)===null,
    'unknown login rejected');
$check(SqlTenantConnectionLease::capture($a,$loginA,'not-a-uuid')===null,
    'malformed tenant identifier rejected');

$baseline=[];
foreach(['A'=>$leaseA,'B'=>$leaseB] as $label=>$lease){
    foreach($views as $view){
        $r=$lease->readDemoView($view);
        $check($r['status']==='credential_scoped_demo_read_only',
            "$label valid SQL lease read $view");
        $check($r['simulation_only']===true
            && $r['ready_for_production']===false
            && $r['media_execution_allowed']===false
            && $r['billing_allowed']===false
            && $r['portal_identity_authenticated']===false,
            "$label view never represents real Portal authorization");
        $check(is_array($r['rows']),"$label returns bounded rows");
        $baseline[$label][$view]=$r['rows'];
        foreach($r['rows'] as $row){
            $check($row['tenant_id']===($label==='A'?$tenantA:$tenantB),
                "$label row limited by authenticated database principal");
        }
    }
}
$check(count($baseline['A']['perf_f91_demo_account_read'])===2,
    'A two candidate accounts');
$check(count($baseline['B']['perf_f91_demo_account_read'])===1,
    'B one candidate account');
$check(count($baseline['A']['perf_f106_credential_budget_demo_read'])===1,
    'A exactly one synthetic budget');
$check(count($baseline['B']['perf_f106_credential_budget_demo_read'])===1,
    'B exactly one synthetic budget');
$check($leaseA->readDemoView('performance_demo_budget_scenarios')['status']
    ==='view_not_allowlisted',
    'base tables not exposed by lab lease');
$check($leaseA->readDemoView('perf_f91_demo_account_read;DROP TABLE X')['rows']===[],
    'user-controlled SQL object name rejected');

$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantA]);
foreach(['A'=>$leaseA,'B'=>$leaseB] as $label=>$lease){
    foreach($views as $view){
        $check($lease->readDemoView($view)['rows']===$baseline[$label][$view],
            "$label mutable GUC does not change pooled session $view");
    }
}

$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$check($leaseA->readDemoView('perf_f106_credential_budget_demo_read')['status']
    ==='connection_identity_changed',
    'caller-owned repeatable-read snapshot rejected');
$a->rollBack();
$check($leaseA->readDemoView('perf_f106_credential_budget_demo_read')['rows']
    ===$baseline['A']['perf_f106_credential_budget_demo_read'],
    'same PostgreSQL backend remains safe after caller transaction rollback');

$revoke->execute([$loginA]);
foreach($views as $view){
    $r=$leaseA->readDemoView($view);
    $check($r['status']==='connection_identity_changed'
        && $r['rows']===[],
        'revoked A existing lease returns no rows for '.$view);
    $check($leaseB->readDemoView($view)['rows']===$baseline['B'][$view],
        'B remains visible during A revocation for '.$view);
}
$check(SqlTenantConnectionLease::capture($a,$loginA,$tenantA)===null,
    'revoked A cannot acquire new lease');
$restore->execute([$tenantA,$loginA]);
$check($leaseA->readDemoView('perf_f91_demo_account_read')['rows']
    ===$baseline['A']['perf_f91_demo_account_read'],
    'restored SQL binding works for original owner without changing login');

$restore->execute([$tenantB,$loginA]);
$check($leaseA->readDemoView('perf_f91_demo_account_read')['status']
    ==='connection_identity_changed',
    'old A lease invalid after privileged SQL tenant reassignment');
$check(SqlTenantConnectionLease::capture($a,$loginA,$tenantA)===null,
    'trusted expected A tenant cannot capture reassigned A login');
// NOTE: a NEW lease claiming B for A login could pass SQL checks.
// Therefore the expected tenant MUST come from independently verified Portal
// IAM/S2S, not caller input. We never claim this lab implements Portal IAM.
$restore->execute([$tenantA,$loginA]);
$check($leaseA->readDemoView('perf_f91_demo_account_read')['rows']
    ===$baseline['A']['perf_f91_demo_account_read'],
    'original mapping restored after reassignment rehearsal');

$hooked=new F120InterleavedPdo($dsn,$loginA,'ci-f91-only-a',$options);
$hookLease=SqlTenantConnectionLease::capture($hooked,$loginA,$tenantA);
$check($hookLease instanceof SqlTenantConnectionLease,
    'independent physical PostgreSQL session lease created');
$hooked->duringRead=static function()use($revoke,$loginA):void{
    $revoke->execute([$loginA]);
};
$mid=$hookLease->readDemoView('perf_f106_credential_budget_demo_read');
$check($mid['status']==='connection_identity_changed_during_read'
    && $mid['rows']===[],
    'SQL revocation committed after SELECT but before result emission fails closed');
$check($leaseA->readDemoView('perf_f91_demo_account_read')['rows']===[],
    'previously acquired pooled A lease also denied after concurrent revocation');
$restore->execute([$tenantA,$loginA]);
$check($hookLease->readDemoView('perf_f106_credential_budget_demo_read')['rows']
    ===$baseline['A']['perf_f106_credential_budget_demo_read'],
    'once binding restored, safe backend can view only its A budget');

putenv('PERF_F120_SQL_LEASE_LAB=0');
$check(SqlTenantConnectionLease::capture($a,$loginA,$tenantA)===null,
    'no new lease without explicit CI flag');
$check($leaseA->readDemoView('perf_f91_demo_account_read')['status']
    ==='connection_identity_changed',
    'preexisting lease becomes unusable if CI mode disabled');
putenv('PERF_F120_SQL_LEASE_LAB=1');
putenv('APP_ENV=production');
$check(SqlTenantConnectionLease::capture($a,$loginA,$tenantA)===null,
    'cannot create lease in production mode');
$check($leaseA->readDemoView('perf_f91_demo_account_read')['rows']===[],
    'cannot use lease in production mode');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    && $risk['release_allowed']===false
    && $risk['direct_sql_client_access_allowed']===false,
    'untrusted Portal IAM remains a NO-GO even with SQL lease');

echo "PASS F120 $n assertions: distinct SQL backend leases, pooled-session identity, mid-query revocation and NO-GO\n";
