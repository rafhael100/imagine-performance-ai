<?php
declare(strict_types=1);

/**
 * F1-68 GitHub Actions-only sample report for the FICTIONAL tenant-A role.
 * Format argument only. No caller-provided tenant, secret, account or URL.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('GITHUB_ACTIONS')!=='true'
  ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
  ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'
  ||getenv('PERF_F68_PG_REPORT_LAB')!=='1'
  ||count($argv)!==2||!in_array($argv[1],['json','csv'],true)){
    fwrite(STDERR,"F68 outputs fake-only reports on GitHub Actions CI\n");
    exit(1);
}
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantScopeProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantReportExporter.php';

use Imagine\Performance\Domain\SyntheticPgTenantReportExporter;

$db=new PDO(
    'pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4',
    'perf_f66_tenant_a','ci-f66-role-a-only',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$text=(new SyntheticPgTenantReportExporter())->export($db,$argv[1]);
if(!is_string($text))throw new RuntimeException('F68 fake-only report denied');
echo $text;
