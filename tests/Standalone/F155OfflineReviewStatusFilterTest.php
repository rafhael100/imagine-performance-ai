<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F155_FILTER_LAB')!=='1'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'){
 throw new RuntimeException('F155 synthetic UI tests only');
}
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F155 FAILED: '.$label);
};
$root=dirname(__DIR__,2);
$static=(string)file_get_contents($root.'/contracts/demo/performance-scope-preview-v1.html');
$check(str_contains($static,'data-review-filter="all"'),
 'offline static UI exposes all-review filter');
foreach(['pending_demo','changes_requested_demo','approved_mock_only','rejected_mock_only'] as $state){
 $check(str_contains($static,'data-review-filter="'.$state.'"'),
  'review filter supports explicit mock status '.$state);
}
$check(str_contains($static,'aria-label="Filtrar revisões fictícias"'),
 'review filter has accessible semantic label');
$check(str_contains($static,"reviewFilter==='all'||x.state===reviewFilter"),
 'UI filters only by the state field of synthetic DTO');
$check(str_contains($static,"renderApprovals();"),
 'changing review filter rerenders list');
$check(str_contains($static,"x.setAttribute('aria-pressed',String(active))"),
 'review filter buttons update selected accessibility state');
$check(!str_contains($static,'onclick=')
 &&str_contains($static,"connect-src 'none'")
 &&str_contains($static,"form-action 'none'"),
 'CSP remains offline and user interaction is local only');
$check(substr_count($static,'data-review-filter=')===5,
 'static preview has exactly five explicit filter buttons');

$extract=static function(string $html):array {
 if(preg_match_all('~<script id="preview-data" type="application/json">(.*?)</script>~s',
  $html,$m)!==1)throw new RuntimeException('F155 invalid embedded JSON');
 return json_decode(trim($m[1][0]),true,32,JSON_THROW_ON_ERROR);
};
$staticDto=$extract($static);
$check(($staticDto['dispatch_enabled']??null)===false
 &&($staticDto['billing_enabled']??null)===false
 &&($staticDto['authorization_eligible']??null)===false
 &&($staticDto['ready_for_production']??null)===false,
 'bundled static demo remains NO-GO');
$check(($staticDto['approvals'][0]['state']??null)==='pending_demo'
 &&($staticDto['approvals'][1]['state']??null)==='changes_requested_demo',
 'bundled static demo has both searchable mock states');
$check(count(array_filter($staticDto['approvals'],
  static fn(array $row):bool=>$row['state']==='pending_demo'))===1,
 'filter pending would return one fictional item');
$check(count(array_filter($staticDto['approvals'],
  static fn(array $row):bool=>$row['state']==='changes_requested_demo'))===1,
 'filter changes would return one fictional item');

$generator=$root.'/contracts/demo/f55-render-offline-preview.php';
$env=[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F55_RENDER_LAB'=>'1',
 'PERF_F151_APPROVAL_LAB'=>'1','PERF_F153_PARITY_LAB'=>'1',
 'PERF_F154_ATTENTION_LAB'=>'1','PERF_F155_FILTER_LAB'=>'1',
];
$proc=proc_open([PHP_BINARY,$generator],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,$root,$env);
if(!is_resource($proc))throw new RuntimeException('F155 offline HTML builder missing');
fclose($pipes[0]);
$html=stream_get_contents($pipes[1]);fclose($pipes[1]);
$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($proc);
$check($exit===0&&is_string($html)
 &&str_contains($html,'data-review-filter="pending_demo"'),
 'PHP generator preserves interactive review controls: '.substr((string)$error,0,140));
$dto=$extract((string)$html);
$check(($dto['approvals'][0]['state']??null)==='pending_demo'
 &&($dto['approvals'][1]['state']??null)==='changes_requested_demo',
 'live synthetic F151 queue states are embedded into HTML filters');
$check(($dto['approvals'][0]['approval_source']??null)==='none_real'
 &&($dto['approvals'][1]['approval_source']??null)==='none_real',
 'no reviewer mock state becomes human authorization');
$check(($dto['review_attention']['summary']['review_backlog_demo']??null)===2,
 'review state filter coexists with F154 attention dashboard');
$check(($dto['dispatch_enabled']??null)===false
 &&($dto['billing_enabled']??null)===false,
 'interactive filtering never enables provider or billing');
echo "F155 offline review status filter: {$n} PASS / 0 FAIL — NO-GO\n";
