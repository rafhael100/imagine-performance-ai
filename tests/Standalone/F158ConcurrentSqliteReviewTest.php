<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;

if (PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F52_SCOPE_LAB')!=='1'
    ||getenv('PERF_F157_SQLITE_LAB')!=='1'
    ||getenv('PERF_F158_RACE_LAB')!=='1') {
    throw new RuntimeException('F158 concurrency CI only');
}
$root=(string)getenv('RUNNER_TEMP');
$dir=$root.'/f158-race';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('F158 temp dir unavailable');
$db=$dir.'/race.sqlite';
if(file_exists($db))throw new RuntimeException('F158 DB must be fresh');
$scope=new SyntheticManagedScopeLedger();
$a='perf-demo-aurora';$b='perf-demo-boreal';
$scope->setDemoCapacity($a,'google_ads',2,2);
$scope->setDemoCapacity($b,'google_ads',2,2);
$scope->includeCampaign($a,'google_ads','cam-f158-search');
$scope->includeCampaign($b,'google_ads','cam-f158-boreal');
$writer=new F157SyntheticSqliteReviewStore($db,$scope);
$n=0;
$check=static function(bool $value,string $desc)use(&$n):void{
    ++$n;if(!$value)throw new RuntimeException('F158 FAILED: '.$desc);
};
$worker=dirname(__DIR__,2).'/bin/lab/f158-sqlite-review-worker.php';
$env=[
    'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
    'PERF_F157_SQLITE_LAB'=>'1','PERF_F158_RACE_LAB'=>'1',
    'RUNNER_TEMP'=>$root,
    'PATH'=>(string)(getenv('PATH')?:'/usr/local/bin:/usr/bin:/bin'),
];
$launch=static function(string $op,string $tenant,string $ref,int $version,string $arg)
 use($db,$worker,$env):array{
    $proc=proc_open(
        [PHP_BINARY,$worker,$db,$op,$tenant,$ref,(string)$version,$arg],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
        $pipes,dirname(__DIR__,2),$env
    );
    if(!is_resource($proc))throw new RuntimeException('F158 worker failed to start');
    fclose($pipes[0]);
    return ['proc'=>$proc,'pipes'=>$pipes];
};
$finish=static function(array $p):array {
    $stdout=stream_get_contents($p['pipes'][1]);fclose($p['pipes'][1]);
    $error=stream_get_contents($p['pipes'][2]);fclose($p['pipes'][2]);
    if(proc_close($p['proc'])!==0) {
        throw new RuntimeException('F158 worker exited: '.substr((string)$error,0,120));
    }
    $result=json_decode((string)$stdout,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($result))throw new RuntimeException('F158 invalid worker response');
    return $result;
};
$one=static fn(string $op,string $tenant,string $ref,int $version,string $arg):array
    =>$finish($launch($op,$tenant,$ref,$version,$arg));
$race=static function(string $op,string $tenant,string $ref,int $version,array $args)
 use($launch,$finish):array{
    $workers=[];
    foreach($args as $arg)$workers[]=$launch($op,$tenant,$ref,$version,$arg);
    return array_map($finish,$workers);
};
$check($writer->submit($a,'rev-f158-decision','cam-f158-search','google_ads','Peça em teste')
    ==='submitted_demo','seed A fictional pending review');
$results=$race('decide',$a,'rev-f158-decision',1,['approve_mock','reject_mock']);
$statuses=array_column($results,'status');
$check(count(array_intersect($statuses,['approved_mock_only','rejected_mock_only']))===1,
    'competing decisions: exactly one terminal result');
$check(count(array_filter($statuses,static fn(string $s):bool=>$s==='stale_version_denied'))===1,
    'the losing process receives stale version instead of writing a second decision');
$check(count(array_filter($results,
    static fn(array $r):bool=>$r['action_authorized']!==false
        ||$r['billing_enabled']!==false
        ||$r['real_human_approval']!==false))===0,
    'neither concurrent process can activate real advertising');
$projection=$writer->project($a);
$row=$projection['reviews'][0];
$check($row['version']===2&&in_array($row['status'],
    ['approved_mock_only','rejected_mock_only'],true),
    'persisted review has one increment and one terminal mock status');
$check($projection['audit_entries_demo']===2,
    'exactly one decision event was recorded after review submission');
$check($one('decide',$a,'rev-f158-decision',1,'approve_mock')['status']
    ==='stale_version_denied','replay with old version still denied after subprocess restart');
$check($one('decide',$b,'rev-f158-decision',1,'approve_mock')['status']
    ==='not_found','tenant B cannot race against A review');

$check($writer->submit($a,'rev-f158-revise','cam-f158-search','google_ads','Rascunho para ajustes')
    ==='submitted_demo','seed second A review');
$check($writer->decide($a,'rev-f158-revise',1,'request_changes','reviewer-demo-ana')
    ==='changes_requested_demo','second review awaits fictional changes');
$revised=$race('revise',$a,'rev-f158-revise',2,[
    'Versão alternativa A','Versão alternativa B'
]);
$revisions=array_column($revised,'status');
$check(count(array_filter($revisions,
    static fn(string $s):bool=>$s==='resubmitted_demo'))===1,
    'only one concurrent resubmission can change title');
$check(count(array_filter($revisions,
    static fn(string $s):bool=>$s==='stale_version_denied'))===1,
    'other resubmission loses optimistic version');
$updated=$writer->project($a);
$latest=array_values(array_filter($updated['reviews'],
    static fn(array $r):bool=>$r['review_ref']==='rev-f158-revise'));
$check(count($latest)===1&&$latest[0]['version']===3
    &&$latest[0]['status']==='pending_demo',
    'resubmission increments version only once and returns to pending');
$check(in_array($latest[0]['title'],['Versão alternativa A','Versão alternativa B'],true),
    'stored title comes entirely from one winning request, not merged');
$check($updated['audit_entries_demo']===5,
    'single append for winning resubmission; no duplicate audit event');

$check($writer->submit($b,'rev-f158-boreal','cam-f158-boreal','google_ads','B independente')
    ==='submitted_demo','B can submit own review');
$check($one('decide',$b,'rev-f158-boreal',1,'approve_mock')['status']
    ==='approved_mock_only','B can decide its own review independently');
$check($writer->project($a)['review_count']===2
    &&$writer->project($b)['review_count']===1,
    'cross-tenant data remains separated after concurrent operations');
$check($writer->project($b)['audit_entries_demo']===2,
    'B audit trail has only B events');
$check($writer->project($a)['dispatch_enabled']===false
    &&$writer->project($a)['billing_enabled']===false,
    'both race outcomes preserve production NO-GO');

echo "F158 concurrent SQLite review CAS: {$n} PASS / 0 FAIL — NO-GO\n";
