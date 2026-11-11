<?php
declare(strict_types=1);

/**
 * F168: render a single offline HTML snapshot from the disposable PG16
 * security-invoker reader. No HTTP route, IAM or provider integration.
 */
require dirname(__DIR__,2).'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;

if(PHP_SAPI!=='cli'
 ||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PERF_F168_PG_HTML_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('DATABASE_URL')!==false
 ||getenv('GOOGLE_ADS_ACCESS_TOKEN')!==false
 ||getenv('META_ADS_ACCESS_TOKEN')!==false
 ||count($argv)!==1){
 throw new RuntimeException('F168 only supports credential-free, offline CI fixtures');
}
$accounts=[
 'perf_f162_a'=>[
    'tenant'=>'perf-demo-aurora','password'=>'ci-only-perf-f162-a',
 ],
 'perf_f162_b'=>[
    'tenant'=>'perf-demo-boreal','password'=>'ci-only-perf-f162-b',
 ],
];
$role=(string)getenv('PERF_F168_SYNTHETIC_ROLE');
if(!isset($accounts[$role]))throw new RuntimeException('F168 unknown synthetic role');
$fixture=$accounts[$role];
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci',
    $role,$fixture['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
$report=(new F166Pg16ReadOnlyDashboard($db))->read($fixture['tenant']);
if(!is_array($report)
 ||$report['tenant_ref']!==$fixture['tenant']
 ||$report['schema_version']!=='perf.portal.pg16-tenant-review-report.synthetic.v1'
 ||$report['read_only']!==true
 ||$report['iam_verified']!==false
 ||$report['s2s_verified']!==false
 ||$report['human_approval_verified']!==false
 ||$report['dispatch_enabled']!==false
 ||$report['billing_enabled']!==false
 ||$report['ready_for_production']!==false){
 throw new RuntimeException('F168 PG16 snapshot is not a safe fictional read');
}
$path=__DIR__.'/f168-pg16-portal-offline-template.html';
$template=file_get_contents($path);
if(!is_string($template)
 ||substr_count($template,'__F168_SNAPSHOT__')!==1){
 throw new RuntimeException('F168 HTML template missing exactly one source slot');
}
$json=json_encode($report,
 JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
 |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT
 |JSON_THROW_ON_ERROR);
if(!is_string($json))throw new RuntimeException('F168 JSON encoding failed');
// All text is rendered by DOM textContent, never via innerHTML.
echo str_replace('__F168_SNAPSHOT__',$json,$template);
