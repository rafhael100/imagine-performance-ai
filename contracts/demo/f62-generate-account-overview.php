<?php
declare(strict_types=1);

/** F1-62: fake account/brand read-model fixture for offline bundle. */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticBrandAccountScopeIndex.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticAccountScopedDashboardProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticBrandAccountScopeIndex;
use Imagine\Performance\Domain\SyntheticAccountScopedDashboardProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F52_SCOPE_LAB')!=='1'
  ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
  ||getenv('PERF_F60_ACCOUNT_SCOPE_LAB')!=='1'
  ||getenv('PERF_F61_COMBINED_READ_LAB')!=='1'
  ||getenv('PERF_F62_SHOWCASE_LAB')!=='1'){
    throw new RuntimeException('F62 fake account bundle only in synthetic PHP CLI');
}
$l=new SyntheticManagedScopeLedger();
$accounts=new SyntheticBrandAccountScopeIndex($l);
$t='perf-demo-aurora';
$l->setDemoCapacity($t,'google_ads',2,3);
$l->setDemoCapacity($t,'meta',1,2);
$l->includeCampaign($t,'google_ads','cam-search-demo');
$l->includeCampaign($t,'google_ads','cam-local-demo');
$l->includeCampaign($t,'meta','cam-social-demo');
$l->includeGroup($t,'cam-search-demo','grp-search-demo');
$l->includeGroup($t,'cam-local-demo','grp-local-demo');
$l->includeGroup($t,'cam-social-demo','grp-social-demo');
$l->pauseCampaign($t,'cam-local-demo');
$accounts->addBrand($t,'brand-demo-aurora');
$accounts->addAccount($t,'brand-demo-aurora','google_ads','acct-demo-aurora-google');
$accounts->addAccount($t,'brand-demo-aurora','meta','acct-demo-aurora-meta');
$accounts->bindCampaign($t,'acct-demo-aurora-google','cam-search-demo');
$accounts->bindCampaign($t,'acct-demo-aurora-google','cam-local-demo');
// Meta intentionally unassigned to illustrate incomplete mock mapping.
$dto=(new SyntheticAccountScopedDashboardProjector())->build(
 $l,$accounts,$t,
 ['cam-search-demo','cam-local-demo','cam-social-demo'],
 ['grp-search-demo','grp-local-demo','grp-social-demo']
);
if(!is_array($dto)||$dto['contracted']!==false
   ||$dto['provider_connected']!==false
   ||$dto['dispatch_enabled']!==false
   ||$dto['billing_enabled']!==false
   ||$dto['authorization_eligible']!==false
   ||$dto['ready_for_production']!==false){
    throw new RuntimeException('F62 offline account fixture denied');
}
echo json_encode($dto,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
  |JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
