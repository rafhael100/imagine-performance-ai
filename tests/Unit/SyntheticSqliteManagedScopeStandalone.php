<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticSqliteManagedScopeStore.php';

use Imagine\Performance\Domain\SyntheticSqliteManagedScopeStore;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F64_SQLITE_SCOPE_LAB')!=='1'){
    throw new RuntimeException('F64 only supports disposable SQLite CI');
}
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected)use(&$n):void{
    if($actual!==$expected){
        throw new RuntimeException('FAIL F64 '.$label.' got='.
          var_export($actual,true).' expected='.var_export($expected,true));
    }
    echo 'PASS F64 '.++$n.' '.$label.PHP_EOL;
};
$throws=static function(callable $fn):bool{
    try{$fn();return false;}catch(InvalidArgumentException){return true;}
};
$path=sys_get_temp_dir().'/perf-f64-unit-'.bin2hex(random_bytes(5)).'.sqlite';
$db=new PDO('sqlite:'.$path);
$store=new SyntheticSqliteManagedScopeStore($db);
$a='perf-demo-a';$b='perf-demo-b';
try{
    $check('01 default A Google cap blocked',
        $store->capacity($a,'google_ads')['status'],'unconfigured_blocked_demo');
    $check('02 no default numeric quota',
        $store->capacity($a,'google_ads')['campaigns']['limit'],null);
    $check('03 cannot create managed campaign before configured',
        $store->addCampaign($a,'google_ads','cam-a1'),'capacity_unapproved_denied');
    $check('04 quota A Google set',
        $store->configure($a,'google_ads',2,2),'capacity_configured_demo');
    $check('05 quota A Meta distinct',
        $store->configure($a,'meta',1,1),'capacity_configured_demo');
    $check('06 quota B Google distinct',
        $store->configure($b,'google_ads',1,1),'capacity_configured_demo');
    $check('07 invalid negative quota refused',
        $throws(static fn()=>$store->configure($a,'google_ads',-1,2)),true);
    $check('08 unapproved channel refused',
        $throws(static fn()=>$store->configure($a,'tiktok',1,2)),true);
    $check('09 malformed tenant refused',
        $throws(static fn()=>$store->configure('../evil','meta',1,1)),true);
    $check('10 excess demo quota refused',
        $throws(static fn()=>$store->configure($a,'meta',101,1)),true);
    $check('11 A Google first campaign reserved',
        $store->addCampaign($a,'google_ads','cam-a1'),'included_synthetic_only');
    $check('12 A Google second campaign reserved',
        $store->addCampaign($a,'google_ads','cam-a2'),'included_synthetic_only');
    $check('13 quota full denies more',
        $store->addCampaign($a,'google_ads','cam-a3'),'capacity_review_required');
    $check('14 capacity used is 2',
        $store->capacity($a,'google_ads')['campaigns']['used'],2);
    $check('15 remaining quota zero',
        $store->capacity($a,'google_ads')['campaigns']['remaining'],0);
    $check('16 duplicate campaign denied',
        $store->addCampaign($a,'google_ads','cam-a1'),'already_exists_or_out_of_scope');
    $check('17 B cannot reuse global campaign ID',
        $store->addCampaign($b,'google_ads','cam-a1'),'already_exists_or_out_of_scope');
    $check('18 B own campaign created',
        $store->addCampaign($b,'google_ads','cam-b1'),'included_synthetic_only');
    $check('19 B quota unaffected by A',
        $store->capacity($b,'google_ads')['campaigns']['used'],1);
    $check('20 A Meta campaign under separate limit',
        $store->addCampaign($a,'meta','cam-meta-a'),'included_synthetic_only');
    $check('21 Meta cap full independent',
        $store->capacity($a,'meta')['campaigns']['remaining'],0);
    $check('22 malformed campaign ref rejected',
        $throws(static fn()=>$store->addCampaign($a,'google_ads','=bad')),true);

    $check('23 first group reserved under A',
        $store->addGroup($a,'cam-a1','grp-a1'),'included_synthetic_only');
    $check('24 second group reserved under A',
        $store->addGroup($a,'cam-a1','grp-a2'),'included_synthetic_only');
    $check('25 group cap reached',
        $store->addGroup($a,'cam-a2','grp-a3'),'capacity_review_required');
    $check('26 paused group still managed via campaign',
        $store->capacity($a,'google_ads')['groups']['used'],2);
    $check('27 B cannot add group under A',
        $store->addGroup($b,'cam-a1','grp-b-foreign'),'campaign_not_found');
    $check('28 duplicate group no change',
        $store->addGroup($a,'cam-a1','grp-a1'),'already_exists_or_out_of_scope');
    $check('29 no campaign -> no group',
        $store->addGroup($a,'cam-missing','grp-orphan'),'campaign_not_found');
    $check('30 cannot shrink group cap below use',
        $store->configure($a,'google_ads',2,1),'reduction_below_usage_denied');
    $check('31 cannot shrink campaign cap below use',
        $store->configure($a,'google_ads',1,2),'reduction_below_usage_denied');
    $check('32 denied shrink preserves quota',
        $store->capacity($a,'google_ads')['groups']['limit'],2);
    $check('33 pause changes only stored demo state',
        $store->pauseCampaign($a,'cam-a1'),'paused_synthetic_only');
    $check('34 paused still counted',
        $store->capacity($a,'google_ads')['campaigns']['used'],2);
    $check('35 paused state durable',
        $store->campaignState($a,'cam-a1'),'paused_demo');
    $check('36 pause repeated idempotently',
        $store->pauseCampaign($a,'cam-a1'),'already_paused_demo');
    $check('37 B cannot pause A campaign',
        $store->pauseCampaign($b,'cam-a1'),'not_found');
    $check('38 B cannot read A state',
        $store->campaignState($b,'cam-a1'),'not_found');

    $check('39 SQL FK denies cross-tenant group bypass',
        (static function()use($db,$b):bool{
            try{
                $q=$db->prepare(
                    "INSERT INTO f64_groups(ref,tenant,campaign_ref,state,managed)"
                    ." VALUES(?,?,?,'active_demo',1)"
                );
                $q->execute(['grp-foreign-raw',$b,'cam-a1']);
                return false;
            }catch(PDOException $e){return $e->getCode()==='23000';}
        })(),true);
    $check('40 SQL FK denies cross-tenant replacement receipt bypass',
        (static function()use($db,$b):bool{
            try{
                $q=$db->prepare(
                    "INSERT INTO f64_swaps(request_ref,tenant,old_ref,new_ref,result)"
                    ." VALUES(?,?,?,?,'replaced_synthetic_no_fee')"
                );
                $q->execute(['req-demo-cross-raw',$b,'cam-a1','cam-a2']);
                return false;
            }catch(PDOException $e){return $e->getCode()==='23000';}
        })(),true);
    $check('41 SQL CHECK denies operational state',
        (static function()use($db,$a):bool{
            try{
                $q=$db->prepare(
                    "INSERT INTO f64_campaigns(ref,tenant,channel,state,managed)"
                    ." VALUES(?,?,?,'published_real',1)"
                );
                $q->execute(['cam-evil',$a,'google_ads']);
                return false;
            }catch(PDOException $e){return $e->getCode()==='23000';}
        })(),true);
    $check('42 database has FK enabled',
        (int)$db->query('PRAGMA foreign_keys')->fetchColumn(),1);
    $check('43 provider dispatch never present',
        $store->capacity($a,'google_ads')['dispatch_enabled'],false);
    $check('44 no commercial entitlement',
        $store->capacity($a,'google_ads')['contracted'],false);

    $req='req-demo-a-replace';
    $check('45 mock swap requires recommendation',
        $store->replaceCampaign($a,$req,'cam-a1','cam-new',false,true),
        'recommendation_required');
    $check('46 mock swap requires reviewer',
        $store->replaceCampaign($a,$req,'cam-a1','cam-new',true,false),
        'human_review_required');
    $check('47 denied swap unchanged state',
        $store->campaignState($a,'cam-a1'),'paused_demo');
    $check('48 approved test-only swap atomic',
        $store->replaceCampaign($a,$req,'cam-a1','cam-new',true,true),
        'replaced_synthetic_no_fee');
    $check('49 prior campaign exits scope',
        $store->campaignState($a,'cam-a1'),'outside_scope_demo');
    $check('50 new campaign starts draft',
        $store->campaignState($a,'cam-new'),'draft_demo');
    $check('51 swap does not overfill cap',
        $store->capacity($a,'google_ads')['campaigns']['used'],2);
    $check('52 prior groups formally leave scope',
        $store->capacity($a,'google_ads')['groups']['used'],0);
    $check('53 repeated same request is idempotent',
        $store->replaceCampaign($a,$req,'cam-a1','cam-new',true,true),
        'already_applied_synthetic');
    $check('54 modified request immutable',
        $store->replaceCampaign($a,$req,'cam-a2','cam-evil2',true,true),
        'request_conflict_or_out_of_scope');
    $check('55 foreign tenant cannot use same request',
        $store->replaceCampaign($b,$req,'cam-b1','cam-new-b',true,true),
        'request_conflict_or_out_of_scope');
    $check('56 B untouched by A swap',
        $store->campaignState($b,'cam-b1'),'active_demo');
    $check('57 historical old ID cannot be reused',
        $store->addCampaign($a,'google_ads','cam-a1'),'already_exists_or_out_of_scope');
    $check('58 new group can occupy freed old group quota',
        $store->addGroup($a,'cam-new','grp-new'),'included_synthetic_only');
    $check('59 new group count 1',
        $store->capacity($a,'google_ads')['groups']['used'],1);
    $check('60 foreign group denied via managed scope',
        $store->addGroup($b,'cam-new','grp-foreign'),'campaign_not_found');
    $check('61 cannot shrink quota below current campaign usage',
        $store->configure($a,'google_ads',1,2),'reduction_below_usage_denied');
    $check('62 nested transaction denied rather than implicit commit',
        (static function()use($db,$store,$a):string{
            $db->exec('BEGIN IMMEDIATE');
            try{
                return $store->addCampaign($a,'google_ads','cam-nested');
            }finally{$db->exec('ROLLBACK');}
        })(),'nested_transaction_denied');
    $check('63 nested attempt did not create record',
        $store->campaignState($a,'cam-nested'),'not_found');
    $check('64 no billing flag in persisted capacity',
        $store->capacity($a,'google_ads')['billing_enabled'],false);
    $check('65 no fake provider information serialized',
        !preg_match('/oauth|access_token|provider_account|media_spend|price_brl/i',
        json_encode($store->capacity($a,'google_ads'),JSON_THROW_ON_ERROR)),true);
    $check('66 cap changes only via explicit demonstration call',
        $store->configure($a,'google_ads',2,3),'capacity_configured_demo');
    $check('67 expanded group remaining',
        $store->capacity($a,'google_ads')['groups']['remaining'],2);
    $check('68 group count 1 after expansion',
        $store->capacity($a,'google_ads')['groups']['used'],1);
    $check('69 capacity output deterministic',
        $store->capacity($a,'google_ads'),$store->capacity($a,'google_ads'));

    // Verify durable state from a SECOND connection before closing primary.
    $secondDb=new PDO('sqlite:'.$path);
    $second=new SyntheticSqliteManagedScopeStore($secondDb);
    $check('70 independent connection sees new campaign state',
        $second->campaignState($a,'cam-new'),'draft_demo');
    $check('71 independent connection sees used cap',
        $second->capacity($a,'google_ads')['campaigns']['used'],2);
    $check('72 independent connection sees group count',
        $second->capacity($a,'google_ads')['groups']['used'],1);
    $check('73 independent connection sees B isolated quota',
        $second->capacity($b,'google_ads')['campaigns']['used'],1);
    $check('74 second connection cannot cross-tenant pause',
        $second->pauseCampaign($b,'cam-new'),'not_found');
    $check('75 still no real provider dispatch',
        $second->capacity($a,'google_ads')['dispatch_enabled'],false);
    unset($second,$secondDb);

    putenv('PERF_F64_SQLITE_SCOPE_LAB=0');
    $check('76 disabled flag refuses operation',
        $store->pauseCampaign($a,'cam-new'),'lab_disabled');
    $check('77 disabled flag refuses report',
        $store->capacity($a,'google_ads')['status'],'lab_disabled');
    putenv('PERF_F64_SQLITE_SCOPE_LAB=1');
    putenv('APP_ENV=production');
    $check('78 production denies durable test mutation',
        $store->addGroup($a,'cam-new','grp-production'),'lab_disabled');
    $check('79 production read access disabled',
        $store->capacity($a,'google_ads')['status'],'lab_disabled');
    putenv('APP_ENV=testing');
    $check('80 restored testing reads original count',
        $store->capacity($a,'google_ads')['campaigns']['used'],2);
}finally{
    unset($store,$db);
    @unlink($path);
}
echo "PASS F64 $n SQLite transactional mock scope tests, no providers/billing".PHP_EOL;
