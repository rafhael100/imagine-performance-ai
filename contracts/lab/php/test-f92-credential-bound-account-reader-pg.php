<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
   ||getenv('PGHOST')!=='127.0.0.1'
   ||getenv('PGPORT')!=='5432'
   ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F92 only runs on disposable PostgreSQL CI');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';
$portal=getenv('PORTAL_CHECKOUT_ROOT');
if(!is_string($portal)||$portal===''||!is_dir($portal)) {
    throw new RuntimeException('F92 requires pinned Portal repository');
}
require_once $portal.'/apps/portal/app/Domain/Catalog/PerformanceChannel.php';
require_once $portal.'/apps/portal/app/Domain/PerformanceOnboarding/DemoAdAccount.php';

use App\Performance\ReadSide\CredentialBoundDemoAccountReader;
use App\Domain\Catalog\PerformanceChannel;
use App\Domain\PerformanceOnboarding\DemoAdAccount;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$reader=new CredentialBoundDemoAccountReader();
$n=0;
$check=static function(bool $value,string $message)use(&$n):void {
    if(!$value)throw new RuntimeException('FAIL F92 '.$message);
    ++$n;
};
$flags=[
    'portal_identity_authenticated','provider_connected','authorization_eligible',
    'ready_for_production','dispatch_enabled','billing_enabled'
];
$assertDenied=static function(array $dto,string $label)use($check,$flags):void{
    foreach($flags as $flag) {
        $check(($dto[$flag]??null)===false,"$label / $flag must deny");
    }
    $check(($dto['simulation_only']??null)===true,"$label must remain synthetic");
};
$one=$reader->read($a);
$two=$reader->read($b);
$check($one['status']==='credential_scoped_demo_preview_only','role A scoped read');
$check($two['status']==='credential_scoped_demo_preview_only','role B scoped read');
$check($one['credential_binding_confirmed']===true,'role A DB identity bound');
$check($two['credential_binding_confirmed']===true,'role B DB identity bound');
$check(count($one['accounts'])===2,'role A sees two DEMO account candidates');
$check(count($two['accounts'])===1,'role B sees one DEMO account candidate');
$check(array_column($one['accounts'],'portal_channel')===['google_ads','meta_ads'],
    'F82 Google and Meta enum aliases');
$check(array_column($two['accounts'],'portal_channel')===['meta_ads'],
    'role B Meta only');
foreach([['A',$one],['B',$two]] as [$label,$dto]) {
    $assertDenied($dto,$label);
    $check($dto['schema_version']==='perf.portal.credential-bound-accounts.synthetic.v1',
        "$label versioned DTO");
    foreach($dto['accounts'] as $row){
        $channel=PerformanceChannel::from($row['portal_channel']);
        $demo=new DemoAdAccount($channel,$row['account_ref']);
        $check($demo->channel===$channel,"$label real Portal channel");
        $check($demo->reference===$row['account_ref'],"$label real Portal DEMO ref");
        $check($row['connection_state']==='unverified_demo',"$label never connected");
    }
}

$tenantB='99999999-9999-4999-8999-999999999999';
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")->execute([$tenantB]);
$spoof=$reader->read($a);
$check($spoof['accounts']===$one['accounts'],
    'forged tenant GUC cannot change account preview or tenant');
$assertDenied($spoof,'GUC spoof');

$adminResult=$reader->read($admin);
$check($adminResult['status']==='untrusted_database_login',
    'admin connection refused by test-only read adapter');
$check($adminResult['accounts']===[],'no admin projection');
$assertDenied($adminResult,'admin');

$admin->exec("UPDATE performance_db_principal_tenant_bindings "
   ."SET revoked_at=clock_timestamp() WHERE db_login='perf_f91_reader_a'");
$revoked=$reader->read($a);
$check($revoked['status']==='database_login_revoked','A access revoked');
$check($revoked['accounts']===[],'revoked A cannot view accounts');
$check($revoked['credential_binding_confirmed']===false,'no bound credential for revoked A');
$assertDenied($revoked,'revoked A');
$check(count($reader->read($b)['accounts'])===1,'B unaffected by A revocation');

$admin->exec("UPDATE performance_db_principal_tenant_bindings "
   ."SET revoked_at=NULL WHERE db_login='perf_f91_reader_a'");
$restored=$reader->read($a);
$check($restored['accounts']===$one['accounts'],'administrative restoration scope limited to A');

putenv('PERF_F92_CREDENTIAL_READER_LAB=0');
$disabled=$reader->read($a);
$check($disabled['status']==='lab_disabled' && $disabled['accounts']===[],
    'lab flag disabled');
$assertDenied($disabled,'flag disabled');
putenv('PERF_F92_CREDENTIAL_READER_LAB=1');
putenv('APP_ENV=production');
$production=$reader->read($a);
$check($production['status']==='lab_disabled' && $production['accounts']===[],
    'production execution forbidden');
$assertDenied($production,'production');

$encoded=json_encode($one,JSON_THROW_ON_ERROR);
foreach(['88888888-8888-4888-8888-888888888888','ci-f91-only-a','refresh_token',
         'access_token','credit_card','campaign_budget'] as $hidden) {
    $check(!str_contains($encoded,$hidden),'do not publish tenant UUID, secrets or financial context');
}
echo "PASS F92 $n assertions: credential-bound PostgreSQL → Laravel adapter → real Portal DemoAdAccount\n";
