<?php
declare(strict_types=1);

/**
 * F122: real PostgreSQL16 revocation races in the Laravel F94 campaign
 * reader and F98 review reader after F119 GUC policy removal.
 */
if(PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F122_PROJECTION_REVOKE_LAB')!=='1'
    || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    || getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F122 only works in ephemeral PostgreSQL16 CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoCampaignReader;
use App\Performance\ReadSide\CredentialBoundDemoReviewReader;

final class F122InterleavedPdo extends PDO
{
    public ?Closure $afterProjection = null;
    public string $viewFragment = '';

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        $statement = parent::query($query);
        if($statement !== false
            && $this->afterProjection !== null
            && str_contains($query,$this->viewFragment)) {
            $hook=$this->afterProjection;
            $this->afterProjection=null;
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
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$setBinding=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);
$revoke=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$campaign=new CredentialBoundDemoCampaignReader();
$review=new CredentialBoundDemoReviewReader();
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void {
    if(!$ok)throw new RuntimeException('FAIL F122 '.$label);
    $n++;
};
$assertDenied=static function(array $r,string $status,string $key,string $label)use($check):void{
    $check($r['status']===$status,$label.' denies with reason '.$status);
    $check($r['credential_binding_confirmed']===false,
        $label.' denies successful SQL binding');
    $check($r[$key]===[],$label.' emits zero projection rows');
    $check($r['simulation_only']===true,$label.' remains synthetic');
    foreach([
        'portal_identity_authenticated','provider_connected','authorization_eligible',
        'ready_for_production','dispatch_enabled','billing_enabled'
    ] as $flag){
        $check($r[$flag]===false,$label.' '.$flag.' false');
    }
};
$fixtures=[
    'campaign'=>[
        'reader'=>$campaign,
        'field'=>'campaigns',
        'fragment'=>'FROM public.perf_f93_demo_campaign_account_read',
        'status'=>'credential_scoped_demo_preview_only',
    ],
    'review'=>[
        'reader'=>$review,
        'field'=>'reviews',
        'fragment'=>'FROM public.perf_f97_demo_review_read',
        'status'=>'credential_scoped_demo_review_preview_only',
    ],
];
foreach($fixtures as $name=>$f){
    $one=$f['reader']->read($a);
    $two=$f['reader']->read($b);
    $check($one['status']===$f['status'],"$name A baseline is valid");
    $check($two['status']===$f['status'],"$name B baseline is valid");
    $check(count($one[$f['field']])===1,"$name A one visible DEMO item");
    $check(count($two[$f['field']])===1,"$name B one visible DEMO item");
    $check($one[$f['field']]!==$two[$f['field']],
        "$name A/B projections differ");
    foreach([$one,$two] as $dto){
        $check($dto['ready_for_production']===false
            &&$dto['dispatch_enabled']===false
            &&$dto['billing_enabled']===false,
            "$name cannot authorize media or billing");
        if($name==='review'){
            $check($dto['real_human_approval_verified']===false,
                'synthetic journal not authenticated human approval');
        }
    }

    $a->beginTransaction();
    $a->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    $assertDenied(
        $f['reader']->read($a),'invalid_connection_state',$f['field'],
        "$name caller REPEATABLE READ"
    );
    $check($a->inTransaction(),"$name does not take ownership of caller transaction");
    $a->rollBack();

    $a->beginTransaction();
    $a->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE');
    $assertDenied(
        $f['reader']->read($a),'invalid_connection_state',$f['field'],
        "$name caller write-capable transaction"
    );
    $a->rollBack();

    $a->beginTransaction();
    $a->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
    $check($f['reader']->read($a)[$f['field']]===$one[$f['field']],
        "$name compatible with F100 read-only READ COMMITTED workbench");
    $check($a->inTransaction(),"$name workbench retains control of transaction");
    $a->rollBack();

    foreach(['revoke','rebind'] as $attack){
        $hooked=new F122InterleavedPdo(
            $dsn,'perf_f91_reader_a','ci-f91-only-a',$opt
        );
        $hooked->viewFragment=$f['fragment'];
        $hooked->afterProjection=static function()use(
            $attack,$setBinding,$revoke,$tenantB
        ):void{
            if($attack==='revoke'){
                $revoke->execute(['perf_f91_reader_a']);
            }else{
                $setBinding->execute([$tenantB,'perf_f91_reader_a']);
            }
        };
        $result=$f['reader']->read($hooked);
        $assertDenied($result,'credential_changed_during_read',$f['field'],
            "$name $attack committed after view SELECT");
        $check($f['reader']->read($b)[$f['field']]===$two[$f['field']],
            "$name B remains intact after A $attack");
        $setBinding->execute([$tenantA,'perf_f91_reader_a']);
        $check($f['reader']->read($a)[$f['field']]===$one[$f['field']],
            "$name A recovers its original projection after $attack");
    }

    $a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
        ->execute([$tenantB]);
    $b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
        ->execute([$tenantA]);
    $check($f['reader']->read($a)[$f['field']]===$one[$f['field']],
        "$name A GUC spoof cannot read B projection");
    $check($f['reader']->read($b)[$f['field']]===$two[$f['field']],
        "$name B GUC spoof cannot read A projection");

    $check($f['reader']->read($a)['credential_binding_confirmed']===true,
        "$name original verified SQL login remains intact after all attacks");
}
putenv('APP_ENV=production');
$assertDenied($campaign->read($a),'lab_disabled','campaigns',
    'campaign reader production disabled');
$assertDenied($review->read($a),'lab_disabled','reviews',
    'review reader production disabled');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'Portal IAM/DBA AppSec and Ads approval still NO-GO');
echo "PASS F122 $n assertions: real campaign and review readers reject stale SQL snapshots and mid-read tenant changes\n";
