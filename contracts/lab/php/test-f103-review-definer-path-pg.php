<?php
declare(strict_types=1);

/**
 * F1-103 validates SECURITY DEFINER search_path hardening on actual PG16
 * after F22-F102 integration and F99 legacy SQL grants revocation.
 */
if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F103_REVIEW_DEFINER_LAB')!=='1'
   ||getenv('PGHOST')!=='127.0.0.1'
   ||getenv('PGPORT')!=='5432'
   ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F103 restricted to disposable PostgreSQL16 CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$db=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$n=0;
$check=static function(bool $yes,string $label)use(&$n):void{
    if(!$yes)throw new RuntimeException('FAIL F103 '.$label);
    ++$n;
};
$fn=$db->query(
 "SELECT prosecdef,proconfig,proacl FROM pg_proc "
 ."WHERE proname='perf_f27_capture_review_transition' "
 ."AND pronamespace='public'::regnamespace"
)->fetch(PDO::FETCH_ASSOC);
$check(is_array($fn),'F27 function exists');
$check(in_array($fn['prosecdef'],[true,'t','1',1],true),'F27 is SECURITY DEFINER');
$config=(string)$fn['proconfig'];
$check(str_contains($config,'search_path=pg_catalog'),'uses catalog-only search path');
$check(!str_contains($config,'public'),'no mutable public schema in lookup path');

foreach(['perf_f91_reader_a','perf_f91_reader_b','perf_f28_app_reader'] as $role) {
    $stmt=$db->prepare(
        "SELECT has_function_privilege(?, 'public.perf_f27_capture_review_transition()','EXECUTE')"
    );
    $stmt->execute([$role]);
    $check(!in_array($stmt->fetchColumn(),[true,'t','1',1],true),
        "no direct EXECUTE grant to $role");
}

$intent='77777777-7777-4777-8777-777777777704';
$tenant='88888888-8888-4888-8888-888888888888';
$campaign='88888888-8888-4888-8888-888888888884';
$request='req-demo-f103-locked-trigger';
$insert=$db->prepare(
    'INSERT INTO performance_campaign_intents '
    .'(id,tenant_id,campaign_id,portal_service_ref,operation,review_state,'
    .'proposer_actor_ref,reviewer_actor_ref,manifest_version,request_ref,'
    .'payload_sha256,dispatch_state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
);
$insert->execute([
    $intent,$tenant,$campaign,'svc-demo-f88','campaign.pause',
    'awaiting_approval','actor-proposer-f103',null,'manifest-demo-v1',
    $request,str_repeat('c',64),'disabled'
]);
$start=(int)$db->query(
    "SELECT count(*) FROM performance_intent_review_journal WHERE intent_id='$intent'"
)->fetchColumn();
$check($start===0,'pending state has no terminal journal');
$transition=$db->prepare(
    'UPDATE performance_campaign_intents SET review_state=?,reviewer_actor_ref=? WHERE id=?'
);
$transition->execute(['approved_internal','actor-reviewer-f103',$intent]);
$check($transition->rowCount()===1,'F27 transition executed after search path lockdown');

$journal=$db->query(
    "SELECT tenant_id,portal_service_ref,request_ref,review_state,source,synthetic_only "
    ."FROM performance_intent_review_journal WHERE intent_id='$intent'"
)->fetch(PDO::FETCH_ASSOC);
$check(is_array($journal),'trigger still writes append-only journal');
$check($journal['tenant_id']===$tenant,'correct tenant');
$check($journal['portal_service_ref']==='svc-demo-f88','same service');
$check($journal['request_ref']===$request,'original request reference');
$check($journal['review_state']==='approved_internal','internal DEMO approval only');
$check($journal['source']==='synthetic_postgresql_only','still synthetic');
$check(in_array($journal['synthetic_only'],[true,'t','1',1],true),'journal synthetic flag enforced');

$beforeB=(int)$b->query(
    'SELECT count(*) FROM perf_f97_demo_review_read'
)->fetchColumn();
$rows=$a->query(
    'SELECT request_ref,review_state,dispatch_state,journaled FROM perf_f97_demo_review_read'
)->fetchAll(PDO::FETCH_ASSOC);
$matches=array_values(array_filter(
    $rows,static fn(array $row):bool=>$row['request_ref']===$request
));
$check(count($matches)===1,'login A sees journaled F103 internal review');
$check($matches[0]['review_state']==='approved_internal','VIEW keeps internal state');
$check($matches[0]['dispatch_state']==='disabled','no Ads dispatch');
$check(in_array($matches[0]['journaled'],[true,'t','1',1],true),
    'VIEW validates matching journal');
$check((int)$b->query(
    "SELECT count(*) FROM perf_f97_demo_review_read WHERE request_ref='$request'"
)->fetchColumn()===0,'login B does not see F103 tenant A review');
$check((int)$b->query(
    'SELECT count(*) FROM perf_f97_demo_review_read'
)->fetchColumn()===$beforeB,'tenant B rows unchanged');

$blocked=false;
try{$a->query('SELECT public.perf_f27_capture_review_transition()');}
catch(PDOException){$blocked=true;}
$check($blocked,'tenant login cannot call trigger function');

$check((int)$db->query(
    "SELECT count(*) FROM performance_intent_review_journal WHERE intent_id='$intent'"
)->fetchColumn()===1,'terminal review journal is not duplicated');
echo "PASS F103 $n F27 SECURITY DEFINER fixed search_path, functioning trigger and tenant isolation checks\n";
