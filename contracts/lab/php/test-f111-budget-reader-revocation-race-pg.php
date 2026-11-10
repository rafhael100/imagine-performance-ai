<?php
declare(strict_types=1);

/**
 * F1-111 security regression: a revocation/rebinding committed after SQL
 * has returned budget data must fail closed before any DTO leaves the reader.
 * PostgreSQL16 is ephemeral, all identities and plans are synthetic.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F111_REVOKE_DURING_READ_LAB') !== '1'
    || getenv('PERF_F107_BUDGET_READER_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('F111 needs isolated disposable PG16 CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoBudgetReader;

final class F111RevokeOnBudgetSelectPdo extends PDO
{
    public ?Closure $afterBudgetQuery = null;

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        // F107 only invokes query($sql) without fetch-mode variadics.
        $statement = parent::query($query);
        if ($statement !== false
            && $this->afterBudgetQuery !== null
            && str_contains($query,'FROM public.perf_f106_credential_budget_demo_read')) {
            $hook = $this->afterBudgetQuery;
            $this->afterBudgetQuery = null;
            // This independent DB admin commits a revocation AFTER the
            // SELECT has executed but BEFORE the F107 reader returns.
            $hook();
        }
        return $statement;
    }
}

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$reader=new CredentialBoundDemoBudgetReader();
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $ok,string $label) use(&$n):void {
    if(!$ok) throw new RuntimeException('FAIL F111 '.$label);
    ++$n;
};
$deny=static function(array $dto,string $label) use($check):void {
    $check($dto['simulation_only']===true,"$label stays synthetic");
    $check($dto['credential_binding_confirmed']===false,"$label loses verified binding");
    $check($dto['scenarios']===[],"$label emits no old rows");
    foreach(['portal_identity_authenticated','advertiser_approval_verified',
      'provider_connected','media_execution_allowed','billing_allowed',
      'ready_for_production'] as $flag) {
        $check($dto[$flag]===false,"$label denies $flag");
    }
};

$normalA=$reader->read($a);
$normalB=$reader->read($b);
$check($normalA['status']==='credential_scoped_demo_budget_preview_only',
    'baseline A has authenticated budget preview');
$check($normalB['status']==='credential_scoped_demo_budget_preview_only',
    'baseline B has authenticated budget preview');
$check(count($normalA['scenarios'])===1 && count($normalB['scenarios'])===1,
    'two isolated budget plans still accessible');
$check($normalA['scenarios'][0]['scenario_ref']==='scenario-demo-f106-a',
    'A baseline scenario');
$check($normalB['scenarios'][0]['scenario_ref']==='scenario-demo-f106-b',
    'B baseline scenario');

$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$underCallerTx=$reader->read($a);
$check($underCallerTx['status']==='invalid_connection_state',
    'reject caller-owned repeatable-read snapshot');
$deny($underCallerTx,'caller snapshot');
$check($a->inTransaction(),'caller transaction ownership unchanged');
$a->rollBack();
$check(!$a->inTransaction(),'test closes caller transaction');

$update=$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$restore=$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);
$hooked=new F111RevokeOnBudgetSelectPdo($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$hooked->afterBudgetQuery=static function()use($update):void {
    $update->execute(['perf_f91_reader_a']);
};
$revokedMidRead=$reader->read($hooked);
$check($revokedMidRead['status']==='credential_changed_during_read',
    'concurrent revocation after SQL SELECT rejects entire DTO');
$deny($revokedMidRead,'revoked mid-read');
$check($reader->read($a)['status']==='database_login_revoked',
    'normal A also sees revocation immediately after commit');
$check($reader->read($b)['scenarios']===$normalB['scenarios'],
    'independent B unaffected by A revocation');
$restore->execute([$tenantA,'perf_f91_reader_a']);
$check($reader->read($a)['scenarios']===$normalA['scenarios'],
    'restored A has only own scenario');

$hooked2=new F111RevokeOnBudgetSelectPdo($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$hooked2->afterBudgetQuery=static function()use($restore,$tenantB):void {
    $restore->execute([$tenantB,'perf_f91_reader_a']);
};
$reboundMidRead=$reader->read($hooked2);
$check($reboundMidRead['status']==='credential_changed_during_read',
    'concurrent tenant rebinding after SQL SELECT rejects entire DTO');
$deny($reboundMidRead,'tenant rebound mid-read');
$restore->execute([$tenantA,'perf_f91_reader_a']);
$check($reader->read($a)['scenarios']===$normalA['scenarios'],
    'test restores A original binding with no cross-tenant access');
$check($reader->read($b)['scenarios']===$normalB['scenarios'],
    'B remains unchanged after A rebind rehearsal');

$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$check($reader->read($a)['scenarios']===$normalA['scenarios'],
    'legacy caller-controlled tenant GUC cannot change SQL-bound preview');

echo "PASS F111 $n assertions: caller snapshot denied, mid-read revoke and tenant rebind fail closed\n";
