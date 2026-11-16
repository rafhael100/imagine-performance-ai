<?php
declare(strict_types=1);
require __DIR__.'/../src/Domain/Administration/AdministrativeServiceEnvelope.php';
require __DIR__.'/../src/Domain/Administration/AdministrativeServiceInbox.php';
use Imagine\Performance\Administration\AdministrativeServiceEnvelope as E;
use Imagine\Performance\Administration\AdministrativeServiceInbox as Inbox;
if(getenv('CI')!=='true'||getenv('ADMIN_PHASE2_PG_CI')!=='1')throw new RuntimeException('Disposable CI only');
$db=new PDO(getenv('ADMIN_PG_DSN'),'postgres','postgres',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$db->query('SELECT count(*) FROM performance_admin_candidate.grant_audit')->fetchColumn()!==4)throw new RuntimeException('Restored audit missing');
if((int)$db->query('SELECT count(*) FROM performance_admin_inbox.receipts')->fetchColumn()!==6)throw new RuntimeException('Restored durable receipts missing');
$now=time();$secret=random_bytes(48);
$resolve=fn()=>['active'=>true,'secret'=>$secret,'expires_at'=>$now+120,'companies'=>['company_alfa'],'operations'=>E::OPERATIONS];
$env=E::issue('portal_service','service_key_01','company_alfa','dashboard.read','nonce_unique_001','request_unique_001','{}',$now,$secret);
$denied=false;try{(new Inbox($db))->receive($env,'{}',$now,$resolve,fn()=>throw new RuntimeException('Reader must not run'));}catch(RuntimeException $e){$denied=$e->getMessage()==='Replay denied';}
if(!$denied)throw new RuntimeException('Replay accepted after restore');
// Restored revocation/expiry still deny native identity scope.
$db->exec('SET SESSION AUTHORIZATION admin_phase2_alfa');
if((int)$db->query('SELECT count(*) FROM performance_admin_candidate.companies')->fetchColumn()!==0)throw new RuntimeException('Restored revocation lost');
$db->exec('RESET SESSION AUTHORIZATION; SET SESSION AUTHORIZATION admin_phase2_beta');
if((int)$db->query('SELECT count(*) FROM performance_admin_candidate.companies')->fetchColumn()!==0)throw new RuntimeException('Restored expiry lost');
echo "OK: encrypted PG18 dump restored; audit, receipts, replay, revoked/expired native scopes preserved; operational backup not claimed\n";
