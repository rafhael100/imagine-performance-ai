<?php
declare(strict_types=1);

/** F1-88 PostgreSQL 16 with RLS; never connect to live/development DB. */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F88_AD_ACCOUNT_PG_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F88 requires disposable local PostgreSQL CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$role=new PDO($dsn,'perf_f88_registrar','ci-f88-registrar-only',$options);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$service='svc-demo-f88';
$insertTenant=$admin->prepare(
    'INSERT INTO performance_tenants(id,status) VALUES(?,?)'
);
$insertLink=$admin->prepare(
    'INSERT INTO portal_service_links(id,tenant_id,portal_organization_ref,portal_service_ref,link_status,source_revision) VALUES(?,?,?,?,?,?)'
);
foreach([[$tenantA,'a'],[$tenantB,'b']] as [$t,$label]) {
    $insertTenant->execute([$t,'inactive']);
    $insertLink->execute([
        $label==='a'?'88888888-8888-4888-8888-888888888881':
            '99999999-9999-4999-8999-999999999991',
        $t,'org-demo-'.$label,$service,'pending','demo-revision-f88'
    ]);
}

$sql='INSERT INTO performance_ad_account_candidates '
    .'(id,tenant_id,portal_service_ref,channel,account_ref,connection_state,source,provider_connected,dispatch_allowed,billable) '
    .'VALUES(?,?,?,?,?,?,?,?,?,?)';
$good=static fn(string $id,string $tenant,string $channel,string $ref):array=>[
    $id,$tenant,'svc-demo-f88',$channel,$ref,'unverified_demo',
    'portal_demo_fixture',false,false,false
];
$a=$good('88888888-8888-4888-8888-888888888882',$tenantA,'meta','demo_meta_a');
$b=$good('99999999-9999-4999-8999-999999999992',$tenantB,'meta','demo_meta_a');
$set=static function(PDO $db,string $tenant):void {
    $s=$db->prepare("SELECT set_config('app.performance_tenant_id', ?, true)");
    $s->execute([$tenant]);
};
$transact=static function(PDO $db,string $tenant,callable $cb)use($set):mixed {
    $db->beginTransaction();
    try {
        $set($db,$tenant);
        $ret=$cb($db);
        $db->commit();
        return $ret;
    } catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
};
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
    if(!$ok)throw new RuntimeException("FAIL F88: $msg");
    ++$n;
};
$transact($role,$tenantA,static function(PDO $db)use($sql,$a):void{$db->prepare($sql)->execute($a);});
$transact($role,$tenantB,static function(PDO $db)use($sql,$b):void{$db->prepare($sql)->execute($b);});
$check(true,'insert distinct tenant references');
$read=static fn(PDO $db):array=>$db->query(
    'SELECT tenant_id, channel, account_ref, connection_state, provider_connected, dispatch_allowed, billable '
    .'FROM performance_ad_account_candidates ORDER BY account_ref'
)->fetchAll(PDO::FETCH_ASSOC);
$rows=$transact($role,$tenantA,$read);
$check(count($rows)===1 && $rows[0]['tenant_id']===$tenantA,'tenant A sees only A');
$check($rows[0]['channel']==='meta','internal Meta channel');
$check($rows[0]['account_ref']==='demo_meta_a','synthetic reference');
$check($rows[0]['connection_state']==='unverified_demo','never activated');
foreach(['provider_connected','dispatch_allowed','billable'] as $flag) {
    $check(in_array($rows[0][$flag],[false,'f','0',0],true),"flag $flag always disabled");
}
$rows=$transact($role,$tenantB,$read);
$check(count($rows)===1 && $rows[0]['tenant_id']===$tenantB,'tenant B sees only B');
$check(count($read($role))===0,'transaction-local tenant scope does not leak');

$reject=static function(string $name,string $tenant,array $values)use($role,$sql,$transact,$check):void {
    $failed=false;
    try {
        $transact($role,$tenant,static function(PDO $db)use($sql,$values):void{
            $db->prepare($sql)->execute($values);
        });
    } catch(PDOException) {
        $failed=true;
    }
    $check($failed,"reject $name");
};
$fake='88888888-8888-4888-8888-888888888883';
$case=static fn(int $index,mixed $value):array=>array_replace(
    ['88888888-8888-4888-8888-888888888883',
     '88888888-8888-4888-8888-888888888888',
     'svc-demo-f88','meta','demo_other','unverified_demo','portal_demo_fixture',false,false,false],
    [$index=>$value]
);
$reject('cross-tenant write',$tenantA,$case(1,$tenantB));
$reject('live reference',$tenantA,$case(4,'act_123456789'));
$reject('Meta Portal alias in engine storage',$tenantA,$case(3,'meta_ads'));
$reject('unknown provider',$tenantA,$case(3,'tiktok'));
$reject('invalid account reference',$tenantA,$case(4,'demo_../../secret'));
$reject('fake OAuth connection state',$tenantA,$case(5,'connected'));
$reject('active source claim',$tenantA,$case(6,'provider_verified'));
$reject('provider flag elevated',$tenantA,$case(7,true));
$reject('dispatch flag elevated',$tenantA,$case(8,true));
$reject('billing flag elevated',$tenantA,$case(9,true));
$reject('missing Portal service link',$tenantA,$case(2,'svc-demo-not-owned'));
$reject('duplicate natural account key',$tenantA,$case(4,'demo_meta_a'));

$check(!$admin->query("SELECT has_table_privilege('perf_f88_registrar','performance_ad_account_candidates','UPDATE')")->fetchColumn(),
    'role cannot update candidate');
$check(!$admin->query("SELECT has_table_privilege('perf_f88_registrar','performance_ad_account_candidates','DELETE')")->fetchColumn(),
    'role cannot delete candidate');
$check((int)$admin->query(
 "SELECT count(*) FROM information_schema.columns WHERE table_name='performance_ad_account_candidates' "
 ."AND column_name IN ('oauth_token','refresh_token','access_token','provider_account_id','credit_card')"
)->fetchColumn()===0,'no token, card or real account ID columns');

$flags=$admin->query("SELECT relrowsecurity,relforcerowsecurity FROM pg_class WHERE relname='performance_ad_account_candidates'")->fetch(PDO::FETCH_ASSOC);
$check(in_array($flags['relrowsecurity'],[true,'t','1',1],true),'RLS enabled');
$check(in_array($flags['relforcerowsecurity'],[true,'t','1',1],true),'FORCE RLS enabled');
$check((int)$admin->query("SELECT count(*) FROM performance_ad_account_candidates WHERE provider_connected OR dispatch_allowed OR billable")->fetchColumn()===0,
    'no accounts enabled globally');

echo "PASS F88 $n PostgreSQL tenant-bound account candidate assertions\n";
