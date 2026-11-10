<?php
declare(strict_types=1);

/** F104 security rehearsal against ephemeral PostgreSQL16 only. */
if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F104_RESTRICTED_DEFINER_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F104 requires disposable CI PostgreSQL');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opt);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opt);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opt);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok) throw new RuntimeException('FAIL F104 '.$label);
    ++$n;
};
$role=$db->query(
    "SELECT rolname,rolsuper,rolcanlogin,rolcreaterole,rolcreatedb,"
    ."rolbypassrls,rolinherit FROM pg_catalog.pg_roles "
    ."WHERE rolname='perf_f104_journal_writer'"
)->fetch(PDO::FETCH_ASSOC);
$check(is_array($role),'dedicated journal writer role exists');
foreach(['rolsuper','rolcanlogin','rolcreaterole','rolcreatedb','rolbypassrls','rolinherit'] as $flag) {
    $check(in_array($role[$flag],[false,'f','0',0],true),"$flag denied for owner");
}
$fn=$db->query(
    "SELECT p.prosecdef,p.proconfig,pg_get_userbyid(p.proowner) AS owner "
    ."FROM pg_catalog.pg_proc p WHERE p.proname='perf_f27_capture_review_transition' "
    ."AND p.pronamespace='public'::regnamespace"
)->fetch(PDO::FETCH_ASSOC);
$check(is_array($fn) && $fn['owner']==='perf_f104_journal_writer',
    'F27 trigger no longer owned by postgres superuser');
$check(in_array($fn['prosecdef'],[true,'t','1',1],true),
    'F27 still uses SECURITY DEFINER');
$check(str_contains((string)$fn['proconfig'],'search_path=pg_catalog'),
    'F27 search path remains catalog only');
$check(!str_contains((string)$fn['proconfig'],'public'),
    'mutable public schema excluded from lookup path');

$priv=static function(string $object,string $permission)use($db):bool{
    $stmt=$db->prepare('SELECT has_table_privilege(?,?,?)');
    $stmt->execute(['perf_f104_journal_writer',$object,$permission]);
    return in_array($stmt->fetchColumn(),[true,'t','1',1],true);
};
$check($priv('public.performance_intent_review_journal','INSERT'),
    'only necessary journal INSERT granted');
foreach(['SELECT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'] as $p) {
    $check(!$priv('public.performance_intent_review_journal',$p),
        "journal writer denied $p");
}
foreach(['performance_managed_campaigns','performance_campaign_intents',
    'performance_ad_account_candidates','performance_campaign_account_links',
    'performance_db_principal_tenant_bindings'] as $table) {
    foreach(['SELECT','INSERT','UPDATE','DELETE'] as $p) {
        $check(!$priv('public.'.$table,$p),"writer has no $p on $table");
    }
}
$policy=(int)$db->query(
    "SELECT count(*) FROM pg_catalog.pg_policy WHERE polname='perf_f104_writer_synthetic_insert' "
    ."AND polrelid='public.performance_intent_review_journal'::regclass "
    ."AND polcmd='a'"
)->fetchColumn();
$check($policy===1,'INSERT-only RLS policy exists');

foreach(['perf_f91_reader_a','perf_f91_reader_b','perf_f28_app_reader'] as $login) {
    $q=$db->prepare(
        "SELECT pg_has_role(?, 'perf_f104_journal_writer', 'member')"
    );
    $q->execute([$login]);
    $check(!in_array($q->fetchColumn(),[true,'t','1',1],true),
        "$login cannot SET ROLE or inherit writer");
    $q=$db->prepare(
        "SELECT has_function_privilege(?,'public.perf_f27_capture_review_transition()','EXECUTE')"
    );
    $q->execute([$login]);
    $check(!in_array($q->fetchColumn(),[true,'t','1',1],true),
        "$login cannot directly execute trigger function");
}
$intent='77777777-7777-4777-8777-777777777705';
$tenant='88888888-8888-4888-8888-888888888888';
$campaign='88888888-8888-4888-8888-888888888884';
$request='req-demo-f104-non-superuser-owner';
$insert=$db->prepare(
    'INSERT INTO performance_campaign_intents '
    .'(id,tenant_id,campaign_id,portal_service_ref,operation,review_state,'
    .'proposer_actor_ref,reviewer_actor_ref,manifest_version,request_ref,'
    .'payload_sha256,dispatch_state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
);
$insert->execute([
    $intent,$tenant,$campaign,'svc-demo-f88','campaign.pause','awaiting_approval',
    'actor-proposer-f104',null,'manifest-demo-v1',$request,str_repeat('d',64),'disabled'
]);
$change=$db->prepare(
    'UPDATE performance_campaign_intents SET review_state=?,reviewer_actor_ref=? WHERE id=?'
);
$change->execute(['rejected','actor-reviewer-f104',$intent]);
$check($change->rowCount()===1,'synthetic F27 terminal transition still works under NOLOGIN owner');
$journal=$db->query(
    "SELECT tenant_id,portal_service_ref,request_ref,review_state,source,synthetic_only "
    ."FROM performance_intent_review_journal WHERE intent_id='$intent'"
)->fetch(PDO::FETCH_ASSOC);
$check(is_array($journal),'real F27 trigger inserts journal under restricted owner');
$check($journal['tenant_id']===$tenant,'journal tenant matches intent');
$check($journal['portal_service_ref']==='svc-demo-f88','journal service correct');
$check($journal['review_state']==='rejected','journal terminal state preserved');
$check($journal['request_ref']===$request,'journal request preserved');
$check($journal['source']==='synthetic_postgresql_only','journal source synthetic');
$check(in_array($journal['synthetic_only'],[true,'t','1',1],true),'synthetic_only enforced');

$viewA=$a->prepare(
    'SELECT request_ref,review_state,dispatch_state,journaled '
    .'FROM perf_f97_demo_review_read WHERE request_ref=?'
);
$viewA->execute([$request]);
$rowsA=$viewA->fetchAll(PDO::FETCH_ASSOC);
$check(count($rowsA)===1 && $rowsA[0]['review_state']==='rejected',
    'tenant A sees internal reject after function ownership switch');
$check($rowsA[0]['dispatch_state']==='disabled','new owner cannot dispatch');
$check(in_array($rowsA[0]['journaled'],[true,'t','1',1],true),
    'F97 view verifies F27 journal');
$viewB=$b->prepare(
    'SELECT count(*) FROM perf_f97_demo_review_read WHERE request_ref=?'
);
$viewB->execute([$request]);
$check((int)$viewB->fetchColumn()===0,'tenant B cannot read A rejection');

$blocked=false;
try{$a->exec('SET ROLE perf_f104_journal_writer');}catch(PDOException){$blocked=true;}
$check($blocked,'tenant A cannot assume dedicated definer role');
$blocked=false;
try{$a->exec("INSERT INTO performance_intent_review_journal(id) VALUES(gen_random_uuid())");}
catch(PDOException){$blocked=true;}
$check($blocked,'tenant A cannot write review journal directly');
$check((int)$db->query(
    "SELECT count(*) FROM performance_intent_review_journal WHERE intent_id='$intent'"
)->fetchColumn()===1,'one terminal journal only');

echo "PASS F104 $n assertions: NOLOGIN, NOBYPASSRLS journal SECURITY DEFINER owner with real F27 review trigger\n";
