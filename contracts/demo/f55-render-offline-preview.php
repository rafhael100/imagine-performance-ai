<?php
declare(strict_types=1);

/**
 * F1-55: build-time-only HTML from the actual F1-53 synthetic projector.
 * Replaces ONLY the offline HTML's embedded JSON dataset and sends it to
 * stdout. No routes, files, cookies, provider access, login or live clients.
 */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F52_SCOPE_LAB')!=='1'
   ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
   ||getenv('PERF_F55_RENDER_LAB')!=='1'){
    throw new RuntimeException('F55 offline builder requires explicit synthetic CLI flags');
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
    $l,$t,
    ['cam-search-demo','cam-local-demo','cam-social-demo'],
    ['grp-search-demo','grp-local-demo','grp-social-demo']
);
if(!is_array($dto)||$dto['contracted']!==false
   ||$dto['authorization_eligible']!==false
   ||$dto['dispatch_enabled']!==false
   ||$dto['billing_enabled']!==false
   ||$dto['ready_for_production']!==false){
    throw new RuntimeException('F55 cannot embed an operational payload');
}
// Captions are fixed FICTIONAL strings; unrecognized references are refused.
$labels=[
 'cam-search-demo'=>['title'=>'Pesquisa de serviços','channel'=>'google_ads'],
 'cam-local-demo'=>['title'=>'Presença regional','channel'=>'google_ads'],
 'cam-social-demo'=>['title'=>'Reconhecimento da marca','channel'=>'meta'],
];
$cards=[];
foreach($dto['campaigns'] as $row){
    $ref=$row['ref'];
    if(!isset($labels[$ref])){
        throw new RuntimeException('Unknown fictional campaign label');
    }
    $cards[]=[
        'ref'=>$ref,'title'=>$labels[$ref]['title'],
        'channel'=>$labels[$ref]['channel'],'state'=>$row['state'],
    ];
}
$template=file_get_contents(__DIR__.'/performance-scope-preview-v1.html');
if(!is_string($template)||strlen($template)>80000){
    throw new RuntimeException('Missing or oversized offline preview template');
}
$pattern='~(<script id="preview-data" type="application/json">)(.*?)(</script>)~s';
if(preg_match_all($pattern,$template)!==1){
    throw new RuntimeException('Expected exactly one embedded JSON dataset');
}
preg_match($pattern,$template,$matches);
$previous=json_decode(trim($matches[2]),true,32,JSON_THROW_ON_ERROR);
if(!is_array($previous)
   ||$previous['schema_version']!==$dto['schema_version']
   ||$previous['contracted']!==false
   ||$previous['dispatch_enabled']!==false){
    throw new RuntimeException('Unsafe static preview template');
}
$ui=$dto;
$ui['campaigns']=$cards;
// These suggestions remain solely static UI captions, NOT real approvals.
$ui['approvals']=$previous['approvals'];
if(getenv('PERF_F151_APPROVAL_LAB')==='1'){
    if(getenv('PERF_F153_PARITY_LAB')==='1'){
        require_once dirname(__DIR__,2).'/src/Domain/Performance/F153OfflineReviewFixtureFactory.php';
        $projection=\Imagine\Performance\Domain\F153OfflineReviewFixtureFactory::build($l,$t);
    }else{
        require_once dirname(__DIR__,2).'/src/Domain/Performance/F151SyntheticCreativeReviewQueue.php';
        $reviews=new \Imagine\Performance\Domain\F151SyntheticCreativeReviewQueue($l);
        if($reviews->submit($t,'rev-search-demo','cam-search-demo','google_ads',
                'Revisar peça de pesquisa')!=='submitted_demo'
            ||$reviews->submit($t,'rev-social-demo','cam-social-demo','meta',
                'Revisar campanha social')!=='submitted_demo'){
            throw new RuntimeException('F151 failed to prepare fictional creative reviews');
        }
        if($reviews->decide($t,'rev-social-demo',1,'request_changes',
                'reviewer-demo-maria')!=='changes_requested_demo'){
            throw new RuntimeException('F151 demo review transition failed');
        }
        $projection=$reviews->project($t);
        }
    if(!is_array($projection)
       ||($projection['human_approval_verified']??null)!==false
       ||($projection['dispatch_enabled']??null)!==false
       ||($projection['billing_enabled']??null)!==false){
        throw new RuntimeException('F151 operational approval cannot be embedded');
    }
    $statusLabels=[
        'pending_demo'=>'Aguardando revisão fictícia',
        'changes_requested_demo'=>'Ajustes solicitados (fictício)',
        'approved_mock_only'=>'Aprovada apenas na simulação',
        'rejected_mock_only'=>'Rejeitada apenas na simulação',
    ];
    $ui['approvals']=[];
    foreach($projection['reviews'] as $item){
        $status=$statusLabels[$item['status']]??null;
        if(!is_string($status))throw new RuntimeException('F151 unknown review state');
        $ui['approvals'][]=[
            'title'=>$item['title'],
            'channel'=>$item['channel'],
            'status'=>$status,
            'review_ref'=>$item['review_ref'],
            'state'=>$item['status'],
            'approval_source'=>'none_real',
        ];
    }
    $ui['creative_reviews']=[
        'schema_version'=>$projection['schema_version'],
        'summary'=>$projection['summary'],
        'human_approval_verified'=>false,
        'dispatch_enabled'=>false,
        'billing_enabled'=>false,
    ];
    if(getenv('PERF_F154_ATTENTION_LAB')==='1'){
        require_once dirname(__DIR__,2).'/src/Domain/Performance/F154SyntheticAttentionProjector.php';
        $attention=(new \Imagine\Performance\Domain\F154SyntheticAttentionProjector())
            ->build($dto,$projection);
        if(!is_array($attention)
           ||$attention['dispatch_enabled']!==false
           ||$attention['billing_enabled']!==false
           ||$attention['real_metrics_available']!==false){
            throw new RuntimeException('F154 operational attention cannot be embedded');
        }
        $ui['review_attention']=$attention;
    }
}
$json=json_encode($ui,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
$rendered=preg_replace_callback(
    $pattern,static fn(array $m):string=>$m[1]."\n".$json."\n".$m[3],
    $template,1,$count
);
if(!is_string($rendered)||$count!==1){
    throw new RuntimeException('Could not safely render the offline snapshot');
}
echo $rendered;
