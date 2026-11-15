<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use PDO;
use RuntimeException;
use Throwable;
/** Durable candidate: signature and live authority rechecked BEFORE cached receipt. */
final class AdministrativeServiceInbox
{
 public function __construct(private PDO $db){}
 public function receive(array $envelope,string $body,int $now,callable $resolve,callable $read):array
 {
  $e=AdministrativeServiceEnvelope::verify($envelope,$body,$now,$resolve);
  $digest=hash('sha256',$e['version']."\n".$e['operation']."\n".$e['body_sha256']);
  if($this->db->inTransaction())throw new RuntimeException('Inbox requires own transaction');
  $this->db->beginTransaction();
  try {
   $this->db->exec("SET LOCAL lock_timeout='2s'");
   $s=$this->db->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?,0))');
   $s->execute([$e['issuer'].'/'.$e['company_id'].'/'.$e['idempotency_key']]);
   $s=$this->db->prepare('INSERT INTO performance_admin_inbox.nonces VALUES(?,?,?) ON CONFLICT DO NOTHING');
   $s->execute([$e['issuer'],$e['nonce'],$e['expires_at']]);
   if($s->rowCount()!==1)throw new RuntimeException('Replay denied');
   $s=$this->db->prepare('SELECT request_digest,response FROM performance_admin_inbox.receipts WHERE issuer=? AND company_id=? AND idempotency_key=?');
   $s->execute([$e['issuer'],$e['company_id'],$e['idempotency_key']]);$old=$s->fetch(PDO::FETCH_ASSOC);
   if($old){if(!hash_equals($old['request_digest'],$digest))throw new RuntimeException('Idempotency conflict');$result=json_decode($old['response'],true,512,JSON_THROW_ON_ERROR);}
   else {
    $result=$read($e['company_id'],$e['operation'],$body,$e['correlation_id']);
    if(!is_array($result))throw new RuntimeException('Invalid response');
    // Mandatory server facts; caller cannot elevate the candidate to operational authority.
    $result['external_operations_enabled']=false;$result['billable']=false;
    $s=$this->db->prepare('INSERT INTO performance_admin_inbox.receipts(issuer,company_id,idempotency_key,request_digest,response,correlation_id) VALUES(?,?,?,?,?::jsonb,?)');
    $s->execute([$e['issuer'],$e['company_id'],$e['idempotency_key'],$digest,json_encode($result,JSON_THROW_ON_ERROR),$e['correlation_id']]);
   }
   AdministrativeServiceEnvelope::verify($envelope,$body,$now,$resolve);
   $this->db->commit();return $result;
  }catch(Throwable $ex){if($this->db->inTransaction())$this->db->rollBack();throw $ex;}
 }
}
