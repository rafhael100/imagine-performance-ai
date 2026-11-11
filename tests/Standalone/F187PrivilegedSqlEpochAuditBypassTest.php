<?php
declare(strict_types=1);

/**
 * F187 EXPECTED NEGATIVE: a PostgreSQL superuser controls local triggers,
 * management epochs and the in-DB audit. None of F184-F186 is WORM or a
 * remotely anchored authorization authority. CI-only on synthetic B data.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F187_LOCAL_DBA_BYPASS_LAB')!=='1'
 ||getenv('PERF_F186_EPOCH_WRITE_AUDIT_LAB')!=='1'
 ||getenv('PERF_F185_MANAGEMENT_EPOCH_LAB')!=='1'
 ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F187 destructive proof restricted to disposable PG16 CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $password):PDO=>new PDO($dsn,$role,$password,$options);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $ok,string $reason)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F187 FAILED: '.$reason);
};
$denied=static function(callable $action):bool{
 try{$action();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$campaignId=$admin->query("SELECT id FROM public.performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-b'")->fetchColumn();
$check(is_string($campaignId)&&strlen($campaignId)===36,
 'B original historic campaign exists');
$historicalRequest=$admin->query(
 "SELECT i.request_ref FROM public.performance_campaign_intents i
  WHERE i.campaign_id='{$campaignId}'::uuid LIMIT 1"
)->fetchColumn();
$check(is_string($historicalRequest)&&preg_match('/^req-demo-/',$historicalRequest)===1,
 'historic B review remains stored although it is tombstoned');
$before=$reader->read($b);
$otherTenant=$reader->read($a);
$check($before['status']==='credential_scoped_demo_workbench_only'
 &&$before['counts']['linked_campaigns']===1
 &&$before['counts']['visible_reviews']===1,
 'F186 left B with only the new first-generation campaign and pending review');
$check(!in_array('cam-demo-f89-b',array_column($before['campaigns'],'campaign_ref'),true)
 &&!in_array($historicalRequest,array_column($before['reviews'],'request_ref'),true),
 'historic B campaign/review correctly remain hidden in the hardened scope');
$epoch=$admin->query("SELECT management_epoch FROM public.performance_managed_campaigns
 WHERE id='{$campaignId}'::uuid")->fetchColumn();
$check((int)$epoch===2,
 'original B campaign currently in generation 2 after retirement/reenable');
$events=$admin->query(
 "SELECT tenant_id,campaign_id,previous_epoch,current_epoch,
         previous_managed,current_managed
  FROM public.performance_f186_management_transition_audit
  WHERE campaign_id='{$campaignId}'::uuid ORDER BY event_id"
)->fetchAll(PDO::FETCH_ASSOC);
$check(count($events)===2,'two local B audit events exist before hostile SQL changes');
$check($denied(static fn()=>$b->exec(
 'ALTER TABLE public.performance_managed_campaigns DISABLE TRIGGER USER'
)), 'ordinary tenant reader cannot disable SQL campaign triggers');
$check($denied(static fn()=>$a->exec(
 'ALTER TABLE public.performance_f186_management_transition_audit DISABLE TRIGGER USER'
)), 'ordinary tenant reader cannot disable SQL audit triggers');
$check($denied(static fn()=>$admin->exec(
 "UPDATE public.performance_managed_campaigns SET management_epoch=1
  WHERE id='{$campaignId}'::uuid"
)), 'normal SQL UPDATE is blocked by F185 trigger, even for admin session');
$check($denied(static fn()=>$admin->exec(
 'DELETE FROM public.performance_f186_management_transition_audit'
)), 'normal SQL DELETE is blocked by F186 audit trigger');

// Deliberately disable BOTH local guards as PostgreSQL superuser.
// This simulates a compromised DB administrator/restore domain only.
// No provider data, live user or service is present in the runner.
$admin->exec('ALTER TABLE public.performance_managed_campaigns DISABLE TRIGGER USER');
$admin->exec('ALTER TABLE public.performance_f186_management_transition_audit DISABLE TRIGGER USER');
$admin->exec("UPDATE public.performance_managed_campaigns SET management_epoch=1
 WHERE id='{$campaignId}'::uuid");
$admin->exec("DELETE FROM public.performance_f186_management_transition_audit
 WHERE campaign_id='{$campaignId}'::uuid");
$admin->exec('ALTER TABLE public.performance_f186_management_transition_audit ENABLE TRIGGER USER');
$admin->exec('ALTER TABLE public.performance_managed_campaigns ENABLE TRIGGER USER');

$check((int)$admin->query("SELECT management_epoch
 FROM public.performance_managed_campaigns
 WHERE id='{$campaignId}'::uuid")->fetchColumn()===1,
 'EXPECTED BYPASS: privileged DDL reset campaign epoch to historic generation');
$check((int)$admin->query(
 "SELECT count(*) FROM public.performance_f186_management_transition_audit
  WHERE campaign_id='{$campaignId}'::uuid"
)->fetchColumn()===0,
 'EXPECTED BYPASS: privileged DDL erased all evidence of B retirement');
$check((int)$b->query(
 "SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn()===1,
 'EXPECTED BYPASS: F93 now exposes old account link after epoch reset');
$check((int)$b->query(
 "SELECT count(*) FROM public.perf_f97_demo_review_read
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn()===1,
 'EXPECTED BYPASS: F97 now exposes original reviewed intent after epoch reset');
$compromised=$reader->read($b);
$check($compromised['status']==='credential_scoped_demo_workbench_only'
 &&$compromised['counts']['linked_campaigns']===2
 &&$compromised['counts']['visible_reviews']===2,
 'EXPECTED BYPASS: F100 composes historic approval alongside fresh pending review');
$check(in_array('cam-demo-f89-b',array_column($compromised['campaigns'],'campaign_ref'),true)
 &&in_array($historicalRequest,array_column($compromised['reviews'],'request_ref'),true),
 'EXPECTED BYPASS: exact historic B identifiers reappear after privileged rollback');
$check($reader->read($a)===$otherTenant,
 'hostile B SQL change does not spill across into tenant A');
$check($compromised['portal_identity_authenticated']===false
 &&$compromised['dispatch_enabled']===false
 &&$compromised['billing_enabled']===false
 &&$compromised['ready_for_production']===false,
 'even compromised local read-model never grants real Ads or billing action');

// Restore the guard and epoch for any later CI tests, but the same DBA can
// forge matching local audit entries. They are NOT independent evidence.
$admin->exec('ALTER TABLE public.performance_managed_campaigns DISABLE TRIGGER USER');
$admin->exec("UPDATE public.performance_managed_campaigns SET management_epoch=2
 WHERE id='{$campaignId}'::uuid");
$admin->exec('ALTER TABLE public.performance_managed_campaigns ENABLE TRIGGER USER');
$check(!in_array($historicalRequest,array_column(
 $reader->read($b)['reviews'],'request_ref'
),true), 'restoring epoch to 2 hides old B approval once again');
$insert=$admin->prepare(
 'INSERT INTO public.performance_f186_management_transition_audit
  (tenant_id,campaign_id,previous_epoch,current_epoch,previous_managed,current_managed)
  VALUES(CAST(? AS uuid),CAST(? AS uuid),CAST(? AS bigint),CAST(? AS bigint),
         CAST(? AS boolean),CAST(? AS boolean))'
);
foreach($events as $event){
 $insert->execute([
  $event['tenant_id'],$event['campaign_id'],
  $event['previous_epoch'],$event['current_epoch'],
  $event['previous_managed'],$event['current_managed']
 ]);
}
$check((int)$admin->query(
 "SELECT count(*) FROM public.performance_f186_management_transition_audit
  WHERE campaign_id='{$campaignId}'::uuid"
)->fetchColumn()===2,
 'EXPECTED GAP: privileged SQL can forge a locally consistent-looking audit');
$check($reader->read($b)===$before,
 'restored synthetic F100 B rows equal pre-attack output, without proving integrity');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'all SQL readers closed after negative adversarial DBA demonstration');
echo "F187 privileged local epoch/audit bypass: {$n} PASS / 0 FAIL — EXPECTED VULNERABILITY / NO-GO\n";
