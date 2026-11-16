<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use Closure;
use RuntimeException;
/** Private read adapter. Deliberately no public route or default authority provider.
 * peer: authenticated server-owned mTLS principal, never headers/body/session.
 * authorize: live delegation lease resolved by issuer/company/operation/idempotency,
 * from a homologated Portal authority; null/changed lease always denies.
 */
final class AdministrativeReadReceiver
{
 public function __construct(private AdministrativeServiceInbox $inbox,private Closure $peer,
  private Closure $identity,private Closure $authorize,private Closure $reader,private Closure $audit){}
 public function receive(string $wire,int $now):array
 {
  if(strlen($wire)>16384)throw new RuntimeException('Read request denied');
  $request=json_decode($wire,true,16,JSON_THROW_ON_ERROR);
  if(!is_array($request)||count($request)!==2||!isset($request['envelope'],$request['body'])||!is_array($request['envelope'])||!is_string($request['body']))throw new RuntimeException('Read request denied');
  $envelope=$request['envelope'];$body=$request['body'];$lease=null;
  $resolve=function(string $issuer,string $key)use($envelope,&$lease):?array{
   if(($this->peer)()!==$issuer)return null;
   $identity=($this->identity)($issuer,$key);
   $current=($this->authorize)($issuer,$envelope['company_id'],$envelope['operation'],$envelope['idempotency_key']);
   if(!is_string($current)||!preg_match('/\A[a-f0-9]{64}\z/D',$current)||($lease!==null&&!hash_equals($lease,$current)))return null;
   $lease=$current;return is_array($identity)?$identity:null;
  };
  // Validate before audit/provider, including schema, signature, audience and live scope.
  AdministrativeServiceEnvelope::verify($envelope,$body,$now,$resolve);
  $read=function(string $company,string $operation,string $body,string $correlation):array{
   $data=($this->reader)($company,$operation,json_decode($body,true,16,JSON_THROW_ON_ERROR));
   if(!is_array($data)||array_diff(array_keys($data),['status','metrics'])!==[]||!in_array($data['status']??null,['synthetic','unavailable'],true)
    ||!array_key_exists('metrics',$data)||($data['status']==='unavailable'&&$data['metrics']!==null))throw new RuntimeException('Read provider denied');
   return ['version'=>AdministrativeServiceEnvelope::VERSION,'company_id'=>$company,'operation'=>$operation,
    'correlation_id'=>$correlation,'status'=>$data['status'],'metrics'=>$data['metrics'],'external_operations_enabled'=>false,'billable'=>false];
  };
  // Record intent before DB commit; failure prevents execution. No raw request/key/metrics in audit.
  ($this->audit)('read_attempt',$envelope['company_id'],$envelope['operation'],$envelope['correlation_id']);
  return $this->inbox->receive($envelope,$body,$now,$resolve,$read,$lease);
 }
}
