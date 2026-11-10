<?php
declare(strict_types=1);

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
 throw new RuntimeException('F58 review preview only runs as a fake-only CLI suite');
}
$l=new SyntheticManagedScopeLedger();
$f=new SyntheticScopeReviewWorkflow($l);
$p=new SyntheticReviewQueueProjector();
$a='perf-demo-a';$b='perf-demo-b';
$t=1800000000;
$hash=hash('sha256','f58 signed manifest is still just a mock');
$person='actor-demo-proposer';$reviewer='actor-demo-reviewer';
$operator='actor-demo-operator';
$n=0;
$check=static function(string $name,mixed $got,mixed $want)use(&$n):void{
 if($got!==$want)throw new RuntimeException(
   'FAIL F58 '.$name.' actual='.var_export($got,true)
   .' expected='.var_export($want,true));
 echo 'PASS F58 '.++$n.' '.$name.PHP_EOL;
};
$refs=[
 'req-demo-pending','req-demo-approved','req-demo-rejected',
 'req-demo-applied','req-demo-stale'
];
$check('01 scoped A capacity configured',$l->setDemoCapacity($a,'google_ads',5,5),'capacity_configured_demo');
$check('02 scoped B capacity configured',$l->setDemoCapacity($b,'google_ads',1,1),'capacity_configured_demo');
for($i=1;$i<=5;$i++){
 $check('synthetic A campaign '.$i,$l->includeCampaign($a,'google_ads','cam-f58-'.$i),'included_synthetic_only');
}
$check('B owns different resource',$l->includeCampaign($b,'google_ads','cam-f58-b'),'included_synthetic_only');
for($i=0;$i<5;$i++){
 $check('proposal '.$i,$f->propose($a,$refs[$i],$person,'cam-f58-'.($i+1),
    'cam-f58-new-'.($i+1),$hash,$t,$t+120),'proposed_synthetic_only');
}
$check('approval mock is never execution',$f->decide($a,$refs[1],$reviewer,$hash,true,true,$t+1),
 'approved_scope_demo_not_executed');
$check('rejection mock recorded',$f->decide($a,$refs[2],$reviewer,$hash,true,false,$t+1),
 'rejected_scope_demo');
$check('applied request first reviewed',$f->decide($a,$refs[3],$reviewer,$hash,true,true,$t+1),
 'approved_scope_demo_not_executed');
$check('applied request alters only fake scope',$f->applyMock($a,$refs[3],$operator,$hash,true,$t+2),
 'scope_applied_synthetic_only');
$check('stale request initially reviewed',$f->decide($a,$refs[4],$reviewer,$hash,true,true,$t+1),
 'approved_scope_demo_not_executed');
$check('stale source pause before fake apply',$l->pauseCampaign($a,'cam-f58-5'),
 'paused_synthetic_only');
$check('stale change rejected',$f->applyMock($a,$refs[4],$operator,$hash,true,$t+2),
 'stale_scope_review_denied');
$check('B separate request submitted',
 $f->propose($b,'req-demo-b-only',$person,'cam-f58-b','cam-f58-b-new',$hash,$t,$t+120),
 'proposed_synthetic_only');

$view=$p->build($f,$a,[$refs[0],$refs[1],$refs[2],$refs[3],$refs[4],
                         'req-demo-b-only',$refs[0]]);
$check('read-only projection exists',is_array($view),true);
$check('versioned review queue',$view['schema_version'],'perf.portal.review-queue.synthetic.v1');
$check('only synthetic source',$view['source'],'in_memory_review_fixture_only');
$check('A tenant ref',$view['tenant_ref'],$a);
foreach(['read_only'=>true,'contracted'=>false,'human_identity_verified'=>false,
 'authorization_eligible'=>false,'ready_for_production'=>false,
 'dispatch_enabled'=>false,'billing_enabled'=>false,
 'scope_changes_permitted'=>false] as $key=>$value){
 $check('fail-closed DTO '.$key,$view[$key],$value);
}
$check('reviewer is fixture only',$view['reviewed_by'],'fixture_only');
$check('all five A states projected',count($view['items']),5);
$check('duplicate hint does not duplicate review row',
 count(array_filter($view['items'],static fn(array $r):bool=>$r['request_ref']==='req-demo-pending')),1);
$check('pending count',$view['counts']['pending'],1);
$check('approved but NOT executed count',$view['counts']['approved_mock_no_action'],1);
$check('rejected count',$view['counts']['rejected_mock'],1);
$check('applied mock count',$view['counts']['applied_mock'],1);
$check('stale deny count',$view['counts']['stale_denied'],1);
$check('pending shows request reference',$view['items'][0]['request_ref'],'req-demo-pending');
$check('pending includes only synthetic old scope ID',$view['items'][0]['old_campaign_ref'],'cam-f58-1');
$check('pending includes only synthetic destination',$view['items'][0]['new_campaign_ref'],'cam-f58-new-1');
$check('approved case never marked executable',$view['items'][1]['approval_real'],false);
$check('approved case no provider action',$view['items'][1]['provider_action'],'none_synthetic');
$check('approved case does not expose actor identity',array_key_exists('reviewer_ref',$view['items'][1]),false);
$check('nonpending hides old campaign',$view['items'][1]['old_campaign_ref'],null);
$check('nonpending hides new campaign',$view['items'][1]['new_campaign_ref'],null);
$check('applied fake has no real provider permission',$view['items'][3]['provider_action'],'none_synthetic');
$check('stale case final state',$view['items'][4]['state'],'stale_scope_review_denied');
$check('audit aggregate is present',$view['audit_events_demo']>0,true);
$check('raw audit never shown',array_key_exists('audit',$view),false);
$check('same input yields deterministic view',
 $p->build($f,$a,[$refs[0],$refs[1],$refs[2],$refs[3],$refs[4],'req-demo-b-only',$refs[0]]),
 $view);
$json=json_encode($view,JSON_THROW_ON_ERROR);
$check('B request never leaked into A projection',str_contains($json,'req-demo-b-only'),false);
$check('A audit does not expose user names',str_contains($json,'actor-demo-reviewer'),false);
$check('no secrets/financial/provider account data',
 (bool)preg_match('/access_token|client_email|price_brl|media_spend|provider_account_id/i',$json),false);
$check('no approve/apply button or scope mutation in projection',
 (bool)preg_match('/apply_button|execute_url|approval_token|billing_effect/i',$json),false);

$bView=$p->build($f,$b,['req-demo-b-only','req-demo-pending']);
$check('B sees only B request',count($bView['items']),1);
$check('B queue lists pending own request',$bView['items'][0]['request_ref'],'req-demo-b-only');
$check('B queue has one pending count',$bView['counts']['pending'],1);
$check('B queue cannot include A pending old campaign',
 str_contains(json_encode($bView,JSON_THROW_ON_ERROR),'cam-f58-1'),false);
$check('unknown tenant has empty review queue',
 count($p->build($f,'perf-demo-c',$refs)['items']),0);
$check('malformed tenant rejected',$p->build($f,'../../bad',[]),null);
$check('malformed request rejected',$p->build($f,$a,['req-demo-valid','=HYPERLINK("evil")']),null);
$check('more than 30 request hints rejected',$p->build($f,$a,array_fill(0,31,'req-demo-pending')),null);
$check('missing request omitted rather than returned',
 count($p->build($f,$a,['req-demo-doesnotexist'])['items']),0);
$check('request ID list deduplicated',
 count($p->build($f,$a,[$refs[0],$refs[0]])['items']),1);
$check('review dashboard has no permission to apply',
 method_exists($p,'applyMock'),false);
putenv('PERF_F58_REVIEW_QUEUE_LAB=0');
$check('feature off closes queue',$p->build($f,$a,$refs),null);
putenv('PERF_F58_REVIEW_QUEUE_LAB=1');
putenv('APP_ENV=production');
$check('production cannot use fake review queue',$p->build($f,$a,$refs),null);
putenv('APP_ENV=testing');
putenv('PERF_F57_REVIEW_LAB=0');
$check('fake review feature off closes queue',$p->build($f,$a,$refs),null);
putenv('PERF_F57_REVIEW_LAB=1');
$check('queue returns only after restoring synthetic gates',
 $p->build($f,$a,[$refs[0]])['counts']['pending']??null,1);
$check('scope change never sends provider request',
 $l->capacity($a,'google_ads')['dispatch_enabled'],false);
echo "PASS F58 $n synthetic review queue read-only and tenant-isolation assertions".PHP_EOL;
