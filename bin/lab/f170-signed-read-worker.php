<?php
declare(strict_types=1);

/** F170 disposable subprocess for two-process replay collision proof. */
require dirname(__DIR__,2).'/src/Domain/Performance/F170NonceBoundPortalReadGateway.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;
use Imagine\Performance\Domain\F169SignedPortalReadGateway;
use Imagine\Performance\Domain\F170NonceBoundPortalReadGateway;

if(PHP_SAPI!=='cli'
 ||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PERF_F169_SIGNED_READ_LAB')!=='1'
 ||getenv('PERF_F170_NONCE_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('DATABASE_URL')!==false
 ||getenv('GOOGLE_ADS_ACCESS_TOKEN')!==false
 ||getenv('META_ADS_ACCESS_TOKEN')!==false
 ||count($argv)!==1){
 fwrite(STDERR,"F170 worker refused non-synthetic environment\n");
 exit(2);
}
$public=base64_decode((string)getenv('PERF_F170_SYNTHETIC_PUBLIC'),true);
if(!is_string($public)||strlen($public)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES){
 fwrite(STDERR,"F170 invalid synthetic public key\n");
 exit(2);
}
$env=(string)getenv('PERF_F170_TENANT_ROLE');
if(!in_array($env,['perf_f162_a','perf_f162_b'],true)){
 fwrite(STDERR,"F170 tenant login refused\n");
 exit(2);
}
$password=$env==='perf_f162_a'?'ci-only-perf-f162-a':'ci-only-perf-f162-b';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES=>false];
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
try{
 $tenantConnection=new PDO($dsn,$env,$password,$opts);
 $nonceConnection=new PDO($dsn,'perf_f170_gateway','ci-only-f170-gateway',$opts);
 $gateway=new F170NonceBoundPortalReadGateway(
     new F169SignedPortalReadGateway(
         new F166Pg16ReadOnlyDashboard($tenantConnection),$public),
     $nonceConnection
 );
 $payload=stream_get_contents(STDIN,4097);
 if(!is_string($payload)||strlen($payload)>4096){
     throw new RuntimeException('F170 bounded envelope required');
 }
 $result=$gateway->read($payload);
 echo json_encode([
     'status'=>$result['status'],
     'action_authorized'=>false,'can_bill'=>false,
     'has_report'=>is_array($result['report']),
 ],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
 echo '{"status":"worker_unknown_fail_closed","action_authorized":false,"can_bill":false,"has_report":false}'."\n";
}
