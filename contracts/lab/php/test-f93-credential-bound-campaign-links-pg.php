<?php
declare(strict_types=1);

/**
 * F1-93: exercise a genuine PostgreSQL VIEW under two authenticated logins.
 * This test only runs with disposable localhost PG16 CI credentials.
 */
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F93_LOGIN_CAMPAIGN_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F93 requires disposable PostgreSQL test database');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void {
    if(!$ok)throw new RuntimeException('FAIL F93 '.$label);
    ++$n;
};
$read=static fn(PDO $pdo):array=>$pdo->query(
    'SELECT tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,'
    .'lifecycle_state,account_ref,link_state '
    .'FROM public.perf_f93_demo_campaign_account_read ORDER BY campaign_ref'
)->fetchAll(PDO::FETCH_ASSOC);
foreach([[$a,$tenantA,'A'],[$b,$tenantB,'B']] as [$pdo,$tenant,$tag]) {
    $rows=$read($pdo);
    $check(count($rows)===1,"$tag sees one linked DEMO campaign");
    $check($rows[0]['tenant_id']===$tenant,"$tag scoped to login tenant");
    $check($rows[0]['portal_service_ref']==='svc-demo-f88',"$tag service matched");
    $check($rows[0]['channel']==='meta',"$tag account channel matched");
    $check($rows[0]['account_ref']==='demo_meta_a',"$tag account reference DEMO");
    $check($rows[0]['lifecycle_state']==='draft',"$tag no live campaign");
    $check($rows[0]['link_state']==='unverified_demo',"$tag link not authenticated");
}
$baselineA=$read($a);$baselineB=$read($b);
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")->execute([$tenantA]);
$check($read($a)===$baselineA,'attacker A forged tenant GUC, but read unchanged');
$check($read($b)===$baselineB,'attacker B forged tenant GUC, but read unchanged');
$check($a->query(
   "SELECT count(*) FROM perf_f93_demo_campaign_account_read WHERE tenant_id='$tenantB'"
)->fetchColumn()==='0','explicit WHERE tenant B cannot expose another tenant');
$check($b->query(
   "SELECT count(*) FROM perf_f93_demo_campaign_account_read WHERE tenant_id='$tenantA'"
)->fetchColumn()==='0','explicit WHERE tenant A cannot expose another tenant');

$deny=static function(PDO $pdo,string $sql,string $label)use($check):void {
    $failed=false;
    try{$pdo->exec($sql);}catch(PDOException){$failed=true;}
    $check($failed,$label);
};
$deny($a,'SELECT tenant_id FROM performance_campaign_account_links',
    'read role A cannot directly SELECT base campaign-account link');
$deny($a,'SELECT tenant_id FROM performance_managed_campaigns',
    'read role A cannot directly SELECT base campaign');
$deny($a,'SELECT tenant_id FROM performance_ad_account_candidates',
    'read role A cannot directly SELECT base advertiser account');
$deny($b,'SELECT tenant_id FROM performance_db_principal_tenant_bindings',
    'read role B cannot inspect protected login-tenant map');
$deny($a,"UPDATE perf_f93_demo_campaign_account_read SET account_ref='demo_forged'",
    'VIEW cannot be updated');
$deny($b,"INSERT INTO perf_f93_demo_campaign_account_read(tenant_id) VALUES(NULL)",
    'VIEW cannot be inserted');
$deny($a,'SET ROLE perf_f91_reader_b','login A cannot impersonate B');
$deny($b,'SET SESSION AUTHORIZATION perf_f91_reader_a','login B cannot impersonate A');

$admin->prepare(
    "UPDATE performance_db_principal_tenant_bindings SET revoked_at=clock_timestamp() WHERE db_login=?"
)->execute(['perf_f91_reader_a']);
$check($read($a)===[],'revoked login A cannot see links even with legacy GUC spoofed');
$check($read($b)===$baselineB,'revoking A does not affect B');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($read($a)===$baselineA,'only database administrator restores credential binding');

$view=$admin->query(
    "SELECT reloptions FROM pg_class WHERE relname='perf_f93_demo_campaign_account_read'"
)->fetchColumn();
$check(is_string($view)&&str_contains($view,'security_barrier=true'),
    'VIEW enforces security barrier');
foreach(['perf_f91_reader_a','perf_f91_reader_b'] as $user) {
    foreach([
       ['performance_managed_campaigns','SELECT'],
       ['performance_campaign_account_links','SELECT'],
       ['performance_ad_account_candidates','SELECT'],
       ['performance_db_principal_tenant_bindings','SELECT'],
       ['perf_f93_demo_campaign_account_read','INSERT'],
       ['perf_f93_demo_campaign_account_read','UPDATE'],
       ['perf_f93_demo_campaign_account_read','DELETE']
    ] as [$table,$priv]) {
        $p=$admin->query("SELECT has_table_privilege('$user','$table','$priv')")->fetchColumn();
        $check(!in_array($p,[true,'t','1',1],true),"$user lacks $priv on $table");
    }
}
echo "PASS F93 {$n} assertions: credential-bound PostgreSQL campaign/account view, revocation, role isolation\n";
