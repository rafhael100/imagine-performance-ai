<?php
declare(strict_types=1);

/** F1-71 fixture CLI: two independent PostgreSQL role-bound connections. */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('GITHUB_ACTIONS')!=='true'
   ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
   ||getenv('PERF_F71_INDEPENDENT_RESTORE_LAB')!=='1'
   ||count($argv)!==3){
    throw new RuntimeException('F71 only works in isolated GitHub Actions CI');
}
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticPgIndependentRecoveryGuard.php';
use Imagine\Performance\Domain\SyntheticPgIndependentRecoveryGuard;

$primaryRoles=[
    'a'=>['perf_f66_tenant_a','ci-f66-role-a-only'],
    'b'=>['perf_f66_tenant_b','ci-f66-role-b-only'],
    'admin'=>['postgres','ci-f66-db-only'],
];
$independentRoles=[
    'a'=>['perf_f71_tenant_a','ci-f71-role-a-only'],
    'b'=>['perf_f71_tenant_b','ci-f71-role-b-only'],
    'admin'=>['postgres','ci-f71-db-only'],
];
if(!isset($primaryRoles[$argv[1]],$independentRoles[$argv[2]])){
    throw new RuntimeException('Unknown synthetic fixture role');
}
$connection=static function(int $port,string $db,array $role):PDO{
    return new PDO(
       "pgsql:host=127.0.0.1;port=$port;dbname=$db;connect_timeout=3",
       $role[0],$role[1],
       [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false]
    );
};
try{
    $main=$connection(5436,'perf_f66_ci',$primaryRoles[$argv[1]]);
    $checkpoint=$connection(5437,'perf_f71_ci',$independentRoles[$argv[2]]);
    $result=(new SyntheticPgIndependentRecoveryGuard())->inspect($main,$checkpoint);
}catch(Throwable){
    $result=[
      'schema_version'=>'perf.recovery.guard.synthetic.v1',
      'status'=>'connection_unavailable',
      'consistent_mock_only'=>false,
      'read_preview_eligible'=>false,
      'authorization_eligible'=>false,
      'ready_for_production'=>false,
      'dispatch_enabled'=>false,
      'billing_enabled'=>false,
    ];
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    |JSON_THROW_ON_ERROR).PHP_EOL;
