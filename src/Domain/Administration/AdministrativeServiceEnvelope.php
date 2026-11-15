<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use InvalidArgumentException;
use RuntimeException;
/** Versioned isolated S2S candidate. No HTTP endpoint or operational credential. */
final class AdministrativeServiceEnvelope
{
 public const VERSION='imagine.performance.admin.s2s.v1';
 public const OPERATIONS=['dashboard.read','clients.read','reports.read'];
 public static function issue(string $service,string $keyId,string $company,string $operation,string $nonce,string $idempotency,string $body,int $now,string $secret):array
 {
  $e=['version'=>self::VERSION,'issuer'=>$service,'audience'=>'imagine-performance-admin','key_id'=>$keyId,
   'company_id'=>$company,'operation'=>$operation,'nonce'=>$nonce,'idempotency_key'=>$idempotency,
   'correlation_id'=>$idempotency,'body_sha256'=>hash('sha256',$body),'issued_at'=>$now,'expires_at'=>$now+60];
  $e['signature']=hash_hmac('sha256',self::canonical($e),$secret);return $e;
 }
 /** resolve returns server-owned identity: secret, active, expires_at, companies, operations. */
 public static function verify(array $e,string $body,int $now,callable $resolve):array
 {
  $fields=['version','issuer','audience','key_id','company_id','operation','nonce','idempotency_key','correlation_id','body_sha256','issued_at','expires_at','signature'];
  $keys=array_keys($e);sort($keys);sort($fields);if($keys!==$fields)throw new InvalidArgumentException('Invalid envelope fields');
  foreach(['issuer','key_id','company_id','nonce','idempotency_key','correlation_id'] as $k)
   if(!is_string($e[$k])||!preg_match('/\A[A-Za-z0-9_-]{8,96}\z/D',$e[$k]))throw new InvalidArgumentException('Invalid service scope');
  if($e['version']!==self::VERSION||$e['audience']!=='imagine-performance-admin'||!in_array($e['operation'],self::OPERATIONS,true)
   ||!is_int($e['issued_at'])||!is_int($e['expires_at'])||$e['issued_at']>$now+5||$e['issued_at']<=$now-60
   ||$e['expires_at']<=$now||$e['expires_at']-$e['issued_at']>60||$e['expires_at']<=$e['issued_at']
   ||!is_string($e['body_sha256'])||!hash_equals(hash('sha256',$body),$e['body_sha256'])
   ||!is_string($e['signature'])||!preg_match('/\A[a-f0-9]{64}\z/D',$e['signature']))throw new InvalidArgumentException('Invalid signed request');
  $identity=$resolve($e['issuer'],$e['key_id']);
  if(!is_array($identity)||($identity['active']??false)!==true||($identity['expires_at']??0)<=$now
   ||!is_string($identity['secret']??null)||strlen($identity['secret'])<32
   ||!in_array($e['company_id'],$identity['companies']??[],true)||!in_array($e['operation'],$identity['operations']??[],true))throw new RuntimeException('Service authorization denied');
  $unsigned=$e;unset($unsigned['signature']);
  if(!hash_equals(hash_hmac('sha256',self::canonical($unsigned),$identity['secret']),$e['signature']))throw new RuntimeException('Signature denied');
  return $e;
 }
 private static function canonical(array $e):string {ksort($e,SORT_STRING);return json_encode($e,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
}
