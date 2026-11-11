<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F153OfflinePreviewCsvParity.php';

use Imagine\Performance\Domain\F153OfflinePreviewCsvParity;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F55_RENDER_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F152_CSV_LAB')!=='1'
 ||getenv('PERF_F153_PARITY_LAB')!=='1') {
 throw new RuntimeException('F153 report parity CLI synthetic only');
}
$env=[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F55_RENDER_LAB'=>'1',
 'PERF_F151_APPROVAL_LAB'=>'1','PERF_F152_CSV_LAB'=>'1',
 'PERF_F153_PARITY_LAB'=>'1',
];
$run=static function(string $path)use($env):string{
 $root=dirname(__DIR__,2);
 $proc=proc_open([PHP_BINARY,$root.'/'.$path],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,$root,$env);
 if(!is_resource($proc))throw new RuntimeException('F153 report subprocess unavailable');
 fclose($pipes[0]);
 $out=stream_get_contents($pipes[1]);fclose($pipes[1]);
 $error=stream_get_contents($pipes[2]);fclose($pipes[2]);
 $status=proc_close($proc);
 if($status!==0||!is_string($out)) {
  throw new RuntimeException('F153 report builder error '.(string)$status.' '.substr((string)$error,0,140));
 }
 return $out;
};
$html=$run('contracts/demo/f55-render-offline-preview.php');
$csv=$run('contracts/demo/f152-export-creative-reviews.php');
$result=(new F153OfflinePreviewCsvParity())->compare($html,$csv);
if(!is_array($result)||$result['ok']!==true)
 throw new RuntimeException('F153 HTML and CSV drifted or violated synthetic contract');
echo json_encode([
 'schema_version'=>'perf.offline-review-parity.synthetic.v1',
 'status'=>'CONSISTENT_DEMO_ONLY',
 'count'=>$result['count'],'digest'=>$result['digest'],
 'dispatch_enabled'=>false,'billing_enabled'=>false,
 'approval_real'=>false,'ready_for_production'=>false,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
