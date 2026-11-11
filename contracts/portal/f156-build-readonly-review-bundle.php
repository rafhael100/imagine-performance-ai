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
 throw new RuntimeException('F156 Portal JSON bundle forbidden outside synthetic CLI');
}
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
$scope=(new SyntheticScopeDashboardProjector())->build($l,$t,
 ['cam-search-demo','cam-local-demo','cam-social-demo'],
 ['grp-search-demo','grp-local-demo','grp-social-demo']);
$reviews=F153OfflineReviewFixtureFactory::build($l,$t);
$attention=(new F154SyntheticAttentionProjector())->build($scope,$reviews);
$bundle=(new F156SyntheticPortalReadBundle())->build(
 $l,$scope,$reviews,$attention,$t
);
if(!is_array($bundle)||$bundle['dispatch_enabled']!==false
 ||$bundle['billing_enabled']!==false
 ||$bundle['iam_verified']!==false
 ||$bundle['s2s_verified']!==false) {
 throw new RuntimeException('F156 cannot emit operational or authenticated bundle');
}
echo json_encode($bundle,
 JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
