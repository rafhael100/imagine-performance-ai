<?php
declare(strict_types=1);
require __DIR__.'/../src/Domain/Administration/VerifiedPrivatePostgres.php';
use Imagine\Performance\Administration\VerifiedPrivatePostgres as V;
$valid='pgsql:host=database;dbname=performance_private;sslmode=verify-full;sslrootcert=/run/private/db-ca.pem';
$n=0;$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('PostgreSQL policy assertion '.($n+1));$n++;};
$ok(V::parse($valid)['port']==='5432');$ok(V::parse($valid.';port=5433')['port']==='5433');
foreach([$valid.';sslmode=disable',$valid.';sslmode=verify-full',str_replace('sslmode=verify-full','sslmode=disable',$valid),$valid.';password=secret',$valid.';options=-cwork_mem=1GB',$valid.';hostaddr=127.0.0.1',$valid.';',str_replace('host=database','host=database hostaddr=1.2.3.4',$valid),str_replace('sslmode=verify-full','sslmode=verify-fullx',$valid),str_replace('dbname=performance_private','dbname=prod\'',$valid),str_replace('/run/private/db-ca.pem','relative.pem',$valid),$valid.';port=0',$valid.';port=65536',$valid.';port=5432;port=5433'] as $bad){
 try{V::parse($bad);}catch(Throwable){$ok(true);continue;}$ok(false);
}
echo "OK: $n strict PostgreSQL duplicate/option/transport policy assertions\n";
