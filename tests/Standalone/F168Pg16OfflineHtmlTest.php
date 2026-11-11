<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F168_PG_HTML_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F168 offline HTML tests restricted to disposable CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$open=static fn(string $role,string $pwd):PDO=>new PDO($dsn,$role,$pwd,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F168 FAILED: '.$why);
};

$admin->exec("INSERT INTO perf_f162.managed_campaigns
 (tenant,campaign,channel,managed) VALUES
 ('perf-demo-aurora','cam-f168-search','google_ads',true),
 ('perf-demo-aurora','cam-f168-meta','meta',true),
 ('perf-demo-boreal','cam-f168-boreal','meta',true)");
$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f168-old-search','cam-f168-search',
 'google_ads','Pesquisa antiga'),
 ('perf-demo-aurora','rev-f168-meta','cam-f168-meta',
 'meta','Campanha Meta & revisão local')");
$b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f168-b','cam-f168-boreal',
 'meta','Empresa B independente')");
$check((int)$a->query("SELECT count(*) FROM perf_f162.active_review_dashboard
 WHERE reference LIKE 'rev-f168-%'")->fetchColumn()===2,
 'two A mock reviews visible before retirement');

$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f168-search'");
$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=true WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f168-search'");
$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f168-new-search','cam-f168-search',
 'google_ads','Pesquisa nova da geração 3')");
$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f168-new-search'
 AND status='pending_demo' AND version=1");

$script=dirname(__DIR__,2).'/contracts/demo/f168-render-pg16-offline.php';
$baseEnv=[
 'APP_ENV'=>'testing','PERF_F162_PG_LAB'=>'1',
 'PERF_F166_READ_DASHBOARD_LAB'=>'1',
 'PERF_F167_SCOPE_EPOCH_LAB'=>'1','PERF_F168_PG_HTML_LAB'=>'1',
 'PGHOST'=>'127.0.0.1','PGPORT'=>'5432','PGDATABASE'=>'perf_f162_ci',
];
$run=static function(string $role,array $additional=[])use($script,$baseEnv):array {
 $env=array_merge($baseEnv,['PERF_F168_SYNTHETIC_ROLE'=>$role],$additional);
 $proc=proc_open([PHP_BINARY,$script],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2),$env);
 if(!is_resource($proc))throw new RuntimeException('F168 HTML subprocess unavailable');
 fclose($pipes[0]);
 $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
 $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
 return [
   'status'=>proc_close($proc),
   'html'=>(string)$stdout,
   'stderr'=>(string)$stderr,
 ];
};
$extract=static function(string $html):array {
 if(preg_match_all('~<script type="application/json" id="f168-snapshot">(.*?)</script>~s',
  $html,$m)!==1)throw new RuntimeException('F168 snapshot missing or duplicated');
 return json_decode($m[1][0],true,64,JSON_THROW_ON_ERROR);
};
$resultA=$run('perf_f162_a');
$resultB=$run('perf_f162_b');
$check($resultA['status']===0&&$resultB['status']===0,
 'A and B PostgreSQL roles each render offline HTML successfully');
$check(strlen($resultA['html'])>2500&&strlen($resultB['html'])>2500,
 'both offline HTML pages contain accessible visible interface');
$check(!str_contains($resultA['html'],'__F168_SNAPSHOT__'),
 'HTML template snapshot placeholder replaced once');
$check(str_contains($resultA['html'],"connect-src 'none'")
 &&str_contains($resultA['html'],"form-action 'none'"),
 'offline CSP disables network connections and form submissions');
$check(str_contains($resultA['html'],'id="filter-channel"')
 &&str_contains($resultA['html'],'id="filter-state"'),
 'Google/Meta and mock review status filters are present');
$check(str_contains($resultA['html'],'textContent')
 &&str_contains($resultA['html'],'target.replaceChildren()'),
 'review rows rendered via safe DOM text rather than HTML injection');
$check(!str_contains($resultA['html'],'innerHTML')
 &&!str_contains($resultA['html'],'XMLHttpRequest')
 &&!str_contains($resultA['html'],'fetch('),
 'HTML has no dynamic HTML insertion or outbound data transport');
$check(str_contains($resultA['html'],'Simulação / NÃO É PRODUÇÃO'),
 'visual page explicitly identifies as an offline simulation');
$check(str_contains($resultA['html'],'id="quarantined-count"'),
 'quarantined historic review count visible without exposing old row details');

$dataA=$extract($resultA['html']);
$dataB=$extract($resultB['html']);
$check($dataA['tenant_ref']==='perf-demo-aurora'
 &&$dataB['tenant_ref']==='perf-demo-boreal',
 'tenant identities are derived from actual PG login roles');
$check($dataA['active_review_count']===2
 &&$dataA['review_status_demo']['pending_demo']===1
 &&$dataA['review_status_demo']['approved_mock_only']===1,
 'Aurora snapshot displays only active current-generation Google/Meta mock reviews');
$check($dataB['active_review_count']===1
 &&$dataB['reviews'][0]['review_ref']==='rev-f168-b',
 'Boreal snapshot displays only its own independent review');
$check($dataA['quarantined_review_counts_demo']['google_ads']>=1,
 'old-generation Google review retained only as quarantine count');
$refs=array_column($dataA['reviews'],'review_ref');
$check(in_array('rev-f168-new-search',$refs,true)
 &&in_array('rev-f168-meta',$refs,true)
 &&!in_array('rev-f168-old-search',$refs,true),
 'new generation review displayed but old review never resurrected');
$check(!str_contains($resultA['html'],'rev-f168-b')
 &&!str_contains($resultB['html'],'rev-f168-new-search'),
 'HTML snapshot cannot include other tenant review references');
$check(!str_contains($resultA['html'],'Pesquisa antiga'),
 'historic title is not inadvertently leaked into generated HTML');
$check(str_contains($resultA['html'],'\\u0026'),
 'dynamic JSON escapes ampersand characters in a safe script data block');
$check($dataA['reviews'][0]['real_approval']===false
 &&$dataA['reviews'][0]['action_authorized']===false,
 'even mock-approved rows do not authorize provider actions');
foreach(['iam_verified','s2s_verified','human_approval_verified',
 'authorization_eligible','contracted','dispatch_enabled','billing_enabled',
 'ready_for_production','real_metrics_available'] as $flag) {
 $check($dataA[$flag]===false &&$dataB[$flag]===false,
  'both HTML reports keep '.$flag.' disabled');
}
$check($run('postgres')['status']!==0,
 'untrusted or superuser role refused before generating HTML');
$check($run('perf_f162_a',['DATABASE_URL'=>'postgres://synthetic'])['status']!==0,
 'supplying real-style DB URL causes fail-closed refusal');
$check($run('perf_f162_a',['GOOGLE_ADS_ACCESS_TOKEN'=>'synthetic'])['status']!==0,
 'Google Ads token-like env is refused even though testing is enabled');
$check($run('perf_f162_a',['META_ADS_ACCESS_TOKEN'=>'synthetic'])['status']!==0,
 'Meta token-like env is refused even though testing is enabled');
$check($run('perf_f162_a')['html']===$resultA['html'],
 'offline HTML is byte-identical for unchanged synthetic database state');
echo "F168 PG16-backed offline Portal HTML: {$n} PASS / 0 FAIL — NO-GO\n";
