<?php
declare(strict_types=1);

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
  ||getenv('PERF_F57_REVIEW_LAB')!=='1'
  ||getenv('PERF_F58_REVIEW_READ_LAB')!=='1'){
    throw new RuntimeException('F58 only CLI synthetic read projection');
}
$n=0;
$check=static function(string $msg,mixed $actual,mixed $expected)use(&$n):void{
 if($actual!==$expected)throw new RuntimeException(
   'FAIL F58 '.$msg.' actual='.var_export($actual,true)
   .' expected='.var_export($expected,true));
 echo 'PASS F58 '.++$n.' '.$msg.PHP_EOL;
};
$ledger=new SyntheticManagedScopeLedger();
$flow=new SyntheticScopeReviewWorkflow($ledger);
$projector=new SyntheticScopeDashboardProjector();
$reviewView=new SyntheticReviewQueueProjector();
$t='perf-demo-a';$foreign='perf-demo-b';
$p='actor-demo-p';$r='actor-demo-r';$operator='actor-demo-o';
$hash=hash('sha256','f58 synthetic manifest');
$now=1800000400;
$check('01 A mock quota', $ledger->setDemoCapacity($t,'google_ads',4,4),'capacity_configured_demo');
$check('02 B mock quota', $ledger->setDemoCapacity($foreign,'google_ads',1,1),'capacity_configured_demo');
foreach(['cam-a1','cam-a2','cam-a3','cam-a4'] as $i=>$c){
 $check(sprintf('%02d A campaign %d',3+$i,$i+1),
   $ledger->includeCampaign($t,'google_ads',$c),'included_synthetic_only');
}
$check('07 B separate campaign', $ledger->includeCampaign($foreign,'google_ads','cam-b1'),'included_synthetic_only');
$requests=[
 'req-demo-pending'=>['cam-a1','cam-next1'],
 'req-demo-approved'=>['cam-a2','cam-next2'],
 'req-demo-rejected'=>['cam-a3','cam-next3'],
 'req-demo-applied'=>['cam-a4','cam-next4'],
];
foreach($requests as $id=>[$old,$new]){
 $check('propose '.$id,
    $flow->propose($t,$id,$p,$old,$new,$hash,$now,$now+120),
    'proposed_synthetic_only');
}
$check('12 B mock pending request',
 $flow->propose($foreign,'req-demo-private',$p,'cam-b1','cam-b2',$hash,$now,$now+120),
 'proposed_synthetic_only');
$check('13 A approve request two',
 $flow->decide($t,'req-demo-approved',$r,$hash,true,true,$now+1),
 'approved_scope_demo_not_executed');
$check('14 A reject request three',
 $flow->decide($t,'req-demo-rejected',$r,$hash,true,false,$now+1),
 'rejected_scope_demo');
$check('15 A approve request four',
 $flow->decide($t,'req-demo-applied',$r,$hash,true,true,$now+1),
 'approved_scope_demo_not_executed');
$check('16 apply reviewed scope only in local ledger',
 $flow->applyMock($t,'req-demo-applied',$operator,$hash,true,$now+2),
 'scope_applied_synthetic_only');
$check('17 old campaign out of scope', $ledger->campaignState($t,'cam-a4'),'outside_scope_demo');
$check('18 newly included campaign only draft', $ledger->campaignState($t,'cam-next4'),'draft_demo');

$scope=$projector->build($ledger,$t,
 ['cam-a1','cam-a2','cam-a3','cam-a4','cam-next4'],[]);
$check('19 source F53 DTO valid',is_array($scope),true);
$refs=array_keys($requests);
$refs[]='req-demo-private';
$refs[]='req-demo-missing';
$refs[]='req-demo-pending';
$view=$reviewView->build($flow,$scope,$t,$refs);
$check('20 review DTO built',is_array($view),true);
$check('21 review DTO schema', $view['schema_version'],'perf.portal.review-queue.synthetic.v1');
$check('22 source only synthetic', $view['source'],'in_memory_fixture_only');
$check('23 tenant remains explicit', $view['tenant_ref'],$t);
$check('24 read-only true', $view['read_only'],true);
$check('25 no actual human review', $view['human_review_real'],false);
$check('26 no authorization eligible', $view['authorization_eligible'],false);
$check('27 no external dispatch', $view['dispatch_enabled'],false);
$check('28 no billing', $view['billing_enabled'],false);
$check('29 A sees exactly four own requests',count($view['items']),4);
$check('30 output requests only from A',
 array_column($view['items'],'request_ref'),array_keys($requests));
$check('31 first is still awaiting review',
 $view['items'][0]['state'],'pending_review_synthetic');
$check('32 pending item may reference old campaign',
 $view['items'][0]['from_campaign_ref'],'cam-a1');
$check('33 pending item references new draft proposal only',
 $view['items'][0]['to_campaign_ref'],'cam-next1');
$check('34 second approval is explicitly nonexecuted',
 $view['items'][1]['state'],'approved_scope_demo_not_executed');
$check('35 third decision is rejection',
 $view['items'][2]['state'],'rejected_scope_demo');
$check('36 applied only to fictional scope',
 $view['items'][3]['state'],'scope_applied_synthetic_only');
$check('37 final review avoids leaking old/new refs',
 array_key_exists('from_campaign_ref',$view['items'][1]),false);
$check('38 every row marks review not real',
 count(array_filter($view['items'],static fn(array $row):bool=>
  $row['approval_is_real']!==false)),0);
$check('39 every row denies operator permissions',
 count(array_filter($view['items'],static fn(array $row):bool=>
  $row['operator_action_allowed']!==false)),0);
$check('40 every row has no provider action',
 count(array_filter($view['items'],static fn(array $row):bool=>
  $row['provider_action']!=='none_synthetic')),0);
$check('41 every row has no billing effect',
 count(array_filter($view['items'],static fn(array $row):bool=>
  $row['billing_effect']!=='none_synthetic')),0);
$check('42 pending aggregate accurate', $view['summary']['pending_synthetic'],1);
$check('43 raw audit count non-negative', $view['summary']['decisions_recorded_demo']>=7,true);
$check('44 no direct reviewer identities in output', 
 preg_match('/actor-demo-/',json_encode($view,JSON_THROW_ON_ERROR)),0);
$check('45 B request absent from A output', 
 str_contains(json_encode($view,JSON_THROW_ON_ERROR),'req-demo-private'),false);
$check('46 no A tenant leak to B review DTO',
 count($reviewView->build(
   $flow,$projector->build($ledger,$foreign,['cam-b1'],[]),$foreign,
   ['req-demo-pending','req-demo-private'])['items']),1);
$check('47 B sees its own pending request',
 $reviewView->build(
   $flow,$projector->build($ledger,$foreign,['cam-b1'],[]),$foreign,
   ['req-demo-pending','req-demo-private'])['items'][0]['request_ref'],
 'req-demo-private');
$check('48 duplicate inputs deduplicated',
 count($view['items']),4);
$check('49 malformed request ID blocked',
 $reviewView->build($flow,$scope,$t,['../../bad']),null);
$check('50 request refs capped at 30',
 $reviewView->build($flow,$scope,$t,array_fill(0,31,'req-demo-pending')),null);
$check('51 forged tenant denied',
 $reviewView->build($flow,$scope,'perf-demo-evil',['req-demo-pending']),null);
$check('52 tenant mismatch with DTO denied',
 $reviewView->build($flow,$scope,$foreign,['req-demo-pending']),null);
$check('53 missing DTO refused',
 $reviewView->build($flow,[],$t,['req-demo-pending']),null);
$bad=$scope;$bad['dispatch_enabled']=true;
$check('54 fake dispatch enable refused',$reviewView->build($flow,$bad,$t,[]),null);
$bad=$scope;$bad['billing_enabled']=true;
$check('55 fake billing enable refused',$reviewView->build($flow,$bad,$t,[]),null);
$bad=$scope;$bad['contracted']=true;
$check('56 fake real contract refused',$reviewView->build($flow,$bad,$t,[]),null);
$bad=$scope;$bad['authorization_eligible']=true;
$check('57 forged authority refused',$reviewView->build($flow,$bad,$t,[]),null);
$bad=$scope;$bad['ready_for_production']=true;
$check('58 forged production-ready refused',$reviewView->build($flow,$bad,$t,[]),null);
$check('59 no raw audit history exposed',array_key_exists('audit',$view),false);
$check('60 deterministic projection',$reviewView->build($flow,$scope,$t,$refs),$view);
putenv('PERF_F58_REVIEW_READ_LAB=0');
$check('61 preview disabled by own flag',$reviewView->build($flow,$scope,$t,$refs),null);
putenv('PERF_F58_REVIEW_READ_LAB=1');
putenv('APP_ENV=production');
$check('62 production never exposes mock review queue',$reviewView->build($flow,$scope,$t,$refs),null);
putenv('APP_ENV=testing');
putenv('PERF_F57_REVIEW_LAB=0');
$check('63 source review flag also required',$reviewView->build($flow,$scope,$t,$refs),null);
putenv('PERF_F57_REVIEW_LAB=1');
$check('64 reenabled review projection still read-only',
 $reviewView->build($flow,$scope,$t,$refs)['read_only'],true);
echo "PASS F58 $n tenant-filtered read-only review queue assertions".PHP_EOL;
