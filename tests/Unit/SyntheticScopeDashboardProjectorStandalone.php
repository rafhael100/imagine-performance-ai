<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'){
    throw new RuntimeException('F53 only runs as an explicit synthetic PHP CLI test');
}
$n=0;
$check=static function(string $msg,mixed $got,mixed $expected)use(&$n):void{
    if($got!==$expected){
        throw new RuntimeException('FAIL F53 '.$msg.' got '.var_export($got,true)
            .' expected '.var_export($expected,true));
    }
    echo 'PASS F53 '.++$n.' '.$msg.PHP_EOL;
};
$l=new SyntheticManagedScopeLedger();
$p=new SyntheticScopeDashboardProjector();
$a='perf-demo-a';$b='perf-demo-b';
$check('01 unapproved quota blocks inclusion',$l->includeCampaign($a,'google_ads','cam-a1'),'capacity_unapproved_denied');
$check('02 demo quota A Google',$l->setDemoCapacity($a,'google_ads',2,3),'capacity_configured_demo');
$check('03 demo quota A Meta',$l->setDemoCapacity($a,'meta',1,1),'capacity_configured_demo');
$check('04 demo quota B Google',$l->setDemoCapacity($b,'google_ads',1,1),'capacity_configured_demo');
$check('05 include A campaign 1',$l->includeCampaign($a,'google_ads','cam-a1'),'included_synthetic_only');
$check('06 include A campaign 2',$l->includeCampaign($a,'google_ads','cam-a2'),'included_synthetic_only');
$check('07 include A Meta campaign',$l->includeCampaign($a,'meta','cam-m1'),'included_synthetic_only');
$check('08 include B campaign',$l->includeCampaign($b,'google_ads','cam-b1'),'included_synthetic_only');
$check('09 include A group 1',$l->includeGroup($a,'cam-a1','grp-a1'),'included_synthetic_only');
$check('10 include A group 2',$l->includeGroup($a,'cam-a1','grp-a2'),'included_synthetic_only');
$check('11 include A Meta set',$l->includeGroup($a,'cam-m1','grp-m1'),'included_synthetic_only');
$check('12 include B group',$l->includeGroup($b,'cam-b1','grp-b1'),'included_synthetic_only');
$check('13 pause A campaign still managed',$l->pauseCampaign($a,'cam-a1'),'paused_synthetic_only');
$check('14 pause A group still managed',$l->pauseGroup($a,'grp-a1'),'paused_synthetic_only');
$check('15 foreign tenant cannot read A campaign',$l->campaignState($b,'cam-a1'),'not_found');

$preview=$p->build($l,$a,
 ['cam-a1','cam-a2','cam-m1','cam-b1','cam-a1'],
 ['grp-a1','grp-a2','grp-m1','grp-b1','grp-a1']);
$check('16 new preview built under explicit lab',is_array($preview),true);
$check('17 stable schema',$preview['schema_version'],'perf.portal.scope-preview.synthetic.v1');
$check('18 fixture source never real ads',$preview['source'],'in_memory_fixture_only');
$check('19 tenant ref',$preview['tenant_ref'],$a);
$check('20 read-only',$preview['read_only'],true);
$check('21 not contracted',$preview['contracted'],false);
$check('22 not ready for production',$preview['ready_for_production'],false);
$check('23 preview is not a grant',$preview['authorization_eligible'],false);
$check('24 no external dispatch',$preview['dispatch_enabled'],false);
$check('25 no billing',$preview['billing_enabled'],false);
$check('26 prices unapproved',$preview['prices_homologated'],false);
$check('27 channel segregation',array_column($preview['channels'],'id'),['google_ads','meta']);
$check('28 Google label',$preview['channels'][0]['label'],'Google Ads');
$check('29 Meta label does not create separate Facebook billable channel',
 $preview['channels'][1]['label'],'Meta (Instagram/Facebook)');
$check('30 Google campaign count includes paused',$preview['channels'][0]['campaigns']['used'],2);
$check('31 Google group count includes paused',$preview['channels'][0]['groups']['used'],2);
$check('32 Meta campaign capacity independent',$preview['channels'][1]['campaigns']['used'],1);
$check('33 Meta group capacity independent',$preview['channels'][1]['groups']['used'],1);
$check('34 full campaign scope prompts review, not upgrade',
 $preview['channels'][0]['attention'],'review_scope_no_auto_upgrade');
$check('35 full Meta group scope prompts review',
 $preview['channels'][1]['attention'],'review_scope_no_auto_upgrade');
$check('36 A preview excludes B campaigns',count($preview['campaigns']),3);
$check('37 A preview excludes B groups',count($preview['groups']),3);
$check('38 duplicate input refs are deduplicated',
 count(array_filter($preview['campaigns'],static fn($r):bool=>$r['ref']==='cam-a1')),1);
$check('39 paused campaign state present',$preview['campaigns'][0]['state'],'paused_demo');
$check('40 paused group state present',$preview['groups'][0]['state'],'paused_demo');
$check('41 cards never dispatch provider action',
 $preview['campaigns'][0]['provider_action'],'none_synthetic');
$check('42 no source of real approval',
 $preview['review_summary']['approval_source'],'none_real');
$check('43 rejected inclusion included only in aggregate',
 $preview['review_summary']['rejected_actions_demo']>=1,true);
$json=json_encode($preview,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$check('44 foreign B campaign never serialized',str_contains($json,'cam-b1'),false);
$check('45 foreign B group never serialized',str_contains($json,'grp-b1'),false);
$check('46 secrets/prices/account/budgets not exposed',
 (bool)preg_match('/media_spend|billing_effect|access_token|provider_account_id|amount_cents|price_brl/i',$json),false);
$check('47 raw audit not returned',array_key_exists('audit',$preview),false);
$check('48 same fixtures deterministic',
 $p->build($l,$a,['cam-a1','cam-a2','cam-m1','cam-b1','cam-a1'],
                   ['grp-a1','grp-a2','grp-m1','grp-b1','grp-a1']),$preview);
$check('49 foreign tenant cannot project A campaigns',
 count($p->build($l,$b,['cam-a1'],['grp-a1'])['campaigns']),0);
$check('50 foreign tenant only sees its own quota',
 $p->build($l,$b,['cam-b1'],['grp-b1'])['channels'][0]['campaigns']['used'],1);
$check('51 unknown tenant still has unapproved capacity',
 $p->build($l,'perf-demo-c',[],[])['channels'][0]['attention'],'commercial_capacity_not_approved');
$check('52 malformed tenant denied',$p->build($l,'../secret',[],[]),null);
$check('53 malformed campaign denied',$p->build($l,$a,['../../bad'],[]),null);
$check('54 malformed group denied',$p->build($l,$a,[],['xx']),null);
$check('55 campaign ref list capped',$p->build($l,$a,array_fill(0,21,'cam-a1'),[]),null);
$check('56 group ref list capped',$p->build($l,$a,[],array_fill(0,81,'grp-a1')),null);
$check('57 unknown resource silently omitted',
 count($p->build($l,$a,['cam-not-there'],[])['campaigns']),0);
$check('58 foreign tenant identifier never returned',str_contains($json,'perf-demo-b'),false);
$check('59 fake release requires reviewer',$l->releaseCampaign($a,'cam-a2',false),'human_review_required');
$check('60 formal scope release only mock',$l->releaseCampaign($a,'cam-a2',true),'released_synthetic_only');
$check('61 released campaign not displayed as managed',
 count($p->build($l,$a,['cam-a1','cam-a2'],[])['campaigns']),1);
$check('62 quota changes after formal release',
 $p->build($l,$a,[],[])['channels'][0]['campaigns']['used'],1);
$check('63 groups on unrelated campaign unaffected',
 $p->build($l,$a,[],[])['channels'][0]['groups']['used'],2);
putenv('PERF_F53_PORTAL_PREVIEW_LAB=0');
$check('64 disabling preview flag closes projection',$p->build($l,$a,[],[]),null);
putenv('PERF_F53_PORTAL_PREVIEW_LAB=1');
putenv('APP_ENV=production');
$check('65 production cannot emit synthetic DTO',$p->build($l,$a,[],[]),null);
putenv('APP_ENV=testing');
putenv('PERF_F52_SCOPE_LAB=0');
$check('66 ledger permission flag is independently required',$p->build($l,$a,[],[]),null);
putenv('PERF_F52_SCOPE_LAB=1');
$check('67 synthetic DTO works only after restoring both flags',
 is_array($p->build($l,$a,[],[])),true);
echo "PASS F53 $n read-only Portal scope preview tests; no contracts or external effects".PHP_EOL;
