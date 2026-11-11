<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F153OfflineReviewFixtureFactory.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F154SyntheticAttentionProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;
use Imagine\Performance\Domain\F153OfflineReviewFixtureFactory;
use Imagine\Performance\Domain\F154SyntheticAttentionProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F153_PARITY_LAB')!=='1'
 ||getenv('PERF_F154_ATTENTION_LAB')!=='1')
 throw new RuntimeException('F154 synthetic report CLI flags required');
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F154 FAIL: '.$why);
};
$l=new SyntheticManagedScopeLedger();$t='perf-demo-aurora';
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
$check(is_array($scope)&&is_array($reviews),
 'F154 consumes existing F52/F53 and F151/F153 fictional DTOs');
$projector=new F154SyntheticAttentionProjector();
$attention=$projector->build($scope,$reviews);
$check(is_array($attention)
 &&$attention['schema_version']==='perf.portal.attention.synthetic.v1',
 'attention projection has versioned synthetic contract');
$check($attention['summary']['alert_cards_demo']===4,
 'two fictional review alerts and two capacity alerts');
$check($attention['summary']['review_backlog_demo']===2
 &&$attention['summary']['capacity_attention_demo']===2,
 'backlog and capacity attention counted separately');
$check($attention['summary']['sla_verified']===false
 &&$attention['real_metrics_available']===false,
 'no real SLA or provider performance metrics are invented');
$check($attention['contracted']===false
 &&$attention['dispatch_enabled']===false
 &&$attention['billing_enabled']===false
 &&$attention['authorization_eligible']===false
 &&$attention['human_approval_verified']===false
 &&$attention['ready_for_production']===false,
 'operational effects remain disabled');
$google=$attention['channels'][0];$meta=$attention['channels'][1];
$check($google['id']==='google_ads'&&$google['pending_demo']===1
 &&$google['changes_requested_demo']===0,
 'Google Ads fictional pending review counted accurately');
$check($meta['id']==='meta'&&$meta['pending_demo']===0
 &&$meta['changes_requested_demo']===1,
 'Meta fictional requested changes counted accurately');
$check(count($google['alerts'])===2&&count($meta['alerts'])===2,
 'channel-specific attention cards are correctly separated');
$check($google['alerts'][1]['code']==='fictional_capacity_at_limit'
 &&$meta['alerts'][1]['code']==='fictional_capacity_at_limit',
 'paused managed campaigns count towards illustrative capacity limits');
$check(count(array_filter(
 array_merge($google['alerts'],$meta['alerts']),
 static fn(array $entry):bool=>$entry['action_enabled']!==false))===0,
 'attention cards never expose an enabled action');
$bad=$reviews;$bad['tenant_ref']='perf-demo-boreal';
$check($projector->build($scope,$bad)===null,
 'cross-tenant review projection denied');
$bad=$scope;$bad['dispatch_enabled']=true;
$check($projector->build($bad,$reviews)===null,
 'scope with active dispatcher rejected');
$bad=$reviews;$bad['billing_enabled']=true;
$check($projector->build($scope,$bad)===null,
 'review billing claim rejected');
$bad=$reviews;$bad['human_approval_verified']=true;
$check($projector->build($scope,$bad)===null,
 'review cannot claim true human approval');
$bad=$scope;$bad['channels'][1]['id']='google_ads';
$check($projector->build($bad,$reviews)===null,
 'duplicate channel in scope DTO rejected');
$bad=$scope;$bad['channels'][0]['campaigns']['remaining']=99;
$check($projector->build($bad,$reviews)===null,
 'inconsistent capacity remaining rejected');
$bad=$reviews;$bad['reviews'][0]['status']='approved_live';
$check($projector->build($scope,$bad)===null,
 'live approval status not accepted');
$bad=$reviews;$bad['reviews'][0]['action_authorized']=true;
$check($projector->build($scope,$bad)===null,
 'authorized review record rejected');
$bad=$reviews;$bad['review_count']=3;
$check($projector->build($scope,$bad)===null,
 'inconsistent review count refused');

$php=PHP_BINARY;
$generator=dirname(__DIR__,2).'/contracts/demo/f55-render-offline-preview.php';
$env=[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F55_RENDER_LAB'=>'1',
 'PERF_F151_APPROVAL_LAB'=>'1','PERF_F153_PARITY_LAB'=>'1',
 'PERF_F154_ATTENTION_LAB'=>'1',
];
$proc=proc_open([$php,$generator],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),$env);
if(!is_resource($proc))throw new RuntimeException('F154 HTML renderer process unavailable');
fclose($pipes[0]);$html=stream_get_contents($pipes[1]);fclose($pipes[1]);
$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($proc);
$check($exit===0&&is_string($html)&&str_contains($html,'id="attention-cards"'),
 'offline Reports panel has attention card mount point: '.substr((string)$error,0,180));
$check(str_contains($html,'function renderAttention()')
 &&str_contains($html,'renderAttention();renderView();'),
 'offline UI rerenders attention cards when channel filter changes');
preg_match('~<script id="preview-data" type="application/json">(.*?)</script>~s',
 (string)$html,$match);
$data=json_decode(trim($match[1]),true,32,JSON_THROW_ON_ERROR);
$check(($data['review_attention']['summary']['alert_cards_demo']??null)===4
 &&($data['review_attention']['summary']['review_backlog_demo']??null)===2,
 'offline HTML embeds actual capacity and review DTO alerts');
$check(($data['review_attention']['dispatch_enabled']??null)===false
 &&($data['review_attention']['billing_enabled']??null)===false
 &&($data['review_attention']['real_metrics_available']??null)===false,
 'offline attention DTO cannot claim live Ads, billing or real metrics');
echo "F154 attention panel: {$n} PASS / 0 FAIL — offline only / NO-GO\n";
