<?php
declare(strict_types=1);

/**
 * F1-59 off-line, fully fictional review board build.
 * Reuses the F55 HTML renderer and replaces the F54 static approval captions
 * with rows from the actual F57 -> F58 synthetic review workflow.
 * No network, API, session, database, operator credential or provider action.
 */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeReviewWorkflow.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticReviewQueueProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;
use Imagine\Performance\Domain\SyntheticScopeReviewWorkflow;
use Imagine\Performance\Domain\SyntheticReviewQueueProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F55_RENDER_LAB')!=='1'
 ||getenv('PERF_F57_REVIEW_LAB')!=='1'
 ||getenv('PERF_F58_REVIEW_READ_LAB')!=='1'
 ||getenv('PERF_F59_REVIEW_HTML_LAB')!=='1'){
 throw new RuntimeException('F59 HTML generation only in explicit fictional CLI lab');
}
ob_start();
try{
 require __DIR__.'/f55-render-offline-preview.php';
 $html=ob_get_clean();
}catch(Throwable $e){
 ob_end_clean();throw $e;
}
if(!is_string($html)||strlen($html)>100000){
 throw new RuntimeException('F59 input HTML missing or oversized');
}
$tenant='perf-demo-aurora';
$ledger=new SyntheticManagedScopeLedger();
$ledger->setDemoCapacity($tenant,'google_ads',2,3);
$ledger->setDemoCapacity($tenant,'meta',1,2);
foreach([
 ['google_ads','cam-search-demo'],
 ['google_ads','cam-local-demo'],
 ['meta','cam-social-demo'],
] as [$channel,$campaign]){
 if($ledger->includeCampaign($tenant,$channel,$campaign)!=='included_synthetic_only'){
  throw new RuntimeException('F59 fictional campaign fixture not included');
 }
}
$workflow=new SyntheticScopeReviewWorkflow($ledger);
$time=1800000000;
$manifest=hash('sha256','f59-offline-review-only');
$ids=[
 'req-demo-search-review'=>['cam-search-demo','cam-search-revised'],
 'req-demo-meta-review'=>['cam-social-demo','cam-social-revised'],
 'req-demo-local-review'=>['cam-local-demo','cam-local-revised'],
];
foreach($ids as $request=>[$from,$to]){
 if($workflow->propose($tenant,$request,'actor-demo-author',
   $from,$to,$manifest,$time,$time+120)!=='proposed_synthetic_only'){
  throw new RuntimeException('F59 failed to propose mock review');
 }
}
if($workflow->decide($tenant,'req-demo-meta-review','actor-demo-reviewer',
     $manifest,true,false,$time+1)!=='rejected_scope_demo'
  ||$workflow->decide($tenant,'req-demo-local-review','actor-demo-reviewer',
     $manifest,true,true,$time+1)!=='approved_scope_demo_not_executed'){
 throw new RuntimeException('F59 failed to record mock review states');
}
$scope=(new SyntheticScopeDashboardProjector())->build(
 $ledger,$tenant,
 ['cam-search-demo','cam-local-demo','cam-social-demo'],[]
);
if(!is_array($scope))throw new RuntimeException('F59 failed to project fake scope');
$queue=(new SyntheticReviewQueueProjector())->build(
 $workflow,$scope,$tenant,array_keys($ids)
);
if(!is_array($queue)||count($queue['items'])!==3
 ||$queue['dispatch_enabled']!==false
 ||$queue['authorization_eligible']!==false
 ||$queue['billing_enabled']!==false){
 throw new RuntimeException('F59 fake queue could imply real approval');
}
$lookup=[
 'req-demo-search-review'=>[
   'title'=>'Revisar otimização de pesquisa',
   'channel'=>'google_ads',
   'status'=>'Aguardando revisão fictícia',
 ],
 'req-demo-meta-review'=>[
   'title'=>'Avaliar proposta de reconhecimento',
   'channel'=>'meta',
   'status'=>'Rejeitada em teste — sem execução real',
 ],
 'req-demo-local-review'=>[
   'title'=>'Revisar estratégia regional',
   'channel'=>'google_ads',
   'status'=>'Aprovada em teste — sem execução real',
 ],
];
$approvals=[];
foreach($queue['items'] as $item){
 $id=$item['request_ref'];
 if(!isset($lookup[$id]))throw new RuntimeException('Unknown mock review ID');
 $approved=$item['state']==='approved_scope_demo_not_executed';
 $rejected=$item['state']==='rejected_scope_demo';
 $pending=$item['state']==='pending_review_synthetic';
 if(!$approved&&!$rejected&&!$pending){
  throw new RuntimeException('Review UI cannot display executable states');
 }
 $approvals[]=$lookup[$id];
}
$pattern='~(<script id="preview-data" type="application/json">)(.*?)(</script>)~s';
if(preg_match_all($pattern,$html)!==1){
 throw new RuntimeException('F59 expects exactly one JSON script');
}
preg_match($pattern,$html,$match);
$data=json_decode(trim($match[2]),true,32,JSON_THROW_ON_ERROR);
if(!is_array($data)||$data['dispatch_enabled']!==false
   ||$data['billing_enabled']!==false
   ||$data['authorization_eligible']!==false
   ||$data['ready_for_production']!==false){
 throw new RuntimeException('Unsafe HTML preview contract');
}
$data['approvals']=$approvals;
$data['review_queue']=$queue;
$encoded=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
 |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
$result=preg_replace_callback($pattern,
 static fn(array $m):string=>$m[1]."\n".$encoded."\n".$m[3],$html,1,$replacements);
if(!is_string($result)||$replacements!==1)throw new RuntimeException('F59 failed to render review board');
echo $result;
