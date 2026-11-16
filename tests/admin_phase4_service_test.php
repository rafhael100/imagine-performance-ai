<?php
declare(strict_types=1);
if(getenv('CI')!=='true'||getenv('ADMIN_PHASE4_PG_CI')!=='1')exit(1);
foreach(['AdministrativeServiceEnvelope','AdministrativeServiceInbox','AdministrativeReadReceiver','PrivateAdministrativeService'] as $file)require __DIR__.'/../src/Domain/Administration/'.$file.'.php';
use Imagine\Performance\Administration\AdministrativeServiceEnvelope as E;
use Imagine\Performance\Administration\PrivateAdministrativeService as S;
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=performance_phase4_ci','postgres',getenv('PGPASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(['002-service-inbox.sql','003-service-inbox-native-rls.sql','004-private-service-delegations.sql'] as $sql)$db->exec(file_get_contents(__DIR__.'/../contracts/admin/phase2/'.$sql));
$pw=bin2hex(random_bytes(32));$secret=bin2hex(random_bytes(32));$fp=str_repeat('a',64);$company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$db->exec("CREATE ROLE phase4_reader LOGIN PASSWORD '$pw' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE; GRANT USAGE ON SCHEMA performance_admin_inbox TO phase4_reader; GRANT SELECT,INSERT ON performance_admin_inbox.receipts,performance_admin_inbox.nonces TO phase4_reader; GRANT INSERT ON performance_admin_inbox.request_audit TO phase4_reader; GRANT USAGE ON SEQUENCE performance_admin_inbox.request_audit_id_seq TO phase4_reader; GRANT EXECUTE ON FUNCTION performance_admin_inbox.can_scope(text,text),performance_admin_inbox.can_nonce(text),performance_admin_inbox.resolve_delegation(text,text,text,text,text) TO phase4_reader;
 INSERT INTO performance_admin_inbox.principals VALUES('phase4_reader','portal_service','$company',true,now()+interval '1 hour',NULL);
 INSERT INTO performance_admin_inbox.service_delegations VALUES('phase4_reader','portal_service','phase4_key_01','$fp','$company','dashboard.read',1,true,now()+interval '1 hour',NULL)");
$runtime=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=performance_phase4_ci','phase4_reader',$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$file=tempnam(sys_get_temp_dir(),'phase4-');chmod($file,0600);file_put_contents($file,json_encode(['portal_service'=>['phase4_key_01'=>['active'=>true,'expires_at'=>time()+300,'secret'=>$secret]]]));
$service=new S($runtime,$file);$n=0;$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('Phase4 assertion '.($n+1));$n++;};
$deny=function($f)use($ok){try{$f();}catch(Throwable){$ok(true);return;}$ok(false);};
$wire=function($company,$id='phase4_request_01')use($secret){$body='{}';$e=E::issue('portal_service','phase4_key_01',$company,'dashboard.read',bin2hex(random_bytes(16)),$id,$body,time(),$secret);return json_encode(['envelope'=>$e,'body'=>$body]);};
try{
 $r=$service->handle($wire($company),$fp);$ok($r['status']==='unavailable'&&$r['metrics']===null&&$r['billable']===false);
 $ok($service->handle($wire($company),$fp)===$r);
 $deny(fn()=>$service->handle($wire($company),str_repeat('b',64)));
 $deny(fn()=>$service->handle($wire('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),$fp));
 $same=$wire($company,'phase4_replay_01');$service->handle($same,$fp);$deny(fn()=>$service->handle($same,$fp));
 $db->exec('UPDATE performance_admin_inbox.service_delegations SET revision=2');$deny(fn()=>$service->handle($wire($company),$fp));
 $ok($service->handle($wire($company,'phase4_new_revision'),$fp)['status']==='unavailable');
 $db->exec('UPDATE performance_admin_inbox.service_delegations SET revoked_at=now(),active=false');$deny(fn()=>$service->handle($wire($company,'phase4_after_revoke'),$fp));
 $ok((int)$db->query("SELECT count(*) FROM performance_admin_inbox.request_audit WHERE outcome='read_result'")->fetchColumn()===4);
 $deny(fn()=>$runtime->query('SELECT * FROM performance_admin_inbox.service_delegations'));
 $deny(fn()=>$runtime->exec("UPDATE performance_admin_inbox.principals SET active=true"));
 $ok((int)$runtime->query('SELECT count(*) FROM performance_admin_inbox.receipts')->fetchColumn()===3);
 echo "OK: $n Phase4 persistent service certificate/delegation/read/retry/audit assertions; no operational credentials\n";
}finally{unlink($file);}
