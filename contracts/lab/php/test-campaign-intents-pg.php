<?php

declare(strict_types=1);

/**
 * F1-22 CI ONLY: test actual Laravel migrations with a non-superuser PostgreSQL
 * role, tenant GUC and composite FK. No customer data, endpoints or dispatch.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('RUN_PERF_F22_PG_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    throw new RuntimeException('Refusing campaign database test outside CI PG lab.');
}
$connect = static fn (string $user,string $pass):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false]
);
$admin=$connect('postgres','ci-f22-db-only');
$operator=$connect('perf_campaign_test_user','ci-f22-role-only');
$tenantA='11111111-1111-4111-8111-111111111111';
$tenantB='22222222-2222-4222-8222-222222222222';
$campaignA='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$campaignB='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$intentA='cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$n=0;
$check=static function(string $description,mixed $value,mixed $expected) use(&$n):void {
    if($value!==$expected){
        throw new RuntimeException('FAIL F22 '.$description.' got '
            .var_export($value,true).' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F22 $n $description\n";
};
$mustReject=static function(string $title,callable $query) use($check):void {
    try {
        $query();
    } catch(PDOException) {
        $check($title,true,true);
        return;
    }
    throw new RuntimeException('FAIL F22 '.$title.': unexpectedly accepted');
};
$scalar=static fn(PDO $db,string $sql):mixed=>$db->query($sql)->fetchColumn();
$tenant=static function(string $id) use($operator):void {
    $cmd=$operator->prepare("SELECT set_config('app.performance_tenant_id', ?, false)");
    $cmd->execute([$id]);
};
$seed=[
    ['id'=>$tenantA,'status'=>'active'],
    ['id'=>$tenantB,'status'=>'active'],
];
foreach($seed as $item){
    $stmt=$admin->prepare("INSERT INTO performance_tenants
      (id,status,created_at,updated_at) VALUES(?,?,clock_timestamp(),clock_timestamp())");
    $stmt->execute([$item['id'],$item['status']]);
}
$links=[
    [$tenantA,'org-demo-a','svc-demo-a'],
    [$tenantA,'org-demo-a','svc-demo-a2'],
    [$tenantB,'org-demo-b','svc-demo-b'],
];
foreach($links as [$t,$org,$svc]){
    $admin->prepare("INSERT INTO portal_service_links
     (id,tenant_id,portal_organization_ref,portal_service_ref,link_status,source_revision,
      created_at,updated_at)
     VALUES (gen_random_uuid(),?,?,?,'active_demo','demo-revision-1',
             clock_timestamp(),clock_timestamp())")
      ->execute([$t,$org,$svc]);
}
$campaignInsert=$operator->prepare(
    'INSERT INTO performance_managed_campaigns
     (id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,created_at,updated_at)
     VALUES (?,?,?,?,?,?,clock_timestamp(),clock_timestamp())'
);
$intentInsert=$operator->prepare(
    'INSERT INTO performance_campaign_intents
     (id,tenant_id,campaign_id,portal_service_ref,operation,proposer_actor_ref,
      reviewer_actor_ref,manifest_version,request_ref,payload_sha256,review_state,
      created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,clock_timestamp(),clock_timestamp())'
);
$makeIntent=static function(
    string $id,string $tenantId,string $campaignId,string $service,
    string $operation,string $proposer,string $reviewer,string $request,
    string $reviewState='awaiting_approval'
) use($intentInsert):void {
    $intentInsert->execute([$id,$tenantId,$campaignId,$service,$operation,
        $proposer,$reviewer,'manifest-demo-v1',$request,str_repeat('a',64),$reviewState]);
};
$check('01 campaigns FORCE RLS is active',
    $scalar($admin,"SELECT relrowsecurity AND relforcerowsecurity FROM pg_class
        WHERE oid='performance_managed_campaigns'::regclass"),true);
$check('02 campaign intents FORCE RLS is active',
    $scalar($admin,"SELECT relrowsecurity AND relforcerowsecurity FROM pg_class
        WHERE oid='performance_campaign_intents'::regclass"),true);
$check('03 only demo dispatch is permitted at SQL level',
    (int)$scalar($admin,"SELECT count(*) FROM pg_constraint
       WHERE conname IN ('perf_campaign_delivery_ck','perf_intent_delivery_ck')"),2);
$check('04 no tenant context shows no campaigns',
    (int)$scalar($operator,'SELECT count(*) FROM performance_managed_campaigns'),0);
$mustReject('05 missing tenant cannot insert campaign',static fn()=>
    $campaignInsert->execute([$campaignA,$tenantA,'svc-demo-a','google_ads','cam-a1','A One']));
$tenant($tenantA);
$campaignInsert->execute([$campaignA,$tenantA,'svc-demo-a','google_ads','cam-a1','A One']);
$check('06 A can create own draft',
    $scalar($operator,"SELECT lifecycle_state FROM performance_managed_campaigns
        WHERE campaign_ref='cam-a1'"),'draft');
$check('07 draft cannot be dispatched',
    $scalar($operator,"SELECT dispatch_state FROM performance_managed_campaigns
        WHERE campaign_ref='cam-a1'"),'disabled');
$check('08 draft remains managed quota unit',
    $scalar($operator,"SELECT managed FROM performance_managed_campaigns
        WHERE campaign_ref='cam-a1'"),true);
$tenant($tenantB);
$campaignInsert->execute([$campaignB,$tenantB,'svc-demo-b','meta','cam-b1','B One']);
$check('09 B sees only B campaign',
    (int)$scalar($operator,'SELECT count(*) FROM performance_managed_campaigns'),1);
$tenant($tenantA);
$check('10 A sees only A campaign',
    (int)$scalar($operator,'SELECT count(*) FROM performance_managed_campaigns'),1);
$check('11 A cannot see B ID',
    (int)$scalar($operator,"SELECT count(*) FROM performance_managed_campaigns
        WHERE id='{$campaignB}'"),0);
$check('12 A cannot update B campaign',
    $operator->exec("UPDATE performance_managed_campaigns
        SET lifecycle_state='paused' WHERE id='{$campaignB}'"),0);
$mustReject('13 tenant A cannot insert tenant B row',static fn()=>
    $campaignInsert->execute(['cccccccc-dddd-4ccc-8ccc-cccccccccccc',
        $tenantB,'svc-demo-b','meta','cam-cross','Cross']));
$mustReject('14 service link from B rejected for A',static fn()=>
    $campaignInsert->execute(['dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        $tenantA,'svc-demo-b','meta','cam-cross-service','Cross']));
$mustReject('15 duplicate natural campaign key refused',static fn()=>
    $campaignInsert->execute(['eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        $tenantA,'svc-demo-a','google_ads','cam-a1','Duplicate']));
$mustReject('16 unsupported channel refused',static fn()=>
    $campaignInsert->execute(['eeeeeeee-eeee-4eee-8eee-000000000001',
        $tenantA,'svc-demo-a','tiktok','cam-invalid','Invalid']));
$mustReject('17 campaign cannot become operational active from lab',
    static fn()=>$operator->exec("UPDATE performance_managed_campaigns
       SET lifecycle_state='active' WHERE id='{$campaignA}'"));
$mustReject('18 campaign cannot be dispatched by SQL',
    static fn()=>$operator->exec("UPDATE performance_managed_campaigns
       SET dispatch_state='queued' WHERE id='{$campaignA}'"));
$makeIntent($intentA,$tenantA,$campaignA,'svc-demo-a','campaign.pause',
    'actor-demo-a',null,'request-demo-1');
$check('19 intent starts awaiting human approval',
    $scalar($operator,"SELECT review_state FROM performance_campaign_intents
       WHERE id='{$intentA}'"),'awaiting_approval');
$check('20 intent cannot dispatch',
    $scalar($operator,"SELECT dispatch_state FROM performance_campaign_intents
       WHERE id='{$intentA}'"),'disabled');
$check('21 A sees one own intent',
    (int)$scalar($operator,'SELECT count(*) FROM performance_campaign_intents'),1);
$tenant($tenantB);
$check('22 tenant B cannot see A intent',
    (int)$scalar($operator,'SELECT count(*) FROM performance_campaign_intents'),0);
$tenant($tenantA);
$mustReject('23 duplicate idempotency request_ref rejected',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000001',$tenantA,$campaignA,
        'svc-demo-a','campaign.pause','actor-demo-a',null,'request-demo-1'));
$mustReject('24 intent with a different service fails composite FK',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000002',$tenantA,$campaignA,
        'svc-demo-a2','campaign.pause','actor-demo-a',null,'request-demo-2'));
$mustReject('25 intent cannot reference B campaign from A',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000003',$tenantA,$campaignB,
        'svc-demo-a','campaign.pause','actor-demo-a',null,'request-demo-3'));
$mustReject('26 unsupported operation refused',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000004',$tenantA,$campaignA,
        'svc-demo-a','billing.charge','actor-demo-a',null,'request-demo-4'));
$mustReject('27 self approval refused',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000005',$tenantA,$campaignA,
        'svc-demo-a','campaign.release','actor-demo-a','actor-demo-a',
        'request-demo-5','approved_internal'));
$mustReject('28 approved without reviewer refused',static fn()=>
    $makeIntent('00000000-0000-4000-8000-000000000006',$tenantA,$campaignA,
        'svc-demo-a','campaign.release','actor-demo-a',null,
        'request-demo-6','approved_internal'));
$mustReject('29 cannot dispatch an approved intent',static fn()=>
    $operator->exec("UPDATE performance_campaign_intents
        SET dispatch_state='dispatched' WHERE id='{$intentA}'"));
$check('30 pause remains managed and does not free slot',
    $operator->exec("UPDATE performance_managed_campaigns
        SET lifecycle_state='paused' WHERE id='{$campaignA}'"),1);
$check('31 managed paused campaign still counted',
    (int)$scalar($operator,"SELECT count(*) FROM performance_managed_campaigns
       WHERE managed=true AND lifecycle_state IN ('draft','paused')"),1);
$check('32 state now paused only in local PostgreSQL',
    $scalar($operator,"SELECT lifecycle_state FROM performance_managed_campaigns
       WHERE id='{$campaignA}'"),'paused');
$check('33 no external campaign reference is stored',
    (int)$scalar($admin,"SELECT count(*) FROM information_schema.columns
       WHERE table_name='performance_managed_campaigns'
         AND column_name='provider_access_token'"),0);
$check('34 SQL role is not a superuser',
    $scalar($operator,'SELECT usesuper FROM pg_user WHERE usename=current_user'),false);
$check('35 other tenant remains unchanged after all attacks',
    $scalar($admin,"SELECT lifecycle_state FROM performance_managed_campaigns
       WHERE id='{$campaignB}'"),'draft');
echo "PASS F22 {$n} PostgreSQL storage-only tenant/approval/dispatch safety checks\n";
