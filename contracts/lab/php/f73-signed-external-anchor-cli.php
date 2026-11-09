<?php
declare(strict_types=1);

/**
 * F1-73 CI-only signing and checking CLI. Keys exist in an EPHEMERAL runner
 * folder only. The "trusted" epoch and public key are also runner files,
 * NOT a WORM/TPM/KMS/hardware or production trust root.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('GITHUB_ACTIONS')!=='true'
   ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
   ||getenv('PERF_F71_INDEPENDENT_RESTORE_LAB')!=='1'
   ||getenv('PERF_F73_SIGNED_ANCHOR_LAB')!=='1'
   ||!extension_loaded('sodium')){
    throw new RuntimeException('F73 Ed25519 fixture only available in isolated CI');
}
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticPgIndependentRecoveryGuard.php';
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticSignedFixtureAnchorGuard.php';

use Imagine\Performance\Domain\SyntheticSignedFixtureAnchorGuard;
use Imagine\Performance\Domain\SyntheticPgIndependentRecoveryGuard;

$mode=$argv[1]??'';
$checkPath=static function(string $path):string{
    $root=realpath(sys_get_temp_dir());
    $parent=realpath(dirname($path));
    if(!is_string($root)||!is_string($parent)
       ||!str_starts_with($parent,$root.DIRECTORY_SEPARATOR)
       ||is_link($path)){
        throw new RuntimeException('F73 only uses new files in a CI temp directory');
    }
    return $path;
};
$writeNew=static function(string $path,string $data)use($checkPath):void{
    $checkPath($path);
    if(file_exists($path)||is_link($path)
       ||file_put_contents($path,$data,LOCK_EX)!==strlen($data)){
        throw new RuntimeException('F73 cannot overwrite/create mock key or anchor file');
    }
    chmod($path,0600);
};
$read=static function(string $path,int $max=25000)use($checkPath):string{
    $checkPath($path);
    if(!is_file($path)||!is_readable($path)||filesize($path)>$max){
        throw new RuntimeException('Invalid F73 CI-only key/anchor file');
    }
    $s=file_get_contents($path);
    if(!is_string($s))throw new RuntimeException('F73 file unreadable');
    return trim($s);
};
$db=static function(int $port,string $database,string $role,string $pw):PDO=>
 new PDO(
    "pgsql:host=127.0.0.1;port=$port;dbname=$database;connect_timeout=3",
    $role,$pw,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
 );
$primary=static fn(string $mode):PDO=>$db(5436,'perf_f66_ci',
    $mode==='admin'?'postgres':'perf_f66_tenant_a',
    $mode==='admin'?'ci-f66-db-only':'ci-f66-role-a-only'
);
$checkpoint=static fn(string $mode):PDO=>$db(5437,'perf_f71_ci',
    $mode==='b'?'perf_f71_tenant_b':'perf_f71_tenant_a',
    $mode==='b'?'ci-f71-role-b-only':'ci-f71-role-a-only'
);

if($mode==='keys'&&count($argv)===4){
    $pair=sodium_crypto_sign_keypair();
    $pub=sodium_bin2hex(sodium_crypto_sign_publickey($pair));
    $secret=sodium_bin2hex(sodium_crypto_sign_secretkey($pair));
    $writeNew($argv[2],$pub."\n");
    $writeNew($argv[3],$secret."\n");
    echo "PASS F73 ephemeral CI Ed25519 keypair generated (no production trust)\n";
}elseif($mode==='issue'&&count($argv)===5){
    $secret=$read($argv[2],256);
    $epoch=(int)$argv[4];
    $a=$primary('a');
    $b=$checkpoint('a');
    $guard=(new SyntheticPgIndependentRecoveryGuard())->inspect($a,$b);
    if(($guard['consistent_mock_only']??null)!==true
       ||($guard['tenant_role']??null)!=='perf_f66_tenant_a'){
        throw new RuntimeException('F73 refuses to sign inconsistent fixture state');
    }
    $rows=$b->query(
        'SELECT kind,ref,channel,old_ref,new_ref'
        .' FROM perf_f71_guard.checkpoints ORDER BY kind,ref'
    )->fetchAll(PDO::FETCH_ASSOC);
    if(!is_array($rows)||count($rows)<1||count($rows)>50){
        throw new RuntimeException('No valid F73 checkpoint claims to sign');
    }
    $envelope=SyntheticSignedFixtureAnchorGuard::signFixture(
        $rows,'perf_f66_tenant_a',$epoch,$secret
    );
    $writeNew($argv[3],$envelope);
    echo "PASS F73 signed ".$epoch." fixture claims=".count($rows)."\n";
}elseif($mode==='check'&&count($argv)===6){
    $pub=$read($argv[2],200);
    $envelope=$read($argv[3],25000);
    $minText=$read($argv[4],64);
    $minimum=preg_match('/^[0-9]{1,7}$/D',$minText)
        ?(int)$minText:null;
    $role=$argv[5];
    if(!in_array($role,['a','b','admin'],true)){
        throw new RuntimeException('Invalid CI fixture role selection');
    }
    $a=$primary($role);
    $b=$checkpoint($role==='b'?'b':'a');
    $result=(new SyntheticSignedFixtureAnchorGuard())->verify(
        $a,$b,$envelope,$pub,$minimum
    );
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
}else{
    throw new RuntimeException('Unsupported F73 fixture command');
}
