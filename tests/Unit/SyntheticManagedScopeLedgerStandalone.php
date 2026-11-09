<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F52_SCOPE_LAB')!=='1'){
    throw new RuntimeException('F1-52 runs exclusively as an explicit synthetic CLI test.');
}
$n=0;
$check=static function(string $label,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected){
        throw new RuntimeException('FAIL F52 '.$label.' got '
            .var_export($got,true).' expected '.var_export($expected,true));
    }
    echo 'PASS F52 '.++$n.' '.$label.PHP_EOL;
};
$throws=static function(callable $fn):bool{
    try{$fn();return false;}
    catch(InvalidArgumentException){return true;}
};
$l=new SyntheticManagedScopeLedger();
$a='perf-demo-a';$b='perf-demo-b';$google='google_ads';$meta='meta';

$check('01 no quota approval defaults to blocked',
    $l->capacity($a,$google)['status'],'unconfigured_blocked_demo');
$check('02 no default numeric plan quota',
    $l->capacity($a,$google)['campaigns']['limit'],null);
$check('03 unconfigured campaign cannot be managed',
    $l->includeCampaign($a,$google,'cam-a1'),'capacity_unapproved_denied');
$check('04 no hidden quota mutates', $l->capacity($a,$google)['campaigns']['used'],0);
$check('05 Google A fictional cap', $l->setDemoCapacity($a,$google,2,3),'capacity_configured_demo');
$check('06 Meta A independent fictional cap', $l->setDemoCapacity($a,$meta,1,1),'capacity_configured_demo');
$check('07 Google B independent fictional cap', $l->setDemoCapacity($b,$google,1,1),'capacity_configured_demo');
$check('08 explicit Google limit', $l->capacity($a,$google)['campaigns']['limit'],2);
$check('09 explicit Meta limit', $l->capacity($a,$meta)['campaigns']['limit'],1);
$check('10 negative cap refused', $throws(static fn()=>$l->setDemoCapacity($a,$google,-1,0)),true);
$check('11 unreasonable group cap refused', $throws(static fn()=>$l->setDemoCapacity($a,$google,1,10001)),true);
$check('12 Facebook is not second Meta channel', $throws(static fn()=>$l->includeCampaign($a,'facebook','cam-a1')),true);
$check('13 malformed campaign refused', $throws(static fn()=>$l->includeCampaign($a,$meta,'../secret')),true);
$check('14 malformed tenant refused', $throws(static fn()=>$l->includeCampaign('perf-demo-../b',$google,'cam-x')),true);

$check('15 first A Google campaign', $l->includeCampaign($a,$google,'cam-a1'),'included_synthetic_only');
$check('16 second A Google campaign', $l->includeCampaign($a,$google,'cam-a2'),'included_synthetic_only');
$check('17 managed campaign quota reached', $l->capacity($a,$google)['campaigns']['used'],2);
$check('18 third campaign requires review not billing', $l->includeCampaign($a,$google,'cam-a3'),'capacity_review_required');
$check('19 commercial pricing is not approved', $l->capacity($a,$google)['commercial'],'not_homologated');
$check('20 duplicate A campaign cannot consume extra slot', $l->includeCampaign($a,$google,'cam-a1'),'already_exists_or_out_of_scope');
$check('21 foreign tenant cannot impersonate existing campaign', $l->includeCampaign($b,$google,'cam-a1'),'already_exists_or_out_of_scope');
$check('22 B cannot read A campaign state', $l->campaignState($b,'cam-a1'),'not_found');
$check('23 B can manage its own campaign', $l->includeCampaign($b,$google,'cam-b1'),'included_synthetic_only');
$check('24 B capacity independent', $l->capacity($b,$google)['campaigns']['used'],1);
$check('25 Meta under one channel', $l->includeCampaign($a,$meta,'cam-m1'),'included_synthetic_only');
$check('26 Meta quota independent of Google', $l->capacity($a,$meta)['campaigns']['remaining'],0);

$check('27 include formal Google group one', $l->includeGroup($a,'cam-a1','grp-a1'),'included_synthetic_only');
$check('28 include formal Google group two', $l->includeGroup($a,'cam-a1','grp-a2'),'included_synthetic_only');
$check('29 include formal Google group three', $l->includeGroup($a,'cam-a2','grp-a3'),'included_synthetic_only');
$check('30 group quota distinct from campaign quota', $l->capacity($a,$google)['groups']['used'],3);
$check('31 group cap denied no charge', $l->includeGroup($a,'cam-a2','grp-a4'),'capacity_review_required');
$check('32 B cannot create group under A campaign', $l->includeGroup($b,'cam-a1','grp-foreign'),'campaign_not_found');
$check('33 B cannot read A group state', $l->groupState($b,'grp-a1'),'not_found');
$check('34 orphan group rejected', $l->includeGroup($a,'cam-missing','grp-orphan'),'campaign_not_found');
$check('35 foreign group identifier collision denied', $l->includeGroup($b,'cam-b1','grp-a1'),'already_exists_or_out_of_scope');
$check('36 Meta group capacity usable', $l->includeGroup($a,'cam-m1','grp-m1'),'included_synthetic_only');
$check('37 Meta group quota full', $l->capacity($a,$meta)['groups']['remaining'],0);
$check('38 cannot silently shrink quota below use', $l->setDemoCapacity($a,$google,1,2),'reduction_below_usage_denied');
$check('39 rejected change leaves quota unchanged', $l->capacity($a,$google)['groups']['limit'],3);

$check('40 pause campaign only a local simulated state', $l->pauseCampaign($a,'cam-a1'),'paused_synthetic_only');
$check('41 paused managed campaign still consumes slot', $l->capacity($a,$google)['campaigns']['used'],2);
$check('42 campaign paused status visible to owning tenant', $l->campaignState($a,'cam-a1'),'paused_demo');
$check('43 duplicate pause remains idempotent', $l->pauseCampaign($a,'cam-a1'),'already_paused_demo');
$check('44 group pause only synthetic status', $l->pauseGroup($a,'grp-a1'),'paused_synthetic_only');
$check('45 paused group still consumes scoped capacity', $l->capacity($a,$google)['groups']['used'],3);
$check('46 foreign tenant cannot pause campaign', $l->pauseCampaign($b,'cam-a1'),'not_found');
$check('47 foreign tenant cannot pause group', $l->pauseGroup($b,'grp-a1'),'not_found');

$check('48 replacement requires human review fixture', $l->replaceCampaign($a,'cam-a1','cam-new1',true,false),'human_review_required');
$check('49 replacement requires Imagine recommendation fixture', $l->replaceCampaign($a,'cam-a1','cam-new1',false,true),'recommendation_required');
$check('50 cross-tenant replacement target id rejected', $l->replaceCampaign($a,'cam-a1','cam-b1',true,true),'already_exists_or_out_of_scope');
$check('51 refused swap retains existing capacity', $l->capacity($a,$google)['campaigns']['used'],2);
$check('52 refused swap never created target', $l->campaignState($a,'cam-new1'),'not_found');
$check('53 in-memory formal no-fee swap after both demo approvals', $l->replaceCampaign($a,'cam-a1','cam-new1',true,true),'replaced_synthetic_no_fee');
$check('54 old campaign exits managed scope', $l->campaignState($a,'cam-a1'),'outside_scope_demo');
$check('55 new campaign is draft not live', $l->campaignState($a,'cam-new1'),'draft_demo');
$check('56 no overage after swap', $l->capacity($a,$google)['campaigns']['used'],2);
$check('57 old paused group formally exits scope', $l->groupState($a,'grp-a1'),'outside_scope_demo');
$check('58 old groups only freed after formal swap', $l->capacity($a,$google)['groups']['used'],1);
$check('59 newly free group quota may be used', $l->includeGroup($a,'cam-new1','grp-new1'),'included_synthetic_only');
$check('60 retired group id cannot recycle', $l->includeGroup($a,'cam-new1','grp-a1'),'already_exists_or_out_of_scope');
$check('61 retired campaign not managed anymore', $l->pauseCampaign($a,'cam-a1'),'not_found');
$check('62 historical campaign cannot be swapped twice', $l->replaceCampaign($a,'cam-a1','cam-again',true,true),'not_found');
$check('63 B cannot release A campaign', $l->releaseCampaign($b,'cam-new1',true),'not_found');
$check('64 release requires independent human fixture', $l->releaseCampaign($a,'cam-new1',false),'human_review_required');
$check('65 formal release only synthetic scope', $l->releaseCampaign($a,'cam-new1',true),'released_synthetic_only');
$check('66 released campaign frees slot', $l->capacity($a,$google)['campaigns']['used'],1);
$check('67 associated group exits scope with campaign', $l->groupState($a,'grp-new1'),'outside_scope_demo');
$check('68 release never triggers provider dispatch', $l->capacity($a,$google)['dispatch_enabled'],false);
$check('69 retired campaign id is immutable history', $l->includeCampaign($a,$google,'cam-new1'),'already_exists_or_out_of_scope');
$check('70 configured demo quota unchanged', $l->capacity($a,$google)['status'],'configured_synthetic_only');

$aAudit=$l->auditFor($a);$bAudit=$l->auditFor($b);
$check('71 no billing effects in A audit',
    count(array_filter($aAudit,static fn(array $r):bool=>$r['billing_effect']!=='none_synthetic')),0);
$check('72 A audit cannot contain B campaign under A ownership',
    count(array_filter($aAudit,static fn(array $r):bool=>$r['resource']==='cam-b1'&&$r['result']==='included_synthetic_only')),0);
$check('73 B never has A approved swap as its event',
    count(array_filter($bAudit,static fn(array $r):bool=>$r['resource']==='cam-new1'&&$r['result']==='replaced_synthetic_no_fee')),0);
$check('74 B own usage unaffected', $l->capacity($b,$google)['campaigns']['used'],1);
$check('75 no prices, money or media spend in capacity JSON',
    preg_match('/price|charge|media_spend|amount|checkout|billing_enabled/i',
      json_encode($l->capacity($a,$google),JSON_THROW_ON_ERROR)),0);
$check('76 exactly one no-fee swap decision recorded',
    count(array_filter($aAudit,static fn(array $r):bool=>$r['result']==='replaced_synthetic_no_fee')),1);

putenv('PERF_F52_SCOPE_LAB=0');
$check('77 lab disabled blocks mutating entry', $l->includeCampaign($a,$google,'cam-noop'),'lab_disabled');
$check('78 lab disabled hides audit', $l->auditFor($a),[]);
$check('79 lab disabled hides state', $l->campaignState($a,'cam-a2'),'lab_disabled');
putenv('PERF_F52_SCOPE_LAB=1');
putenv('APP_ENV=production');
$check('80 production denies scope change', $l->pauseCampaign($a,'cam-a2'),'lab_disabled');
$check('81 production never reports synthetic quota as saleable', $l->capacity($a,$google)['status'],'lab_disabled');
putenv('APP_ENV=testing');
$check('82 restoring lab leaves state unchanged', $l->capacity($a,$google)['campaigns']['used'],1);

echo "PASS F52 $n scope/capacity/adversarial checks; no real contract, charges or Ads".PHP_EOL;
