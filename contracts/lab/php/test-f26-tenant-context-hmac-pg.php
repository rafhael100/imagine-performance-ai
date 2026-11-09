<?php

declare(strict_types=1);

/**
 * F1-26: fixture-only RLS forgery demonstration and mitigated claim test.
 * All data synthetic in disposable PostgreSQL; never use in production.
 */
if (PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F26_RLS_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry'
    || !in_array(getenv('PERF_F26_PHASE'),['unsafe','signed'],true)) {
    throw new RuntimeException('F26 runs only in local CI test database.');
}
$phase=(string)getenv('PERF_F26_PHASE');
$pdo=static fn(string $user,string $pass):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$actor=$pdo('perf_campaign_test_user','ci-f22-role-only');
$admin=$pdo('postgres','ci-f22-db-only');
$A='11111111-1111-4111-8111-111111111111';
$B='22222222-2222-4222-8222-222222222222';
$count=static fn(string $table):int=>(int)$actor->query(
    'SELECT count(*) FROM '.$table
)->fetchColumn();
$set=static function(string $key,string $value) use($actor):void {
    $p=$actor->prepare('SELECT set_config(?,?,false)');
    $p->execute([$key,$value]);
};
$n=0;
$check=static function(string $name,mixed $got,mixed $expected) use(&$n,$phase):void {
    if ($got!==$expected) {
        throw new RuntimeException('FAIL F26 '.$phase.' '.$name.' got '
            .var_export($got,true).' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F26 $phase $n $name\n";
};
$mustDeny=static function(string $label,callable $fn) use($check):void {
    try {
        $fn();
    } catch(PDOException) {
        $check($label,true,true);
        return;
    }
    throw new RuntimeException('FAIL F26 '.$label.' succeeded unexpectedly.');
};
if($phase==='unsafe'){
    $check('01 RLS enabled',
        $admin->query(
            "SELECT relrowsecurity AND relforcerowsecurity FROM pg_class
             WHERE oid='performance_managed_campaigns'::regclass"
        )->fetchColumn(),true);
    $check('02 absent custom GUC shows no campaign',$count('performance_managed_campaigns'),0);
    $set('app.performance_tenant_id',$B);
    $check('03 forged untrusted GUC exposes B campaigns (known laboratory defect)',
        $count('performance_managed_campaigns'),1);
    $set('app.performance_tenant_id',$A);
    $check('04 same role can forge A GUC too',$count('performance_managed_campaigns'),2);
    $check('05 no credentials or real media spend stored',
        (int)$admin->query(
            "SELECT count(*) FROM performance_managed_campaigns
             WHERE dispatch_state<>'disabled'"
        )->fetchColumn(),0);
    echo "F26 UNSAFE RLS GUC INJECTION REPRODUCED ONLY IN CI\n";
    exit(0);
}

$secret=(string)getenv('PERF_F26_ISSUER_KEY');
if(!preg_match('/^[0-9a-f]{64}$/D',$secret)) {
    throw new RuntimeException('Missing ephemeral issuer secret in CI.');
}
$sign=static function (
    string $tenant,
    int $expires,
    string $nonce,
    string $audience
) use($secret):string {
    $payload=implode('|',[$tenant,(string)$expires,$nonce,$audience]);
    return $payload.'|'.hash_hmac('sha256',$payload,hex2bin($secret));
};
$audience='perf-f26-ci';
$exp=time()+120;
$Aclaim=$sign($A,$exp,'nonce-a-12345',$audience);
$Bclaim=$sign($B,$exp,'nonce-b-12345',$audience);
$set('app.performance_tenant_id',$B);
$check('06 arbitrary insecure GUC alone no longer authorizes tenant',
    $count('performance_managed_campaigns'),0);
$check('07 missing signed claim no intents',
    $count('performance_campaign_intents'),0);
$check('08 verified function without claim is null',
    $actor->query('SELECT perf_f26_identity.verified_tenant()')->fetchColumn(),
    null);

$set('app.performance_tenant_claim',$Aclaim);
$check('09 authentic CI-only A claim yields A tenant via SECURITY DEFINER',
    $actor->query('SELECT perf_f26_identity.verified_tenant()')->fetchColumn(),$A);
$check('10 A claim shows own two campaigns despite forged B GUC',
    $count('performance_managed_campaigns'),2);
$check('11 forged B GUC cannot reveal B campaigns under A signature',
    (int)$actor->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$B}'"
    )->fetchColumn(),0);
$check('12 signed A sees four own intents',
    $count('performance_campaign_intents'),4);
$check('13 cannot see owner secret table via SQL privileges',
    (bool)$admin->query(
        "SELECT has_table_privilege('perf_campaign_test_user',
           'perf_f26_identity.test_issuer','SELECT')"
    )->fetchColumn(),false);
$mustDeny('14 account cannot query signer secret',static fn()=>
    $actor->query('SELECT secret FROM perf_f26_identity.test_issuer')->fetchAll());

$set('app.performance_tenant_claim',$Bclaim);
$check('15 correctly signed B claim maps only B tenant',
    $actor->query('SELECT perf_f26_identity.verified_tenant()')->fetchColumn(),$B);
$check('16 correctly signed B claim shows B one campaign',
    $count('performance_managed_campaigns'),1);
$check('17 signed B scope does not show A campaigns',
    (int)$actor->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$A}'"
    )->fetchColumn(),0);

// Mutate values without recomputing the HMAC. Only trusted issuer can mint
// a different tenant/expiry/audience under its ephemeral key.
$forged=explode('|',$Aclaim);
$forged[0]=$B;
$set('app.performance_tenant_claim',implode('|',$forged));
$check('18 tenant replacement under A signature is rejected',$count('performance_managed_campaigns'),0);
$forged=explode('|',$Aclaim);
$forged[1]=(string)(time()+100);
$set('app.performance_tenant_claim',implode('|',$forged));
$check('19 edited expiry under original signature is rejected',$count('performance_managed_campaigns'),0);
$forged=explode('|',$Aclaim);
$forged[3]='perf-live';
$set('app.performance_tenant_claim',implode('|',$forged));
$check('20 changed audience under original signature rejected',$count('performance_managed_campaigns'),0);

$negatives=[
    ['21 empty claim',''],
    ['22 arbitrary tenant GUC payload',$B],
    ['23 invalid token delimiter','junk:1:hello'],
    ['24 truncated signature',substr($Aclaim,0,-4)],
    ['25 invalid nonce regex',$sign($A,$exp,'!!bad!!',$audience)],
    ['26 unrecognized audience signed token',$sign($A,$exp,'nonce-a-45678','portal-live')],
    ['27 expired correctly signed claim',$sign($A,time()-1,'nonce-a-expire',$audience)],
    ['28 claim >5min into future',$sign($A,time()+301,'nonce-a-future',$audience)],
    ['29 invalid tenant hex case',$sign(strtoupper($A),$exp,'nonce-a-upper',$audience)],
    ['30 malformed signed tenant',$sign('not-a-uuid',$exp,'nonce-a-invalid',$audience)],
    ['31 payload with extra token segment',$Aclaim.'|extra'],
    ['32 token exceeds max size',str_repeat('x',301)],
    ['33 invalid timestamp string',$sign($A,200,'nonce-a-badtime',$audience)],
    ['34 malformed 63-char mac',substr($Aclaim,0,-1)],
];
foreach($negatives as [$label,$candidate]) {
    $set('app.performance_tenant_claim',$candidate);
    $check($label,$count('performance_managed_campaigns'),0);
}
$set('app.performance_tenant_claim',$Aclaim);
$check('35 restored valid A claim still maps A',
    $count('performance_managed_campaigns'),2);

// Check WITH CHECK independently of SELECT: A-signed user cannot insert B.
$mustDeny('36 A signed user cannot INSERT B record',function() use($actor,$B):void {
    $s=$actor->prepare(
        "INSERT INTO performance_managed_campaigns
         (id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,
          created_at,updated_at)
         VALUES (?,?,?,?,?,?,clock_timestamp(),clock_timestamp())"
    );
    $s->execute([
        'f6666666-6666-4666-8666-666666666666',
        $B,'svc-demo-b','meta','cam-f26-cross','Cross test'
    ]);
});
$check('37 B table unchanged by denied cross-tenant write',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$B}'"
    )->fetchColumn(),1);
$check('38 original A table unchanged by denied cross-tenant write',
    (int)$admin->query(
        "SELECT count(*) FROM performance_managed_campaigns
         WHERE tenant_id='{$A}'"
    )->fetchColumn(),2);
$check('39 force RLS remains enabled on campaigns',
    $admin->query(
        "SELECT relrowsecurity AND relforcerowsecurity FROM pg_class
         WHERE oid='performance_managed_campaigns'::regclass"
    )->fetchColumn(),true);
$check('40 force RLS remains enabled on approvals',
    $admin->query(
        "SELECT relrowsecurity AND relforcerowsecurity FROM pg_class
         WHERE oid='performance_campaign_intents'::regclass"
    )->fetchColumn(),true);
$check('41 claims only valid for configured synthetic audience',
    $actor->query('SELECT perf_f26_identity.verified_tenant()')->fetchColumn(),$A);
$check('42 original RLS policies replaced on both tables',
    (int)$admin->query(
        "SELECT count(*) FROM pg_policies
         WHERE tablename IN ('performance_managed_campaigns',
                             'performance_campaign_intents')
           AND qual LIKE '%perf_f26_identity.verified_tenant%'"
    )->fetchColumn(),2);

echo "PASS F26 $n signed-tenant security lab assertions after GUC spoof repro\n";
