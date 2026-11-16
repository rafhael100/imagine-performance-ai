<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use PDO;
use RuntimeException;
/** Strict transport bootstrap. Config strings are never evidence of negotiated TLS. */
final class VerifiedPrivatePostgres {
 /** @return array{host:string,port:string,dbname:string,sslmode:string,sslrootcert:string} */
 public static function parse(string $dsn):array {
  if(strlen($dsn)>1024||!str_starts_with($dsn,'pgsql:'))throw new RuntimeException('Private database configuration denied');
  $options=[];foreach(explode(';',substr($dsn,6)) as $pair){
   if(!preg_match('/\A(host|port|dbname|sslmode|sslrootcert)=([^;\s\x00\x27\x22]+)\z/D',$pair,$m)||isset($options[$m[1]]))throw new RuntimeException('Private database configuration denied');
   $options[$m[1]]=$m[2];
  }
  if(count($options)<4||!isset($options['host'],$options['dbname'],$options['sslmode'],$options['sslrootcert'])||
   !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]{0,127}\z/D',$options['host'])||
   !preg_match('/\A[a-zA-Z0-9_]{1,63}\z/D',$options['dbname'])||$options['sslmode']!=='verify-full'||
   !preg_match('/\A\/[a-zA-Z0-9_.\/-]{1,200}\z/D',$options['sslrootcert']))throw new RuntimeException('Private database configuration denied');
  $options['port']??='5432';if(!ctype_digit($options['port'])||(int)$options['port']<1||(int)$options['port']>65535)throw new RuntimeException('Private database configuration denied');
  return $options;
 }
 public static function connect(string $dsn,string $user,string $password):PDO {
  $c=self::parse($dsn);
  if(!preg_match('/\A[a-zA-Z0-9_]{1,63}\z/D',$user)||strlen($password)<16||strlen($password)>4096||str_contains($password,"\0")||is_link($c['sslrootcert'])||!is_file($c['sslrootcert'])||!is_readable($c['sslrootcert']))throw new RuntimeException('Private database configuration denied');
  $canonical='pgsql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['dbname'].';sslmode=verify-full;sslrootcert='.$c['sslrootcert'].';connect_timeout=3';
  $db=new PDO($canonical,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3,PDO::ATTR_PERSISTENT=>false,PDO::ATTR_EMULATE_PREPARES=>false]);
  $s=$db->query('SELECT ssl,(SELECT current_database()) AS db,(SELECT session_user) AS login FROM pg_stat_ssl WHERE pid=pg_backend_pid()')->fetch(PDO::FETCH_ASSOC);
  if(!$s||$s['ssl']!==true||$s['db']!==$c['dbname']||$s['login']!==$user)throw new RuntimeException('Private database transport denied');
  $db->exec("SET statement_timeout='3000ms'; SET lock_timeout='1500ms'");return $db;
 }
}
