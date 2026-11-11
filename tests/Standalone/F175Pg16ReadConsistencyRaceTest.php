<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php';
use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;

if(PHP_SAPI!=='cli'
 ||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F175_READ_CONSISTENCY_LAB')!=='1'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F175 requires disposable PostgreSQL16 testing');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $user,string $pw):PDO=>new PDO($dsn,$user,$pw,$opts);
$tenant='perf-demo-aurora';

if(($argv[1]??'')==='--worker'){
 // No caller passes a tenant parameter; role identity remains SQL-owned.
 $sql=$open('perf_f162_a','ci-only-perf-f162-a');
 $result=(new F166Pg16ReadOnlyDashboard($sql))->read($tenant);
 echo json_encode([
  'status'=>$result===null?'denied_inconsistent':'returned',
  'active_count'=>$result['active_review_count']??null,
 ],JSON_THROW_ON_ERROR)."\n";
 exit(0);
}

$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$readerA=new F166Pg16ReadOnlyDashboard($a);
$readerB=new F166Pg16ReadOnlyDashboard($b);
$n=0;
$check=static function(bool $pass,string $label)use(&$n):void{
 ++$n;
 if(!$pass)throw new RuntimeException('F175 FAILED: '.$label);
};

$admin->exec("INSERT INTO perf_f162.managed_campaigns
 (tenant,campaign,channel,managed)
 VALUES('perf-demo-aurora','cam-f175-active','google_ads',true),
 ('perf-demo-aurora','cam-f175-retired','meta',true),
 ('perf-demo-boreal','cam-f175-b','meta',true)");
$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f175-live','cam-f175-active',
 'google_ads','Novo em gestão ativa'),
 ('perf-demo-aurora','rev-f175-retired','cam-f175-retired',
 'meta','Nunca expor após retirada')");
$b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f175-b','cam-f175-b',
 'meta','Empresa B isolada')");
$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE campaign='cam-f175-retired'
 AND tenant='perf-demo-aurora'");
$baseline=$readerA->read($tenant);
$check(is_array($baseline)&&$baseline['active_review_count']===1,
 'read-only baseline returns only current-generation active campaign');
$check($baseline['reviews'][0]['review_ref']==='rev-f175-live'
 &&$baseline['quarantined_review_counts_demo']['meta']>=1,
 'baseline excludes retired review detail while preserving aggregated count');
$check(is_array($readerB->read('perf-demo-boreal'))
 &&$readerB->read('perf-demo-aurora')===null,
 'B role cannot request A data even with authenticated SQL session');

$a->beginTransaction();
$check($readerA->read($tenant)===null,
 'reader rejects an externally owned transaction instead of composing in it');
$a->rollBack();
$check($readerA->read($tenant)===$baseline,
 'reader safely returns deterministic snapshot after external transaction ends');

// Slow down ONLY the second query in F166 via an instrumented disposable
// PG16 view, so an administrative retirement can happen between the first
// active review SELECT and the final verification SELECT.
$slowView=<<<'SQL'
CREATE OR REPLACE VIEW perf_f162.retired_review_counts
WITH (security_invoker=true,security_barrier=true) AS
SELECT r.tenant,r.channel,
 (count(*)::integer+(SELECT 0 FROM pg_sleep(2.0)))::integer AS count_retired_demo
FROM perf_f162.reviews AS r
JOIN perf_f162.managed_campaigns AS c
 ON c.tenant=r.tenant AND c.campaign=r.campaign AND c.channel=r.channel
WHERE c.managed=false OR r.scope_epoch=0 OR r.scope_epoch<>c.scope_epoch
GROUP BY r.tenant,r.channel
SQL;
$admin->exec($slowView);
$check($admin->query("SELECT count(*) FROM pg_views
 WHERE schemaname='perf_f162' AND viewname='retired_review_counts'")
 ->fetchColumn()==1,
 'disposable view instrumentation installed under original SQL name');
$delayedBaseline=$readerA->read($tenant);
$check(is_array($delayedBaseline)
 &&$delayedBaseline['active_review_count']===1,
 'instrumented slow view alone preserves successful baseline result');

$child=proc_open([PHP_BINARY,__FILE__,'--worker'],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),array_merge(getenv()?:[],[
 'APP_ENV'=>'testing','PERF_F175_READ_CONSISTENCY_LAB'=>'1',
 'PERF_F166_READ_DASHBOARD_LAB'=>'1','PERF_F162_PG_LAB'=>'1',
 'PGDATABASE'=>'perf_f162_ci','PGHOST'=>'127.0.0.1',
 'PGPORT'=>'5432',
]));
if(!is_resource($child))throw new RuntimeException('F175 worker failed to start');
fclose($pipes[0]);

$active=false;
for($i=0;$i<95;$i++){
 $q=$admin->query("SELECT count(*) FROM pg_stat_activity
 WHERE datname='perf_f162_ci' AND usename='perf_f162_a'
 AND state='active' AND query LIKE '%retired_review_counts%'")->fetchColumn();
 if((int)$q>0){$active=true;break;}
 usleep(50000);
}
$check($active,
 'observed SQL worker inside intentionally delayed second scope query');
$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f175-active'");
$check($admin->query("SELECT managed FROM perf_f162.managed_campaigns
 WHERE campaign='cam-f175-active'")->fetchColumn()===false
 ||$admin->query("SELECT managed::text FROM perf_f162.managed_campaigns
 WHERE campaign='cam-f175-active'")->fetchColumn()==='false',
 'retirement commits before worker completes second-pass validation');

$out=stream_get_contents($pipes[1]);fclose($pipes[1]);
$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($child);
$check($exit===0,'worker finishes without PHP exception: '.substr((string)$err,0,80));
$work=json_decode((string)$out,true,8,JSON_THROW_ON_ERROR);
$check($work['status']==='denied_inconsistent'
 &&$work['active_count']===null,
 'committed retirement between reads causes fail-closed null, not stale title');
$check($readerB->read('perf-demo-boreal')['active_review_count']===1,
 'other tenant B remains unaffected during A retirement');

$normalView=<<<'SQL'
CREATE OR REPLACE VIEW perf_f162.retired_review_counts
WITH (security_invoker=true,security_barrier=true) AS
SELECT r.tenant,r.channel,count(*)::integer AS count_retired_demo
FROM perf_f162.reviews AS r
JOIN perf_f162.managed_campaigns AS c
 ON c.tenant=r.tenant AND c.campaign=r.campaign AND c.channel=r.channel
WHERE c.managed=false OR r.scope_epoch=0 OR r.scope_epoch<>c.scope_epoch
GROUP BY r.tenant,r.channel
SQL;
$admin->exec($normalView);
$after=$readerA->read($tenant);
$check(is_array($after)&&$after['active_review_count']===0
 &&$after['reviews']===[],
 'after retired view restoration, no old A review is exposed');
$check($after['quarantined_review_counts_demo']['google_ads']>=1
 &&$after['quarantined_review_counts_demo']['meta']>=1,
 'both A reviews remain counted but not detailed in quarantine');
$check($after['campaign_scope_events_demo']['retired_demo']>=2,
 'scope audit includes both administrative A retirement events');
$check($after['iam_verified']===false
 &&$after['s2s_verified']===false
 &&$after['dispatch_enabled']===false
 &&$after['billing_enabled']===false
 &&$after['ready_for_production']===false,
 'read consistency does not confer Portal IAM or production privileges');
$check($a->inTransaction()===false &&$b->inTransaction()===false,
 'F166 reader closes read-only transaction for A and B');
echo "F175 PG16 consistent read after campaign retirement: {$n} PASS / 0 FAIL — NO-GO\n";
