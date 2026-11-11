<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F151SyntheticCreativeReviewQueue.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F151SyntheticCreativeReviewQueue;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'){
 throw new RuntimeException('F151 synthetic reviews require guarded CLI');
}
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F151 FAIL: '.$label);
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(InvalidArgumentException $e){return true;}
};
$l=new SyntheticManagedScopeLedger();
$a='perf-demo-aurora';$b='perf-demo-boreal';
$check($l->setDemoCapacity($a,'google_ads',2,3)==='capacity_configured_demo',
 'A can configure fictional Google capacity');
$check($l->setDemoCapacity($a,'meta',1,2)==='capacity_configured_demo',
 'A can configure fictional Meta capacity');
$check($l->setDemoCapacity($b,'google_ads',1,2)==='capacity_configured_demo',
 'B can configure separate fictional capacity');
$check($l->includeCampaign($a,'google_ads','cam-f151-search')==='included_synthetic_only',
 'A has managed Google campaign');
$check($l->includeCampaign($a,'meta','cam-f151-social')==='included_synthetic_only',
 'A has managed Meta campaign');
$check($l->includeCampaign($b,'google_ads','cam-f151-boreal')==='included_synthetic_only',
 'B campaign belongs only to B');
$q=new F151SyntheticCreativeReviewQueue($l);
$check($q->submit($a,'rev-f151-search','cam-f151-search','google_ads',
 'Revisão de pesquisa')==='submitted_demo','A can submit fictional review');
$check($q->submit($a,'rev-f151-social','cam-f151-social','meta',
 'Revisão social')==='submitted_demo','Meta review can be submitted independently');
$check($q->submit($a,'rev-f151-search','cam-f151-search','google_ads',
 'Submissão repetida')==='duplicate_review_denied','duplicate review id denied');
$check($q->submit($b,'rev-f151-cross','cam-f151-search','google_ads',
 'Tentativa B')==='out_of_scope_or_wrong_channel_denied',
 'cross-tenant campaign spoofing blocked by existing scope ledger');
$check($q->submit($a,'rev-f151-wrong','cam-f151-social','google_ads',
 'Canal falso')==='out_of_scope_or_wrong_channel_denied',
 'channel cannot be forged independently of campaign');
$check($throws(static fn()=>$q->submit($a,'rev-f151-x','cam-f151-search','email',
 'Canal desconhecido')),'invalid provider channel rejected');
$check($throws(static fn()=>$q->submit($a,'rev-f151-x','cam-f151-search','google_ads',
 '<script>alert(1)</script>')),'markup in title rejected');
$check($q->decide($b,'rev-f151-search',1,'approve_mock','reviewer-demo-ana')
 ==='not_found','tenant B cannot review tenant A');
$check($q->decide($a,'rev-f151-search',0,'approve_mock','reviewer-demo-ana')
 ==='stale_version_denied','stale review version refused');
$check($q->decide($a,'rev-f151-search',1,'unknown','reviewer-demo-ana')
 ==='unknown_decision_denied','unsupported decision denied');
$check($q->decide($a,'rev-f151-search',1,'request_changes','reviewer-demo-ana')
 ==='changes_requested_demo','reviewer can request fictional changes');
$check($q->decide($a,'rev-f151-search',1,'approve_mock','reviewer-demo-ana')
 ==='stale_version_denied','optimistic revision prevents race after first review');
$check($q->revise($a,'rev-f151-search',2,'Pesquisa - versão revisada')
 ==='resubmitted_demo','changes requested allow synthetic resubmission');
$check($q->revise($a,'rev-f151-search',2,'Revisão obsoleta')
 ==='stale_version_denied','resubmission cannot reuse old revision');
$check($q->decide($a,'rev-f151-search',3,'approve_mock','reviewer-demo-ana')
 ==='approved_mock_only','terminal state is explicitly mock approval');
$check($q->decide($a,'rev-f151-search',4,'reject_mock','reviewer-demo-ana')
 ==='review_not_pending_denied','terminal mock state cannot be reviewed twice');
$check($q->revise($a,'rev-f151-search',4,'Revisão indevida')
 ==='revision_not_requested_denied','already approved mock cannot be reopened');
$check($q->decide($a,'rev-f151-social',1,'reject_mock','reviewer-demo-beto')
 ==='rejected_mock_only','independent Meta review can be rejected mock-only');
$project=$q->project($a);
$check(is_array($project)&&$project['review_count']===2
 &&$project['summary']['approved_mock_only']===1
 &&$project['summary']['rejected_mock_only']===1,
 'tenant scoped read-only projection summarizes review decisions');
$check($project['contracted']===false && $project['read_only']===true
 &&$project['authorization_eligible']===false
 &&$project['dispatch_enabled']===false
 &&$project['billing_enabled']===false
 &&$project['human_approval_verified']===false
 &&$project['ready_for_production']===false,
 'mock approval never becomes real Ads, billing, contract or human authority');
$check(count($q->auditFor($a))===6
 &&count($q->auditFor($b))===0,
 'append audit counts transitions and does not leak across tenants');
$check($q->project($b)['review_count']===0,
 'tenant B projection does not expose reviews of tenant A');

$check($q->submit($b,'rev-f151-boreal','cam-f151-boreal','google_ads',
 'Revisão B')==='submitted_demo','tenant B can have independent review');
$check($q->decide($b,'rev-f151-boreal',1,'request_changes','reviewer-demo-beto')
 ==='changes_requested_demo','B request-change before campaign retirement');
$check($l->releaseCampaign($b,'cam-f151-boreal',true)==='released_synthetic_only',
 'existing scope ledger formally removes B campaign using mock approval');
$check($q->revise($b,'rev-f151-boreal',2,'Sem escopo')
 ==='campaign_no_longer_in_scope_denied',
 'resubmission refuses campaign outside scoped management');

$php=PHP_BINARY;
$generator=dirname(__DIR__,2).'/contracts/demo/f55-render-offline-preview.php';
$env=[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F55_RENDER_LAB'=>'1',
 'PERF_F151_APPROVAL_LAB'=>'1',
];
$proc=proc_open([$php,$generator],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),$env);
if(!is_resource($proc))throw new RuntimeException('F151 cannot run offline preview generator');
fclose($pipes[0]);
$html=stream_get_contents($pipes[1]);fclose($pipes[1]);
$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($proc);
$check($exit===0&&is_string($html)&&str_contains($html,'preview-data'),
 'offline Portal renderer can embed fictional F151 reviews: '.substr((string)$stderr,0,200));
$matched=preg_match('~<script id="preview-data" type="application/json">(.*?)</script>~s',
 (string)$html,$m);
$check($matched===1,'offline HTML contains exactly one parsable dataset');
$data=json_decode(trim($m[1]),true,32,JSON_THROW_ON_ERROR);
$check(count($data['approvals']??[])===2
 &&($data['creative_reviews']['schema_version']??null)
    ==='perf.portal.creative-reviews.synthetic.v1',
 'F55 offline preview now renders queue-backed fictional approvals');
$check($data['authorization_eligible']===false
 &&$data['dispatch_enabled']===false
 &&$data['billing_enabled']===false
 &&$data['creative_reviews']['human_approval_verified']===false,
 'offline preview preserves every NO-GO flag');
$check(($data['approvals'][0]['approval_source']??null)==='none_real'
 &&($data['approvals'][1]['approval_source']??null)==='none_real',
 'no synthetic review is mislabeled as real human authorization');
echo "F151 creative review queue: {$n} PASS / 0 FAIL — offline only, NO-GO\n";
