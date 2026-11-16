<?php
declare(strict_types=1);
if(getenv('CI')!=='true'||getenv('ADMIN_PHASE4_PG_CI')!=='1')exit(1);
foreach(['AdministrativeServiceEnvelope','AdministrativeServiceInbox','AdministrativeReadReceiver','PrivateAdministrativeService'] as $file)require __DIR__.'/../src/Domain/Administration/'.$file.'.php';
use Imagine\Performance\Administration\AdministrativeServiceEnvelope as E;
use Imagine\Performance\Administration\AdministrativeServiceInbox as I;
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=performance_phase4_ci';$owner=new PDO($dsn,'postgres',getenv('PGPASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pw=bin2hex(random_bytes(32));$secret=bin2hex(random_bytes(32));$company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';$fp=str_repeat('c',64);
$owner->exec("CREATE ROLE phase6_reader LOGIN PASSWORD '$pw' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE; GRANT USAGE ON SCHEMA performance_admin_inbox TO phase6_reader; GRANT SELECT,INSERT ON performance_admin_inbox.receipts,performance_admin_inbox.nonces TO phase6_reader; GRANT EXECUTE ON FUNCTION performance_admin_inbox.can_scope(text,text),performance_admin_inbox.can_nonce(text),performance_admin_inbox.resolve_delegation(text,text,text,text,text) TO phase6_reader;
 INSERT INTO performance_admin_inbox.principals VALUES('phase6_reader','phase6_portal','$company',true,now()+interval '1 hour',NULL);
 INSERT INTO performance_admin_inbox.service_delegations VALUES('phase6_reader','phase6_portal','phase6_key_01','$fp','$company','dashboard.read',1,true,now()+interval '1 hour',NULL)");
$db=new PDO($dsn,'phase6_reader',$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$i=new I($db);$n=0;$calls=0;
$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('Phase6 isolation assertion '.($n+1));$n++;};
$deny=function($f)use($ok){try{$f();}catch(Throwable){$ok(true);return;}$ok(false);};
$resolve=function()use($db,$company,$fp,$secret){$s=$db->prepare('SELECT * FROM performance_admin_inbox.resolve_delegation(?,?,?,?,?)');$s->execute(['phase6_portal','phase6_key_01',$fp,$company,'dashboard.read']);return $s->fetch()?['secret'=>$secret,'active'=>true,'expires_at'=>time()+60,'companies'=>[$company],'operations'=>['dashboard.read']]:null;};
$wire=fn()=>E::issue('phase6_portal','phase6_key_01',$company,'dashboard.read',bin2hex(random_bytes(16)),bin2hex(random_bytes(16)),'{}',time(),$secret);
$ordinary=function()use(&$calls){$calls++;return [];};
$revoke=function()use($owner,&$calls){$calls++;$owner->exec("UPDATE performance_admin_inbox.service_delegations SET active=false,revoked_at=clock_timestamp() WHERE database_login='phase6_reader'");return ['status'=>'synthetic'];};
$db->exec("SET default_transaction_isolation='repeatable read'");$deny(fn()=>$i->receive($wire(),'{}',time(),$resolve,$ordinary));$ok($calls===0);
$db->exec("SET default_transaction_isolation='read committed'");$db->beginTransaction();$deny(fn()=>$i->receive($wire(),'{}',time(),$resolve,fn()=>[]));$db->rollBack();
$deny(fn()=>$i->receive($wire(),'{}',time(),$resolve,$revoke));
$ok($calls===1);$ok((int)$owner->query("SELECT count(*) FROM performance_admin_inbox.receipts WHERE issuer='phase6_portal'")->fetchColumn()===0);
foreach(['TRUNCATE','UPDATE','DELETE'] as $priv){
 $owner->exec("GRANT $priv ON performance_admin_inbox.receipts TO phase6_reader");
 try{(new \Imagine\Performance\Administration\PrivateAdministrativeService($db,'/missing-never-read'))->handle(json_encode(['envelope'=>$wire(),'body'=>'{}']),$fp);$ok(false);}catch(RuntimeException $e){$ok($e->getMessage()==='Unsafe runtime role');}
 $owner->exec("REVOKE $priv ON performance_admin_inbox.receipts FROM phase6_reader");
}
echo "OK: $n own-transaction/isolation and concurrent SQL delegation revocation assertions\n";
