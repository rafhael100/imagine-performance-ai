<?php
declare(strict_types=1);
header('Content-Type: application/json');header('Cache-Control: no-store');
try{
 if(PHP_SAPI!=='fpm-fcgi'||getenv('PERFORMANCE_PRIVATE_READ_ENABLED')!=='1'||getenv('APP_ENV')!=='staging'
  ||($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_SERVER['REQUEST_URI']??'')!=='/v1/admin/read'
  ||($_SERVER['IMAGINE_TLS_VERIFY']??'')!=='SUCCESS'||isset($_SERVER['HTTP_COOKIE'])||isset($_SERVER['HTTP_AUTHORIZATION']))throw new RuntimeException;
 $cert=rawurldecode($_SERVER['IMAGINE_TLS_CERT']??'');$fp=openssl_x509_fingerprint($cert,'sha256');if(!is_string($fp))throw new RuntimeException;
 $dsn=getenv('PERFORMANCE_PRIVATE_PG_DSN');if(!is_string($dsn)||!str_contains($dsn,'sslmode=verify-full')||str_contains($dsn,'password='))throw new RuntimeException;
 $passwordFile=getenv('PERFORMANCE_PRIVATE_PG_PASSWORD_FILE');$st=lstat($passwordFile);
 if(!$st||is_link($passwordFile)||!is_file($passwordFile)||!in_array($st['mode']&0777,[0400,0600],true)||$st['size']>4096)throw new RuntimeException;
 $db=new PDO($dsn,getenv('PERFORMANCE_PRIVATE_PG_USER'),trim(file_get_contents($passwordFile)),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
 foreach(['AdministrativeServiceEnvelope','AdministrativeServiceInbox','AdministrativeReadReceiver','PrivateAdministrativeService'] as $file)require __DIR__.'/../../src/Domain/Administration/'.$file.'.php';
 $result=(new Imagine\Performance\Administration\PrivateAdministrativeService($db,getenv('PERFORMANCE_PRIVATE_KEYRING_FILE')))->handle(file_get_contents('php://input',false,null,0,16385),$fp);
 echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable){http_response_code(403);echo '{"error":"private_read_denied"}';}
