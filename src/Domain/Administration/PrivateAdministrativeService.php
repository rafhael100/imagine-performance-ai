<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use PDO;
use RuntimeException;
/** Executable receiver composition, synthetic/unavailable reader only. No human token forwarding. */
final class PrivateAdministrativeService {
 public function __construct(private PDO $db,private string $keyring){}
 public function handle(string $wire,string $certificate):array {
  if($this->db->inTransaction()||$this->db->query('SHOW transaction_isolation')->fetchColumn()!=='read committed')throw new RuntimeException('Service requires current independent transaction');
  if(strlen($wire)>16384||!preg_match('/\A[a-f0-9]{64}\z/D',$certificate))throw new RuntimeException('Private request denied');
  $request=json_decode($wire,true,16,JSON_THROW_ON_ERROR);$e=$request['envelope']??null;
  if(!is_array($e))throw new RuntimeException('Private request denied');
  $profile=$this->db->query("SELECT r.rolsuper OR r.rolbypassrls OR r.rolcreatedb OR r.rolcreaterole OR r.rolreplication OR bool_or(pg_has_role(current_user,c.relowner,'MEMBER')) OR bool_or(CASE WHEN c.relname IN ('principals','service_delegations') THEN has_table_privilege(current_user,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE') OR has_any_column_privilege(current_user,c.oid,'INSERT,UPDATE') ELSE false END) OR bool_or(CASE WHEN c.relname IN ('receipts','nonces','request_audit') THEN has_table_privilege(current_user,c.oid,'UPDATE,DELETE,TRUNCATE') OR has_any_column_privilege(current_user,c.oid,'UPDATE') ELSE false END) OR bool_or(CASE WHEN c.relname='request_audit' THEN has_table_privilege(current_user,c.oid,'SELECT') ELSE false END) OR bool_or(CASE WHEN c.relname IN ('receipts','nonces','request_audit') THEN NOT(c.relrowsecurity AND c.relforcerowsecurity) ELSE false END) AS unsafe FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=current_user AND c.oid IN ('performance_admin_inbox.principals'::regclass,'performance_admin_inbox.service_delegations'::regclass,'performance_admin_inbox.receipts'::regclass,'performance_admin_inbox.nonces'::regclass,'performance_admin_inbox.request_audit'::regclass) GROUP BY r.oid,r.rolsuper,r.rolbypassrls,r.rolcreatedb,r.rolcreaterole,r.rolreplication")->fetch(PDO::FETCH_ASSOC);
  if(!$profile||$profile['unsafe'])throw new RuntimeException('Unsafe runtime role');
  $lookup=function($issuer,$key,$company,$op)use($certificate){
   $s=$this->db->prepare('SELECT revision,expires_epoch FROM performance_admin_inbox.resolve_delegation(?,?,?,?,?)');$s->execute([$issuer,$key,$certificate,$company,$op]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
  };
  $identity=function($issuer,$key)use($e,$lookup){
   $grant=$lookup($issuer,$key,$e['company_id']??'',$e['operation']??'');if(!$grant)return null;
   $stat=lstat($this->keyring);if(!$stat||!is_file($this->keyring)||is_link($this->keyring)||!in_array($stat['mode']&0777,[0400,0600],true)||$stat['size']>16384)throw new RuntimeException('Key custody denied');
   $ring=json_decode(file_get_contents($this->keyring),true,8,JSON_THROW_ON_ERROR);$k=$ring[$issuer][$key]??null;
   if(!is_array($k)||($k['active']??false)!==true||($k['expires_at']??0)<=time()||!is_string($k['secret']??null))return null;
   return ['secret'=>$k['secret'],'active'=>true,'expires_at'=>min((int)$grant['expires_epoch'],$k['expires_at']),'companies'=>[$e['company_id']],'operations'=>[$e['operation']]];
  };
  $authorize=function($issuer,$company,$op)use($e,$lookup){$grant=$lookup($issuer,$e['key_id']??'',$company,$op);return $grant?hash('sha256',json_encode([$issuer,$company,$op,$grant],JSON_THROW_ON_ERROR)):null;};
  $audit=function($event,$company,$op,$correlation)use($e){$s=$this->db->prepare('INSERT INTO performance_admin_inbox.request_audit(issuer,company_id,operation,correlation_id,outcome) VALUES(?,?,?,?,?)');$s->execute([$e['issuer'],$company,$op,$correlation,$event]);};
  $receiver=new AdministrativeReadReceiver(new AdministrativeServiceInbox($this->db),fn()=>$e['issuer']??'', $identity,$authorize,
   fn()=>['status'=>'unavailable','metrics'=>null],$audit);
  $response=$receiver->receive($wire,time());
  // Result audit is required before sending; failure withholds the response. Inbox receipt enables safe retry.
  $audit('read_result',$e['company_id'],$e['operation'],$e['correlation_id']);return $response;
 }
}
