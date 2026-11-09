<?php
declare(strict_types=1);

/**
 * F1-63. Produce an F1-62 demo bundle + an UNKEYED SHA-256 checksum
 * manifest. Detects accidental corruption only; NOT a signature, release
 * approval, authentication, trusted provenance or protection from an author
 * who changes both content and hashes.
 *
 * Emits the new local directory on stdout; NEVER overwrites existing files.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F62_SHOWCASE_LAB')!=='1'
   ||getenv('PERF_F63_INTEGRITY_LAB')!=='1'
   ||count($argv)!==2){
    throw new RuntimeException('F63 integrity demo disabled outside explicit synthetic CLI');
}
ob_start();
try{
    require __DIR__.'/f62-build-offline-showcase.php';
    $output=ob_get_clean();
}catch(Throwable $e){
    ob_end_clean();
    throw $e;
}
if(!is_string($output)||trim($output)===''){
    throw new RuntimeException('F62 synthetic demo build failed');
}
$target=trim($output);
if($target!==realpath($target)||!is_dir($target)||is_link($target)){
    throw new RuntimeException('F63 requires a newly created local directory');
}
$names=[
    'index.html','painel-campanhas.html','revisoes.html',
    'relatorio-capacidade.csv','marcas-contas.json','manifest.json',
];
$records=[];
foreach($names as $name){
    $path=$target.DIRECTORY_SEPARATOR.$name;
    if(is_link($path)||!is_file($path)||!is_readable($path)){
        throw new RuntimeException('Unsafe or missing F63 source file');
    }
    $bytes=filesize($path);
    if(!is_int($bytes)||$bytes<1||$bytes>200000){
        throw new RuntimeException('Invalid F63 file size');
    }
    $hash=hash_file('sha256',$path);
    if(!is_string($hash))throw new RuntimeException('F63 cannot hash file');
    $records[$name]=['sha256'=>$hash,'size_bytes'=>$bytes];
}
$lines=[];
foreach($records as $name=>$row){
    $lines[]=$name."\t".$row['size_bytes']."\t".$row['sha256'];
}
$integrity=[
    'schema_version'=>'perf.showcase.integrity.synthetic.v1',
    'source'=>'unkeyed_sha256_checksum_only',
    'tenant_ref'=>'perf-demo-aurora',
    'signer'=>null,
    'signed'=>false,
    'release_approved'=>false,
    'production_ready'=>false,
    'network_enabled'=>false,
    'external_dispatch_enabled'=>false,
    'files'=>$records,
    'aggregate_sha256'=>hash('sha256',implode("\n",$lines)."\n"),
    'warning'=>'Sem autenticidade; autor malicioso pode recalcular os hashes',
];
$data=json_encode($integrity,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
    |JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
$path=$target.DIRECTORY_SEPARATOR.'integrity.json';
if(is_link($path)||file_exists($path)){
    throw new RuntimeException('F63 refuses to replace any file');
}
if(file_put_contents($path,$data,LOCK_EX)!==strlen($data)){
    @unlink($path);
    throw new RuntimeException('F63 integrity file write failed');
}
echo $target.PHP_EOL;
