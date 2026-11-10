<?php
declare(strict_types=1);

/**
 * F1-58: generate only a fictional read-only review-queue JSON.
 * No HTTP, Portal real, human login, Ads provider, billing or persistence.
 */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeReviewWorkflow.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticReviewQueueProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeReviewWorkflow;
use Imagine\Performance\Domain\SyntheticReviewQueueProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F57_REVIEW_LAB')!=='1'
 ||getenv('PERF_F58_REVIEW_QUEUE_LAB')!=='1'){
    throw new RuntimeException('F58 mock queue only available in standalone CLI');
}
$l=new SyntheticManagedScopeLedger();
$f=new SyntheticScopeReviewWorkflow($l);
$t='perf-demo-aurora';
$l->setDemoCapacity($t,'google_ads',2,2);
$l->includeCampaign($t,'google_ads','cam-aurora-one');
$l->includeCampaign($t,'google_ads','cam-aurora-two');
$h=hash('sha256','f58-demo-only-workflow-manifest');
$now=1800000000;
$f->propose($t,'req-demo-review-one','actor-demo-proposer',
    'cam-aurora-one','cam-aurora-new-one',$h,$now,$now+120);
$f->propose($t,'req-demo-review-two','actor-demo-proposer',
    'cam-aurora-two','cam-aurora-new-two',$h,$now,$now+120);
$f->decide($t,'req-demo-review-two','actor-demo-reviewer',
    $h,true,true,$now+1);
$dto=(new SyntheticReviewQueueProjector())->build($f,$t,[
    'req-demo-review-one','req-demo-review-two'
]);
if(!is_array($dto)||$dto['authorization_eligible']!==false
 ||$dto['dispatch_enabled']!==false||$dto['billing_enabled']!==false
 ||$dto['scope_changes_permitted']!==false){
    throw new RuntimeException('F58 refused unsafe review queue fixture');
}
echo json_encode($dto,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
    |JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
