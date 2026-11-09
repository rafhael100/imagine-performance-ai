<?php
declare(strict_types=1);

/**
 * F1-62: build a FOUR-PART OFFLINE MVP DEMO in a new local folder.
 * Requires *all* offline feature flags and fake-only PHP CLI. It NEVER
 * publishes, calls a provider, connects to customers or changes a project.
 *
 * Usage (example): php contracts/demo/f62-build-offline-showcase.php
 *                       /tmp/perf-showcase-demo
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F52_SCOPE_LAB')!=='1'
  ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
  ||getenv('PERF_F55_RENDER_LAB')!=='1'
  ||getenv('PERF_F56_CSV_LAB')!=='1'
  ||getenv('PERF_F57_REVIEW_LAB')!=='1'
  ||getenv('PERF_F58_REVIEW_QUEUE_LAB')!=='1'
  ||getenv('PERF_F59_UI_LAB')!=='1'
  ||getenv('PERF_F60_ACCOUNT_SCOPE_LAB')!=='1'
  ||getenv('PERF_F61_COMBINED_READ_LAB')!=='1'
  ||getenv('PERF_F62_SHOWCASE_LAB')!=='1'
  ||count($argv)!==2){
    fwrite(STDERR,"F62 export blocked outside fake-only PHP CLI\n");
    exit(1);
}
$targetArg=$argv[1];
$base=basename($targetArg);
if(!preg_match('/^perf-showcase-[a-z0-9-]{1,50}$/D',$base)){
    throw new RuntimeException('F62 target must be a new perf-showcase-* directory');
}
$parent=realpath(dirname($targetArg));
if(!is_string($parent)||!is_dir($parent)||!is_writable($parent)){
    throw new RuntimeException('F62 target parent must exist and be writable');
}
$target=rtrim($parent,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$base;
if(file_exists($target)||is_link($target)){
    throw new RuntimeException('F62 NEVER overwrites existing files or directories');
}
$run=static function(string $file):string{
    ob_start();
    try{
        require __DIR__.DIRECTORY_SEPARATOR.$file;
        $output=ob_get_clean();
    }catch(Throwable $e){
        ob_end_clean();
        throw $e;
    }
    if(!is_string($output)||strlen($output)>200000){
        throw new RuntimeException('F62 invalid or oversized fake source artifact');
    }
    return $output;
};
$index=file_get_contents(__DIR__.'/f62-offline-showcase-index.html');
if(!is_string($index)||strlen($index)>40000){
    throw new RuntimeException('F62 index missing or oversized');
}
$files=[
    'index.html'=>$index,
    'painel-campanhas.html'=>$run('f55-render-offline-preview.php'),
    'revisoes.html'=>$run('f59-render-review-queue.php'),
    'relatorio-capacidade.csv'=>$run('f56-export-capacity-csv.php'),
    'marcas-contas.json'=>$run('f62-generate-account-overview.php'),
];
$account=json_decode($files['marcas-contas.json'],true,32,JSON_THROW_ON_ERROR);
if(!is_array($account)
   ||$account['schema_version']!=='perf.portal.account-scope-preview.synthetic.v1'
   ||$account['tenant_ref']!=='perf-demo-aurora'
   ||$account['provider_connected']!==false
   ||$account['contracted']!==false
   ||$account['dispatch_enabled']!==false
   ||$account['billing_enabled']!==false
   ||$account['authorization_eligible']!==false
   ||$account['ready_for_production']!==false){
    throw new RuntimeException('F62 unsafe account summary refused');
}
foreach(['painel-campanhas.html','revisoes.html'] as $name){
    if(!str_contains($files[$name],"connect-src 'none'")
        ||!str_contains($files[$name],'application/json')){
        throw new RuntimeException('F62 unsafe HTML fixture '.$name);
    }
}
$manifest=[
    'schema_version'=>'perf.showcase.offline.synthetic.v1',
    'source'=>'local_fixtures_only',
    'tenant_ref'=>'perf-demo-aurora',
    'read_only'=>true,
    'contracted'=>false,
    'ready_for_production'=>false,
    'provider_connected'=>false,
    'billing_enabled'=>false,
    'dispatch_enabled'=>false,
    'external_network_enabled'=>false,
    'files'=>array_keys($files),
    'warning'=>'DEMONSTRACAO: nenhum cliente, verba, anuncio ou aprovacao real',
];
$files['manifest.json']=json_encode($manifest,JSON_PRETTY_PRINT
    |JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(!mkdir($target,0700)){
    throw new RuntimeException('F62 cannot create new isolated output folder');
}
$written=[];
try{
    foreach($files as $name=>$bytes){
        $path=$target.DIRECTORY_SEPARATOR.$name;
        if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)){
            throw new RuntimeException('F62 failed writing self-contained artifact '.$name);
        }
        $written[]=$path;
    }
}catch(Throwable $e){
    foreach($written as $file)@unlink($file);
    @rmdir($target);
    throw $e;
}
echo $target.PHP_EOL;
