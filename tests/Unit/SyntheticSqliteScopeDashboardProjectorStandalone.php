<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticSqliteManagedScopeStore.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticSqliteScopeDashboardProjector.php';

use Imagine\Performance\Domain\SyntheticSqliteManagedScopeStore;
use Imagine\Performance\Domain\SyntheticSqliteScopeDashboardProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F64_SQLITE_SCOPE_LAB')!=='1'
  ||getenv('PERF_F65_DURABLE_PREVIEW_LAB')!=='1'){
    throw new RuntimeException('F65 only runs under disposable SQLite CI');
}
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected)use(&$n):void{
    if($actual!==$expected){
        throw new RuntimeException('FAIL F65 '.$label.' got='
            .var_export($actual,true).' expected='.var_export($expected,true));
    }
    echo 'PASS F65 '.++$n.' '.$label.PHP_EOL;
};
$path=sys_get_temp_dir().'/perf-f64-f65-'.bin2hex(random_bytes(5)).'.sqlite';
$db=new PDO('sqlite:'.$path);
$store=new SyntheticSqliteManagedScopeStore($db);
$p=new SyntheticSqliteScopeDashboardProjector();
$a='perf-demo-a';$b='perf-demo-b';
try{
    $check('01 A Google capacity setup',
        $store->configure($a,'google_ads',2,3),'capacity_configured_demo');
    $check('02 A Meta capacity setup',
        $store->configure($a,'meta',1,2),'capacity_configured_demo');
    $check('03 B Google capacity setup',
        $store->configure($b,'google_ads',1,1),'capacity_configured_demo');
    $check('04 A first Google campaign',
        $store->addCampaign($a,'google_ads','cam-a-g1'),'included_synthetic_only');
    $check('05 A second Google campaign',
        $store->addCampaign($a,'google_ads','cam-a-g2'),'included_synthetic_only');
    $check('06 A Meta campaign',
        $store->addCampaign($a,'meta','cam-a-meta'),'included_synthetic_only');
    $check('07 B separate Google campaign',
        $store->addCampaign($b,'google_ads','cam-b-g1'),'included_synthetic_only');
    $check('08 A first group',
        $store->addGroup($a,'cam-a-g1','grp-a1'),'included_synthetic_only');
    $check('09 A second group',
        $store->addGroup($a,'cam-a-g1','grp-a2'),'included_synthetic_only');
    $check('10 A Meta group',
        $store->addGroup($a,'cam-a-meta','grp-meta'),'included_synthetic_only');
    $check('11 B group',
        $store->addGroup($b,'cam-b-g1','grp-b1'),'included_synthetic_only');
    $check('12 pause keeps managed campaign',
        $store->pauseCampaign($a,'cam-a-g1'),'paused_synthetic_only');
    $check('13 group state for own tenant',
        $store->groupState($a,'grp-a1'),'active_demo');
    $check('14 foreign group read denied',
        $store->groupState($b,'grp-a1'),'not_found');
    $check('15 missing group read denied',
        $store->groupState($a,'grp-missing'),'not_found');

    $campaignHints=['cam-a-g1','cam-a-g2','cam-a-meta','cam-b-g1','cam-a-g1'];
    $groupHints=['grp-a1','grp-a2','grp-meta','grp-b1','grp-a1'];
    $dto=$p->build($store,$a,$campaignHints,$groupHints);
    $check('16 initial durable projection exists',is_array($dto),true);
    $check('17 same schema as F53 mock UI',
        $dto['schema_version'],'perf.portal.scope-preview.synthetic.v1');
    $check('18 SQLite source identifies its fake origin',
        $dto['source'],'sqlite_ci_fixture_only');
    $check('19 correct tenant',$dto['tenant_ref'],$a);
    foreach([
        'read_only'=>true,'contracted'=>false,'ready_for_production'=>false,
        'authorization_eligible'=>false,'dispatch_enabled'=>false,
        'billing_enabled'=>false,'prices_homologated'=>false
    ] as $flag=>$value){
        $check('NO-GO '.$flag,$dto[$flag],$value);
    }
    $check('20 commercial NOT homologated',
        $dto['commercial'],'not_homologated');
    $check('21 correct channel IDs',
        array_column($dto['channels'],'id'),['google_ads','meta']);
    $check('22 Google managed campaign count includes pause',
        $dto['channels'][0]['campaigns']['used'],2);
    $check('23 Google cap 2',
        $dto['channels'][0]['campaigns']['limit'],2);
    $check('24 Google remaining 0',
        $dto['channels'][0]['campaigns']['remaining'],0);
    $check('25 Google groups count 2',
        $dto['channels'][0]['groups']['used'],2);
    $check('26 Meta campaign remains independent',
        $dto['channels'][1]['campaigns']['used'],1);
    $check('27 Meta group remains independent',
        $dto['channels'][1]['groups']['used'],1);
    $check('28 full Google cap prompts human review, never upgrade',
        $dto['channels'][0]['attention'],'review_scope_no_auto_upgrade');
    $check('29 full Meta campaign cap prompts review',
        $dto['channels'][1]['attention'],'review_scope_no_auto_upgrade');
    $check('30 only three A managed campaigns projected',
        count($dto['campaigns']),3);
    $check('31 duplicate input deduplicated',
        array_column($dto['campaigns'],'ref'),
        ['cam-a-g1','cam-a-g2','cam-a-meta']);
    $check('32 paused campaign retained',
        $dto['campaigns'][0]['state'],'paused_demo');
    $check('33 no provider action on paused campaign',
        $dto['campaigns'][0]['provider_action'],'none_synthetic');
    $check('34 foreign B campaign hidden',
        !str_contains(json_encode($dto,JSON_THROW_ON_ERROR),'cam-b-g1'),true);
    $check('35 A groups listed only once',
        array_column($dto['groups'],'ref'),['grp-a1','grp-a2','grp-meta']);
    $check('36 B group filtered out',
        !str_contains(json_encode($dto,JSON_THROW_ON_ERROR),'grp-b1'),true);
    $check('37 fixture has no real reviewer',
        $dto['review_summary']['approval_source'],'none_real');
    $check('38 persisted store cannot invent real audit history',
        $dto['review_summary']['audit_entries_demo'],0);
    $check('39 payload excludes tokens/accounts/spend',
        !preg_match('/access_token|provider_account_id|customer_email|price_brl|media_spend/i',
         json_encode($dto,JSON_THROW_ON_ERROR)),true);
    $check('40 deterministic snapshot before mutation',
        $p->build($store,$a,$campaignHints,$groupHints),$dto);

    $check('41 foreign tenant only views B',
        array_column($p->build($store,$b,['cam-a-g1','cam-b-g1'],['grp-a1','grp-b1'])['campaigns'],'ref'),
        ['cam-b-g1']);
    $check('42 foreign group omitted',
        array_column($p->build($store,$b,[],['grp-a1','grp-b1'])['groups'],'ref'),
        ['grp-b1']);
    $check('43 unknown demo tenant has no capacity approval',
        $p->build($store,'perf-demo-unknown',[],[])['channels'][0]['attention'],
        'commercial_capacity_not_approved');
    $check('44 malformed tenant refused',
        $p->build($store,'../../bad',[],[]),null);
    $check('45 malformed campaign hint refused',
        $p->build($store,$a,['=BAD'],[]),null);
    $check('46 malformed group hint refused',
        $p->build($store,$a,[],['grp-ok'."\n".'evil']),null);
    $check('47 more than 20 campaign hints refused',
        $p->build($store,$a,array_fill(0,21,'cam-a-g1'),[]),null);
    $check('48 more than 80 group hints refused',
        $p->build($store,$a,[],array_fill(0,81,'grp-a1')),null);
    $check('49 absent campaign silently omitted',
        count($p->build($store,$a,['cam-not-found'],[])['campaigns']),0);
    $check('50 absent group silently omitted',
        count($p->build($store,$a,[],['grp-not-found'])['groups']),0);

    $check('51 read transaction returns same state',
        $store->consistentRead(static fn(SyntheticSqliteManagedScopeStore $s):string=>
            $s->campaignState($a,'cam-a-g1')),'paused_demo');
    $check('52 nested consistent read denied (no unexpected COMMIT)',
        $store->consistentRead(static fn(SyntheticSqliteManagedScopeStore $s):mixed=>
            $s->consistentRead(static fn():string=>'should not run')),null);
    $check('53 attempted write during read snapshot denied',
        $store->consistentRead(static fn(SyntheticSqliteManagedScopeStore $s):string=>
            $s->addCampaign($a,'google_ads','cam-illegal-in-read')),
        'nested_transaction_denied');
    $check('54 nested write did not create campaign',
        $store->campaignState($a,'cam-illegal-in-read'),'not_found');

    // Close and re-open the file to demonstrate actual persistence.
    unset($store,$db);
    $db2=new PDO('sqlite:'.$path);
    $store2=new SyntheticSqliteManagedScopeStore($db2);
    $after=$p->build($store2,$a,$campaignHints,$groupHints);
    $check('55 source survives connection close/reopen',$after['source'],'sqlite_ci_fixture_only');
    $check('56 persisted campaign totals survive reopen',
        $after['channels'][0]['campaigns']['used'],2);
    $check('57 persisted group totals survive reopen',
        $after['channels'][0]['groups']['used'],2);
    $check('58 paused state persists',
        $after['campaigns'][0]['state'],'paused_demo');
    $check('59 byte-equivalent DTO after reopen',$after,$dto);

    $check('60 swap requires mocked reviewer approval',
        $store2->replaceCampaign($a,'req-demo-swap','cam-a-g1','cam-next',true,false),
        'human_review_required');
    $check('61 swap with both mock approvals',
        $store2->replaceCampaign($a,'req-demo-swap','cam-a-g1','cam-next',true,true),
        'replaced_synthetic_no_fee');
    $newHints=['cam-a-g1','cam-a-g2','cam-a-meta','cam-next'];
    $updated=$p->build($store2,$a,$newHints,$groupHints);
    $check('62 old scope campaign hidden after swap',
        array_column($updated['campaigns'],'ref'),
        ['cam-a-g2','cam-a-meta','cam-next']);
    $check('63 newly included campaign is draft',
        $updated['campaigns'][2]['state'],'draft_demo');
    $check('64 old paused campaign no longer in scope',
        count(array_filter($updated['campaigns'],static fn(array $x):bool=>
            $x['state']==='paused_demo')),0);
    $check('65 capacity remains exactly two',
        $updated['channels'][0]['campaigns']['used'],2);
    $check('66 old groups do not remain managed',
        array_column($updated['groups'],'ref'),['grp-meta']);
    $check('67 Google groups freed formally',
        $updated['channels'][0]['groups']['used'],0);
    $check('68 Meta group not affected',
        $updated['channels'][1]['groups']['used'],1);
    $check('69 repeated mock swap idempotent',
        $store2->replaceCampaign($a,'req-demo-swap','cam-a-g1','cam-next',true,true),
        'already_applied_synthetic');
    $check('70 capacity still correct after replay attempt',
        $store2->capacity($a,'google_ads')['campaigns']['used'],2);

    putenv('PERF_F65_DURABLE_PREVIEW_LAB=0');
    $check('71 missing F65 flag closes DTO',
        $p->build($store2,$a,[],[]),null);
    putenv('PERF_F65_DURABLE_PREVIEW_LAB=1');
    putenv('APP_ENV=production');
    $check('72 production closes DTO',
        $p->build($store2,$a,[],[]),null);
    putenv('APP_ENV=testing');
    putenv('PERF_F64_SQLITE_SCOPE_LAB=0');
    $check('73 missing F64 flag closes DTO',
        $p->build($store2,$a,[],[]),null);
    putenv('PERF_F64_SQLITE_SCOPE_LAB=1');
    $check('74 restored test flags allow read-only DTO',
        is_array($p->build($store2,$a,[],[])),true);
    $check('75 no execution after all tests',
        $p->build($store2,$a,[],[])['dispatch_enabled'],false);
    unset($store2,$db2);
}finally{
    @unlink($path);
}
echo "PASS F65 $n durable read-model consistency checks, NO providers or billing".PHP_EOL;
