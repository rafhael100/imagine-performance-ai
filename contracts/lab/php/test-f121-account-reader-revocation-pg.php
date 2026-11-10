<?php
declare(strict_types=1);

/**
 * F121: the REAL Laravel account reader (F92) must refuse stale principal
 * snapshots and mid-query tenant revocation after F119 policy cutover.
 * Only synthetic PostgreSQL16 users, no advertiser accounts/OAuth.
 */
if(PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F121_ACCOUNT_REVOKE_LAB')!=='1'
    || getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F121 accepts disposable PG16 CI only');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoAccountReader;

final class F121InterleavedAccountPdo extends PDO
{
    public ?Closure $afterAccountQuery = null;

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        $statement = parent::query($query);
        if ($statement !== false
            && $this->afterAccountQuery !== null
            && str_contains($query, 'FROM public.perf_f91_demo_account_read')) {
            $hook = $this->afterAccountQuery;
            $this->afterAccountQuery = null;
            $hook();
        }
        return $statement;
    }
}

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$reader=new CredentialBoundDemoAccountReader();
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $ok,string $name)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F121 '.$name);
    $n++;
};
$denied=static function(array $r,string $expected,string $label)use($check):void {
    $check($r['schema_version']==='perf.portal.credential-bound-accounts.synthetic.v1',
        $label.' keeps schema');
    $check($r['status']===$expected,
        $label.' correct status (received '.($r['status']??'none').')');
    $check($r['simulation_only']===true,$label.' only synthetic');
    $check($r['credential_binding_confirmed']===false,
        $label.' denies binding');
    $check($r['accounts']===[],$label.' no account rows');
    foreach([
        'portal_identity_authenticated','provider_connected','authorization_eligible',
        'ready_for_production','dispatch_enabled','billing_enabled',
    ] as $field){
        $check($r[$field]===false,$label.' flag '.$field.' false');
    }
};

$baselineA=$reader->read($a);
$baselineB=$reader->read($b);
$check($baselineA['status']==='credential_scoped_demo_preview_only',
    'A baseline DEMO accounts valid');
$check($baselineB['status']==='credential_scoped_demo_preview_only',
    'B baseline DEMO accounts valid');
$check(count($baselineA['accounts'])===2,'A two demo accounts');
$check(count($baselineB['accounts'])===1,'B one demo account');
$check($baselineA['ready_for_production']===false
    &&$baselineA['dispatch_enabled']===false
    &&$baselineA['billing_enabled']===false,
    'no operational access in baseline A');
$check($baselineB['ready_for_production']===false,
    'no operational access in baseline B');

$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$inside=$reader->read($a);
$denied($inside,'invalid_connection_state','caller REPEATABLE READ transaction');
$check($a->inTransaction(),'F121 does not close caller transaction itself');
$a->rollBack();
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'normal view resumes after transaction owner rolls back');

// The F100 workbench is intentionally composed under READ COMMITTED,
// READ ONLY. The new guard must preserve that legitimate readonly usage.
$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'F100 read committed READ ONLY composition remains supported');
$check($a->inTransaction(),
    'reader never commits workbench-owned readonly transaction');
$a->rollBack();

// READ COMMITTED with write permissions is not an approved consumer path.
$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE');
$denied($reader->read($a),'invalid_connection_state',
    'caller READ COMMITTED write-capable transaction');
$a->rollBack();
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'safe account read resumes after write-capable transaction ended');

$update=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$restore=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);
$hook=new F121InterleavedAccountPdo($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$hook->afterAccountQuery=static function()use($update):void{
    $update->execute(['perf_f91_reader_a']);
};
$mid=$reader->read($hook);
$denied($mid,'credential_changed_during_read',
    'revocation committed after SQL account SELECT');
$denied($reader->read($a),'database_login_revoked',
    'existing A SQL session sees committed revocation');
$check($reader->read($b)['accounts']===$baselineB['accounts'],
    'B unaffected by revocation A');
$restore->execute([$tenantA,'perf_f91_reader_a']);
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'A account reader recovers after explicit restoration');

$hook2=new F121InterleavedAccountPdo($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$hook2->afterAccountQuery=static function()use($restore,$tenantB):void{
    $restore->execute([$tenantB,'perf_f91_reader_a']);
};
$rebinding=$reader->read($hook2);
$denied($rebinding,'credential_changed_during_read',
    'tenant SQL reassigned after SELECT before response');
$restore->execute([$tenantA,'perf_f91_reader_a']);
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'A restored with original accounts only');
$check($reader->read($b)['accounts']===$baselineB['accounts'],
    'B accounts remain unchanged after A rebind experiment');

$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantA]);
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'legacy GUC B cannot influence A account read after F119 cutover');
$check($reader->read($b)['accounts']===$baselineB['accounts'],
    'legacy GUC A cannot influence B account read after F119 cutover');

putenv('PERF_F92_CREDENTIAL_READER_LAB=0');
$denied($reader->read($a),'lab_disabled','F92 lab feature disabled');
putenv('PERF_F92_CREDENTIAL_READER_LAB=1');
putenv('APP_ENV=production');
$denied($reader->read($a),'lab_disabled',
    'lab reader called in production mode');
putenv('APP_ENV=testing');
$check($reader->read($a)['accounts']===$baselineA['accounts'],
    'restored CI flags do not lose safe SQL account scope');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'independent Portal IAM, SQL cutover and AppSec review still NO-GO');

echo "PASS F121 $n assertions: Laravel DEMO account reader rejects caller snapshot, concurrent revoke and rebinding\n";
