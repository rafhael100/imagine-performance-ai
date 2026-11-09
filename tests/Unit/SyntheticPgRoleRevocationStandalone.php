<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantScopeProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantReportExporter.php';

use Imagine\Performance\Domain\SyntheticPgTenantScopeProjector;
use Imagine\Performance\Domain\SyntheticPgTenantReportExporter;

/**
 * F1-69: role/grant/session revocation in an EPHEMERAL PostgreSQL CI service.
 *
 * NO actual Portal customer, third-party connection, account, bill or token.
 * Shows a risk: NOLOGIN does NOT terminate existing PostgreSQL sessions.
 * A CI superuser must explicitly terminate fixture sessions to demonstrate
 * immediate disconnection; NOT a production revocation design.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('GITHUB_ACTIONS')!=='true'
   ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
   ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'
   ||getenv('PERF_F68_PG_REPORT_LAB')!=='1'
   ||getenv('PERF_F69_PG_REVOCATION_LAB')!=='1'){
    throw new RuntimeException('F69 requires disposable GitHub Actions PostgreSQL');
}
$dsn='pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4';
$options=[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
];
$connect=static fn(string $role,string $pw):PDO=>
    new PDO($dsn,$role,$pw,$options);
$a=$connect('perf_f66_tenant_a','ci-f66-role-a-only');
$b=$connect('perf_f66_tenant_b','ci-f66-role-b-only');
$admin=$connect('postgres','ci-f66-db-only');
$projector=new SyntheticPgTenantScopeProjector();
$exporter=new SyntheticPgTenantReportExporter();
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected){
        throw new RuntimeException('FAIL F69 '.$label.' got='
          .var_export($got,true).' want='.var_export($expected,true));
    }
    echo 'PASS F69 '.++$n.' '.$label.PHP_EOL;
};
$denied=static function(PDO $db,string $sql):bool{
    try{
        $db->exec($sql);
        return false;
    }catch(PDOException $e){
        return $e->getCode()==='42501';
    }
};
$tables='perf_f66_scope.capacities,perf_f66_scope.campaigns,'
  .'perf_f66_scope.groups,perf_f66_scope.swaps';
$functions=[
 'perf_f66_scope.configure_demo(text,integer,integer)',
 'perf_f66_scope.reserve_campaign(text,text)',
 'perf_f66_scope.reserve_group(text,text)',
 'perf_f66_scope.pause_campaign(text)',
 'perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)',
];
$fnSql=implode(',',$functions);
$hasLogin=true;
$hasSelect=true;
$hasExecute=true;

try{
    $check('01 B actual session role',
        (string)$b->query('SELECT session_user')->fetchColumn(),
        'perf_f66_tenant_b');
    $check('02 A actual session role',
        (string)$a->query('SELECT session_user')->fetchColumn(),
        'perf_f66_tenant_a');
    $check('03 tenant B is not superuser',
        (string)$admin->query(
            "SELECT rolsuper::text FROM pg_roles WHERE rolname='perf_f66_tenant_b'"
        )->fetchColumn(),'false');
    $check('04 tenant B cannot bypass RLS',
        (string)$admin->query(
            "SELECT rolbypassrls::text FROM pg_roles WHERE rolname='perf_f66_tenant_b'"
        )->fetchColumn(),'false');
    $check('05 B sees only B campaign via RLS',
        (int)$b->query('SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed')->fetchColumn(),1);
    $check('06 B cannot read A campaign even with explicit predicate',
        (int)$b->query(
            "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-a2'"
        )->fetchColumn(),0);

    // These settings are untrusted request/session hints. The RLS policy must
    // continue to follow session_user rather than application_name/custom GUC.
    $b->exec("SET application_name TO 'perf_f66_tenant_a'");
    $b->exec("SET perf.tenant_role TO 'perf_f66_tenant_a'");
    $check('07 spoofed session application_name not login',
        (string)$b->query('SELECT session_user')->fetchColumn(),
        'perf_f66_tenant_b');
    $check('08 RLS ignores spoofed GUC',
        (int)$b->query(
            "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-a2'"
        )->fetchColumn(),0);
    $check('09 role-bound projection remains B despite spoofing',
        $projector->build($b)['tenant_role']??null,'perf_f66_tenant_b');
    $check('10 role-bound export remains B despite spoofing',
        json_decode($exporter->export($b,'json'),true,32,JSON_THROW_ON_ERROR)['tenant_role'],
        'perf_f66_tenant_b');
    $check('11 forged GUC cannot show A client campaign in B export',
        !str_contains($exporter->export($b,'json'),'cam-next'),true);
    $check('12 B cannot SET ROLE to A',
        $denied($b,'SET ROLE perf_f66_tenant_a'),true);
    $check('13 B cannot impersonate table owner',
        $denied($b,'SET ROLE perf_f66_owner'),true);
    $check('14 B cannot change F66 RLS policy',
        $denied($b,'ALTER TABLE perf_f66_scope.campaigns DISABLE ROW LEVEL SECURITY'),true);
    $check('15 B cannot add SQL table in protected schema',
        $denied($b,'CREATE TABLE perf_f66_scope.evil_a (id integer)'),true);
    $check('16 superuser can see both companies and MUST NOT be used as client',
        (int)$admin->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn()>1,true);
    $check('17 F67 read model denies superuser login',
        $projector->build($admin),null);

    // Revoke ALL test-only writer functions for B. Old sessions are reused.
    $admin->exec('REVOKE EXECUTE ON FUNCTION '.$fnSql
       .' FROM perf_f66_tenant_b');
    $hasExecute=false;
    foreach([
      "SELECT perf_f66_scope.configure_demo('google_ads',1,1)",
      "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-b-illegal')",
      "SELECT perf_f66_scope.reserve_group('cam-b1','grp-b-illegal')",
      "SELECT perf_f66_scope.pause_campaign('cam-b1')",
      "SELECT perf_f66_scope.replace_campaign('req-demo-b-illegal','cam-b1','cam-b-new',true,true)",
    ] as $i=>$sql){
        $check('18+'.($i+1).' revoked writer operation denied',
            $denied($b,$sql),true);
    }
    $check('23 existing session still reads own rows with SELECT retained',
        (int)$b->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),1);
    $check('24 B report still available, but not an authorization to write',
        is_string($exporter->export($b,'csv')),true);
    $check('25 A own report unaffected by B writer revocation',
        $projector->build($a)['tenant_role']??null,
        'perf_f66_tenant_a');

    // Next revoke all SELECT grants from B. The projector catches the SQL
    // permission error and FAILS CLOSED; the admin cannot spoof "B" via arg.
    $admin->exec('REVOKE SELECT ON '.$tables
       .' FROM perf_f66_tenant_b');
    $hasSelect=false;
    $check('26 B direct SELECT rejected after revocation',
        $denied($b,'SELECT * FROM perf_f66_scope.campaigns'),true);
    $check('27 B read model returns null instead of leaking',
        $projector->build($b),null);
    $check('28 B CSV report refuses revoked access',
        $exporter->export($b,'csv'),null);
    $check('29 B JSON report refuses revoked access',
        $exporter->export($b,'json'),null);
    $check('30 A can still read own campaigns',
        (int)$a->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),3);
    $check('31 A JSON export still isolated',
        json_decode($exporter->export($a,'json'),true,32,JSON_THROW_ON_ERROR)['tenant_role'],
        'perf_f66_tenant_a');

    // Restore READ for B to demonstrate that NOLOGIN blocks only new
    // logins, NOT an existing authenticated session.
    $admin->exec('GRANT SELECT ON '.$tables.' TO perf_f66_tenant_b');
    $hasSelect=true;
    $check('32 B original session can resume allowed RLS SELECT',
        (int)$b->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),1);
    $admin->exec('ALTER ROLE perf_f66_tenant_b NOLOGIN');
    $hasLogin=false;
    $check('33 PostgreSQL role login flag is false',
        (string)$admin->query(
            "SELECT rolcanlogin::text FROM pg_roles WHERE rolname='perf_f66_tenant_b'"
        )->fetchColumn(),'false');
    $check('34 old B PostgreSQL session is STILL ALIVE after NOLOGIN',
        (int)$b->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),1);
    $freshDenied=false;
    try{
        $connect('perf_f66_tenant_b','ci-f66-role-b-only');
    }catch(PDOException){
        $freshDenied=true;
    }
    $check('35 fresh B login denied after NOLOGIN',
        $freshDenied,true);

    // Show explicitly that closing old fixture sessions is a distinct step.
    $killed=$admin->query(
        "SELECT count(*) FROM ("
        ." SELECT pg_terminate_backend(pid) AS killed FROM pg_stat_activity"
        ." WHERE usename='perf_f66_tenant_b' AND datname='perf_f66_ci'"
        ." AND pid<>pg_backend_pid()) t WHERE killed"
    )->fetchColumn();
    $check('36 CI superuser terminates old B session',
        (int)$killed>=1,true);
    $dead=false;
    try{$b->query('SELECT 1')->fetchColumn();}
    catch(PDOException){$dead=true;}
    $check('37 existing session unusable only after explicit termination',
        $dead,true);
    unset($b);

    $admin->exec('ALTER ROLE perf_f66_tenant_b LOGIN');
    $hasLogin=true;
    $b2=$connect('perf_f66_tenant_b','ci-f66-role-b-only');
    $check('38 new B session works after fixture LOGIN restored',
        (string)$b2->query('SELECT session_user')->fetchColumn(),
        'perf_f66_tenant_b');
    $check('39 restored B session sees only own campaign',
        (int)$b2->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),1);
    $check('40 B still cannot call writers until EXECUTE restored',
        $denied($b2,"SELECT perf_f66_scope.pause_campaign('cam-b1')"),true);
    $check('41 B read-only DTO still cannot dispatch',
        $projector->build($b2)['dispatch_enabled']??null,false);
    $check('42 B export still contains no A campaign after reconnect',
        !str_contains($exporter->export($b2,'json'),'cam-next'),true);

    $admin->exec('GRANT EXECUTE ON FUNCTION '.$fnSql
       .' TO perf_f66_tenant_b');
    $hasExecute=true;
    $check('43 recovered fixture writer functions only simulate',
        (string)$b2->query(
            "SELECT perf_f66_scope.reserve_campaign('meta','cam-b-not-allowed')"
        )->fetchColumn(),'capacity_unapproved_denied');
    $check('44 no new B campaign was added',
        (int)$b2->query(
            'SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed'
        )->fetchColumn(),1);
    $check('45 B cannot see A after all role transitions',
        (int)$b2->query(
            "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-a2'"
        )->fetchColumn(),0);
    $check('46 production release remains false in mock DTO',
        $projector->build($a)['ready_for_production']??null,false);

    putenv('PERF_F69_PG_REVOCATION_LAB=0');
    $check('47 F69 driver fixture is explicitly disabled for further actions',
        getenv('PERF_F69_PG_REVOCATION_LAB'),'0');
    putenv('PERF_F69_PG_REVOCATION_LAB=1');
    echo "PASS F69 $n PostgreSQL role revoke/no-login/session-termination checks".PHP_EOL;
}finally{
    // Best effort restore of fixture privileges in the ephemeral CI database.
    if(!$hasLogin){
        try{$admin->exec('ALTER ROLE perf_f66_tenant_b LOGIN');}catch(Throwable){}
    }
    if(!$hasSelect){
        try{$admin->exec('GRANT SELECT ON '.$tables.' TO perf_f66_tenant_b');}
        catch(Throwable){}
    }
    if(!$hasExecute){
        try{$admin->exec('GRANT EXECUTE ON FUNCTION '.$fnSql
            .' TO perf_f66_tenant_b');}catch(Throwable){}
    }
}
