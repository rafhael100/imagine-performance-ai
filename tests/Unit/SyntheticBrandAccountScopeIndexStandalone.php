<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticBrandAccountScopeIndex.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticBrandAccountScopeIndex;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F60_ACCOUNT_SCOPE_LAB')!=='1'){
 throw new RuntimeException('F60 only in standalone fake-only CLI');
}
$l=new SyntheticManagedScopeLedger();
$x=new SyntheticBrandAccountScopeIndex($l);
$a='perf-demo-a';$b='perf-demo-b';
$n=0;
$check=static function(string $name,mixed $actual,mixed $want)use(&$n):void{
 if($actual!==$want){
  throw new RuntimeException('FAIL F60 '.$name.' got='
    .var_export($actual,true).' expected='.var_export($want,true));
 }
 echo 'PASS F60 '.++$n.' '.$name.PHP_EOL;
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(InvalidArgumentException){return true;}
};
$check('01 Google A capacity fixture',$l->setDemoCapacity($a,'google_ads',2,3),'capacity_configured_demo');
$check('02 Meta A capacity fixture',$l->setDemoCapacity($a,'meta',1,2),'capacity_configured_demo');
$check('03 Google B capacity fixture',$l->setDemoCapacity($b,'google_ads',1,2),'capacity_configured_demo');
$check('04 A Google campaign one',$l->includeCampaign($a,'google_ads','cam-a-g1'),'included_synthetic_only');
$check('05 A Google campaign two',$l->includeCampaign($a,'google_ads','cam-a-g2'),'included_synthetic_only');
$check('06 A Meta campaign',$l->includeCampaign($a,'meta','cam-a-m1'),'included_synthetic_only');
$check('07 B Google campaign',$l->includeCampaign($b,'google_ads','cam-b-g1'),'included_synthetic_only');
$check('08 campaign read channel owned',$l->campaignChannel($a,'cam-a-g1'),'google_ads');
$check('09 Meta campaign read channel owned',$l->campaignChannel($a,'cam-a-m1'),'meta');
$check('10 cross tenant campaign channel denied',$l->campaignChannel($b,'cam-a-g1'),null);
$check('11 unknown campaign channel denied',$l->campaignChannel($a,'cam-missing'),null);
$check('12 malformed campaign channel ID refused',
 $throws(static fn()=>$l->campaignChannel($a,'../../bad')),true);

$check('13 A brand registered',$x->addBrand($a,'brand-demo-a'),'brand_registered_synthetic_only');
$check('14 B brand registered',$x->addBrand($b,'brand-demo-b'),'brand_registered_synthetic_only');
$check('15 duplicate brand denied',$x->addBrand($a,'brand-demo-a'),'duplicate_or_out_of_scope');
$check('16 B cannot reuse A brand',$x->addBrand($b,'brand-demo-a'),'duplicate_or_out_of_scope');
$check('17 malformed tenant denied',
 $throws(static fn()=>$x->addBrand('../../bad','brand-demo-z')),true);
$check('18 malformed brand denied',
 $throws(static fn()=>$x->addBrand($a,'brand-real-customer')),true);

$check('19 A Google account placeholder',$x->addAccount($a,'brand-demo-a','google_ads','acct-demo-a-g'),'account_registered_synthetic_only');
$check('20 A Meta account placeholder',$x->addAccount($a,'brand-demo-a','meta','acct-demo-a-m'),'account_registered_synthetic_only');
$check('21 B Google account placeholder',$x->addAccount($b,'brand-demo-b','google_ads','acct-demo-b-g'),'account_registered_synthetic_only');
$check('22 orphan account denied',$x->addAccount($a,'brand-demo-missing','google_ads','acct-demo-orphan'),'brand_not_found');
$check('23 B cannot attach to A brand',$x->addAccount($b,'brand-demo-a','google_ads','acct-demo-cross'),'brand_not_found');
$check('24 duplicate account denied',$x->addAccount($a,'brand-demo-a','google_ads','acct-demo-a-g'),'duplicate_or_out_of_scope');
$check('25 different tenant cannot reuse ID',$x->addAccount($b,'brand-demo-b','google_ads','acct-demo-a-g'),'duplicate_or_out_of_scope');
$check('26 fake channel Facebook separately forbidden',
 $throws(static fn()=>$x->addAccount($a,'brand-demo-a','facebook','acct-demo-fb')),true);
$check('27 fake channel TikTok not yet eligible',
 $throws(static fn()=>$x->addAccount($a,'brand-demo-a','tiktok','acct-demo-tiktok')),true);
$check('28 real account-number format refused',
 $throws(static fn()=>$x->addAccount($a,'brand-demo-a','google_ads','1234567890')),true);

$check('29 Google campaign under matching account',$x->bindCampaign($a,'acct-demo-a-g','cam-a-g1'),'bound_synthetic_only');
$check('30 Meta campaign under matching account',$x->bindCampaign($a,'acct-demo-a-m','cam-a-m1'),'bound_synthetic_only');
$check('31 B campaign under B account',$x->bindCampaign($b,'acct-demo-b-g','cam-b-g1'),'bound_synthetic_only');
$check('32 channel mismatch A Meta denied',$x->bindCampaign($a,'acct-demo-a-g','cam-a-m1'),'already_linked_or_out_of_scope');
$check('33 separate A Google campaign cannot link to Meta account',$x->bindCampaign($a,'acct-demo-a-m','cam-a-g2'),'channel_mismatch_denied');
$check('34 A Google second campaign linked',$x->bindCampaign($a,'acct-demo-a-g','cam-a-g2'),'bound_synthetic_only');
$check('35 duplicate same link denied',$x->bindCampaign($a,'acct-demo-a-g','cam-a-g1'),'already_linked_or_out_of_scope');
$check('36 cannot use B account to bind A resource',$x->bindCampaign($a,'acct-demo-b-g','cam-a-g1'),'account_not_found');
$check('37 cannot bind A campaign from B',$x->bindCampaign($b,'acct-demo-b-g','cam-a-g2'),'already_linked_or_out_of_scope');
$check('38 cannot bind absent campaign',$x->bindCampaign($a,'acct-demo-a-g','cam-absent'),'managed_campaign_not_found');
$check('39 fake account ID validation enforced',
 $throws(static fn()=>$x->bindCampaign($a,'acct-demo-real@token','cam-a-g1')),true);

$aView=$x->snapshotFor($a);$bView=$x->snapshotFor($b);
$check('40 versioned account/brand hierarchy',$aView['schema_version'],'perf.scope.brand-account.synthetic.v1');
$check('41 A tenant is A',$aView['tenant_ref'],$a);
$check('42 B tenant is B',$bView['tenant_ref'],$b);
$check('43 source is local PHP only',$aView['source'],'in_memory_only');
foreach([
 'read_only'=>true,'contracted'=>false,'provider_connected'=>false,
 'authorization_eligible'=>false,'dispatch_enabled'=>false,
 'billing_enabled'=>false,'quotas_homologated'=>false
] as $key=>$value){
 $check('NO-GO '.$key,$aView[$key],$value);
}
$check('44 A sees one brand',count($aView['brands']),1);
$check('45 A sees two fictional accounts',count($aView['accounts']),2);
$check('46 A sees three campaign links',count($aView['campaign_links']),3);
$check('47 Google A account only two bound campaigns',$aView['accounts'][0]['managed_campaigns_demo'],2);
$check('48 Meta A account only one bound campaign',$aView['accounts'][1]['managed_campaigns_demo'],1);
$check('49 no real provider connection',$aView['accounts'][0]['provider_connection'],'none_synthetic');
$check('50 cross-tenant B brand never in A snapshot',
 str_contains(json_encode($aView,JSON_THROW_ON_ERROR),'brand-demo-b'),false);
$check('51 cross-tenant B account never in A snapshot',
 str_contains(json_encode($aView,JSON_THROW_ON_ERROR),'acct-demo-b-g'),false);
$check('52 B sees exactly B account',count($bView['accounts']),1);
$check('53 B sees exactly B campaign link',count($bView['campaign_links']),1);
$check('54 original F52 capacity not changed by account binding',
 $l->capacity($a,'google_ads')['campaigns']['used'],2);
$check('55 original F52 Meta capacity not changed by account binding',
 $l->capacity($a,'meta')['campaigns']['used'],1);

$check('56 paused campaign only local flag',$l->pauseCampaign($a,'cam-a-g1'),'paused_synthetic_only');
$check('57 paused campaign still linked',count($x->snapshotFor($a)['campaign_links']),3);
$check('58 paused campaign remains quota-consuming',$l->capacity($a,'google_ads')['campaigns']['used'],2);
$check('59 matching link shows paused state',
 $x->snapshotFor($a)['campaign_links'][0]['campaign_state'],'paused_demo');
$check('60 A cannot unlink B campaign',$x->unlinkCampaign($a,'cam-b-g1',true),'link_not_found');
$check('61 unlink requires human mock approval',$x->unlinkCampaign($a,'cam-a-m1',false),'human_review_required');
$check('62 denied unlink does not change A link count',count($x->snapshotFor($a)['campaign_links']),3);
$check('63 fictitious approved unlink does not touch provider',$x->unlinkCampaign($a,'cam-a-m1',true),'unlinked_synthetic_only');
$check('64 unlink removes from account index',count($x->snapshotFor($a)['campaign_links']),2);
$check('65 unlink alone does not free managed Meta capacity',$l->capacity($a,'meta')['campaigns']['used'],1);
$check('66 cannot unlink twice',$x->unlinkCampaign($a,'cam-a-m1',true),'link_not_found');
$check('67 released campaign requires fake human approval',
 $l->releaseCampaign($a,'cam-a-g1',false),'human_review_required');
$check('68 formal scope release after mock approval',
 $l->releaseCampaign($a,'cam-a-g1',true),'released_synthetic_only');
$check('69 released campaign no longer visible in account index',
 count($x->snapshotFor($a)['campaign_links']),1);
$check('70 released campaign channel hidden',$l->campaignChannel($a,'cam-a-g1'),null);
$check('71 released campaign frees F52 slot',
 $l->capacity($a,'google_ads')['campaigns']['used'],1);
$check('72 linking removed campaign never becomes active again',
 $x->bindCampaign($a,'acct-demo-a-g','cam-a-g1'),'already_linked_or_out_of_scope');
$check('73 old fake account has one remaining managed campaign',
 $x->snapshotFor($a)['accounts'][0]['managed_campaigns_demo'],1);

$check('74 tenant A activity exists',count($x->auditFor($a))>0,true);
$check('75 B cannot read A history',
 count(array_filter($x->auditFor($b),static fn(array $row):bool=>$row['resource']==='cam-a-g1'
    &&$row['status']==='bound_synthetic_only')),0);
$check('76 A history contains no provider action',
 count(array_filter($x->auditFor($a),static fn(array $row):bool=>$row['provider_action']!=='none_synthetic')),0);
$check('77 A history contains no billing',
 count(array_filter($x->auditFor($a),static fn(array $row):bool=>$row['billing_effect']!=='none_synthetic')),0);
$check('78 no token, IDs from Google/Meta or media cost in DTO',
 preg_match('/access_token|provider_account_id|media_spend|price_brl|oauth/i',
  json_encode($x->snapshotFor($a),JSON_THROW_ON_ERROR)),0);
$check('79 unknown tenant empty without inferring ownership',
 count($x->snapshotFor('perf-demo-z')['accounts']),0);
$check('80 malformed tenant snapshot throws',
 $throws(static fn()=>$x->snapshotFor('../bad')),true);
putenv('PERF_F60_ACCOUNT_SCOPE_LAB=0');
$check('81 disabled feature blocks new brand',$x->addBrand($a,'brand-demo-disabled'),'brand_account_lab_disabled');
$check('82 disabled feature blocks read snapshot',$x->snapshotFor($a),null);
$check('83 disabled feature hides audit',$x->auditFor($a),[]);
putenv('PERF_F60_ACCOUNT_SCOPE_LAB=1');
putenv('APP_ENV=production');
$check('84 production refuses account registration',
 $x->addAccount($a,'brand-demo-a','meta','acct-demo-prod'),'brand_account_lab_disabled');
$check('85 production refuses account snapshot',$x->snapshotFor($a),null);
putenv('APP_ENV=testing');
$check('86 restoring lab keeps B isolation',count($x->snapshotFor($b)['accounts']),1);
$check('87 no commercial entitlement ever granted',
 $x->snapshotFor($a)['authorization_eligible'],false);
echo "PASS F60 $n tenant-brand-account-channel isolation and NO-GO checks".PHP_EOL;
