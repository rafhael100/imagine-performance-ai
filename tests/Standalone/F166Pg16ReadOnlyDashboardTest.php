<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F166 PG16 read-only test requires disposable CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$connect=static fn(string $user,string $pw):PDO=>new PDO($dsn,$user,$pw,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$admin=$connect('postgres','postgres');
$a=$connect('perf_f162_a','ci-only-perf-f162-a');
$b=$connect('perf_f162_b','ci-only-perf-f162-b');
$n=0;
$check=static function(bool $passed,string $desc)use(&$n):void{
 ++$n;
 if(!$passed)throw new RuntimeException('F166 FAILED: '.$desc);
};
$denied=static function(callable $callback):bool{
 try{$callback();return false;}catch(PDOException $e){return true;}
};
$constructDenied=static function(callable $callback):bool{
 try{$callback();return false;}catch(RuntimeException $e){return true;}
};
$check($constructDenied(static fn()=>new F166Pg16ReadOnlyDashboard($admin)),
 'superuser is not a valid synthetic tenant reader in PHP');

$readerA=new F166Pg16ReadOnlyDashboard($a);
$readerB=new F166Pg16ReadOnlyDashboard($b);
$tenantA='perf-demo-aurora';$tenantB='perf-demo-boreal';
$check($readerA->read($tenantB)===null
 &&$readerB->read($tenantA)===null,
 'caller-supplied tenant cannot switch SQL login identity');
$check($a->query("SELECT count(*) FROM perf_f162.active_review_dashboard
 WHERE tenant='perf-demo-boreal'")->fetchColumn()==0,
 'security_invoker view respects RLS instead of superuser view ownership');
$check($b->query("SELECT count(*) FROM perf_f162.campaign_scope_activity
 WHERE tenant='perf-demo-aurora'")->fetchColumn()==0,
 'campaign scope aggregation does not leak other tenant');

$admin->exec("INSERT INTO perf_f162.managed_campaigns
 (tenant,campaign,channel,managed)
 VALUES('perf-demo-aurora','cam-f166-aurora','google_ads',true),
       ('perf-demo-boreal','cam-f166-boreal','meta',true)");
$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f166-pending','cam-f166-aurora',
 'google_ads','Revisão A pendente'),
 ('perf-demo-aurora','rev-f166-mock','cam-f166-aurora',
 'google_ads','Revisão A fictícia')");
$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f166-mock'
 AND status='pending_demo' AND version=1");
$b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f166-b','cam-f166-boreal',
 'meta','B independente')");
$projectA=$readerA->read($tenantA);
$projectB=$readerB->read($tenantB);
$check(is_array($projectA)&&$projectA['active_review_count']===2,
 'A reader sees its two active scoped mock reviews');
$check(is_array($projectB)&&$projectB['active_review_count']===1,
 'B reader sees one independent scoped review');
$check($projectA['review_status_demo']['pending_demo']===1
 &&$projectA['review_status_demo']['approved_mock_only']===1,
 'read-only DTO accurately reports pending/mock-approved states');
$check($projectA['reviews'][1]['real_approval']===false
 &&$projectA['reviews'][1]['action_authorized']===false,
 'mock-approved review never generates operational permission');
$check($projectA['iam_verified']===false
 &&$projectA['s2s_verified']===false
 &&$projectA['human_approval_verified']===false
 &&$projectA['authorization_eligible']===false
 &&$projectA['dispatch_enabled']===false
 &&$projectA['billing_enabled']===false
 &&$projectA['ready_for_production']===false
 &&$projectA['real_metrics_available']===false,
 'Portal mock dashboard cannot imply identity, advertising or billing authorization');
$check($projectA['source']==='pg16_security_invoker_ci_only',
 'lab-only source clearly identifies restricted PG16 view');
$check($projectA['quarantined_review_counts_demo']['google_ads']===0,
 'no A review quarantined before campaign retirement');
$check($projectB['quarantined_review_counts_demo']['meta']===0,
 'no B review quarantined before own retirement');

$a->exec("SELECT set_config('app.performance_tenant_id','perf-demo-boreal',false)");
$check($readerA->read($tenantB)===null
 &&$readerA->read($tenantA)['active_review_count']===2,
 'spoofed GUC cannot override PostgreSQL session-bound identity');
$check($denied(static fn()=>$a->exec("DELETE FROM perf_f162.active_review_dashboard
 WHERE tenant='perf-demo-aurora'")),
 'read-only dashboard cannot be used to delete underlying reviews');
$check($denied(static fn()=>$b->exec("UPDATE perf_f162.retired_review_counts
 SET count_retired_demo=99")),
 'aggregated archived counts cannot be modified by tenant SQL account');

$admin->exec("UPDATE perf_f162.managed_campaigns SET managed=false
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f166-aurora'");
$afterRetire=$readerA->read($tenantA);
$check($afterRetire['active_review_count']===0
 &&$afterRetire['reviews']===[],
 'retired campaign no longer exposes review titles and IDs in active view');
$check($afterRetire['quarantined_review_counts_demo']['google_ads']===2,
 'retired reviews appear only as aggregated quarantined count');
$check($afterRetire['campaign_scope_events_demo']['retired_demo']>=1,
 'tenant-visible scope activity includes administrative retirement');
$check($readerB->read($tenantB)===$projectB,
 'B projection unaffected by A retirement');
$check($a->query("SELECT count(*) FROM perf_f162.active_review_dashboard
 WHERE tenant='perf-demo-aurora'")->fetchColumn()==0,
 'raw SQL view obeys retirement even without PHP');

$admin->exec("UPDATE perf_f162.managed_campaigns SET managed=true
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f166-aurora'");
$afterReturn=$readerA->read($tenantA);
$check($afterReturn['active_review_count']===0,
 'reactivating campaign does not automatically resurrect old mock-approved review');
$check($afterReturn['quarantined_review_counts_demo']['google_ads']===2,
 'once-retired campaign reviews remain quarantined after reactivation');
$check($afterReturn['campaign_scope_events_demo']['reactivated_demo']>=1,
 'administrative reactivation remains visible as an event count');
$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f166-post-reactivate',
 'cam-f166-aurora','google_ads','Novo após reativação')");
$afterNew=$readerA->read($tenantA);
$check($afterNew['active_review_count']===0
 &&$afterNew['quarantined_review_counts_demo']['google_ads']===3,
 'new review is conservatively quarantined until independent scope reset mechanism exists');
$check($readerB->read($tenantB)['active_review_count']===1,
 'B unrelated campaign remains readable during A quarantine');

$views=$admin->query("SELECT c.relname,c.reloptions FROM pg_class AS c
 WHERE c.oid IN (
  'perf_f162.active_review_dashboard'::regclass,
  'perf_f162.retired_review_counts'::regclass,
  'perf_f162.campaign_scope_activity'::regclass)
 ORDER BY c.relname")->fetchAll();
$check(count($views)===3
 &&count(array_filter($views,
  static fn(array $row):bool=>str_contains((string)$row['reloptions'],
   'security_invoker=true'))===3,
 'all three PG16 views explicitly run with security_invoker');
$admin->exec('REVOKE SELECT ON perf_f162.campaign_events FROM perf_f162_a');
$check($readerA->read($tenantA)===null,
 'revoking underlying event SELECT causes security-invoker dashboard to fail closed');
$admin->exec('GRANT SELECT ON perf_f162.campaign_events TO perf_f162_a');
$check($readerA->read($tenantA)===$afterNew,
 'restoring synthetic permission restores same deterministic read-only DTO');

echo "F166 PostgreSQL16 read-only tenant dashboard: {$n} PASS / 0 FAIL — NO-GO\n";
