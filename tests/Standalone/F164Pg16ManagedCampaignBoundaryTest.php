<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F164_PG_SCOPE_LAB')!=='1'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGPORT')!=='5432'||!extension_loaded('pdo_pgsql')) {
 throw new RuntimeException('F164 synthetic PostgreSQL16-only test');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$open=static fn(string $role,string $pass):PDO=>
 new PDO($dsn,$role,$pass,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$n=0;
$check=static function(bool $valid,string $label)use(&$n):void{
 ++$n;if(!$valid)throw new RuntimeException('F164 FAILED: '.$label);
};
$blocked=static function(callable $run):bool{
 try{$run();return false;}catch(PDOException $e){return true;}
};

$check($a->query('SELECT count(*) FROM perf_f162.managed_campaigns')->fetchColumn()==3,
 'A sees only its three seeded synthetic managed campaigns');
$check($b->query('SELECT count(*) FROM perf_f162.managed_campaigns')->fetchColumn()==3,
 'B sees only its own three seeded managed campaigns');
$check($a->query("SELECT count(*) FROM perf_f162.managed_campaigns
 WHERE tenant='perf-demo-boreal'")->fetchColumn()==0,
 'SQL RLS prevents A reading B campaign registry');
$check($b->query("SELECT count(*) FROM perf_f162.managed_campaigns
 WHERE tenant='perf-demo-aurora'")->fetchColumn()==0,
 'SQL RLS prevents B reading A registry');

$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.managed_campaigns
 (tenant,campaign,channel) VALUES('perf-demo-aurora','cam-f164-injected','google_ads')")),
 'A cannot add arbitrary campaign to SQL authority registry');
$check($blocked(static fn()=>$a->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE tenant='perf-demo-aurora'")),
 'A cannot disable or re-enable managed status directly');
$check($blocked(static fn()=>$a->exec("DELETE FROM perf_f162.managed_campaigns
 WHERE tenant='perf-demo-aurora'")),
 'A cannot remove its own trusted campaign binding');
$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f164-unknown','cam-f164-unknown','google_ads','Unknown')")),
 'direct SQL review for unknown campaign is blocked by F164 trigger');
$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f164-wrong','cam-f164-aurora','meta','Wrong channel')")),
 'direct SQL cannot change managed campaign channel');
$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f164-cross','cam-f164-boreal','meta','B campaign')")),
 'A cannot reference B managed campaign even with its own tenant');
$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f164-cross2','cam-f164-boreal','meta','Spoof B')")),
 'direct tenant B review insert still blocked by F162 RLS');

$check($a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f164-a','cam-f164-aurora','google_ads','A fictional managed')")===1,
 'A can submit review for recognized A campaign and channel');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f164-a'")->fetchColumn()==1,
 'accepted direct SQL review gets atomic audit event');

$admin->exec("UPDATE perf_f162.managed_campaigns SET managed=false
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f164-aurora'");
$check($blocked(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f164-a'")),
 'A cannot approve even a mock review after managed campaign is retired');
$check($blocked(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f164-retired','cam-f164-aurora','google_ads','Retired')")),
 'A cannot submit a new review after campaign retirement');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f164-a'")->fetchColumn()==1,
 'failed post-retirement update did not append audit event');
$check($a->query("SELECT status FROM perf_f162.reviews
 WHERE reference='rev-f164-a'")->fetchColumn()==='pending_demo',
 'failed post-retirement update preserved pending row and version');

$check($b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f164-b','cam-f164-boreal','meta','B fictional')")===1,
 'B remains able to submit its own managed Meta review');
$check($b->exec("UPDATE perf_f162.reviews SET
 status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-boreal' AND reference='rev-f164-b'
 AND status='pending_demo' AND version=1")===1,
 'B mock approval still follows F162 versioned SQL transition');
$check($b->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f164-b'")->fetchColumn()==2,
 'B has precisely two state/audit events');
$check($a->query("SELECT count(*) FROM perf_f162.reviews
 WHERE reference='rev-f164-b'")->fetchColumn()==0,
 'A still cannot read B review after F164 policies applied');

$catalog=$admin->query("SELECT
  CASE WHEN relrowsecurity THEN 1 ELSE 0 END AS rls_on,
  CASE WHEN relforcerowsecurity THEN 1 ELSE 0 END AS force_on
 FROM pg_class WHERE oid='perf_f162.managed_campaigns'::regclass")->fetch();
$check((int)$catalog['rls_on']===1&&(int)$catalog['force_on']===1,
 'managed_campaigns table has RLS ENABLE and FORCE set');

$admin->exec("UPDATE perf_f162.managed_campaigns SET managed=true
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f164-aurora'");
$check($a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f164-a'
 AND status='pending_demo' AND version=1")===1,
 'EXPECTED ADMIN AUTHORITY: superuser reactivating synthetic scope permits later mock decision');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f164-a'")->fetchColumn()==2,
 'newly permitted mock decision still appends just one event');
echo "F164 PG16 managed-campaign guard: {$n} PASS / 0 FAIL — NO-GO\n";
