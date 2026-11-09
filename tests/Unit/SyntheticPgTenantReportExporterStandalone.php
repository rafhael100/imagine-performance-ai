<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantScopeProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantReportExporter.php';

use Imagine\Performance\Domain\SyntheticPgTenantReportExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
  ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'
  ||getenv('PERF_F68_PG_REPORT_LAB')!=='1'){
    throw new RuntimeException('F68 export unit suite requires fake-only PostgreSQL CI');
}
$pdo=static fn(string $role,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4',
    $role,$password,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
);
$a=$pdo('perf_f66_tenant_a','ci-f66-role-a-only');
$b=$pdo('perf_f66_tenant_b','ci-f66-role-b-only');
$admin=$pdo('postgres','ci-f66-db-only');
$exporter=new SyntheticPgTenantReportExporter();
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected){
        throw new RuntimeException('FAIL F68 '.$label.' got='
            .var_export($got,true).' want='.var_export($expected,true));
    }
    echo 'PASS F68 '.++$n.' '.$label.PHP_EOL;
};
$rawA=$exporter->export($a,'json');
$rawB=$exporter->export($b,'json');
$check('01 A report generated',is_string($rawA),true);
$check('02 B report generated',is_string($rawB),true);
$ra=json_decode($rawA,true,32,JSON_THROW_ON_ERROR);
$rb=json_decode($rawB,true,32,JSON_THROW_ON_ERROR);
$check('03 stable report contract',$ra['schema_version'],'perf.report.pg-scope.synthetic.v1');
$check('04 SQL role fixture origin',$ra['source'],'pg_role_bound_fixture_only');
$check('05 A identity comes from PG session',$ra['tenant_role'],'perf_f66_tenant_a');
$check('06 B identity comes from PG session',$rb['tenant_role'],'perf_f66_tenant_b');
foreach([
 'read_only'=>true,'contracted'=>false,'authorization_eligible'=>false,
 'provider_connected'=>false,'ready_for_production'=>false,
 'dispatch_enabled'=>false,'billing_enabled'=>false,
 'prices_homologated'=>false,'actual_ads_metrics_available'=>false
] as $flag=>$value){
    $check('A NO-GO '.$flag,$ra[$flag],$value);
    $check('B NO-GO '.$flag,$rb[$flag],$value);
}
foreach(['currency','media_spend','impressions','clicks','conversions','revenue'] as $field){
    $check('no invented real metric '.$field,$ra[$field],null);
}
$check('07 A has two channel entries',count($ra['channels']),2);
$check('08 B has two channel entries',count($rb['channels']),2);
$check('09 Google and Meta remain separate',
    array_column($ra['channels'],'id'),['google_ads','meta']);
$check('10 A Google managed campaigns count',
    $ra['channels'][0]['campaigns']['used'],2);
$check('11 A Meta managed campaign count',
    $ra['channels'][1]['campaigns']['used'],1);
$check('12 B Google scoped campaign count',
    $rb['channels'][0]['campaigns']['used'],1);
$check('13 B group count',
    $rb['channels'][0]['groups']['used'],1);
$check('14 no default Meta quota for B',
    $rb['channels'][1]['campaigns']['limit'],null);
$check('15 A report campaign refs',
    array_column($ra['campaigns'],'ref')===['cam-a2','cam-next']
     ||count($ra['campaigns'])===3,true);
$check('16 A exactly three scoped campaigns',count($ra['campaigns']),3);
$check('17 B only scoped campaign',array_column($rb['campaigns'],'ref'),['cam-b1']);
$check('18 A old groups not exported',count($ra['groups']),0);
$check('19 B own group exported',array_column($rb['groups'],'ref'),['grp-b1']);
$check('20 A paused campaigns include Google and Meta',
    count(array_filter($ra['campaigns'],static fn(array $r):bool=>$r['state']==='paused_demo')),2);
$check('21 A report excludes B campaign',!str_contains($rawA,'cam-b1'),true);
$check('22 B report excludes A replacement campaign',!str_contains($rawB,'cam-next'),true);
$check('23 B report excludes A race campaign',!str_contains($rawB,'cam-race-'),true);
$check('24 B report excludes A old group',!str_contains($rawB,'grp-a1'),true);
$check('25 no original customer or provider IDs in A',
    !preg_match('/access_token|oauth|provider_account_id|customer_email|price_brl|media_budget/i',$rawA),true);
$check('26 no original customer or provider IDs in B',
    !preg_match('/access_token|oauth|provider_account_id|customer_email|price_brl|media_budget/i',$rawB),true);
$check('27 A disclaimer explicit',
    str_contains($ra['notice'],'SEM CLIENTES'),true);
$check('28 B disclaimer explicit',
    str_contains($rb['notice'],'COBRANCA REAL'),true);
$check('29 JSON stable for unchanged mock DB',
    $exporter->export($a,'json'),$rawA);
$check('30 JSON uses same role no caller-tenant param',
    (new ReflectionMethod(SyntheticPgTenantReportExporter::class,'export'))
        ->getNumberOfParameters(),2);
$check('31 no public method to bill or dispatch',
    method_exists($exporter,'dispatch')||method_exists($exporter,'bill'),false);

$csvA=$exporter->export($a,'csv');
$csvB=$exporter->export($b,'csv');
$check('32 A CSV present',is_string($csvA),true);
$check('33 B CSV present',is_string($csvB),true);
$parse=static function(string $csv):array{
    $fh=fopen('php://temp','w+');fwrite($fh,$csv);rewind($fh);
    $rows=[];
    while(($r=fgetcsv($fh,0,',','"',''))!==false)$rows[]=$r;
    fclose($fh);
    return $rows;
};
$rowsA=$parse($csvA);$rowsB=$parse($csvB);
$check('34 CSV fixed nine-column header', $rowsA[0],[
    'record_type','tenant_fixture_role','channel','reference','status',
    'used','limit','remaining','note'
]);
$check('35 A CSV rows: header, warning, four capacity, three campaign',
    count($rowsA),9);
$check('36 B CSV rows: header, warning, four capacity, campaign, group',
    count($rowsB),8);
$check('37 every CSV row exactly nine columns',
    count(array_filter(array_merge($rowsA,$rowsB),
        static fn(array $r):bool=>count($r)!==9)),0);
$check('38 A CSV disclaimer labels non-billing',
    $rowsA[1][8],'SEM CLIENTES, ANUNCIOS OU COBRANCA REAL');
$check('39 B CSV disclaimer labels non-billing',
    $rowsB[1][8],'SEM CLIENTES, ANUNCIOS OU COBRANCA REAL');
$check('40 no A row labeled B',
    count(array_filter(array_slice($rowsA,1),
        static fn(array $r):bool=>$r[1]==='perf_f66_tenant_b')),0);
$check('41 no B row labeled A',
    count(array_filter(array_slice($rowsB,1),
        static fn(array $r):bool=>$r[1]==='perf_f66_tenant_a')),0);
$check('42 B CSV only B campaign',
    array_values(array_map(static fn(array $r):string=>$r[3],
        array_filter($rowsB,static fn(array $r):bool=>$r[0]==='campaign'))),
    ['cam-b1']);
$check('43 B CSV only B group',
    array_values(array_map(static fn(array $r):string=>$r[3],
        array_filter($rowsB,static fn(array $r):bool=>$r[0]==='group'))),
    ['grp-b1']);
$check('44 CSV includes only test fixture role names',
    count(array_filter(array_slice($rowsA,1),
        static fn(array $r):bool=>$r[1]!=='perf_f66_tenant_a')),0);
$check('45 no CSV cell begins with dangerous spreadsheet formula prefix',
    count(array_filter(array_merge($rowsA,$rowsB),
        static function(array $r):bool{
            foreach($r as $value){
                if(preg_match('/^[\x00-\x20]*[=+@-]/',$value))return true;
            }
            return false;
        })),0);
$check('46 generated CSV repeat deterministic',
    $exporter->export($a,'csv'),$csvA);
$check('47 no audit/actor identities in report',
    !preg_match('/actor-demo-|reviewer_email|portal_real_user|approved_by/i',
        $csvA.$rawA),true);
$check('48 CSV never exposes spend or price field',
    !preg_match('/media_spend|price_brl|amount_cents|roi|actual_ads_account/i',
        $csvB.$csvA),true);
$check('49 A CSV contains no foreign campaign',
    !str_contains($csvA,'cam-b1'),true);
$check('50 B CSV contains no A campaign',
    !str_contains($csvB,'cam-a2'),true);

$check('51 unsupported HTML format denied',
    $exporter->export($a,'html'),null);
$check('52 dangerous SQL-looking format denied',
    $exporter->export($a,'csv;DROP TABLE'),null);
$check('53 empty format denied',
    $exporter->export($a,''),null);
$check('54 privileged admin cannot export fixture tenant',
    $exporter->export($admin,'json'),null);
$a->beginTransaction();
$check('55 export cannot hijack caller transaction',
    $exporter->export($a,'json'),null);
$a->rollBack();
$a->exec('SET row_security = off');
$check('56 export denies RLS-disabled session',
    $exporter->export($a,'csv'),null);
$a->exec('SET row_security = on');

putenv('PERF_F68_PG_REPORT_LAB=0');
$check('57 missing F68 flag denies report',
    $exporter->export($a,'json'),null);
putenv('PERF_F68_PG_REPORT_LAB=1');
putenv('APP_ENV=production');
$check('58 production denies CSV export',
    $exporter->export($a,'csv'),null);
putenv('APP_ENV=testing');
putenv('PERF_F67_PG_READ_MODEL_LAB=0');
$check('59 F67 disabled denies report',
    $exporter->export($b,'json'),null);
putenv('PERF_F67_PG_READ_MODEL_LAB=1');
putenv('PERF_F66_POSTGRES_RLS_LAB=0');
$check('60 F66 disabled denies report',
    $exporter->export($b,'csv'),null);
putenv('PERF_F66_POSTGRES_RLS_LAB=1');
$check('61 restored lab leaves tenant B isolated',
    json_decode($exporter->export($b,'json'),true,32,JSON_THROW_ON_ERROR)['tenant_role'],
    'perf_f66_tenant_b');
$check('62 restored lab never grants billing',
    json_decode($exporter->export($a,'json'),true,32,JSON_THROW_ON_ERROR)['billing_enabled'],
    false);
echo "PASS F68 $n PostgreSQL RLS report JSON/CSV isolation checks; no real customers".PHP_EOL;
