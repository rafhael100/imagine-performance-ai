<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticManagedScopeLedger.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeDashboardProjector.php';
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticScopeCsvExporter.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\SyntheticScopeDashboardProjector;
use Imagine\Performance\Domain\SyntheticScopeCsvExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
 ||getenv('PERF_F56_CSV_LAB')!=='1'){
    throw new RuntimeException('F1-56 CSV demo only in explicit CLI mode');
}
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected)use(&$n):void{
    if($actual!==$expected){
        throw new RuntimeException('FAIL F56 '.$label.' got '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    echo 'PASS F56 '.++$n.' '.$label.PHP_EOL;
};
$l=new SyntheticManagedScopeLedger();
$t='perf-demo-aurora';
$check('01 simulated Google quota',$l->setDemoCapacity($t,'google_ads',2,3),'capacity_configured_demo');
$check('02 simulated Meta quota',$l->setDemoCapacity($t,'meta',1,2),'capacity_configured_demo');
$check('03 demo Google campaign 1',$l->includeCampaign($t,'google_ads','cam-search-demo'),'included_synthetic_only');
$check('04 demo Google campaign 2',$l->includeCampaign($t,'google_ads','cam-local-demo'),'included_synthetic_only');
$check('05 demo Meta campaign',$l->includeCampaign($t,'meta','cam-social-demo'),'included_synthetic_only');
$check('06 Google group one',$l->includeGroup($t,'cam-search-demo','grp-search-demo'),'included_synthetic_only');
$check('07 Google group two',$l->includeGroup($t,'cam-local-demo','grp-local-demo'),'included_synthetic_only');
$check('08 Meta set',$l->includeGroup($t,'cam-social-demo','grp-social-demo'),'included_synthetic_only');
$check('09 pause campaign remains managed',$l->pauseCampaign($t,'cam-local-demo'),'paused_synthetic_only');
$check('10 pause group remains managed',$l->pauseGroup($t,'grp-local-demo'),'paused_synthetic_only');
$dto=(new SyntheticScopeDashboardProjector())->build(
    $l,$t,['cam-search-demo','cam-local-demo','cam-social-demo'],
    ['grp-search-demo','grp-local-demo','grp-social-demo']);
$check('11 valid read-only DTO',is_array($dto),true);
$csv=new SyntheticScopeCsvExporter();
$out=$csv->export($dto);
$check('12 CSV returned under test-only flags',is_string($out),true);
$check('13 UTF-8 BOM for spreadsheet reader',str_starts_with($out,"\xEF\xBB\xBF"),true);
$check('14 header and exactly two channel rows',count(explode("\n",trim($out))),3);
$check('15 Google Ads label',str_contains($out,'Google Ads'),true);
$check('16 Meta label is one channel',str_contains($out,'Meta (Instagram/Facebook)'),true);
$check('17 paused Google campaign included in managed capacity',
    str_contains($out,'"Google Ads";2;2;2;3'),true);
$check('18 Meta count independent',str_contains($out,'"Meta (Instagram/Facebook)";1;1;1;2'),true);
$check('19 explicit no invoicing or execution',str_contains($out,'SEM FATURAMENTO OU EXECUCAO'),true);
$check('20 group quota still unapproved',str_contains($out,'HIPOTESE NAO HOMOLOGADA'),true);
$check('21 byte-identical repeat export',$csv->export($dto),$out);
$check('22 no tenant identifier emitted',str_contains($out,$t),false);
$check('23 no provider tokens or budget in CSV',
    preg_match('/access_token|provider_account_id|media_spend|price_brl|billing_effect/i',$out),0);
$check('24 no spreadsheet formula prefixes',
    preg_match('/(^|;)\s*[=+@]/m',$out),0);
$mutate=static function(string $field,mixed $value)use($csv,$dto):?string{
    $x=$dto;$x[$field]=$value;return $csv->export($x);
};
$check('25 contracted=true rejected',$mutate('contracted',true),null);
$check('26 dispatch=true rejected',$mutate('dispatch_enabled',true),null);
$check('27 billing=true rejected',$mutate('billing_enabled',true),null);
$check('28 production ready claim rejected',$mutate('ready_for_production',true),null);
$check('29 auth eligibility forged rejected',$mutate('authorization_eligible',true),null);
$check('30 commercial approval forged rejected',$mutate('commercial','approved'),null);
$check('31 real provider source forged rejected',$mutate('source','google_ads_live'),null);
$check('32 formula-injected tenant refused',$mutate('tenant_ref','=HYPERLINK("http://bad")'),null);
$check('33 missing channel rows rejected',$mutate('channels',[]),null);
$check('34 unknown schema version rejected',
    $mutate('schema_version','perf.portal.scope-preview.production.v1'),null);
$check('35 missing DTO rejected',$csv->export(null),null);
$bad=$dto;$bad['channels'][0]['campaigns']['used']=-1;
$check('36 negative campaign metric rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][0]['campaigns']['used']='2';
$check('37 string instead of integer rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][0]['campaigns']['used']=3;
$check('38 usage above demo limit rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][0]['groups']['limit']=null;
$check('39 configured quota with missing group limit rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][1]['id']='google_ads';
$check('40 duplicate Google channel rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][1]['id']='facebook';
$check('41 separate billable Facebook channel rejected',$csv->export($bad),null);
$bad=$dto;$bad['channels'][0]['label']='=HYPERLINK("malicious")';
$check('42 attacker-controlled channel label never copied',$csv->export($bad),$out);
$bad=$dto;$bad['channels'][0]['campaigns']['limit']=10001;
$check('43 oversized mock capacity refused',$csv->export($bad),null);
$bad=$dto;$bad['channels'][0]['capacity_status']='approved_paid';
$check('44 forged commercial quota status rejected',$csv->export($bad),null);
$bad=$dto;
$bad['channels'][0]['campaigns']['limit']=null;
$bad['channels'][0]['groups']['limit']=null;
$bad['channels'][0]['capacity_status']='unconfigured_blocked_demo';
$unknown=$csv->export($bad);
$check('45 unconfigured capacity explicitly not homologated',
    str_contains($unknown,'NAO HOMOLOGADO'),true);
$check('46 unconfigured capacity never means a free subscription',
    str_contains($unknown,'SEM FATURAMENTO OU EXECUCAO'),true);
putenv('PERF_F56_CSV_LAB=0');
$check('47 feature switch off denies export',$csv->export($dto),null);
putenv('PERF_F56_CSV_LAB=1');
putenv('APP_ENV=production');
$check('48 production environment refuses synthetic CSV',$csv->export($dto),null);
putenv('APP_ENV=testing');
$check('49 input DTO still prevents dispatch',$dto['dispatch_enabled'],false);
echo "PASS F56 $n synthetic scope CSV export checks; no clients, ads or money".PHP_EOL;
