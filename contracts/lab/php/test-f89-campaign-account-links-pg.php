<?php
declare(strict_types=1);

/**
 * F89 tests actual FK + RLS, never contacts Google/Meta or uses real IDs.
 * Relies on F88 fixture in the SAME disposable CI PostgreSQL instance.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F89_ACCOUNT_LINK_PG_LAB')!=='1'
   ||getenv('PGHOST')!=='127.0.0.1'
   ||getenv('PGPORT')!=='5432'
   ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F89 requires disposable PostgreSQL16 lab');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$role=new PDO($dsn,'perf_f89_link_registrar','ci-f89-link-only',$options);
$a='88888888-8888-4888-8888-888888888888';
$b='99999999-9999-4999-8999-999999999999';
$camA='88888888-8888-4888-8888-888888888884';
$camB='99999999-9999-4999-8999-999999999994';
$accountA='88888888-8888-4888-8888-888888888882';
$accountB='99999999-9999-4999-8999-999999999992';
$accountGoogleA='88888888-8888-4888-8888-888888888883';
$service='svc-demo-f88';

$checkN=0;
$check=static function(bool $okay,string $name)use(&$checkN):void{
    if(!$okay)throw new RuntimeException('FAIL F89 '.$name);
    ++$checkN;
};
$admin->prepare(
 'INSERT INTO performance_managed_campaigns '
 .'(id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,lifecycle_state,managed,dispatch_state) '
 .'VALUES(?,?,?,?,?,?,?,?,?)'
)->execute([$camA,$a,$service,'meta','cam-demo-f89-a','F89 demo campaign A','draft',true,'disabled']);
$admin->prepare(
 'INSERT INTO performance_managed_campaigns '
 .'(id,tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,lifecycle_state,managed,dispatch_state) '
 .'VALUES(?,?,?,?,?,?,?,?,?)'
)->execute([$camB,$b,$service,'meta','cam-demo-f89-b','F89 demo campaign B','draft',true,'disabled']);
$admin->prepare(
 'INSERT INTO performance_ad_account_candidates '
 .'(id,tenant_id,portal_service_ref,channel,account_ref) VALUES(?,?,?,?,?)'
)->execute([$accountGoogleA,$a,$service,'google_ads','demo_google_a']);

$sql='INSERT INTO performance_campaign_account_links '
 . '(id,tenant_id,portal_service_ref,channel,campaign_id,account_candidate_id,link_state,source,dispatch_allowed) '
 . 'VALUES (?,?,?,?,?,?,?,?,?)';
$make=static fn(string $id,string $tenant,string $campaign,string $account):array=>[
    $id,$tenant,'svc-demo-f88','meta',$campaign,$account,
    'unverified_demo','synthetic_sql_fixture',false
];
$insert=static function(PDO $db,array $values)use($sql):void {
    $q=$db->prepare($sql);
    foreach($values as $index=>$value) {
        $q->bindValue($index+1,$value,is_bool($value)?PDO::PARAM_BOOL:PDO::PARAM_STR);
    }
    $q->execute();
};
$run=static function(string $tenant,callable $cb)use($role):mixed {
    $role->beginTransaction();
    try{
        $q=$role->prepare("SELECT set_config('app.performance_tenant_id',?,true)");
        $q->execute([$tenant]);
        $result=$cb($role);
        $role->commit();
        return $result;
    }catch(Throwable $e){
        if($role->inTransaction())$role->rollBack();
        throw $e;
    }
};
$initialA=$make('88888888-8888-4888-8888-888888888885',$a,$camA,$accountA);
$initialB=$make('99999999-9999-4999-8999-999999999995',$b,$camB,$accountB);
$run($a,static function(PDO $db)use($insert,$initialA):void{$insert($db,$initialA);});
$run($b,static function(PDO $db)use($insert,$initialB):void{$insert($db,$initialB);});
$check(true,'same tenant/service/channel valid links');

$read=static fn(PDO $db):array=>$db->query(
 'SELECT tenant_id,channel,campaign_id,account_candidate_id,link_state,dispatch_allowed '
 .'FROM performance_campaign_account_links'
)->fetchAll(PDO::FETCH_ASSOC);
$rows=$run($a,$read);
$check(count($rows)===1 && $rows[0]['tenant_id']===$a,'A sees only A');
$check($rows[0]['campaign_id']===$camA && $rows[0]['account_candidate_id']===$accountA,'A campaign+account paired');
$check($rows[0]['channel']==='meta','Meta internal canonical');
$check($rows[0]['link_state']==='unverified_demo','no OAuth claim');
$check(in_array($rows[0]['dispatch_allowed'],[false,'f','0',0],true),'no dispatch');
$rows=$run($b,$read);
$check(count($rows)===1 && $rows[0]['tenant_id']===$b,'B sees only B');
$check(count($read($role))===0,'transaction scoped tenant context reset');

$reject=static function(string $name,string $tenant,array $v)use($run,$insert,$check):void {
    $failed=false;
    try{
        $run($tenant,static function(PDO $db)use($insert,$v):void{$insert($db,$v);});
    }catch(PDOException){$failed=true;}
    $check($failed,"deny $name");
};
$new=$make('88888888-8888-4888-8888-888888888886',$a,$camA,$accountA);
$mut=static fn(int $i,mixed $v):array=>array_replace($new,[$i=>$v]);
$reject('cross tenant insert',$a,$mut(1,$b));
$reject('account from tenant B',$a,$mut(5,$accountB));
$reject('campaign from tenant B',$a,$mut(4,$camB));
$reject('wrong account channel',$a,$mut(5,$accountGoogleA));
$reject('wrong link channel',$a,$mut(3,'google_ads'));
$reject('wrong service scope',$a,$mut(2,'svc-demo-other'));
$reject('duplicate campaign link',$a,$new);
$reject('forged active link',$a,$mut(6,'connected'));
$reject('forged provider source',$a,$mut(7,'google_ads_api'));
$reject('enable dispatch',$a,$mut(8,true));
$reject('unknown channel',$a,$mut(3,'tiktok'));

$bool=(static fn(string $sql):bool=>in_array(
    $admin->query($sql)->fetchColumn(),[true,'t','1',1],true
));
$check(!$bool("SELECT has_table_privilege('perf_f89_link_registrar','performance_campaign_account_links','UPDATE')"),
    'role cannot update relationship');
$check(!$bool("SELECT has_table_privilege('perf_f89_link_registrar','performance_campaign_account_links','DELETE')"),
    'role cannot delete relationship');
$check(!$bool("SELECT has_table_privilege('perf_f89_link_registrar','performance_ad_account_candidates','SELECT')"),
    'link role cannot list candidate accounts');
$check(!$bool("SELECT has_table_privilege('perf_f89_link_registrar','performance_managed_campaigns','SELECT')"),
    'link role cannot list campaigns');
$rls=$admin->query(
 "SELECT relrowsecurity,relforcerowsecurity FROM pg_class WHERE relname='performance_campaign_account_links'"
)->fetch(PDO::FETCH_ASSOC);
$check(in_array($rls['relrowsecurity'],[true,'t','1',1],true),'RLS enabled');
$check(in_array($rls['relforcerowsecurity'],[true,'t','1',1],true),'FORCE RLS enabled');
$check((int)$admin->query(
 "SELECT count(*) FROM performance_campaign_account_links WHERE dispatch_allowed=true"
)->fetchColumn()===0,'no provider activity possible');

echo "PASS F89 $checkN PostgreSQL tenant+service+channel FK and RLS assertions\n";
