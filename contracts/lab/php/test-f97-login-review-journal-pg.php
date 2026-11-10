<?php
declare(strict_types=1);

/**
 * FAST-PERF F1-97 PostgreSQL16 disposable tenant review journal lab.
 * No real actor identity, human approval, dispatch or billing.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F97_LOGIN_REVIEW_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F97 requires disposable PostgreSQL16 CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$camA='88888888-8888-4888-8888-888888888884';
$camB='99999999-9999-4999-8999-999999999994';
$idPending='77777777-7777-4777-8777-777777777701';
$idApproved='77777777-7777-4777-8777-777777777702';
$idRejected='77777777-7777-4777-8777-777777777703';
$n=0;
$check=static function(bool $ok,string $label) use (&$n):void {
    if(!$ok)throw new RuntimeException('FAIL F97 '.$label);
    ++$n;
};
$insert=$admin->prepare(
    'INSERT INTO performance_campaign_intents '
    .'(id,tenant_id,campaign_id,portal_service_ref,operation,review_state,'
    .'proposer_actor_ref,reviewer_actor_ref,manifest_version,request_ref,'
    .'payload_sha256,dispatch_state) '
    .'VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach([
    [$idPending,$tenantA,$camA,'campaign.pause','req-demo-f97-a-pending'],
    [$idApproved,$tenantA,$camA,'campaign.create','req-demo-f97-a-approved'],
    [$idRejected,$tenantB,$camB,'campaign.replace','req-demo-f97-b-rejected'],
] as [$id,$tenant,$cam,$operation,$request]) {
    $insert->execute([
        $id,$tenant,$cam,'svc-demo-f88',$operation,'awaiting_approval',
        'actor-proposer-demo',null,'manifest-demo-v1',$request,str_repeat('a',64),'disabled'
    ]);
}
$terminal=$admin->prepare(
    'UPDATE performance_campaign_intents SET review_state=?,reviewer_actor_ref=? WHERE id=?'
);
$terminal->execute(['approved_internal','actor-reviewer-demo-a',$idApproved]);
$terminal->execute(['rejected','actor-reviewer-demo-b',$idRejected]);
$check((int)$admin->query(
    "SELECT count(*) FROM performance_intent_review_journal WHERE intent_id IN "
    ."('$idApproved','$idRejected')"
)->fetchColumn()===2,'real F27 trigger writes two terminal journal records');

$read=static fn(PDO $db):array=>$db->query(
    'SELECT tenant_id,portal_service_ref,channel,campaign_ref,request_ref,'
    .'operation,review_state,dispatch_state,journal_review_state,journaled '
    .'FROM public.perf_f97_demo_review_read ORDER BY request_ref'
)->fetchAll(PDO::FETCH_ASSOC);

$rowsA=$read($a);$rowsB=$read($b);
$check(count($rowsA)===2,'login A sees pending and approved DEMO reviews');
$check(count($rowsB)===1,'login B sees rejected DEMO review');
$check(array_column($rowsA,'review_state')===['approved_internal','awaiting_approval'],
    'A states sorted by request reference');
$check($rowsB[0]['review_state']==='rejected','B rejection state');
foreach([[$a,$tenantA,$rowsA,'A'],[$b,$tenantB,$rowsB,'B']] as [$db,$tenant,$rows,$name]){
    $check($db->query('SELECT session_user')->fetchColumn()==='perf_f91_reader_'.strtolower($name),
        "$name authenticated PostgreSQL login");
    foreach($rows as $row){
        $check($row['tenant_id']===$tenant,"$name tenant isolated");
        $check($row['portal_service_ref']==='svc-demo-f88',"$name DEMO service");
        $check($row['channel']==='meta',"$name Meta channel");
        $check($row['dispatch_state']==='disabled',"$name cannot publish");
        $check(str_starts_with($row['request_ref'],'req-demo-f97-'),"$name DEMO request");
        if($row['review_state']==='awaiting_approval'){
            $check(in_array($row['journaled'],[false,'f','0',0],true),
                "$name pending not journaled");
            $check($row['journal_review_state']===null,
                "$name pending has no terminal journal");
        }else{
            $check(in_array($row['journaled'],[true,'t','1',1],true),
                "$name final decision journaled");
            $check($row['journal_review_state']===$row['review_state'],
                "$name matching F27 journal");
        }
    }
}
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantA]);
$check($read($a)===$rowsA,'spoofed GUC B cannot change login A review read');
$check($read($b)===$rowsB,'spoofed GUC A cannot change login B review read');
$check((int)$a->query(
    "SELECT count(*) FROM perf_f97_demo_review_read WHERE tenant_id='$tenantB'"
)->fetchColumn()===0,'A cannot query tenant B with WHERE');
$check((int)$b->query(
    "SELECT count(*) FROM perf_f97_demo_review_read WHERE tenant_id='$tenantA'"
)->fetchColumn()===0,'B cannot query tenant A with WHERE');

$deny=static function(PDO $db,string $sql,string $label)use($check):void{
    $blocked=false;
    try{$db->exec($sql);}catch(PDOException){$blocked=true;}
    $check($blocked,$label);
};
$deny($a,'SELECT id FROM performance_campaign_intents','A cannot read base intents');
$deny($a,'SELECT id FROM performance_intent_review_journal','A cannot read base journal');
$deny($b,'SELECT id FROM performance_campaign_intents','B cannot read base intents');
$deny($a,"UPDATE perf_f97_demo_review_read SET review_state='approved_internal'",
    'A cannot modify read VIEW');
$deny($b,"INSERT INTO perf_f97_demo_review_read(tenant_id) VALUES(NULL)",
    'B cannot insert into VIEW');
$deny($a,'SET ROLE perf_f91_reader_b','A cannot impersonate B role');
$deny($b,'SET SESSION AUTHORIZATION perf_f91_reader_a','B cannot impersonate A login');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($read($a)===[],'revocation immediately hides A review rows');
$check($read($b)===$rowsB,'B remains available while A is revoked');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($read($a)===$rowsA,'DB-admin restore returns only A reviews');

$viewOpts=$admin->query(
    "SELECT reloptions FROM pg_class WHERE relname='perf_f97_demo_review_read'"
)->fetchColumn();
$check(is_string($viewOpts)&&str_contains($viewOpts,'security_barrier=true'),
    'VIEW declares security barrier');
foreach(['perf_f91_reader_a','perf_f91_reader_b'] as $role){
    foreach(['performance_campaign_intents','performance_intent_review_journal',
             'performance_managed_campaigns','performance_db_principal_tenant_bindings'] as $table) {
        $can=$admin->query("SELECT has_table_privilege('$role','$table','SELECT')")->fetchColumn();
        $check(!in_array($can,[true,'t','1',1],true),"$role cannot select base $table");
    }
}

// Deliberate synthetic corruption inserted by DB admin: a terminal journal
// forged for an intent that is still pending. A safe VIEW must not publish it.
$rogue=$admin->prepare(
    'INSERT INTO performance_intent_review_journal '
    .'(id,intent_id,tenant_id,portal_service_ref,request_ref,review_state,'
    .'reviewer_actor_ref,manifest_version,payload_sha256,source,synthetic_only) '
    .'VALUES(?,?,?,?,?,?,?,?,?,?,?)'
);
$rogue->execute([
    '77777777-7777-4777-8777-777777777799',$idPending,$tenantA,'svc-demo-f88',
    'req-demo-f97-a-pending','rejected','actor-rogue-demo',
    'manifest-demo-v1',str_repeat('a',64),'synthetic_postgresql_only',true
]);
$quarantined=$read($a);
$check(count($quarantined)===1
    && $quarantined[0]['review_state']==='approved_internal',
    'pending intent with a rogue terminal journal is quarantined');
$check($read($b)===$rowsB,'quarantine cannot alter independent tenant B');
echo "PASS F97 $n assertions: PostgreSQL authenticated reviews, real F27 journal, GUC spoof denial and rogue journal quarantine\n";
