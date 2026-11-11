<?php
declare(strict_types=1);

/**
 * F189 real PostgreSQL16 tenant/service state boundary.
 * The "eligible" state is FICTIONAL: never an actual Portal subscription.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;
use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F189_SERVICE_STATUS_LAB',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F189 CI lab flag missing: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F189 only runs in disposable PG16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $pw):PDO=>new PDO($dsn,$role,$pw,$opt);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F189 FAILED: '.$why);
};
$fence=new CredentialBoundDemoServiceStatusFence();
$read=static fn(PDO $db):array=>$fence->read($db);
$deny=static function(PDO $db,string $why)use($read,$check):void{
 $data=$read($db);
 $check($data['status']!=='credential_scoped_demo_workbench_only'
  &&$data['accounts']===[]&&$data['campaigns']===[]&&$data['reviews']===[]
  &&$data['counts']===[],
  $why);
 $check($data['authorization_eligible']===false
  &&$data['portal_identity_authenticated']===false
  &&$data['dispatch_enabled']===false
  &&$data['billing_enabled']===false,
  'denial remains entirely non-operational');
};
$deny($a,'original fixture A inactive/pending must not be served');
$deny($b,'original fixture B inactive/pending must not be served');

$rawA=$a->query('SELECT * FROM public.perf_f189_login_service_status()')->fetchAll(PDO::FETCH_ASSOC);
$rawB=$b->query('SELECT * FROM public.perf_f189_login_service_status()')->fetchAll(PDO::FETCH_ASSOC);
$check(count($rawA)===1&&count($rawB)===1
 &&$rawA[0]['tenant_id']!==$rawB[0]['tenant_id'],
 'login-bound SQL function returns only its own tenant without caller parameter');
$check($rawA[0]['tenant_status']==='inactive'
 &&$rawB[0]['tenant_status']==='inactive'
 &&$rawB[0]['link_status']==='pending'
 &&$rawB[0]['eligibility_state']===null,
 'original demo data explicitly lacks active service proof');
$check($rawA[0]['link_revision']==='demo-revision-f88'
 &&$rawB[0]['link_revision']==='demo-revision-f88',
 'initial Portal link revision is historical CI fixture only');
$check($a->query("SELECT session_user")->fetchColumn()==='perf_f91_reader_a'
 &&$b->query("SELECT session_user")->fetchColumn()==='perf_f91_reader_b',
 'SQL logins cannot be supplied as a query parameter');
$check($admin->query(
 "SELECT has_function_privilege('perf_f91_reader_a',
 'public.perf_f189_login_service_status()','EXECUTE')"
)->fetchColumn()===true,
 'restricted demo A role has EXECUTE on fixed service-read function');
$check($admin->query(
 "SELECT has_function_privilege('perf_f91_reader_b',
 'public.perf_f189_login_service_status()','EXECUTE')"
)->fetchColumn()===true,
 'restricted demo B role has EXECUTE on fixed service-read function');
$check($admin->query(
 "SELECT has_table_privilege('perf_f91_reader_b','service_eligibility_snapshots','SELECT')"
)->fetchColumn()===false,
 'caller SQL role has no direct read privileges on eligibility table');
$check($admin->query(
 "SELECT has_table_privilege('perf_f91_reader_b','portal_service_links','UPDATE')"
)->fetchColumn()===false,
 'caller role cannot mutate Portal service status via base table');
$check((int)$admin->query(
 "SELECT count(*) FROM public.service_eligibility_snapshots
  WHERE tenant_id='99999999-9999-4999-8999-999999999999'::uuid"
)->fetchColumn()===0,
 'synthetic B has no eligibility snapshot');

$tenant='99999999-9999-4999-8999-999999999999';
$admin->exec("UPDATE public.performance_tenants SET status='active'
 WHERE id='{$tenant}'::uuid");
$deny($b,'activating tenant alone cannot bypass pending Portal service link');
$admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$deny($b,'an active local link without snapshot cannot provide service access');

$insert=$admin->prepare(
 "INSERT INTO public.service_eligibility_snapshots
  (id,tenant_id,portal_service_ref,state,source,source_revision,
   observed_at,created_at,updated_at)
  VALUES(CAST(? AS uuid),CAST(? AS uuid),'svc-demo-f88',?,?,?,?,clock_timestamp(),clock_timestamp())"
);
// Parameters in this insert are id, tenant, state, source, revision and observed_at.
// The explicit datetime is provided by the caller only for the CI freshness test.
$revision='demo-revision-f88';
$put=static function(string $id,string $state,string $source,
 string $rev,string $date)use($insert,$tenant):void{
 $insert->execute([$id,$tenant,$state,$source,$rev,$date]);
};
$put('ac890000-0000-4000-8000-000000000001',
 'eligible','unverified_client_claim',$revision,date('Y-m-d H:i:sP'));
$deny($b,'local snapshot with untrusted source cannot authorize even a demo');
$admin->exec("UPDATE public.service_eligibility_snapshots SET source='synthetic_ci_only'
 WHERE id='ac890000-0000-4000-8000-000000000001'::uuid");
$ok=$read($b);
$check($ok['status']==='credential_scoped_demo_workbench_only'
 &&$ok['counts']['account_candidates']===1
 &&$ok['counts']['linked_campaigns']===1
 &&$ok['counts']['visible_reviews']===1,
 'complete synthetic-only B status permits F100 preview with real SQL rows');
$check($ok['campaigns'][0]['campaign_ref']==='cam-demo-f186-new'
 &&$ok['reviews'][0]['review_observation_state']==='pending_human_demo',
 'only new-generation campaign and pending review appear');
$check($ok['authorization_eligible']===false
 &&$ok['portal_identity_authenticated']===false
 &&$ok['ready_for_production']===false
 &&$ok['dispatch_enabled']===false
 &&$ok['billing_enabled']===false,
 'even locally eligible demo cannot claim real service, identity or dispatch');

$a->exec("SELECT set_config('app.performance_tenant_id','{$tenant}',false)");
$deny($a,'forged legacy GUC B cannot make inactive A see B eligible rows');
$check($read($b)===$ok,
 'A GUC spoof cannot change B authenticated SQL results');

$admin->exec("UPDATE public.portal_service_links SET source_revision='demo-revision-f189-next'
 WHERE tenant_id='{$tenant}'::uuid");
$deny($b,'revision mismatch invalidates previously eligible snapshot');
$admin->exec("UPDATE public.portal_service_links SET source_revision='demo-revision-f88'
 WHERE tenant_id='{$tenant}'::uuid");
$check($read($b)===$ok,'restoring same local revision is only a synthetic demo preflight');

$admin->exec("UPDATE public.service_eligibility_snapshots
 SET observed_at=clock_timestamp()-interval '12 minutes'
 WHERE id='ac890000-0000-4000-8000-000000000001'::uuid");
$deny($b,'stale 12-minute snapshot fails the five-minute demo TTL');
$admin->exec("UPDATE public.service_eligibility_snapshots
 SET observed_at=clock_timestamp()
 WHERE id='ac890000-0000-4000-8000-000000000001'::uuid");
$check($read($b)===$ok,'fresh local synthetic evidence restores only demo read');

$admin->exec("UPDATE public.performance_tenants
 SET revoked_at=clock_timestamp() WHERE id='{$tenant}'::uuid");
$deny($b,'tenant revoked_at immediately blocks the preview');
$admin->exec("UPDATE public.performance_tenants
 SET revoked_at=NULL,status='inactive' WHERE id='{$tenant}'::uuid");
$deny($b,'inactive tenant blocks preview even with fresh snapshot and active link');
$admin->exec("UPDATE public.performance_tenants
 SET status='active' WHERE id='{$tenant}'::uuid");

$put('ac890000-0000-4000-8000-000000000002',
 'revoked','synthetic_ci_only','demo-revision-f189-revoked',
 date('Y-m-d H:i:sP',time()+1));
$deny($b,'newer revocation snapshot outranks earlier eligible snapshot');
$check($read($a)['status']!=='credential_scoped_demo_workbench_only',
 'B positive fixture never makes inactive A eligible');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'F100 and service probes leave no transactions open');

echo "F189 SQL tenant+Portal service-status deny/TTL/revision/revoke: {$n} PASS / 0 FAIL — SYNTHETIC NO-GO\n";
