<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticBrandAccountScopeIndex.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticAccountScopedDashboardProjector.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticBrandAccountScopeIndex;
use Imagine\Performance\Domain\SyntheticAccountScopedDashboardProjector;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F52_SCOPE_LAB')!=='1'
  ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
  ||getenv('PERF_F60_ACCOUNT_SCOPE_LAB')!=='1'
  ||getenv('PERF_F61_COMBINED_READ_LAB')!=='1'){
 throw new RuntimeException('F61 strictly fake-only CLI');
}
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected)use(&$n):void{
 if($actual!==$expected){
  throw new RuntimeException('FAIL F61 '.$label.' got='
    .var_export($actual,true).' expected='.var_export($expected,true));
 }
 echo 'PASS F61 '.++$n.' '.$label.PHP_EOL;
};
$l=new SyntheticManagedScopeLedger();
$x=new SyntheticBrandAccountScopeIndex($l);
$p=new SyntheticAccountScopedDashboardProjector();
$a='perf-demo-a';$b='perf-demo-b';
$check('01 A google sample quota configured',
 $l->setDemoCapacity($a,'google_ads',2,3),'capacity_configured_demo');
$check('02 A Meta sample quota configured',
 $l->setDemoCapacity($a,'meta',1,2),'capacity_configured_demo');
$check('03 B google sample quota configured',
 $l->setDemoCapacity($b,'google_ads',1,1),'capacity_configured_demo');
$check('04 A Google managed campaign one',
 $l->includeCampaign($a,'google_ads','cam-a-g1'),'included_synthetic_only');
$check('05 A Google managed campaign two',
 $l->includeCampaign($a,'google_ads','cam-a-g2'),'included_synthetic_only');
$check('06 A Meta managed campaign',
 $l->includeCampaign($a,'meta','cam-a-m1'),'included_synthetic_only');
$check('07 B foreign managed campaign',
 $l->includeCampaign($b,'google_ads','cam-b-g1'),'included_synthetic_only');
$check('08 A group scoped to Google',
 $l->includeGroup($a,'cam-a-g1','grp-a-g1'),'included_synthetic_only');
$check('09 A group scoped to Meta',
 $l->includeGroup($a,'cam-a-m1','grp-a-m1'),'included_synthetic_only');
$check('10 paused campaign remains in managed scope',
 $l->pauseCampaign($a,'cam-a-g1'),'paused_synthetic_only');
$check('11 fake A brand',
 $x->addBrand($a,'brand-demo-a'),'brand_registered_synthetic_only');
$check('12 fake B brand',
 $x->addBrand($b,'brand-demo-b'),'brand_registered_synthetic_only');
$check('13 fake Google A account',
 $x->addAccount($a,'brand-demo-a','google_ads','acct-demo-a-g'),'account_registered_synthetic_only');
$check('14 fake Meta A account',
 $x->addAccount($a,'brand-demo-a','meta','acct-demo-a-m'),'account_registered_synthetic_only');
$check('15 fake Google B account',
 $x->addAccount($b,'brand-demo-b','google_ads','acct-demo-b-g'),'account_registered_synthetic_only');
$check('16 A paused campaign linked',
 $x->bindCampaign($a,'acct-demo-a-g','cam-a-g1'),'bound_synthetic_only');
$check('17 A Meta linked',
 $x->bindCampaign($a,'acct-demo-a-m','cam-a-m1'),'bound_synthetic_only');
$check('18 B campaign linked',
 $x->bindCampaign($b,'acct-demo-b-g','cam-b-g1'),'bound_synthetic_only');

$view=$p->build($l,$x,$a,['cam-a-g1','cam-a-g2','cam-a-m1','cam-b-g1'],
    ['grp-a-g1','grp-a-m1','grp-not-there']);
$check('19 combined view available',is_array($view),true);
$check('20 DTO schema version',$view['schema_version'],
 'perf.portal.account-scope-preview.synthetic.v1');
$check('21 reconciled synthetic sources only',
 $view['source'],'reconciled_in_memory_fixtures_only');
$check('22 tenant A only',$view['tenant_ref'],$a);
foreach([
 'read_only'=>true,'contracted'=>false,'provider_connected'=>false,
 'human_identity_verified'=>false,'authorization_eligible'=>false,
 'dispatch_enabled'=>false,'billing_enabled'=>false,
 'ready_for_production'=>false,'quotas_homologated'=>false
] as $field=>$value){
 $check('NO-GO '.$field,$view[$field],$value);
}
$check('23 two fake A accounts',$view['accounts'][0]['ref'],'acct-demo-a-g');
$check('24 second A account remains Meta',$view['accounts'][1]['channel'],'meta');
$check('25 two channels projected',count($view['channels']),2);
$check('26 only three A campaign cards',count($view['campaigns']),3);
$check('27 one paused A campaign',$view['counts']['paused_campaigns_demo'],1);
$check('28 two mock mapped A campaigns',$view['counts']['mapped_campaigns_demo'],2);
$check('29 A unassigned campaign explicitly reported',$view['counts']['unassigned_campaigns_demo'],1);
$check('30 Google paused A has fake account ref',
 $view['campaigns'][0]['account_ref'],'acct-demo-a-g');
$check('31 Google paused A has fake brand ref',
 $view['campaigns'][0]['brand_ref'],'brand-demo-a');
$check('32 paused state preserved',$view['campaigns'][0]['campaign_state'],'paused_demo');
$check('33 Google channel validated against ledger',
 $view['campaigns'][0]['channel'],'google_ads');
$check('34 unassigned Google has no account',
 $view['campaigns'][1]['account_ref'],null);
$check('35 unassigned Google is distinctly marked',
 $view['campaigns'][1]['account_scope_status'],'unassigned_synthetic');
$check('36 Meta campaign mapped to Meta account',
 $view['campaigns'][2]['account_ref'],'acct-demo-a-m');
$check('37 no fake provider operation performed',
 $view['campaigns'][2]['provider_action'],'none_synthetic');
$check('38 group association remains unproven',
 $view['counts']['groups_without_account_mapping_demo'],2);
$check('39 groups displayed only under F53 scope',
 array_column($view['groups'],'ref'),['grp-a-g1','grp-a-m1']);
$check('40 fake capacity does not sell quotas',
 $view['channels'][0]['capacity_status'],'configured_synthetic_only');
$check('41 source service still not contracted',
 $view['contracted'],false);
$check('42 no real commercial usage claim',
 str_contains($view['warning'],'MOCK'),true);

$json=json_encode($view,JSON_THROW_ON_ERROR);
$check('43 tenant B account not exported',
 str_contains($json,'acct-demo-b-g'),false);
$check('44 tenant B campaign not exported',
 str_contains($json,'cam-b-g1'),false);
$check('45 tenant B brand not exported',
 str_contains($json,'brand-demo-b'),false);
$check('46 no customer token, spending or prices leaked',
 preg_match('/access_token|provider_account_id|media_spend|price_brl|customer_email|oauth/i',$json),0);
$check('47 same data yields deterministic view',
 $p->build($l,$x,$a,['cam-a-g1','cam-a-g2','cam-a-m1','cam-b-g1'],
   ['grp-a-g1','grp-a-m1','grp-not-there']),$view);
$check('48 tenant B only B campaign',
 count($p->build($l,$x,$b,['cam-b-g1','cam-a-g1'],[])['campaigns']),1);
$check('49 tenant B only B account',
 count($p->build($l,$x,$b,['cam-b-g1'],[])['accounts']),1);
$check('50 unknown tenant has no mapped campaign',
 $p->build($l,$x,'perf-demo-unknown',[],[])['counts']['mapped_campaigns_demo'],0);
$check('51 malformed tenant denied',
 $p->build($l,$x,'../bad',[],[]),null);
$check('52 malformed campaign hint denied',
 $p->build($l,$x,$a,['=cmd'],[]),null);
$check('53 malformed group hint denied',
 $p->build($l,$x,$a,[],['../../bad']),null);
$check('54 no group/account affiliation invented',
 array_key_exists('account_ref',$view['groups'][0]),false);
$check('55 no real account connection',
 $view['accounts'][0]['provider_connection'],'none_synthetic');

$check('56 disassociation requires human mock review',
 $x->unlinkCampaign($a,'cam-a-m1',false),'human_review_required');
$check('57 association remains until approved fake unlink',
 $p->build($l,$x,$a,['cam-a-m1'],[])['counts']['mapped_campaigns_demo'],1);
$check('58 fake approved unlink only changes local mapping',
 $x->unlinkCampaign($a,'cam-a-m1',true),'unlinked_synthetic_only');
$next=$p->build($l,$x,$a,['cam-a-g1','cam-a-g2','cam-a-m1'],['grp-a-g1','grp-a-m1']);
$check('59 mapped A decreases after unlink',
 $next['counts']['mapped_campaigns_demo'],1);
$check('60 A unassigned count increases after unlink',
 $next['counts']['unassigned_campaigns_demo'],2);
$check('61 Meta campaign remains under F52 management',
 $l->capacity($a,'meta')['campaigns']['used'],1);
$check('62 account index does not invent saleable slots',
 $next['quotas_homologated'],false);
$check('63 release requires human mock fixture',
 $l->releaseCampaign($a,'cam-a-g1',false),'human_review_required');
$check('64 released campaign leaves managed scope',
 $l->releaseCampaign($a,'cam-a-g1',true),'released_synthetic_only');
$after=$p->build($l,$x,$a,['cam-a-g1','cam-a-g2','cam-a-m1'],['grp-a-g1','grp-a-m1']);
$check('65 formerly linked campaign no longer in view',
 count($after['campaigns']),2);
$check('66 former paused item no longer counted',
 $after['counts']['paused_campaigns_demo'],0);
$check('67 no mapped campaigns remain',
 $after['counts']['mapped_campaigns_demo'],0);
$check('68 remaining two campaigns are unmapped',
 $after['counts']['unassigned_campaigns_demo'],2);
$check('69 linked account managed count drops to zero',
 $after['accounts'][0]['managed_campaigns_demo'],0);
$check('70 groups no longer scoped to released campaign',
 array_column($after['groups'],'ref'),['grp-a-m1']);
$check('71 company B unaffected by releases in A',
 count($p->build($l,$x,$b,['cam-b-g1'],[])['campaigns']),1);
$check('72 no real provider effect',
 $after['dispatch_enabled'],false);

putenv('PERF_F61_COMBINED_READ_LAB=0');
$check('73 disabled combined preview denied',
 $p->build($l,$x,$a,[],[]),null);
putenv('PERF_F61_COMBINED_READ_LAB=1');
putenv('APP_ENV=production');
$check('74 production environment denied',
 $p->build($l,$x,$a,[],[]),null);
putenv('APP_ENV=testing');
putenv('PERF_F60_ACCOUNT_SCOPE_LAB=0');
$check('75 account scope flag removed denies combined view',
 $p->build($l,$x,$a,[],[]),null);
putenv('PERF_F60_ACCOUNT_SCOPE_LAB=1');
putenv('PERF_F53_PORTAL_PREVIEW_LAB=0');
$check('76 source preview flag removed denies combined view',
 $p->build($l,$x,$a,[],[]),null);
putenv('PERF_F53_PORTAL_PREVIEW_LAB=1');
$check('77 combined demo projection returns under restored lab flags',
 is_array($p->build($l,$x,$a,[],[])),true);
echo "PASS F61 $n combined fake account/brand/tenant read model checks".PHP_EOL;
