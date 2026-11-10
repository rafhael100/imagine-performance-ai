<?php
declare(strict_types=1);

/** F1-100: test the Laravel-ready composite against real PostgreSQL16 CI. */
if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
    || getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    || getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F100 must run in disposable PostgreSQL CI');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$f28=new PDO($dsn,'perf_f28_app_reader','ci-f28-app-read-only',$opt);
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void {
    if(!$ok)throw new RuntimeException('FAIL F100 '.$msg);
    ++$n;
};
$assertNoGo=static function(array $p,string $prefix) use($check):void {
    foreach(['portal_identity_authenticated','real_human_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'] as $flag) {
        $check(($p[$flag]??null)===false,"$prefix: $flag always false");
    }
    $check(($p['synthetic_only']??null)===true,"$prefix: never real tenant data");
    $check(($p['read_only']??null)===true,"$prefix: never mutate");
};
$one=$reader->read($a);
$two=$reader->read($b);
$check($one['status']==='credential_scoped_demo_workbench_only','A complete unified preview');
$check($two['status']==='credential_scoped_demo_workbench_only','B complete unified preview');
$check($one['credential_binding_confirmed']===true,'A bound to SQL session login');
$check($two['credential_binding_confirmed']===true,'B bound to SQL session login');
$check($one['schema_version']==='perf.portal.credential-bound-workbench.synthetic.v1',
    'versioned composition schema');
$check($one['counts']===[
    'account_candidates'=>2,
    'linked_campaigns'=>1,
    'visible_reviews'=>1,
    'review_pending'=>0,
    'review_internal_no_execution'=>1,
    'review_rejected'=>0,
], 'A atomic account/campaign/approval counts');
$check($two['counts']===[
    'account_candidates'=>1,
    'linked_campaigns'=>1,
    'visible_reviews'=>1,
    'review_pending'=>0,
    'review_internal_no_execution'=>0,
    'review_rejected'=>1,
], 'B separate account/campaign/rejection counts');
$check($one['campaigns'][0]['campaign_ref']==='cam-demo-f89-a','A genuine SQL link');
$check($two['campaigns'][0]['campaign_ref']==='cam-demo-f89-b','B genuine SQL link');
$check($one['reviews'][0]['review_observation_state']==='approved_demo_no_execution',
    'A internal DEMO review is NOT Ads authorization');
$check($two['reviews'][0]['review_observation_state']==='rejected_demo',
    'B isolated rejection');
$check($one['campaigns'][0]['account_ref']==='demo_meta_a',
    'A campaign references actual scoped account');
$check($one['reviews'][0]['campaign_ref']===$one['campaigns'][0]['campaign_ref'],
    'A review joins same credential-scoped campaign');
$check($two['reviews'][0]['campaign_ref']===$two['campaigns'][0]['campaign_ref'],
    'B review joins same credential-scoped campaign');
$assertNoGo($one,'A');
$assertNoGo($two,'B');
$check(!$a->inTransaction() && !$b->inTransaction(),
    'read-only transactions always closed before returning');

$identityA=$a->query('SELECT session_user')->fetchColumn();
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['88888888-8888-4888-8888-888888888888']);
$check($a->query('SELECT session_user')->fetchColumn()===$identityA,
    'forged session tenant cannot change authenticated login');
$check($reader->read($a)['accounts']===$one['accounts'],'GUC spoof A account set unchanged');
$check($reader->read($a)['campaigns']===$one['campaigns'],'GUC spoof A campaign unchanged');
$check($reader->read($a)['reviews']===$one['reviews'],'GUC spoof A review unchanged');
$check($reader->read($b)['accounts']===$two['accounts'],'GUC spoof B account set unchanged');
$check($reader->read($b)['campaigns']===$two['campaigns'],'GUC spoof B campaigns unchanged');
$check($reader->read($b)['reviews']===$two['reviews'],'GUC spoof B reviews unchanged');

$a->beginTransaction();
$a->exec('SET TRANSACTION READ ONLY');
$ongoing=$reader->read($a);
$check($ongoing['status']==='invalid_connection_state'
    && $ongoing['accounts']===[],'reject caller-managed transaction');
$assertNoGo($ongoing,'nested transaction denied');
$check($a->inTransaction(),'caller transaction remains owned by caller');
$a->rollBack();

$old=$reader->read($f28);
$check($old['status']==='untrusted_database_login'
    && $old['accounts']===[],'old F28 role cannot impersonate protected reader');
$assertNoGo($old,'legacy role denied');
$rootReader=$reader->read($admin);
$check($rootReader['status']==='untrusted_database_login'
    && $rootReader['reviews']===[],'admin not accepted as customer principal');
$assertNoGo($rootReader,'admin denied');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$revoked=$reader->read($a);
$check($revoked['status']==='database_login_revoked'
    && $revoked['accounts']===[] && $revoked['campaigns']===[] && $revoked['reviews']===[],
    'revocation clears all component outputs at once');
$assertNoGo($revoked,'revocation');
$check($reader->read($b)['counts']===$two['counts'],
    'B unaffected by revocation of A');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings '
    .'SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($reader->read($a)['counts']===$one['counts'],
    'admin restoration cannot broaden A scope');

putenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB=0');
$disabled=$reader->read($a);
$check($disabled['status']==='lab_disabled' && $disabled['accounts']===[],
    'no invocation when workbench flag disabled');
$assertNoGo($disabled,'flag disabled');
putenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB=1');
putenv('APP_ENV=production');
$prod=$reader->read($a);
$check($prod['status']==='lab_disabled' && $prod['campaigns']===[],
    'production mode rejects workbench');
$assertNoGo($prod,'production disabled');
putenv('APP_ENV=testing');

$json=json_encode($one,JSON_THROW_ON_ERROR);
foreach(['88888888-8888-4888-8888-888888888888','ci-f91-only-a',
    'actor-reviewer-demo','payload_sha256','access_token','refresh_token',
    'service_entitlement','billing_amount','auth_token'] as $secret) {
    $check(!str_contains($json,$secret),'no tenant UUID/SQL password/secrets/actor in preview');
}
$out=getenv('PERF_F100_JSON_OUTPUT_FILE');
if(is_string($out)&&$out!=='') {
    $check(str_starts_with($out,'/tmp/') && !is_link($out) && !file_exists($out),
        'CI output path must be a fresh /tmp file');
    $check(file_put_contents($out,$json."\n",LOCK_EX)!==false,
        'write only synthetic JSON for Portal cross-repo test');
}
echo "PASS F100 $n assertions: 3 login-bound PostgreSQL views → one Laravel DEMO workbench, fail closed\n";
