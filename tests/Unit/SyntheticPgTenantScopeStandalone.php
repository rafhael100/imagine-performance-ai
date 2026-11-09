<?php
declare(strict_types=1);

/**
 * F1-66: actual ephemeral Postgres roles and FORCE RLS under PHP PDO.
 * No Portal identity, Ads accounts, billing or provider side effect.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'){
    throw new RuntimeException('F66 only executes in the isolated CI role laboratory');
}
$pdo=static function(string $user,string $password):PDO{
    $db=new PDO(
        'pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4',
        $user,$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]
    );
    return $db;
};
$a=$pdo('perf_f66_tenant_a','ci-f66-role-a-only');
$b=$pdo('perf_f66_tenant_b','ci-f66-role-b-only');
$admin=$pdo('postgres','ci-f66-db-only');
$n=0;
$check=static function(string $name,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected)throw new RuntimeException(
        'FAIL F66 '.$name.' got='.var_export($got,true)
        .' want='.var_export($expected,true)
    );
    echo 'PASS F66 '.++$n.' '.$name.PHP_EOL;
};
$call=static function(PDO $db,string $sql,array $params=[]):string{
    $s=$db->prepare($sql);
    // PDO PostgreSQL maps PHP false to empty text on some drivers;
    // bind explicit PostgreSQL boolean literals for CAST(? AS boolean).
    $normalized=array_map(static fn(mixed $value):mixed=>
       is_bool($value)?($value?'true':'false'):$value,$params);
    $s->execute($normalized);
    return (string)$s->fetchColumn();
};
$deny=static function(PDO $db,string $sql,array $params=[]):bool{
    try{
        $s=$db->prepare($sql);
        $s->execute($params);
        return false;
    }catch(PDOException $e){return $e->getCode()==='42501';}
};
$configure='SELECT perf_f66_scope.configure_demo(?,CAST(? AS integer),CAST(? AS integer))';
$reserve='SELECT perf_f66_scope.reserve_campaign(?,?)';
$group='SELECT perf_f66_scope.reserve_group(?,?)';
$pause='SELECT perf_f66_scope.pause_campaign(?)';
$swap='SELECT perf_f66_scope.replace_campaign(?,?,?,CAST(? AS boolean),CAST(? AS boolean))';

$check('01 A PostgreSQL session_user is server-bound login role',
    $call($a,'SELECT session_user'),'perf_f66_tenant_a');
$check('02 B PostgreSQL session_user independent',
    $call($b,'SELECT session_user'),'perf_f66_tenant_b');
$check('03 no RLS-exempt B connection role',
    $call($b,'SELECT current_user'),'perf_f66_tenant_b');
$check('04 F66 table RLS enabled on all tables',
    (int)$admin->query("SELECT count(*) FROM pg_class c JOIN pg_namespace n"
      ." ON n.oid=c.relnamespace WHERE n.nspname='perf_f66_scope'"
      ." AND c.relkind='r' AND c.relrowsecurity AND c.relforcerowsecurity"
    )->fetchColumn(),4);
$check('05 A cannot bypass table INSERT privileges',
    $deny($a,"INSERT INTO perf_f66_scope.campaigns(ref,tenant_role,channel,state,managed)"
       ." VALUES('cam-unauth','perf_f66_tenant_a','google_ads','active_demo',true)"),true);
$check('06 A cannot bypass table UPDATE privileges',
    $deny($a,"UPDATE perf_f66_scope.campaigns SET state='paused_demo'"),true);
$check('07 A cannot directly DELETE quota rows',
    $deny($a,'DELETE FROM perf_f66_scope.capacities'),true);
$check('08 A cannot invoke internal advisory helper directly',
    $deny($a,'SELECT perf_f66_scope.demo_lock()'),true);
$check('09 B cannot invoke internal advisory helper directly',
    $deny($b,'SELECT perf_f66_scope.demo_lock()'),true);
$check('10 A cannot SET ROLE B by membership',
    $deny($a,'SET ROLE perf_f66_tenant_b'),true);
$check('11 A session identity remains original',
    $call($a,'SELECT session_user'),'perf_f66_tenant_a');
$check('12 A cannot change session authorization',
    $deny($a,'SET SESSION AUTHORIZATION perf_f66_tenant_b'),true);
$check('13 B cannot elevate to table owner',
    $deny($b,'SET ROLE perf_f66_owner'),true);

$check('14 unconfigured campaign denied',
    $call($a,$reserve,['google_ads','cam-a1']),'capacity_unapproved_denied');
$check('15 A config Google demo capacity',
    $call($a,$configure,['google_ads',2,2]),'capacity_configured_mock');
$check('16 B config Google demo capacity',
    $call($b,$configure,['google_ads',1,1]),'capacity_configured_mock');
$check('17 invalid third channel denied',
    $call($a,$configure,['tiktok',1,1]),'invalid_demo_capacity_denied');
$check('18 negative quota denied',
    $call($a,$configure,['google_ads',-1,1]),'invalid_demo_capacity_denied');
$check('19 A first managed campaign',
    $call($a,$reserve,['google_ads','cam-a1']),'included_synthetic_only');
$check('20 A second managed campaign',
    $call($a,$reserve,['google_ads','cam-a2']),'included_synthetic_only');
$check('21 A cap prevents overbooking',
    $call($a,$reserve,['google_ads','cam-a3']),'capacity_review_required');
$check('22 B its own campaign',
    $call($b,$reserve,['google_ads','cam-b1']),'included_synthetic_only');
$check('23 B cannot reuse same ID in other tenant',
    $call($b,$reserve,['google_ads','cam-a1']),'capacity_review_required');
$check('24 A sees exactly two campaigns under RLS',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.campaigns')->fetchColumn(),2);
$check('25 B sees exactly one campaign under RLS',
    (int)$b->query('SELECT count(*) FROM perf_f66_scope.campaigns')->fetchColumn(),1);
$check('26 B cannot SELECT A ID',
    (int)$b->query("SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-a1'")->fetchColumn(),0);
$check('27 A cannot SELECT B ID',
    (int)$a->query("SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-b1'")->fetchColumn(),0);
$check('28 A only sees A capacity',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.capacities')->fetchColumn(),1);
$check('29 B only sees B capacity',
    (int)$b->query('SELECT count(*) FROM perf_f66_scope.capacities')->fetchColumn(),1);
$check('30 PostgreSQL fixture ID validation rejects unsafe campaign ref',
    $call($a,$reserve,['google_ads','=HYPERLINK("bad")']),'invalid_mock_campaign_denied');

$check('31 A first group',
    $call($a,$group,['cam-a1','grp-a1']),'included_synthetic_only');
$check('32 A second group',
    $call($a,$group,['cam-a1','grp-a2']),'included_synthetic_only');
$check('33 group cap reached',
    $call($a,$group,['cam-a2','grp-a3']),'capacity_review_required');
$check('34 B cannot create group under A campaign',
    $call($b,$group,['cam-a1','grp-b-foreign']),'campaign_not_found');
$check('35 B own group',
    $call($b,$group,['cam-b1','grp-b1']),'included_synthetic_only');
$check('36 A sees its 2 groups',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.groups')->fetchColumn(),2);
$check('37 B only sees its group',
    (int)$b->query('SELECT count(*) FROM perf_f66_scope.groups')->fetchColumn(),1);
$check('38 B cannot SELECT A group ID',
    (int)$b->query("SELECT count(*) FROM perf_f66_scope.groups WHERE ref='grp-a1'")->fetchColumn(),0);
$check('39 A cannot shrink groups cap below usage',
    $call($a,$configure,['google_ads',2,1]),'reduction_below_usage_denied');
$check('40 A cannot shrink campaign cap below usage',
    $call($a,$configure,['google_ads',1,2]),'reduction_below_usage_denied');
$check('41 configuration stable after denied shrink',
    (int)$a->query("SELECT group_limit FROM perf_f66_scope.capacities WHERE channel='google_ads'")->fetchColumn(),2);

$check('42 pause only synthetic internal state',
    $call($a,$pause,['cam-a1']),'paused_synthetic_only');
$check('43 repeat pause idempotent',
    $call($a,$pause,['cam-a1']),'already_paused_demo');
$check('44 B cannot pause A campaign',
    $call($b,$pause,['cam-a1']),'not_found');
$check('45 paused campaign still managed',
    (int)$a->query("SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed AND channel='google_ads'")->fetchColumn(),2);
$check('46 paused campaign still blocks cap',
    $call($a,$reserve,['google_ads','cam-after-pause']),'capacity_review_required');
$check('47 mock swap requires recommendation',
    $call($a,$swap,['req-demo-swap','cam-a1','cam-next',false,true]),'recommendation_required');
$check('48 mock swap requires approval fixture',
    $call($a,$swap,['req-demo-swap','cam-a1','cam-next',true,false]),'human_review_required');
$check('49 fake swap updates SQL atomically',
    $call($a,$swap,['req-demo-swap','cam-a1','cam-next',true,true]),'replaced_synthetic_no_fee');
$check('50 swap old campaign exited scope',
    $call($a,"SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-a1'"),'outside_scope_demo');
$check('51 swap new campaign is draft',
    $call($a,"SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-next'"),'draft_demo');
$check('52 swap does not overcount managed cap',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed')->fetchColumn(),2);
$check('53 groups old scope released',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.groups WHERE managed')->fetchColumn(),0);
$check('54 same request duplicate acknowledged',
    $call($a,$swap,['req-demo-swap','cam-a1','cam-next',true,true]),'already_applied_synthetic');
$check('55 modified request cannot replay',
    $call($a,$swap,['req-demo-swap','cam-a2','cam-changed',true,true]),'request_conflict_or_out_of_scope');
$check('56 B cannot reuse A request ID for B',
    $call($b,$swap,['req-demo-swap','cam-b1','cam-b-new',true,true]),'already_exists_or_out_of_scope');
$check('57 B never sees A swap receipt',
    (int)$b->query('SELECT count(*) FROM perf_f66_scope.swaps')->fetchColumn(),0);
$check('58 A sees exactly one receipt',
    (int)$a->query('SELECT count(*) FROM perf_f66_scope.swaps')->fetchColumn(),1);
$check('59 A swap has no fee field',
    (int)$admin->query("SELECT count(*) FROM information_schema.columns"
      ." WHERE table_schema='perf_f66_scope' AND table_name='swaps'"
      ." AND column_name IN ('price','amount','billing_enabled')")->fetchColumn(),0);
$check('60 A can change fake capacity after successful swap',
    $call($a,$configure,['google_ads',3,2]),'capacity_configured_mock');
$check('60b A cannot reuse retired campaign ID even with spare capacity',
    $call($a,$reserve,['google_ads','cam-a1']),'already_exists_or_out_of_scope');
$check('61 B still has own campaign state unchanged',
    $call($b,"SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-b1'"),'active_demo');
$check('62 B cannot see A private audit/swap receipt',
    (int)$b->query("SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-swap'")->fetchColumn(),0);
$check('63 SQL role A cannot modify ownership using direct UPDATE',
    $deny($a,"UPDATE perf_f66_scope.campaigns SET tenant_role='perf_f66_tenant_b'"),true);
$check('64 application has not deployed any provider action',
    (int)$admin->query("SELECT count(*) FROM information_schema.tables"
      ." WHERE table_schema='perf_f66_scope'"
      ." AND table_name IN ('ads_dispatch','billing_charges')")->fetchColumn(),0);
$check('65 fixture scopes only fixed read-only Google/Meta channels',
    (int)$admin->query("SELECT count(*) FROM perf_f66_scope.capacities"
      ." WHERE channel NOT IN ('google_ads','meta')")->fetchColumn(),0);
$check('66 owner role is not a login identity',
    $call($admin,"SELECT rolcanlogin::text FROM pg_roles WHERE rolname='perf_f66_owner'"),'false');
echo "PASS F66 $n PostgreSQL FORCE RLS and mock quota assertions (not Portal auth)".PHP_EOL;
