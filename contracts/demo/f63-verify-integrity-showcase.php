<?php
declare(strict_types=1);

/**
 * F1-63 read-only verification of a LOCAL fake demonstration folder.
 * Only unkeyed SHA-256 checksums; does NOT establish signer authenticity.
 * Reject symlinks, missing/extra files, mismatched hashes, altered flags,
 * invalid JSON, remote HTML transport and corrupted scope counts.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F63_INTEGRITY_LAB')!=='1'||count($argv)!==2){
    fwrite(STDERR,"F63 verifier requires explicit fake-only CLI environment\n");
    exit(1);
}
$arg=$argv[1];
$fail=static function(string $why):never{
    fwrite(STDERR,'FAIL F63 verifier: '.$why.PHP_EOL);
    exit(2);
};
if(!is_string($arg)||$arg===''||is_link($arg)||!is_dir($arg)){
    $fail('unsafe or nonexistent local directory');
}
$root=realpath($arg);
if(!is_string($root)||!str_starts_with(basename($root),'perf-showcase-')){
    $fail('unexpected root');
}
$names=[
    'index.html','painel-campanhas.html','revisoes.html',
    'relatorio-capacidade.csv','marcas-contas.json','manifest.json',
];
$expected=$names;
$expected[]='integrity.json';
$present=[];
foreach(new DirectoryIterator($root) as $item){
    if($item->isDot())continue;
    if($item->isLink()||!$item->isFile()){
        $fail('symlink or unexpected directory');
    }
    $present[]=$item->getFilename();
}
sort($expected);sort($present);
if($present!==$expected)$fail('missing or unexpected files');
$read=static function(string $name,int $max=200000)use($root,$fail):string{
    $path=$root.DIRECTORY_SEPARATOR.$name;
    if(is_link($path)||!is_file($path))$fail('unsafe file '.$name);
    $len=filesize($path);
    if(!is_int($len)||$len<1||$len>$max)$fail('invalid length '.$name);
    $data=file_get_contents($path);
    if(!is_string($data)||strlen($data)!==$len)$fail('unreadable '.$name);
    return $data;
};
try{
    $info=json_decode($read('integrity.json',24000),true,32,JSON_THROW_ON_ERROR);
    $manifest=json_decode($read('manifest.json',24000),true,32,JSON_THROW_ON_ERROR);
}catch(Throwable){$fail('invalid integrity or F62 manifest JSON');}
if(!is_array($info)||!is_array($manifest))$fail('manifests must be objects');
$exact=static function(array $values,array $required):bool{
    $actual=array_keys($values);
    sort($actual);sort($required);
    return $actual===$required;
};
if(!$exact($info,[
    'schema_version','source','tenant_ref','signer','signed',
    'release_approved','production_ready','network_enabled',
    'external_dispatch_enabled','files','aggregate_sha256','warning'
])||$info['schema_version']!=='perf.showcase.integrity.synthetic.v1'
   ||$info['source']!=='unkeyed_sha256_checksum_only'
   ||$info['tenant_ref']!=='perf-demo-aurora'
   ||$info['signer']!==null
   ||$info['signed']!==false
   ||$info['release_approved']!==false
   ||$info['production_ready']!==false
   ||$info['network_enabled']!==false
   ||$info['external_dispatch_enabled']!==false
   ||!is_array($info['files'])
   ||!is_string($info['aggregate_sha256'])
   ||!preg_match('/^[a-f0-9]{64}$/D',$info['aggregate_sha256'])
   ||!is_string($info['warning'])
   ||!str_contains($info['warning'],'recalcular')){
    $fail('invalid integrity policy');
}
$entries=array_keys($info['files']);
if($entries!==$names)$fail('integrity file order/list differs from expected artifacts');
$lines=[];
foreach($names as $name){
    $r=$info['files'][$name]??null;
    if(!is_array($r)||!$exact($r,['sha256','size_bytes'])
       ||!is_string($r['sha256'])
       ||!preg_match('/^[a-f0-9]{64}$/D',$r['sha256'])
       ||!is_int($r['size_bytes'])||$r['size_bytes']<1
       ||$r['size_bytes']>200000){
        $fail('bad checksum or size record '.$name);
    }
    $bytes=$read($name);
    if(strlen($bytes)!==$r['size_bytes']
       ||!hash_equals($r['sha256'],hash('sha256',$bytes))){
        $fail('file changed '.$name);
    }
    $lines[]=$name."\t".$r['size_bytes']."\t".$r['sha256'];
}
if(!hash_equals($info['aggregate_sha256'],hash('sha256',implode("\n",$lines)."\n"))){
    $fail('aggregate checksum mismatch');
}
if(!$exact($manifest,[
    'schema_version','source','tenant_ref','read_only','contracted',
    'ready_for_production','provider_connected','billing_enabled',
    'dispatch_enabled','external_network_enabled','files','warning'
])||$manifest['schema_version']!=='perf.showcase.offline.synthetic.v1'
   ||$manifest['source']!=='local_fixtures_only'
   ||$manifest['tenant_ref']!==$info['tenant_ref']
   ||$manifest['read_only']!==true
   ||$manifest['contracted']!==false
   ||$manifest['ready_for_production']!==false
   ||$manifest['provider_connected']!==false
   ||$manifest['billing_enabled']!==false
   ||$manifest['dispatch_enabled']!==false
   ||$manifest['external_network_enabled']!==false
   ||$manifest['files']!==array_slice($names,0,5)){
    $fail('F62 release manifest NO-GO mismatch');
}
try{
    $account=json_decode($read('marcas-contas.json'),true,32,JSON_THROW_ON_ERROR);
}catch(Throwable){$fail('invalid fictional account JSON');}
if(!is_array($account)
   ||($account['schema_version']??null)!=='perf.portal.account-scope-preview.synthetic.v1'
   ||($account['tenant_ref']??null)!=='perf-demo-aurora'
   ||($account['contracted']??null)!==false
   ||($account['provider_connected']??null)!==false
   ||($account['billing_enabled']??null)!==false
   ||($account['dispatch_enabled']??null)!==false
   ||($account['ready_for_production']??null)!==false
   ||($account['authorization_eligible']??null)!==false
   ||!is_array($account['campaigns']??null)){
    $fail('unsafe fake account read model');
}
$main=$read('painel-campanhas.html');
$review=$read('revisoes.html');
$index=$read('index.html');
foreach([$main,$review,$index] as $page){
    if(!str_contains($page,"default-src 'none'")
       ||!str_contains($page,"connect-src 'none'")
       ||preg_match('/https?:\/\/|<form\b|<iframe\b|<script[^>]+src=/i',$page)){
        $fail('unsafe network/HTML in an offline page');
    }
}
if(!str_contains($index,"script-src 'none'")){
    $fail('index must not execute script');
}
foreach(array_slice($names,1,4) as $name){
    if(!str_contains($index,'href="'.$name.'"')){
        $fail('missing relative offline navigation');
    }
}
$parseBlock=static function(string $html,string $id)use($fail):array{
    $pattern='~<script id="'.preg_quote($id,'~').'" type="application/json">\s*(.*?)\s*</script>~s';
    if(preg_match_all($pattern,$html,$matches)!==1)$fail('missing or duplicate JSON block');
    try{
        $v=json_decode($matches[1][0],true,32,JSON_THROW_ON_ERROR);
    }catch(Throwable){$fail('invalid embedded JSON');}
    if(!is_array($v))$fail('embedded payload must be object');
    return $v;
};
$mainDto=$parseBlock($main,'preview-data');
$queue=$parseBlock($review,'review-data');
if(($mainDto['tenant_ref']??null)!=='perf-demo-aurora'
   ||($queue['tenant_ref']??null)!=='perf-demo-aurora'
   ||($mainDto['dispatch_enabled']??null)!==false
   ||($mainDto['billing_enabled']??null)!==false
   ||($mainDto['ready_for_production']??null)!==false
   ||($queue['dispatch_enabled']??null)!==false
   ||($queue['billing_enabled']??null)!==false
   ||($queue['scope_changes_permitted']??null)!==false
   ||!is_array($mainDto['campaigns']??null)
   ||!is_array($account['campaigns']??null)
   ||count($mainDto['campaigns'])!==count($account['campaigns'])){
    $fail('cross-file demo tenant, capacity or NO-GO mismatch');
}
$csv=$read('relatorio-capacidade.csv');
if(!str_starts_with($csv,"\xef\xbb\xbf")
   ||!str_contains($csv,'SEM FATURAMENTO OU EXECUCAO')){
    $fail('CSV not marked synthetic/non-billing');
}
echo 'PASS F63 bundle integrity verified (unkeyed SHA-256 only, no authenticity): '.$root.PHP_EOL;
