<?php
declare(strict_types=1);

/**
 * F176: consume the REAL F100 PostgreSQL16-generated JSON in the original
 * F62 Portal consumer from pinned review candidate PR #356. No transform,
 * account fabrication, privileged fallback, HTTP wiring or advertising.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F176_F100_PORTAL_CONTRACT_LAB')!=='1'
    ||getenv('PORTAL_F62_CREDENTIAL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'){
    throw new RuntimeException('F176 real SQL->Portal test restricted to disposable CI');
}
$root=dirname(__DIR__,2);
$portal=$root.'/portal-f176-candidate/apps/portal';
foreach([
 'app/Domain/Catalog/PerformanceChannel.php',
 'app/Domain/PerformanceDashboard/PerformanceCampaignObservationState.php',
 'app/Domain/PerformanceDashboard/PerformanceReviewObservationState.php',
 'app/Domain/PerformanceDashboard/CredentialBoundPerformanceWorkbenchPreview.php',
] as $file){
    if(!is_file($portal.'/'.$file))throw new RuntimeException('F176 pinned Portal source missing: '.$file);
    require_once $portal.'/'.$file;
}
$n=0;
$check=static function(bool $condition,string $reason)use(&$n):void{
    ++$n;
    if(!$condition)throw new RuntimeException('F176 FAILED: '.$reason);
};
$path=getenv('PERF_F100_JSON_OUTPUT_FILE');
$check(is_string($path)&&str_starts_with($path,'/tmp/')
    &&is_file($path)&&filesize($path)>0&&filesize($path)<100000
    &&!is_link($path),
    'F100 SQL-generated payload exists only as bounded CI temporary file');
$raw=file_get_contents($path);
$dto=json_decode((string)$raw,true,24,JSON_THROW_ON_ERROR);
$check(is_array($dto)
    &&($dto['schema_version']??null)==='perf.portal.credential-bound-workbench.synthetic.v1',
    'actual F100 projected the exact contract already accepted by F62');
$check(array_keys($dto)===[
    'schema_version','status','synthetic_only','read_only',
    'credential_binding_confirmed','portal_identity_authenticated',
    'real_human_approval_verified','provider_connected',
    'authorization_eligible','ready_for_production','dispatch_enabled',
    'billing_enabled','counts','accounts','campaigns','reviews'
], 'F100 real SQL projection preserves exact ordered schema; no coerced adapter');

$preview=\App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview::fromDto($dto);
$check($preview instanceof \App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview,
    'PINNED CURRENT Portal F62 accepts the real F100 PostgreSQL16 DTO');
$check($preview->hasPortalIdentity()===false
    &&$preview->canPublishAds()===false
    &&$preview->canBill()===false
    &&$preview->isRealTime()===false,
    'Portal consumer keeps all operational privileges denied');
$check($dto['portal_identity_authenticated']===false
    &&$dto['real_human_approval_verified']===false
    &&$dto['provider_connected']===false
    &&$dto['authorization_eligible']===false
    &&$dto['ready_for_production']===false
    &&$dto['dispatch_enabled']===false
    &&$dto['billing_enabled']===false,
    'SQL credential proof never implies Portal IAM, advertiser consent or money');
$check($dto['credential_binding_confirmed']===true
    &&$dto['synthetic_only']===true
    &&$dto['read_only']===true,
    'response proves only synthetic PG login credential scope');
$check($dto['counts']['account_candidates']===2
    &&$dto['counts']['linked_campaigns']===1
    &&$dto['counts']['visible_reviews']===1,
    'pinned Portal consumer receives actual F100 SQL-backed account/campaign/review counts');
$check(count($dto['accounts'])===2
    &&count($dto['campaigns'])===1
    &&count($dto['reviews'])===1,
    'the F100 read included real fixture database rows rather than empty mocked DTO');
$check($dto['campaigns'][0]['account_ref']==='demo_meta_a'
    &&$dto['campaigns'][0]['portal_channel']==='meta_ads',
    'campaign account link and Meta channel originate from actual SQL fixture');
$check($dto['reviews'][0]['campaign_ref']===$dto['campaigns'][0]['campaign_ref']
    &&$dto['reviews'][0]['journaled']===true
    &&$dto['reviews'][0]['review_observation_state']==='approved_demo_no_execution',
    'review has real SQL campaign reference and only internal, non-executable approval');
$check($preview->counts===$dto['counts']
    &&$preview->accounts===$dto['accounts']
    &&$preview->campaigns===$dto['campaigns']
    &&$preview->reviews===$dto['reviews'],
    'Portal F62 consumed full F100 data without rewriting IDs, channels or statuses');
$check(!array_key_exists('tenant_ref',$dto)
    &&!array_key_exists('portal_service_ref',$dto)
    &&!array_key_exists('authenticated_actor',$dto),
    'F100 DTO alone must NOT be interpreted as user/service IAM entitlement');
$check(!str_contains($raw,'ci-f91-only-a')
    &&!str_contains($raw,'ci-f91-only-b')
    &&!str_contains($raw,'access_token')
    &&!str_contains($raw,'refresh_token'),
    'real SQL export is free of DB passwords and external access secrets');

$rejected=static function(array $mutated)use($check):void{
    $check(\App\Domain\PerformanceDashboard\CredentialBoundPerformanceWorkbenchPreview::fromDto(
        $mutated
    )===null,'pinned Portal F62 rejects unauthorized or invalid F100 alteration');
};
$rejected(array_replace($dto,[
    'schema_version'=>'perf.portal.pg16-tenant-review-report.synthetic.v1',
]));
$rejected(array_replace($dto,['portal_identity_authenticated'=>true]));
$rejected(array_replace($dto,['authorization_eligible'=>true]));
$rejected(array_replace($dto,['provider_connected'=>true]));
$rejected(array_replace($dto,['dispatch_enabled'=>true]));
$rejected(array_replace($dto,['billing_enabled'=>true]));
$rejected(array_replace($dto,['credential_binding_confirmed'=>false]));
$rejected(array_replace($dto,['real_human_approval_verified'=>true]));
$rejected(array_replace($dto,['ready_for_production'=>true]));
$rejected(array_replace($dto,['source'=>'injected_client_scope']));
$rejected(array_replace($dto,['accounts'=>[]]));
$rejected(array_replace($dto,['campaigns'=>[]]));
$rejected(array_replace($dto,['reviews'=>[]]));
$campaigns=$dto['campaigns'];
$campaigns[0]['account_ref']='demo_missing';
$rejected(array_replace($dto,['campaigns'=>$campaigns]));
$campaigns=$dto['campaigns'];
$campaigns[0]['portal_channel']='google_ads';
$rejected(array_replace($dto,['campaigns'=>$campaigns]));
$reviews=$dto['reviews'];
$reviews[0]['campaign_ref']='cam-demo-other-tenant';
$rejected(array_replace($dto,['reviews'=>$reviews]));
$reviews=$dto['reviews'];
$reviews[0]['journaled']=false;
$rejected(array_replace($dto,['reviews'=>$reviews]));
$reviews=$dto['reviews'];
$reviews[0]['review_observation_state']='approved_real';
$rejected(array_replace($dto,['reviews'=>$reviews]));
$reviews=$dto['reviews'];
$reviews[0]['portal_stage']='ready_to_publish';
$rejected(array_replace($dto,['reviews'=>$reviews]));
$accounts=$dto['accounts'];
$accounts[0]['connection_state']='oauth_connected';
$rejected(array_replace($dto,['accounts'=>$accounts]));
$accounts=$dto['accounts'];
$accounts[]=$accounts[0];
$rejected(array_replace($dto,['accounts'=>$accounts]));
$check(file_get_contents($path)===$raw,
    'negative integration tests did not modify the SQL-exported DTO');

$codeF100=file_get_contents(
    $root.'/app/Performance/ReadSide/CredentialBoundDemoWorkbenchReader.php'
);
$codeF166=file_get_contents(
    $root.'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php'
);
$check(str_contains($codeF100,"'perf.portal.credential-bound-workbench.synthetic.v1'")
    &&str_contains($codeF166,"'perf.portal.pg16-tenant-review-report.synthetic.v1'"),
    'F100 is current Portal F62 source; F166 remains separate review-history payload');
$check(!str_contains($codeF166,"'account_ref'")
    &&!str_contains($codeF166,"'journaled'"),
    'no fabricated ad-account identity or review journal is inferred from F166');

echo "F176 REAL F100 PG16 -> current Portal F62: {$n} PASS / 0 FAIL — DEMO/NO-GO\n";
