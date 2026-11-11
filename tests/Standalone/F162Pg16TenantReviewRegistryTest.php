<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('pdo_pgsql'))throw new RuntimeException('F162 must use disposable local PG16');

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$open=static fn(string $role,string $password):PDO=>
 new PDO($dsn,$role,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F162 FAILED: '.$label);
};
$denied=static function(callable $call):bool{
 try{$call();return false;}catch(PDOException $e){return true;}
};
$tenantA='perf-demo-aurora';$tenantB='perf-demo-boreal';
$check($a->query('SELECT perf_f162.tenant_from_session()')->fetchColumn()===$tenantA,
 'A trusted tenant comes from authenticated PG session_user');
$check($b->query('SELECT perf_f162.tenant_from_session()')->fetchColumn()===$tenantB,
 'B trusted tenant comes from different login');
$check($a->query('SELECT current_user')->fetchColumn()==='perf_f162_a'
 &&$b->query('SELECT current_user')->fetchColumn()==='perf_f162_b',
 'sessions use distinct non-superuser PostgreSQL roles');
$check($a->query("SELECT count(*) FROM perf_f162.reviews")->fetchColumn()==0,
 'initial A view is empty');
$check($denied(static fn()=>$a->query('SELECT * FROM perf_f162.principals')->fetchAll()),
 'A cannot inspect trusted role-to-tenant mapping table');
$check($denied(static fn()=>$a->exec('SET ROLE perf_f162_b')),
 'A cannot impersonate B SQL role');
$a->exec("SELECT set_config('app.performance_tenant_id','perf-demo-boreal',false)");
$check($a->query('SELECT perf_f162.tenant_from_session()')->fetchColumn()===$tenantA,
 'untrusted legacy GUC cannot switch session-bound tenant');
$check($denied(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f162-inject','cam-f162-inject','meta','Cross tenant')")),
 'RLS refuses A INSERT into tenant B');

$insert=$a->prepare('INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title) VALUES(?,?,?,?,?)');
$insert->execute([$tenantA,'rev-f162-campaign','cam-f162-google','google_ads','Campanha fictícia']);
$check($a->query('SELECT count(*) FROM perf_f162.reviews')->fetchColumn()==1,
 'A inserts one tenant-scoped review');
$check($b->query('SELECT count(*) FROM perf_f162.reviews')->fetchColumn()==0,
 'B cannot read A review with SQL SELECT');
$check($b->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==0,
 'B cannot read audit event of A');
$check($a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==1,
 'AFTER INSERT trigger appends one SQL audit row');
$check($denied(static fn()=>$a->exec("DELETE FROM perf_f162.reviews
 WHERE tenant='perf-demo-aurora'")),
 'review writer lacks DELETE permission');
$check($denied(static fn()=>$a->exec("DELETE FROM perf_f162.events
 WHERE tenant='perf-demo-aurora'")),
 'review writer cannot erase audit history');
$check($denied(static fn()=>$a->exec("UPDATE perf_f162.events
 SET transition='approved_mock_only' WHERE tenant='perf-demo-aurora'")),
 'review writer cannot rewrite audit transition');
$check($denied(static fn()=>$a->exec('ALTER TABLE perf_f162.reviews DISABLE ROW LEVEL SECURITY')),
 'review writer cannot disable RLS');
$check($denied(static fn()=>$a->exec('ALTER TABLE perf_f162.reviews DISABLE TRIGGER f162_guard')),
 'review writer cannot disable transition guard');

$check($denied(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title,status,version)
 VALUES('perf-demo-aurora','rev-f162-forged','cam-f162-google','google_ads',
 'Forbidden','approved_mock_only',8)")),
 'direct INSERT may not forge approved mock state/version');
$check($denied(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only' WHERE reference='rev-f162-campaign'")),
 'direct UPDATE without atomic version change denied');
$check($denied(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET tenant='perf-demo-boreal',status='approved_mock_only',version=2
 WHERE reference='rev-f162-campaign'")),
 'tenant identity columns cannot be updated');

$edit=$a->prepare('UPDATE perf_f162.reviews
 SET status=?,version=version+1
 WHERE tenant=? AND reference=? AND status=? AND version=?');
$edit->execute(['changes_requested_demo',$tenantA,'rev-f162-campaign','pending_demo',1]);
$check($edit->rowCount()===1,'valid expected-version update commits one mock decision');
$check($a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==2,
 'status transition atomically appends second audit row');
$edit->execute(['approved_mock_only',$tenantA,'rev-f162-campaign','pending_demo',1]);
$check($edit->rowCount()===0,'competing stale transition cannot overwrite committed version');

$revise=$a->prepare('UPDATE perf_f162.reviews
 SET title=?,status=?,version=version+1
 WHERE tenant=? AND reference=? AND status=? AND version=?');
$revise->execute(['Criativo revisado','pending_demo',$tenantA,
 'rev-f162-campaign','changes_requested_demo',2]);
$check($revise->rowCount()===1,'fictional revision after changes requested accepted');
$check($a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==3,
 'resubmission appends exactly one event');
$edit->execute(['approved_mock_only',$tenantA,'rev-f162-campaign','pending_demo',3]);
$check($edit->rowCount()===1,'terminal mock approval version four accepted');
$check($a->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==4,
 'four successive review states have four durable SQL audit events');
$check($denied(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='pending_demo',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f162-campaign'")),
 'final mock approval cannot be reopened with direct SQL');

$insertB=$b->prepare('INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title) VALUES(?,?,?,?,?)');
$insertB->execute([$tenantB,'rev-f162-campaign','cam-f162-boreal','google_ads','B fictícia']);
$check($b->query('SELECT count(*) FROM perf_f162.reviews')->fetchColumn()==1
 &&$a->query('SELECT count(*) FROM perf_f162.reviews')->fetchColumn()==1,
 'A/B same reference isolated by composite tenant PK and RLS');
$check($b->query('SELECT count(*) FROM perf_f162.events')->fetchColumn()==1,
 'B receives only own auto-audit row');

$admin->exec("CREATE FUNCTION perf_f162.synthetic_event_outage()
 RETURNS trigger LANGUAGE plpgsql AS \$\$
 BEGIN IF NEW.reference='rev-f162-fault'
   AND NEW.transition='approved_mock_only'
 THEN RAISE EXCEPTION 'CI injected audit outage'; END IF;
 RETURN NEW; END \$\$");
$admin->exec('CREATE TRIGGER f162_ci_fault BEFORE INSERT ON perf_f162.events
 FOR EACH ROW EXECUTE FUNCTION perf_f162.synthetic_event_outage()');
$insert->execute([$tenantA,'rev-f162-fault','cam-f162-google','google_ads','Falha de histórico']);
$check($denied(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f162-fault'")),
 'inject SQL audit trigger outage after review UPDATE');
$rows=$a->query("SELECT status,version FROM perf_f162.reviews
 WHERE tenant='perf-demo-aurora' AND reference='rev-f162-fault'")->fetch();
$check($rows['status']==='pending_demo'&&(int)$rows['version']===1,
 'failing audit insert atomically rolls back review row and version');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f162-fault'")->fetchColumn()==1,
 'failed transition cannot append a partial audit event');
$admin->exec('DROP TRIGGER f162_ci_fault ON perf_f162.events');
$admin->exec('DROP FUNCTION perf_f162.synthetic_event_outage()');

$metadata=$admin->query("SELECT relname,relrowsecurity,relforcerowsecurity
 FROM pg_class WHERE oid IN ('perf_f162.reviews'::regclass,'perf_f162.events'::regclass)
 ORDER BY relname")->fetchAll();
$check(count($metadata)===2 && $metadata[0]['relrowsecurity']===true
 &&$metadata[0]['relforcerowsecurity']===true
 &&$metadata[1]['relrowsecurity']===true
 &&$metadata[1]['relforcerowsecurity']===true,
 'both PostgreSQL tables have RLS enabled and FORCE ROW LEVEL SECURITY');
echo "F162 PostgreSQL16 tenant RLS review registry: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
