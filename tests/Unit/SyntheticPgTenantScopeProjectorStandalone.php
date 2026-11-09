<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticPgTenantScopeProjector.php';

use Imagine\Performance\Domain\SyntheticPgTenantScopeProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
   ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'){
    throw new RuntimeException('F67 only runs after F66 fixtures in disposable CI');
}
$dsn='pgsql:host=127.0.0.1;port=5436;dbname=perf_f66_ci;connect_timeout=4';
$options=[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
];
$a=new PDO($dsn,'perf_f66_tenant_a','ci-f66-role-a-only',$options);
$b=new PDO($dsn,'perf_f66_tenant_b','ci-f66-role-b-only',$options);
$admin=new PDO($dsn,'postgres','ci-f66-db-only',$options);
$p=new SyntheticPgTenantScopeProjector();
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected)throw new RuntimeException(
        'FAIL F67 '.$label.' got='.var_export($got,true)
        .' want='.var_export($expected,true)
    );
    echo 'PASS F67 '.++$n.' '.$label.PHP_EOL;
};
$ra=$p->build($a);
$rb=$p->build($b);
$check('01 tenant A DTO projected',is_array($ra),true);
$check('02 tenant B DTO projected',is_array($rb),true);
$check('03 projected schema is versioned',
    $ra['schema_version'],'perf.portal.pg-scope-read.synthetic.v1');
$check('04 explicit PostgreSQL fixture source',
    $ra['source'],'postgres_force_rls_fixture_only');
$check('05 tenant A comes from session_user',
    $ra['tenant_role'],'perf_f66_tenant_a');
$check('06 tenant B comes from session_user',
    $rb['tenant_role'],'perf_f66_tenant_b');
foreach([
    'read_only'=>true,'contracted'=>false,'human_identity_verified'=>false,
    'provider_connected'=>false,'authorization_eligible'=>false,
    'ready_for_production'=>false,'dispatch_enabled'=>false,
    'billing_enabled'=>false,'prices_homologated'=>false
] as $field=>$value){
    $check('NO-GO flag '.$field,$ra[$field],$value);
    $check('B NO-GO flag '.$field,$rb[$field],$value);
}
$check('07 Google/Meta channel IDs',array_column($ra['channels'],'id'),
    ['google_ads','meta']);
$check('08 Meta remains one channel not Facebook add-on',
    $ra['channels'][1]['label'],'Meta (Instagram/Facebook)');
$check('09 A Google campaigns still two managed',
    $ra['channels'][0]['campaigns']['used'],2);
$check('10 A Google demo cap expanded to three after F66',
    $ra['channels'][0]['campaigns']['limit'],3);
$check('11 A Google remaining one',
    $ra['channels'][0]['campaigns']['remaining'],1);
$check('12 A old Google groups exited scope',
    $ra['channels'][0]['groups']['used'],0);
$check('13 A Google group demo limit',
    $ra['channels'][0]['groups']['limit'],2);
$check('14 A Meta concurrency reserved one',
    $ra['channels'][1]['campaigns']['used'],1);
$check('15 A Meta concurrency limit one',
    $ra['channels'][1]['campaigns']['limit'],1);
$check('16 A Meta race exhausts slots',
    $ra['channels'][1]['campaigns']['remaining'],0);
$check('17 full A Meta cap prompts review not charge',
    $ra['channels'][1]['attention'],'review_scope_no_auto_upgrade');
$check('18 B Google own campaign total',
    $rb['channels'][0]['campaigns']['used'],1);
$check('19 B Google own group total',
    $rb['channels'][0]['groups']['used'],1);
$check('20 B Google limit one',
    $rb['channels'][0]['campaigns']['limit'],1);
$check('21 B Google no remaining',
    $rb['channels'][0]['campaigns']['remaining'],0);
$check('22 B Meta is not approved',
    $rb['channels'][1]['capacity_status'],'unconfigured_blocked_demo');
$check('23 B Meta has null unapproved quota',
    $rb['channels'][1]['campaigns']['limit'],null);
$check('24 B Meta no inferred remaining',
    $rb['channels'][1]['campaigns']['remaining'],null);
$check('25 B Meta no default capacity grant',
    $rb['channels'][1]['attention'],'commercial_capacity_not_approved');

$check('26 A shows three managed cards across channels',
    count($ra['campaigns']),3);
$check('27 B shows one managed card',
    count($rb['campaigns']),1);
$check('28 A retired campaign not shown',
    in_array('cam-a1',array_column($ra['campaigns'],'ref'),true),false);
$check('29 A active second campaign shown',
    in_array('cam-a2',array_column($ra['campaigns'],'ref'),true),true);
$check('30 A new scope campaign shown',
    in_array('cam-next',array_column($ra['campaigns'],'ref'),true),true);
$check('31 A new scope campaign is draft',
    array_values(array_filter($ra['campaigns'],
        static fn(array $r):bool=>$r['ref']==='cam-next'))[0]['state'],
    'draft_demo');
$check('32 A Meta race winner state remains paused',
    count(array_filter($ra['campaigns'],
        static fn(array $r):bool=>$r['channel']==='meta'&&$r['state']==='paused_demo')),1);
$check('33 B only returns B campaign ID',
    array_column($rb['campaigns'],'ref'),['cam-b1']);
$check('34 B sees only own group ID',
    array_column($rb['groups'],'ref'),['grp-b1']);
$check('35 A has no old managed groups',
    count($ra['groups']),0);
$check('36 raw A payload has no foreign campaign',
    !str_contains(json_encode($ra,JSON_THROW_ON_ERROR),'cam-b1'),true);
$check('37 raw B payload has no A campaign',
    !str_contains(json_encode($rb,JSON_THROW_ON_ERROR),'cam-next'),true);
$check('38 raw B payload has no A group',
    !str_contains(json_encode($rb,JSON_THROW_ON_ERROR),'grp-a1'),true);
$check('39 no hidden role impersonation',
    $ra['tenant_role']!==$rb['tenant_role'],true);
$check('40 every A campaign provider action disabled',
    count(array_filter($ra['campaigns'],
        static fn(array $r):bool=>$r['provider_action']!=='none_synthetic')),0);
$check('41 every B group provider action disabled',
    count(array_filter($rb['groups'],
        static fn(array $r):bool=>$r['provider_action']!=='none_synthetic')),0);
$check('42 no invented live review events',
    $ra['review_summary']['audit_entries_demo'],0);
$check('43 no live human approval',
    $rb['review_summary']['approval_source'],'none_real');
$check('44 A snapshot stable without writes',
    $p->build($a),$ra);
$check('45 B snapshot stable without writes',
    $p->build($b),$rb);
$check('46 no external spend/account/customer fields exposed',
    !preg_match('/access_token|provider_account_id|media_spend|price_brl|customer_email|oauth|campaign_budget/i',
        json_encode($ra,JSON_THROW_ON_ERROR)),true);
$check('47 superuser is NOT a valid read-model tenant',
    $p->build($admin),null);

// Even when the caller can SELECT, there is no trusted tenant argument.
$check('48 no caller-selected tenant on projector API',
    (new ReflectionMethod(SyntheticPgTenantScopeProjector::class,'build'))
        ->getNumberOfParameters(),1);
$check('49 no method for media dispatch',
    method_exists($p,'dispatch'),false);
$check('50 no method to approve or bill',
    method_exists($p,'approve'),false);

$a->beginTransaction();
$check('51 reject nested transaction rather than reusing unknown snapshot',
    $p->build($a),null);
$a->rollBack();
$a->exec('SET row_security = off');
$check('52 deny session with RLS switched off',
    $p->build($a),null);
$a->exec('SET row_security = on');
$check('53 restore RLS allows snapshot',
    $p->build($a)['tenant_role'],'perf_f66_tenant_a');

// Test repeatable read directly with two separate fixture connections.
$writer=new PDO($dsn,'perf_f66_tenant_a','ci-f66-role-a-only',$options);
$a->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
$old=(string)$a->query(
    "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-next'"
)->fetchColumn();
$check('54 first snapshot observes draft before writer',
    $old,'draft_demo');
$changed=(string)$writer->query(
    "SELECT perf_f66_scope.pause_campaign('cam-next')"
)->fetchColumn();
$check('55 separate writer changes only synthetic state',
    $changed,'paused_synthetic_only');
$still=(string)$a->query(
    "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-next'"
)->fetchColumn();
$check('56 original repeatable-read snapshot stays draft',
    $still,'draft_demo');
$a->commit();
$check('57 after commit fresh read sees pause',
    (string)$a->query(
        "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-next'"
    )->fetchColumn(),'paused_demo');
$fresh=$p->build($a);
$check('58 read model observes new paused state',
    array_values(array_filter($fresh['campaigns'],
        static fn(array $r):bool=>$r['ref']==='cam-next'))[0]['state'],
    'paused_demo');
$check('59 paused campaign still occupies Google cap',
    $fresh['channels'][0]['campaigns']['used'],2);
$check('60 no extra campaign capacity generated by pause',
    $fresh['channels'][0]['campaigns']['remaining'],1);
$check('61 other tenant view unchanged by A update',
    $p->build($b),$rb);
$check('62 no billing enabled even after writer action',
    $fresh['billing_enabled'],false);
$check('63 raw audit remains fake and absent',
    $fresh['review_summary']['approval_source'],'none_real');
$check('64 source does not grant provider connection',
    $fresh['provider_connected'],false);

putenv('PERF_F67_PG_READ_MODEL_LAB=0');
$check('65 F67 flag off denies snapshot',$p->build($a),null);
putenv('PERF_F67_PG_READ_MODEL_LAB=1');
putenv('APP_ENV=production');
$check('66 production denies entire mock read model',$p->build($a),null);
putenv('APP_ENV=testing');
putenv('PERF_F66_POSTGRES_RLS_LAB=0');
$check('67 missing F66 RLS lab flag denies snapshot',$p->build($a),null);
putenv('PERF_F66_POSTGRES_RLS_LAB=1');
$check('68 re-enabled test still exposes only A',
    $p->build($a)['tenant_role'],'perf_f66_tenant_a');
$check('69 no operational approval on reopened lab',
    $p->build($b)['authorization_eligible'],false);
echo "PASS F67 $n PostgreSQL RLS derived tenant read snapshot tests (NO-GO)".PHP_EOL;
