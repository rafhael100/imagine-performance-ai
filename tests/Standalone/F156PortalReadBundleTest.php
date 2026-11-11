<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F153OfflineReviewFixtureFactory.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F154SyntheticAttentionProjector.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F156SyntheticPortalReadBundle.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;
use Imagine\Performance\Domain\F153OfflineReviewFixtureFactory;
use Imagine\Performance\Domain\F154SyntheticAttentionProjector;
use Imagine\Performance\Domain\F156SyntheticPortalReadBundle;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F153_PARITY_LAB')!=='1'
 ||getenv('PERF_F154_ATTENTION_LAB')!=='1'
 ||getenv('PERF_F156_PORTAL_BUNDLE_LAB')!=='1') {
 throw new RuntimeException('F156 synthetic Portal bridge test only');
}
$n=0;
$check=static function(bool $condition,string $why)use(&$n):void{
 ++$n;if(!$condition)throw new RuntimeException('F156 FAILED: '.$why);
};
$t='perf-demo-aurora';
$l=new SyntheticManagedScopeLedger();
$l->setDemoCapacity($t,'google_ads',2,3);
$l->setDemoCapacity($t,'meta',1,2);
$l->includeCampaign($t,'google_ads','cam-search-demo');
$l->includeCampaign($t,'google_ads','cam-local-demo');
$l->includeCampaign($t,'meta','cam-social-demo');
$l->includeGroup($t,'cam-search-demo','grp-search-demo');
$l->includeGroup($t,'cam-local-demo','grp-local-demo');
$l->includeGroup($t,'cam-social-demo','grp-social-demo');
$l->pauseCampaign($t,'cam-local-demo');
$l->pauseGroup($t,'grp-local-demo');
$scope=(new SyntheticScopeDashboardProjector())->build(
 $l,$t,['cam-search-demo','cam-local-demo','cam-social-demo'],
 ['grp-search-demo','grp-local-demo','grp-social-demo']);
$reviews=F153OfflineReviewFixtureFactory::build($l,$t);
$attention=(new F154SyntheticAttentionProjector())->build($scope,$reviews);
$check(is_array($scope)&&is_array($reviews)&&is_array($attention),
 'all three existing fictional domain projections are ready');
$builder=new F156SyntheticPortalReadBundle();
$bundle=$builder->build($l,$scope,$reviews,$attention,$t);
$check(is_array($bundle)
 &&$bundle['schema_version']==='perf.portal.performance-dashboard.synthetic.v1',
 'versioned Portal bundle generated from existing domain without HTTP');
$check($bundle['review_count_demo']===2
 &&count($bundle['creative_reviews'])===2
 &&count($bundle['campaign_capacity'])===2
 &&count($bundle['attention'])===2,
 'bundle includes both channels, two reviews and their mock attention');
$check($bundle['iam_verified']===false
 &&$bundle['s2s_verified']===false
 &&$bundle['human_approval_verified']===false
 &&$bundle['dispatch_enabled']===false
 &&$bundle['billing_enabled']===false
 &&$bundle['authorization_eligible']===false
 &&$bundle['ready_for_production']===false
 &&$bundle['real_metrics_available']===false,
 'bundle never claims operational authorization or metrics');
$check($bundle['creative_reviews'][0]['campaign_ref']==='cam-search-demo'
 &&$bundle['creative_reviews'][1]['campaign_ref']==='cam-social-demo',
 'review-to-campaign references retained for future Portal reader');
$check($bundle['attention'][0]['pending_demo']===1
 &&$bundle['attention'][1]['changes_requested_demo']===1,
 'attention counters agree with review states in each channel');
$check($builder->build($l,$scope,$reviews,$attention,$t)===$bundle,
 'bundle contents are deterministic and do not include real timestamps');
$check($builder->build($l,$scope,$reviews,$attention,'perf-demo-boreal')===null,
 'caller tenant mismatch denied');
$bad=$scope;$bad['tenant_ref']='perf-demo-boreal';
$check($builder->build($l,$bad,$reviews,$attention,$t)===null,
 'scope tenant B cannot join review tenant A');
$bad=$reviews;$bad['tenant_ref']='perf-demo-boreal';
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'review tenant mismatch denied');
$bad=$attention;$bad['tenant_ref']='perf-demo-boreal';
$check($builder->build($l,$scope,$reviews,$bad,$t)===null,
 'attention tenant mismatch denied');
$bad=$reviews;$bad['human_approval_verified']=true;
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'claimed real reviewer approval rejected');
$bad=$reviews;$bad['reviews'][0]['campaign_ref']='cam-boreal-fake';
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'review references must resolve through actual scoped campaign ledger');
$bad=$reviews;$bad['reviews'][0]['channel']='meta';
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'campaign channel cannot be forged');
$bad=$reviews;$bad['reviews'][0]['action_authorized']=true;
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'provider authorized flag rejected');
$bad=$attention;$bad['channels'][0]['pending_demo']=4;
$check($builder->build($l,$scope,$reviews,$bad,$t)===null,
 'attention cannot disagree with review backlog');
$bad=$attention;$bad['channels'][0]['alerts'][0]['action_enabled']=true;
$check($builder->build($l,$scope,$reviews,$bad,$t)===null,
 'actionable alert prohibited');
$bad=$scope;$bad['dispatch_enabled']=true;
$check($builder->build($l,$bad,$reviews,$attention,$t)===null,
 'scope cannot turn on Ads dispatcher');
$bad=$scope;$bad['channels'][0]['campaigns']['remaining']=100;
$check($builder->build($l,$bad,$reviews,$attention,$t)===null,
 'invalid campaign capacity remaining is rejected');
$bad=$scope;$bad['channels'][1]['id']='google_ads';
$check($builder->build($l,$bad,$reviews,$attention,$t)===null,
 'duplicate channel rejected');
$bad=$reviews;$bad['reviews'][0]['title']='<script>alert(1)</script>';
$check($builder->build($l,$scope,$bad,$attention,$t)===null,
 'unsafe HTML text cannot enter Portal JSON bundle');

$path=dirname(__DIR__,2).'/contracts/portal/f156-build-readonly-review-bundle.php';
$proc=proc_open([PHP_BINARY,$path],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F151_APPROVAL_LAB'=>'1',
 'PERF_F153_PARITY_LAB'=>'1','PERF_F154_ATTENTION_LAB'=>'1',
 'PERF_F156_PORTAL_BUNDLE_LAB'=>'1',
]);
if(!is_resource($proc))throw new RuntimeException('F156 JSON CLI unavailable');
fclose($pipes[0]);
$out=stream_get_contents($pipes[1]);fclose($pipes[1]);
$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($proc);
$check($exit===0&&is_string($out)
 &&str_contains($out,'perf.portal.performance-dashboard.synthetic.v1'),
 'CLI creates offline JSON response: '.substr((string)$err,0,140));
$decoded=json_decode((string)$out,true,64,JSON_THROW_ON_ERROR);
$check($decoded===$bundle,'CLI output matches in-process domain bundle exactly');
$check(!str_contains((string)$out,'customer_id')
 &&!str_contains((string)$out,'access_token')
 &&!str_contains((string)$out,'refresh_token'),
 'mock Portal response contains no real customer/token field');
echo "F156 Portal synthetic read bundle: {$n} PASS / 0 FAIL — NO-GO\n";
