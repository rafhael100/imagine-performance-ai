<?php
declare(strict_types=1);

/**
 * F91: credential-bound tenant read test using ephemeral PostgreSQL login
 * roles; unlike a caller-controlled GUC, SESSION_USER cannot be impersonated
 * by a non-superuser connection. The view is deliberately read-only and DEMO.
 */
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F91_DB_PRINCIPAL_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F91 accepts only disposable PostgreSQL lab');
}

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $ok,string $name)use(&$n):void{
    if(!$ok) throw new RuntimeException('FAIL F91 '.$name);
    ++$n;
};

$add=$admin->prepare(
    'INSERT INTO performance_db_principal_tenant_bindings (db_login,tenant_id) VALUES (?,?)'
);
$add->execute(['perf_f91_reader_a',$tenantA]);
$add->execute(['perf_f91_reader_b',$tenantB]);

$read=static fn(PDO $db):array=>$db->query(
    'SELECT tenant_id,portal_service_ref,channel,account_ref,connection_state '
    .'FROM public.perf_f91_demo_account_read'
)->fetchAll(PDO::FETCH_ASSOC);
$check($a->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_a','authenticated A login');
$check($b->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_b','authenticated B login');
foreach ([[$a,$tenantA,'A'],[$b,$tenantB,'B']] as [$db,$tenant,$label]) {
    $rows=$read($db);
    $check(count($rows)===1,"$label gets one scoped candidate");
    $check($rows[0]['tenant_id']===$tenant,"$label cannot read foreign tenant");
    $check($rows[0]['portal_service_ref']==='svc-demo-f88',"$label service");
    $check($rows[0]['channel']==='meta',"$label channel");
    $check($rows[0]['account_ref']==='demo_meta_a',"$label DEMO alias");
    $check($rows[0]['connection_state']==='unverified_demo',"$label always unverified");
    $check($db->query("SELECT public.perf_f91_tenant_for_login()")->fetchColumn()===$tenant,
           "$label bound to LOGIN identity");
}

$spoof=$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)");
$spoof->execute([$tenantB]);
$check($a->query("SELECT current_setting('app.performance_tenant_id')")->fetchColumn()===$tenantB,
    'attacker can forge legacy GUC (known weakness)');
$rows=$read($a);
$check(count($rows)===1 && $rows[0]['tenant_id']===$tenantA,
    'new view ignores forged GUC and stays bound to SESSION_USER A');

$spoofB=$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)");
$spoofB->execute([$tenantA]);
$rows=$read($b);
$check(count($rows)===1 && $rows[0]['tenant_id']===$tenantB,
    'new view ignores forged GUC and stays bound to SESSION_USER B');

$denySql=static function(PDO $db,string $sql,string $label)use($check):void {
    $failed=false;
    try {
        $db->exec($sql);
    } catch(PDOException) { $failed=true; }
    $check($failed,$label);
};
$denySql($a,'SELECT tenant_id FROM performance_db_principal_tenant_bindings',
    'mapping table cannot be read by tenant A');
$denySql($a,"UPDATE performance_db_principal_tenant_bindings SET tenant_id='$tenantB' WHERE db_login='perf_f91_reader_a'",
    'mapping cannot be modified');
$denySql($a,'SELECT tenant_id FROM performance_ad_account_candidates',
    'base account table not accessible to read-only view role');
$denySql($a,'SELECT tenant_id FROM performance_campaign_account_links',
    'base account-campaign links not accessible to view role');
$denySql($a,'SELECT tenant_id FROM performance_managed_campaigns',
    'base campaigns not accessible to view role');
$denySql($a,'INSERT INTO perf_f91_demo_account_read(tenant_id) VALUES (NULL)',
    'view cannot be used to insert');
$denySql($a,'SET ROLE perf_f91_reader_b',
    'SQL login A cannot impersonate login B via SET ROLE');
$denySql($a,'SET SESSION AUTHORIZATION perf_f91_reader_b',
    'login A cannot impersonate login B via SESSION AUTHORIZATION');

$revoke=$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$revoke->execute(['perf_f91_reader_a']);
$check($read($a)===[],'revoked login A immediately reads zero rows, even with spoofed GUC');
$check(count($read($b))===1,'revoking A does not revoke B');
$restore=$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL WHERE db_login=?'
);
$restore->execute(['perf_f91_reader_a']);
$rows=$read($a);
$check(count($rows)===1 && $rows[0]['tenant_id']===$tenantA,
    'restored admin mapping brings back only A');
$check($read($b)[0]['tenant_id']===$tenantB,'B remains scoped');

$view=$admin->query(
 "SELECT reloptions FROM pg_class WHERE relname='perf_f91_demo_account_read'"
)->fetchColumn();
$check(is_string($view) && str_contains($view,'security_barrier=true'),
    'view declares security_barrier');
$fn=$admin->query(
    "SELECT prosecdef,proconfig FROM pg_proc WHERE proname='perf_f91_tenant_for_login'"
)->fetch(PDO::FETCH_ASSOC);
$check(in_array($fn['prosecdef'],[true,'t','1',1],true),
    'lookup is SECURITY DEFINER');
$check(str_contains((string)$fn['proconfig'],'search_path=pg_catalog'),
    'lookup has fixed pg_catalog search_path');
$check(!in_array($admin->query(
    "SELECT has_table_privilege('perf_f91_reader_a','performance_db_principal_tenant_bindings','SELECT')"
)->fetchColumn(),[true,'t','1',1],true),'mapping table privilege not granted');
$check(!in_array($admin->query(
    "SELECT has_table_privilege('perf_f91_reader_a','performance_ad_account_candidates','SELECT')"
)->fetchColumn(),[true,'t','1',1],true),'base table privilege not granted');
echo "PASS F91 $n credential-bound tenant view, GUC spoofing, and revocation assertions\n";
