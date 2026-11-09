<?php
declare(strict_types=1);

/** F1-56 output: CSV or JSON from the single F1-53 synthetic fixture. */
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticOfflineReportExporter.php';

use Imagine\Performance\Domain\SyntheticOfflineReportExporter;

$format=$argv[1]??'json';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F52_SCOPE_LAB')!=='1'
   ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
   ||getenv('PERF_F56_EXPORT_LAB')!=='1'
   ||!in_array($format,['json','csv'],true)){
    fwrite(STDERR,"F56 only exports fictional reports under explicit CLI lab flags\n");
    exit(1);
}
ob_start();
require __DIR__.'/f53-generate-scope-preview.php';
$raw=ob_get_clean();
if(!is_string($raw)){
    throw new RuntimeException('Cannot retrieve synthetic PHP scope DTO');
}
$dto=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if(!is_array($dto))throw new RuntimeException('Synthetic scope DTO required');
$result=(new SyntheticOfflineReportExporter())->export(
    $dto,'perf-demo-aurora',$format
);
if(!is_string($result))throw new RuntimeException('F56 exporter denied test fixture');
echo $result;
