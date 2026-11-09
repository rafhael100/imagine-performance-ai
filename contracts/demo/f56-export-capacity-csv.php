<?php
declare(strict_types=1);
/** F1-56 fictional CSV stdout only, not a customer download endpoint. */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeCsvExporter.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;
use Imagine\Performance\Domain\SyntheticScopeCsvExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F56_CSV_LAB')!=='1'){
    throw new RuntimeException('F56 export cannot run outside synthetic CLI');
}
$l=new SyntheticManagedScopeLedger();
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
$l->pauseGroup($t,'grp-local-demo');
$dto=(new SyntheticScopeDashboardProjector())->build(
  $l,$t,['cam-search-demo','cam-local-demo','cam-social-demo'],
  ['grp-search-demo','grp-local-demo','grp-social-demo']
);
$csv=(new SyntheticScopeCsvExporter())->export($dto);
if(!is_string($csv))throw new RuntimeException('CSV output denied by synthetic NO-GO');
echo $csv;
