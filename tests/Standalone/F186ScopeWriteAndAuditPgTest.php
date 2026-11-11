<?php
declare(strict_types=1);

/** F186: enforce write-side generation fencing and append-only local audit. */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F186_EPOCH_WRITE_AUDIT_LAB')!=='1'
 ||getenv('PERF_F185_MANAGEMENT_EPOCH_LAB')!=='1'
 ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
 throw new RuntimeException('F186 SQL assertions restricted to disposable CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $login,string $pass):PDO=>new PDO($dsn,$login,$pass,$options);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $yes,string $why)use(&$n):void{
 ++$n;if(!$yes)throw new RuntimeException('F186 FAILED: '.$why);
};
$refused=static function(callable $statement,array $states=['42501']):bool{
 try{$statement();return false;}
 catch(PDOException $error){return in_array((string)$error->getCode(),$states,true);}
};
$campaignId=$admin->query(
 "SELECT id FROM public.performance_managed_campaigns
  WHERE campaign_ref='cam-demo-f89-b'"
)->fetchColumn();
$check(is_string($campaignId)&&strlen($campaignId)===36,
 'original A fixture campaign exists and has a stable UUID');
$preA=$reader->read($a);
$preB=$reader->read($b);
$check($preA['counts']['linked_campaigns']===0
 &&$preA['counts']['visible_reviews']===0
 &&$preB['counts']['linked_campaigns']===1
 &&$preB['counts']['visible_reviews']===1,
 'F185 has tombstoned A, while original B remains first-generation and visible');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_f186_management_transition_audit'
)->fetchColumn()===0,
 'audit starts empty after applying migration to disposable database');
$check($refused(static fn()=>$a->query(
 'SELECT * FROM public.performance_f186_management_transition_audit'
)), 'A role cannot SELECT global transition audit');
$check($refused(static fn()=>$b->query(
 'SELECT * FROM public.performance_f186_management_transition_audit'
)), 'B role cannot SELECT global transition audit');

// A brand-new campaign in epoch 1 can still use the existing synthetic
// account-link and intent constraints: F186 does not globally disable
// the legitimate F100 CI write-side.
$newCampaign='ac860000-0000-4000-8000-000000000001';
$newLink='ac860000-0000-4000-8000-000000000002';
$newIntent='ac860000-0000-4000-8000-000000000003';
$admin->exec("INSERT INTO public.performance_managed_campaigns (
 id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,
 lifecycle_state,managed,dispatch_state,created_at,updated_at,management_epoch
) SELECT '{$newCampaign}'::uuid,tenant_id,portal_service_ref,channel,
 'cam-demo-f186-new','Fresh synthetic generation 1', 'draft',true,'disabled',
 clock_timestamp(),clock_timestamp(),1
 FROM public.performance_managed_campaigns WHERE id='{$campaignId}'::uuid");
$check((int)$admin->query(
 "SELECT count(*) FROM public.performance_managed_campaigns
  WHERE id='{$newCampaign}'::uuid AND management_epoch=1 AND managed=true"
)->fetchColumn()===1,
 'new synthetic first-generation campaign may be registered independently');
$admin->exec("INSERT INTO public.performance_campaign_account_links (
 id,tenant_id,portal_service_ref,channel,campaign_id,account_candidate_id,
 link_state,source,dispatch_allowed,created_at,updated_at,management_epoch
) SELECT '{$newLink}'::uuid,tenant_id,portal_service_ref,channel,
 '{$newCampaign}'::uuid,account_candidate_id,link_state,source,
 false,clock_timestamp(),clock_timestamp(),1
 FROM public.performance_campaign_account_links
 WHERE campaign_id='{$campaignId}'::uuid");
$check((int)$admin->query(
 "SELECT count(*) FROM public.performance_campaign_account_links
  WHERE id='{$newLink}'::uuid AND management_epoch=1"
)->fetchColumn()===1,
 'new first-generation account link is allowed by F186 insert gate');

$insertIntent=static function(string $uuid,string $targetId,int $epoch,string $request)
 use($admin,$campaignId):void{
 $sql="INSERT INTO public.performance_campaign_intents (
  id,tenant_id,campaign_id,portal_service_ref,operation,review_state,
  proposer_actor_ref,reviewer_actor_ref,manifest_version,request_ref,
  payload_sha256,dispatch_state,created_at,updated_at,management_epoch
 ) SELECT CAST(? AS uuid),tenant_id,CAST(? AS uuid),portal_service_ref,
  operation,'awaiting_approval',proposer_actor_ref,NULL,
  manifest_version,?,payload_sha256,'disabled',
  clock_timestamp(),clock_timestamp(),CAST(? AS bigint)
 FROM public.performance_campaign_intents WHERE campaign_id=CAST(? AS uuid)
 LIMIT 1";
 $stmt=$admin->prepare($sql);
 $stmt->execute([$uuid,$targetId,$request,$epoch,$campaignId]);
};
$insertIntent($newIntent,$newCampaign,1,'req-demo-f186-new');
$check((int)$admin->query(
 "SELECT count(*) FROM public.performance_campaign_intents
  WHERE id='{$newIntent}'::uuid AND review_state='awaiting_approval'"
)->fetchColumn()===1,
 'new first-generation review request is pending, never preapproved');
$check((int)$b->query(
 "SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
  WHERE campaign_ref='cam-demo-f186-new'"
)->fetchColumn()===1
 &&(int)$b->query(
 "SELECT count(*) FROM public.perf_f97_demo_review_read
  WHERE campaign_ref='cam-demo-f186-new'"
)->fetchColumn()===1,
 'existing F100 SQL views accept only freshly created epoch-1 pending workflow');

// Retire + reactivate original A. Must log both and keep old scope tombstoned.
$admin->exec("UPDATE public.performance_managed_campaigns SET managed=false
 WHERE id='{$campaignId}'::uuid");
$admin->exec("UPDATE public.performance_managed_campaigns SET managed=true
 WHERE id='{$campaignId}'::uuid");
$events=$admin->query(
 "SELECT previous_epoch,current_epoch,previous_managed,current_managed
  FROM public.performance_f186_management_transition_audit
  WHERE campaign_id='{$campaignId}'::uuid ORDER BY event_id"
)->fetchAll(PDO::FETCH_ASSOC);
$check(count($events)===2,
 'both retirement and reactivation transitions produce append-only audit rows');
$check((int)$events[0]['previous_epoch']===1
 &&(int)$events[0]['current_epoch']===2
 &&in_array($events[0]['previous_managed'],['t',true,'1'],true)
 &&in_array($events[0]['current_managed'],['f',false,'0'],true),
 'retirement audit captures true->false and monotonic generation 1->2');
$check((int)$events[1]['previous_epoch']===2
 &&(int)$events[1]['current_epoch']===2
 &&in_array($events[1]['previous_managed'],['f',false,'0'],true)
 &&in_array($events[1]['current_managed'],['t',true,'1'],true),
 'reactivation audit captures false->true WITHOUT resetting epoch');
$check($refused(static fn()=>$admin->exec(
 'UPDATE public.performance_f186_management_transition_audit
  SET current_epoch=current_epoch+1 WHERE event_id=1'
)), 'audit records cannot be silently edited through direct UPDATE');
$check($refused(static fn()=>$admin->exec(
 'DELETE FROM public.performance_f186_management_transition_audit'
)), 'audit history DELETE is blocked by trigger');
$check((int)$admin->query(
 'SELECT count(*) FROM public.performance_f186_management_transition_audit'
)->fetchColumn()===2,
 'two append-only audit events remain after direct SQL tamper attempts');

// A caller with raw INSERT privileges cannot invent a "new" scope at 2
// using the same existing campaign or repurpose an old link.
$newLinkOther='ac860000-0000-4000-8000-000000000004';
$insertOldCampaignLink=static function(int $epoch)use($admin,$campaignId,$newLinkOther):void{
 $stmt=$admin->prepare("INSERT INTO public.performance_campaign_account_links (
 id,tenant_id,portal_service_ref,channel,campaign_id,account_candidate_id,
 link_state,source,dispatch_allowed,created_at,updated_at,management_epoch
 ) SELECT '{$newLinkOther}'::uuid,tenant_id,portal_service_ref,channel,
 campaign_id,account_candidate_id,link_state,source,false,
 clock_timestamp(),clock_timestamp(),CAST(? AS bigint)
 FROM public.performance_campaign_account_links
 WHERE campaign_id='{$campaignId}'::uuid");
 $stmt->execute([$epoch]);
};
$check($refused(static fn()=>$insertOldCampaignLink(2)),
 'direct INSERT cannot relink reactivated campaign at generation 2');
$check($refused(static fn()=>$insertOldCampaignLink(1)),
 'direct INSERT cannot reuse old generation 1 after reactivation');
$check($refused(static fn()=>$insertIntent(
 'ac860000-0000-4000-8000-000000000005',
 $campaignId,2,'req-demo-f186-stale-2'
)), 'direct new review for previously retired campaign at epoch 2 is denied');
$check($refused(static fn()=>$insertIntent(
 'ac860000-0000-4000-8000-000000000006',
 $campaignId,1,'req-demo-f186-stale-1'
)), 'direct new review copied from epoch 1 cannot bypass reactivation scope');
$check($refused(static fn()=>$admin->exec(
 "DELETE FROM public.performance_campaign_account_links
  WHERE campaign_id='{$campaignId}'::uuid"
)), 'old link cannot be deleted to bypass unique campaign link constraint');
$check($refused(static fn()=>$admin->exec(
 "DELETE FROM public.performance_campaign_intents
  WHERE campaign_id='{$campaignId}'::uuid"
),['42501','P0001']), 'old reviewed intent cannot be deleted or replaced by a new approval');

$after=$reader->read($b);
$check($after['status']==='credential_scoped_demo_workbench_only'
 &&$after['counts']['linked_campaigns']===1
 &&$after['counts']['visible_reviews']===1
 &&$after['campaigns'][0]['campaign_ref']==='cam-demo-f186-new'
 &&$after['reviews'][0]['review_observation_state']==='pending_human_demo',
 'F100 displays only newly created first-epoch campaign and pending review');
$check($after['dispatch_enabled']===false&&$after['billing_enabled']===false
 &&$after['ready_for_production']===false,
 'F186 scope write restrictions cannot enable paid media or billing');
$check($reader->read($a)===$preA,
 'previously tombstoned tenant A remains unchanged throughout B write/audit experiment');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'both F100 readers close their transactions');
echo "F186 write-side epoch and local audit: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
