<?php
declare(strict_types=1);

use App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview as PortalPreview;

/**
 * F174 dashboard cross-contract denial: the latest pinned Portal candidate's
 * real F62 consumer accepts its own F100-shaped DTO but MUST reject F166
 * even if someone renames the schema_version string.
 *
 * No synthetic fallback may fabricate account candidate connections.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F174_READINESS_LAB')!=='1'
 ||getenv('PORTAL_F62_CREDENTIAL_WORKBENCH_LAB')!=='1'){
 throw new RuntimeException('F174 Portal dashboard contract check is lab-only');
}
$root=dirname(__DIR__,2);
$portal=$root.'/portal-f174-candidate/apps/portal';
$files=[
 'app/Domain/Catalog/PerformanceChannel.php',
 'app/Domain/PerformanceDashboard/PerformanceCampaignObservationState.php',
 'app/Domain/PerformanceDashboard/PerformanceReviewObservationState.php',
 'app/Domain/PerformanceDashboard/CredentialBoundPerformanceWorkbenchPreview.php'
];
foreach($files as $file){
 if(!is_file($portal.'/'.$file))throw new RuntimeException('F174 Portal candidate source missing: '.$file);
 require_once $portal.'/'.$file;
}

$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F174 dashboard FAILED: '.$why);
};
$consumerPath=$portal.'/app/Domain/PerformanceDashboard/CredentialBoundPerformanceWorkbenchPreview.php';
$consumerText=file_get_contents($consumerPath);
$f166Text=file_get_contents($root.'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php');
$check(is_string($consumerText)&&is_string($f166Text),
 'both original repo code files are available to inspect without database');
$check(preg_match("/'schema_version'=>'([^']+)'/",$f166Text,$schema)===1,
 'Performance F166 real PHP class declares a discoverable schema version');
$schemaF166=$schema[1];
$schemaPortal='perf.portal.credential-bound-workbench.synthetic.v1';
$check(str_contains($consumerText,$schemaPortal)
 &&$schemaF166==='perf.portal.pg16-tenant-review-report.synthetic.v1',
 'real current Portal F62 and Performance F166 schemas use different versions');
$check($schemaF166!==$schemaPortal,
 'there is no accidental exact compatibility at schema discriminator level');

$validPortal=[
 'schema_version'=>$schemaPortal,
 'status'=>'credential_scoped_demo_workbench_only',
 'synthetic_only'=>true,'read_only'=>true,
 'credential_binding_confirmed'=>true,
 'portal_identity_authenticated'=>false,
 'real_human_approval_verified'=>false,
 'provider_connected'=>false,
 'authorization_eligible'=>false,
 'ready_for_production'=>false,
 'dispatch_enabled'=>false,'billing_enabled'=>false,
 'counts'=>[
  'account_candidates'=>0,'linked_campaigns'=>0,
  'visible_reviews'=>0,'review_pending'=>0,
  'review_internal_no_execution'=>0,'review_rejected'=>0
 ],
 'accounts'=>[],'campaigns'=>[],'reviews'=>[]
];
$preview=PortalPreview::fromDto($validPortal);
$check($preview instanceof PortalPreview,
 'current Portal F62 consumer really accepts its own safe F100-shaped DTO');
$check($preview->hasPortalIdentity()===false
 &&$preview->canPublishAds()===false
 &&$preview->canBill()===false
 &&$preview->isRealTime()===false,
 'existing consumer stays unprivileged on valid F100 demo specimen');

$f166Sample=[
 'schema_version'=>$schemaF166,
 'source'=>'pg16_security_invoker_ci_only',
 'tenant_ref'=>'perf-demo-aurora',
 'read_only'=>true,
 'iam_verified'=>false,'s2s_verified'=>false,
 'human_approval_verified'=>false,'authorization_eligible'=>false,
 'contracted'=>false,'dispatch_enabled'=>false,'billing_enabled'=>false,
 'ready_for_production'=>false,'real_metrics_available'=>false,
 'active_review_count'=>0,
 'reviews'=>[],
 'review_status_demo'=>[
  'pending_demo'=>0,'changes_requested_demo'=>0,
  'approved_mock_only'=>0,'rejected_mock_only'=>0
 ],
 'quarantined_review_counts_demo'=>['google_ads'=>0,'meta'=>0],
 'campaign_scope_events_demo'=>[]
];
foreach(array_keys($f166Sample) as $field){
 $check(str_contains($f166Text,"'".$field."'"),
  'F166 sample field is grounded in actual Performance reader: '.$field);
}
$check(PortalPreview::fromDto($f166Sample)===null,
 'Portal F62 correctly refuses current F166 PostgreSQL DTO as incompatible');
$versionForged=$f166Sample;
$versionForged['schema_version']=$schemaPortal;
$check(PortalPreview::fromDto($versionForged)===null,
 'forging only schema version cannot bypass missing F100 account/campaign contract');
$versionForged['accounts']=[];
$versionForged['campaigns']=[];
$versionForged['status']='credential_scoped_demo_workbench_only';
$check(PortalPreview::fromDto($versionForged)===null,
 'adding empty account fields cannot manufacture audited F100 link contract');

$check(str_contains($consumerText,'PerformanceCampaignObservationState')
 &&str_contains($consumerText,'PerformanceReviewObservationState'),
 'Portal consumer validates its observation enums independently');
$check(str_contains($consumerText,"'account_candidates'")
 &&str_contains($consumerText,"'account_ref'")
 &&str_contains($consumerText,"'journaled'"),
 'Portal F62 requires account candidates and journaled review references F166 lacks');
$check(!str_contains($f166Text,"'account_ref'")
 &&!str_contains($f166Text,"'journaled'"),
 'F166 does not claim to supply authenticated advertiser account links');
$check(str_contains($consumerText,"'approved_demo_no_execution'")
 &&str_contains($f166Text,"'approved_mock_only'"),
 'approval observation labels differ; never translate to real human authorization');
$check(str_contains(file_get_contents($portal.'/app/Domain/Catalog/PerformanceChannel.php'),"'meta_ads'")
 &&str_contains($f166Text,"'meta'"),
 'channel enums have a separate Portal/Performance mapping contract');
$check($f166Sample['iam_verified']===false
 &&$f166Sample['dispatch_enabled']===false
 &&$f166Sample['billing_enabled']===false,
 'incompatibility must remain NO-GO without auto-promoting privileges');

echo "F174 current Portal F62 vs Performance F166 contract: {$n} PASS / 0 FAIL — INCOMPATIBLE, NO-GO\n";
