<?php
declare(strict_types=1);
require __DIR__.'/../src/Domain/Administration/AdministrativeServiceEnvelope.php';
require __DIR__.'/../src/Domain/Administration/AdministrativeServiceInbox.php';
use Imagine\Performance\Administration\AdministrativeServiceEnvelope as E;
use Imagine\Performance\Administration\AdministrativeServiceInbox as Inbox;
if(getenv('CI')!=='true'||getenv('ADMIN_PHASE2_PG_CI')!=='1')throw new RuntimeException('Disposable CI only');
$connect=fn($u,$p)=>new PDO(getenv('ADMIN_PG_DSN'),$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$owner=$connect('postgres','postgres');
$owner->exec(file_get_contents(__DIR__.'/../contracts/admin/phase2/001-access-candidate.sql'));
$owner->exec(file_get_contents(__DIR__.'/../contracts/admin/phase2/002-service-inbox.sql'));
$a='11111111-1111-4111-8111-111111111111';$b='22222222-2222-4222-8222-222222222222';
foreach(['alfa'=>$a,'beta'=>$b] as $name=>$company){
 $pass=bin2hex(random_bytes(24));$role='admin_phase2_'.$name;
 $owner->exec("CREATE ROLE $role LOGIN PASSWORD '$pass' NOSUPERUSER NOBYPASSRLS NOINHERIT NOCREATEDB NOCREATEROLE");
 $owner->exec("GRANT USAGE ON SCHEMA performance_admin_candidate TO $role; GRANT SELECT ON performance_admin_candidate.companies, performance_admin_candidate.services, performance_admin_candidate.ad_accounts TO $role; GRANT EXECUTE ON FUNCTION performance_admin_candidate.can_access(uuid) TO $role");
 $owner->exec("INSERT INTO performance_admin_candidate.companies VALUES('$company','$company','active','enabled'); INSERT INTO performance_admin_candidate.internal_users VALUES('$company',".($name==='alfa'?1:2).",'$role',true,1); INSERT INTO performance_admin_candidate.grants VALUES('$company','$company','operations',now()+interval '1 hour',NULL,1); INSERT INTO performance_admin_candidate.services VALUES('$company','google_ads',true,1)");
 $dbs[$name]=$connect($role,$pass);
}
$n=0;$ok=function($v)use(&$n){if(!$v)throw new RuntimeException('PG assertion '.($n+1));$n++;};
$deny=function($f)use($ok){try{$f();}catch(Throwable){$ok(true);return;}$ok(false);};
// Two live connections, native identities. Fake GUC cannot change company scope.
foreach(['alfa'=>$a,'beta'=>$b] as $name=>$company){$db=$dbs[$name];$db->exec("SET app.tenant_id='spoofed'; SET app.actor_id='spoofed'");$ok($db->query('SELECT id FROM performance_admin_candidate.companies')->fetchAll(PDO::FETCH_COLUMN)===[$company]);$deny(fn()=>$db->query('SELECT * FROM performance_admin_candidate.grants'));$deny(fn()=>$db->exec("UPDATE performance_admin_candidate.services SET active=false"));}
$owner->exec("UPDATE performance_admin_candidate.grants SET revoked_at=now(),version=2 WHERE company_id='$a'");
$ok((int)$dbs['alfa']->query('SELECT count(*) FROM performance_admin_candidate.services')->fetchColumn()===0);
$ok((int)$dbs['beta']->query('SELECT count(*) FROM performance_admin_candidate.services')->fetchColumn()===1);
$owner->exec("UPDATE performance_admin_candidate.grants SET expires_at=now()-interval '1 second',version=2 WHERE company_id='$b'");
$ok((int)$dbs['beta']->query('SELECT count(*) FROM performance_admin_candidate.companies')->fetchColumn()===0);
$ok((int)$owner->query('SELECT count(*) FROM performance_admin_candidate.grant_audit')->fetchColumn()===4);
$deny(fn()=>$owner->exec('DELETE FROM performance_admin_candidate.grant_audit'));
$now=time();$secret=random_bytes(48);$active=true;
$resolve=function()use(&$active,$now,$secret){return ['active'=>$active,'secret'=>$secret,'expires_at'=>$now+120,'companies'=>['company_alfa'],'operations'=>E::OPERATIONS];};
$env=fn($nonce,$key='request_unique_001',$body='{}')=>E::issue('portal_service','service_key_01','company_alfa','dashboard.read',$nonce,$key,$body,$now,$secret);
$calls=0;$read=function()use(&$calls){$calls++;return ['status'=>'synthetic','metrics'=>null];};
$inbox=new Inbox($owner);$e=$env('nonce_unique_001');$result=$inbox->receive($e,'{}',$now,$resolve,$read);$ok($result['billable']===false&&$calls===1);
$deny(fn()=>$inbox->receive($e,'{}',$now,$resolve,$read));
$second=new Inbox($connect('postgres','postgres'));
$ok($second->receive($env('nonce_unique_002'),'{}',$now,$resolve,$read)===$result&&$calls===1);
$deny(fn()=>$second->receive($env('nonce_unique_003','request_unique_001','{"changed":true}'),'{"changed":true}',$now,$resolve,$read));
$active=false;$deny(fn()=>$second->receive($env('nonce_unique_004'),'{}',$now,$resolve,$read));$active=true;
$deny(fn()=>$second->receive($env('nonce_unique_005','request_unique_002'),'{}',$now,$resolve,fn()=>throw new RuntimeException('Provider timeout')));
$ok($second->receive($env('nonce_unique_005','request_unique_002'),'{}',$now,$resolve,$read)['external_operations_enabled']===false);
echo "OK: $n PG18 RLS/revocation/audit/persistent inbox assertions, two native identities; no deploy\n";
