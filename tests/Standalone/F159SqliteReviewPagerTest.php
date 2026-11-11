<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F157_SQLITE_LAB')!=='1'
 ||getenv('PERF_F159_PAGING_LAB')!=='1') {
 throw new RuntimeException('F159 pages may only be tested in disposable CI');
}
$root=(string)getenv('RUNNER_TEMP');
$dir=$root.'/f159-pages';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('F159 temp dir missing');
$db=$dir.'/pages.sqlite';
if(file_exists($db))throw new RuntimeException('F159 database not isolated');
$scope=new SyntheticManagedScopeLedger();
$a='perf-demo-aurora';$b='perf-demo-boreal';
$scope->setDemoCapacity($a,'google_ads',2,3);
$scope->setDemoCapacity($a,'meta',2,3);
$scope->setDemoCapacity($b,'google_ads',2,3);
$scope->includeCampaign($a,'google_ads','cam-f159-google');
$scope->includeCampaign($a,'meta','cam-f159-meta');
$scope->includeCampaign($b,'google_ads','cam-f159-boreal');
$store=new F157SyntheticSqliteReviewStore($db,$scope);
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void {
 ++$n;if(!$ok)throw new RuntimeException('F159 FAILED: '.$why);
};
for($i=1;$i<=12;$i++){
 $review=sprintf('rev-f159-%02d',$i);
 $channel=$i%2?'google_ads':'meta';
 $campaign=$channel==='google_ads'?'cam-f159-google':'cam-f159-meta';
 $check($store->submit($a,$review,$campaign,$channel,
  'Revisão fictícia '.sprintf('%02d',$i))==='submitted_demo',
  'seed fictional review '.$i);
}
$check($store->submit($b,'rev-f159-01','cam-f159-boreal',
 'google_ads','B separada')==='submitted_demo',
 'B may use same ref without seeing A data');
$check($store->decide($a,'rev-f159-02',1,'request_changes','reviewer-demo-ana')
 ==='changes_requested_demo','seed changes-requested Meta');
$check($store->decide($a,'rev-f159-04',1,'approve_mock','reviewer-demo-ana')
 ==='approved_mock_only','seed approved-mock Meta');
$check($store->decide($a,'rev-f159-06',1,'reject_mock','reviewer-demo-ana')
 ==='rejected_mock_only','seed rejected-mock Meta');

$page=$store->page($a,null,null,5);
$check(is_array($page)&&$page['count']===5&&$page['has_more']===true,
 'first page has five fictional rows and continuation');
$check($page['items'][0]['review_ref']==='rev-f159-01'
 &&$page['items'][4]['review_ref']==='rev-f159-05'
 &&$page['next_cursor']==='rev-f159-05',
 'keyset cursor points to last emitted reference');
$check($page['iam_verified']===false&&$page['s2s_verified']===false
 &&$page['human_approval_verified']===false
 &&$page['dispatch_enabled']===false&&$page['billing_enabled']===false
 &&$page['ready_for_production']===false,
 'no page can claim Portal authority, real Ads or billing');
$second=$store->page($a,null,null,5,$page['next_cursor']);
$check($second['count']===5&&$second['has_more']===true
 &&$second['next_cursor']==='rev-f159-10',
 'second page advances cursor without duplicate');
$third=$store->page($a,null,null,5,$second['next_cursor']);
$check($third['count']===2&&$third['has_more']===false
 &&$third['next_cursor']===null,
 'last page has two rows and terminates');
$refs=array_merge(
 array_column($page['items'],'review_ref'),
 array_column($second['items'],'review_ref'),
 array_column($third['items'],'review_ref'));
$check(count($refs)===12&&count(array_unique($refs))===12,
 'all twelve reviews included exactly once across page boundaries');
$check($store->page($a,null,null,5,'rev-f159-12')['count']===0,
 'cursor beyond final review returns empty page with NO-GO metadata');

$google=$store->page($a,null,'google_ads',10);
$check($google['count']===6 && $google['has_more']===false,
 'Google channel filter returns only six independent reviews');
$check(count(array_filter($google['items'],static fn(array $r):bool=>
 $r['channel']!=='google_ads'))===0,'Google filter cannot return Meta data');
$meta=$store->page($a,null,'meta',5);
$check($meta['count']===5&&$meta['has_more']===true,
 'Meta filter paginates six results');
$metaTail=$store->page($a,null,'meta',5,$meta['next_cursor']);
$check($metaTail['count']===1&&$metaTail['has_more']===false,
 'Meta-specific continuation gets remaining record');

$requested=$store->page($a,'changes_requested_demo',null,10);
$check($requested['count']===1
 &&$requested['items'][0]['review_ref']==='rev-f159-02',
 'review status filter isolates fictitious requested changes');
$both=$store->page($a,'approved_mock_only','meta',10);
$check($both['count']===1&&$both['items'][0]['review_ref']==='rev-f159-04',
 'combined channel/status filters match exact fake approval');
$empty=$store->page($a,'approved_mock_only','google_ads',5);
$check($empty['count']===0&&!$empty['has_more'],
 'nonmatching combined filters return safe empty page');

$bPage=$store->page($b,null,null,5);
$check($bPage['count']===1&&$bPage['items'][0]['title']==='B separada',
 'tenant B sees only its own version of same reference');
$check($store->page('perf-demo-nonexistent')!==null
 &&$store->page('perf-demo-nonexistent')['count']===0,
 'valid tenant without data obtains an empty synthetic page');

$check($store->page($a,null,null,0)===null
 &&$store->page($a,null,null,11)===null,
 'unbounded/zero page size refused');
$check($store->page($a,'approved_live')===null,
 'live provider approval status never accepted');
$check($store->page($a,null,'tiktok')===null,
 'unsupported advertising channel refused');
$check($store->page($a,null,null,5,'rev-%SQL')===null,
 'unsafe cursor value denied before SQL');
$check($store->page('perf-demo-x OR 1=1')===null,
 'malicious tenant identifier refused before SQL');
$check($store->page($a,null,null,5,'rev-f159-05')===$second,
 'deterministic cursor-based page survives new request');

$restarted=new F157SyntheticSqliteReviewStore($db,$scope);
$check($restarted->page($a,null,null,5)===$page,
 'restart of SQLite connection preserves stable first page');
$check($restarted->page($b,null,null,5)===$bPage,
 'restart preserves tenant separation');

// Campaign removed from currently managed scope must not be published via
// a new fictional page: no implicit reauthorization of archived campaigns.
$check($scope->releaseCampaign($b,'cam-f159-boreal',true)
 ==='released_synthetic_only','retire B mock campaign from managed scope');
$check($restarted->page($b,null,null,5)===null,
 'paged reader fails closed on a campaign that left tenant scope');
$check($restarted->page($a,null,null,5)===$page,
 'B retirement does not alter tenant A read contract');

echo "F159 SQLite keyset pagination: {$n} PASS / 0 FAIL — NO-GO\n";
