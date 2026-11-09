<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticOfflineReportExporter.php';

use Imagine\Performance\Domain\SyntheticOfflineReportExporter;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F52_SCOPE_LAB')!=='1'
    ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
    ||getenv('PERF_F56_EXPORT_LAB')!=='1'){
    throw new RuntimeException('F56 report tests require fake-only PHP CLI');
}
ob_start();
require dirname(__DIR__,2).'/contracts/demo/f53-generate-scope-preview.php';
$raw=ob_get_clean();
if(!is_string($raw))throw new RuntimeException('Missing F53 output');
$dto=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
$exporter=new SyntheticOfflineReportExporter();
$n=0;
$check=static function(string $name,mixed $got,mixed $want)use(&$n):void{
    if($got!==$want){
        throw new RuntimeException('FAIL F56 '.$name.' got='
          .var_export($got,true).' want='.var_export($want,true));
    }
    echo 'PASS F56 '.++$n.' '.$name.PHP_EOL;
};
$json=$exporter->export($dto,'perf-demo-aurora','json');
$check('valid JSON output exists',is_string($json),true);
$report=json_decode($json,true,32,JSON_THROW_ON_ERROR);
$check('versioned report schema',$report['schema_version'],'perf.report.synthetic.v1');
$check('source is F53 mock DTO',$report['source'],'derived_from_f53_in_memory_fixture_only');
$check('fixture tenant preserved',$report['tenant_ref'],'perf-demo-aurora');
$check('report read-only',$report['read_only'],true);
$check('report not contracted',$report['contracted'],false);
$check('report never dispatches',$report['dispatch_enabled'],false);
$check('report cannot bill',$report['billing_enabled'],false);
$check('report not production ready',$report['ready_for_production'],false);
$check('no real provider metrics',$report['real_provider_metrics_available'],false);
$check('no currency',$report['currency'],null);
$check('no fabricated media expenditure',$report['media_spend'],null);
$check('Google and Meta only',array_column($report['channels'],'id'),['google_ads','meta']);
$check('paused campaign still counts for quota',$report['channels'][0]['campaigns']['used'],2);
$check('Google group usage from F53',$report['channels'][0]['groups']['used'],2);
$check('Meta campaign usage from F53',$report['channels'][1]['campaigns']['used'],1);
$check('campaign refs retained with no customer names',
    array_column($report['campaigns'],'ref'),
    ['cam-search-demo','cam-local-demo','cam-social-demo']);
$check('paused campaign remains paused',$report['campaigns'][1]['state'],'paused_demo');
$check('groups use only synthetic refs',array_column($report['groups'],'ref'),
    ['grp-search-demo','grp-local-demo','grp-social-demo']);
$check('private raw audit not exported',array_key_exists('audit',$report),false);
$check('report still lacks real approvals',$report['review_summary']['approval_source'],'none_real');
$check('JSON deterministic for identical fixture',$exporter->export($dto,'perf-demo-aurora','json'),$json);
$check('JSON includes synthetic disclaimer',str_contains($json,'SEM CLIENTES')||str_contains($json,'sem clientes'),true);

$csv=$exporter->export($dto,'perf-demo-aurora','csv');
$check('valid CSV output exists',is_string($csv),true);
$fh=fopen('php://temp','w+');fwrite($fh,$csv);rewind($fh);
$rows=[];
while(($row=fgetcsv($fh,0,',','"',''))!==false){$rows[]=$row;}
fclose($fh);
$check('CSV row count = header + summary + 4 channel lines + 6 cards',
    count($rows),12);
$check('CSV 8 headers',$rows[0],[
    'record_type','channel','reference','status','used','limit','remaining','note'
]);
$check('CSV disclaimer row',$rows[1][3],'synthetic_only');
$check('CSV first channel Google campaign',$rows[2][1],'google_ads');
$check('CSV first channel campaign count',$rows[2][4],'2');
$check('CSV first channel quota',$rows[2][5],'2');
$check('CSV first channel remaining',$rows[2][6],'0');
$check('CSV Meta capacity separated',$rows[4][1],'meta');
$check('CSV contains correct rows for campaign and group cards',
    array_count_values(array_column($rows,'0'))['campaign']??null,null); // arrays are positional only
$check('CSV does not include network credentials or customer data',
    (bool)preg_match('/access_token|provider_account_id|CPF|CNPJ|https?:\/\//i',$csv),false);
$check('CSV never invents prices',
    (bool)preg_match('/pre[cç]o|valor da m[ií]dia|faturamento|R\$\s*[0-9]/iu',$csv),false);
$check('CSV output repeat is deterministic',$exporter->export($dto,'perf-demo-aurora','csv'),$csv);
$check('CSV is below 64KB',strlen($csv)<65536,true);
$check('CSV every row eight columns',
    count(array_filter($rows,static fn(array $r):bool=>count($r)!==8)),0);
$check('CSV contains six campaign and group cards',
    count(array_filter($rows,static fn(array $r):bool=>in_array($r[0],['campaign','group'],true))),6);
$check('CSV paused campaign state is visible',
    count(array_filter($rows,static fn(array $r):bool=>$r[0]==='campaign'&&$r[3]==='paused_demo')),1);
$check('CSV formula prefixes do not occur as unescaped cell values',
    count(array_filter($rows,static function(array $r):bool{
        foreach($r as $cell){
            if(preg_match('/^[\x00-\x20]*[=+@-]/',$cell))return true;
        }
        return false;
    })),0);
$check('JSON exports same campaign count as CSV',
    count($report['campaigns']),
    count(array_filter($rows,static fn(array $r):bool=>$r[0]==='campaign')));

// Reject unknown data or scope rather than quietly filtering untrusted values.
$deny=static function(string $name,callable $change)use($dto,$exporter,$check):void{
    $m=$dto;$change($m);
    $check($name.' JSON denied',$exporter->export($m,'perf-demo-aurora','json'),null);
    $check($name.' CSV denied',$exporter->export($m,'perf-demo-aurora','csv'),null);
};
$deny('contract forged',static function(array &$m):void{$m['contracted']=true;});
$deny('dispatch enable attempt',static function(array &$m):void{$m['dispatch_enabled']=true;});
$deny('billing enable attempt',static function(array &$m):void{$m['billing_enabled']=true;});
$deny('authorization forged',static function(array &$m):void{$m['authorization_eligible']=true;});
$deny('release ready forged',static function(array &$m):void{$m['ready_for_production']=true;});
$deny('actual pricing forged',static function(array &$m):void{$m['prices_homologated']=true;});
$deny('source changed',static function(array &$m):void{$m['source']='real_google';});
$deny('tenant changed',static function(array &$m):void{$m['tenant_ref']='perf-demo-b';});
$deny('unexpected sensitive field',static function(array &$m):void{$m['access_token']='not-a-real-token';});
$deny('malicious formula campaign ref',static function(array &$m):void{$m['campaigns'][0]['ref']='=HYPERLINK("bad")';});
$deny('malicious newline group ref',static function(array &$m):void{$m['groups'][0]['ref']="grp-ok\n=SUM(1)";});
$deny('unapproved campaign state',static function(array &$m):void{$m['campaigns'][0]['state']='published_real';});
$deny('unexpected provider operation',static function(array &$m):void{$m['groups'][0]['provider_action']='google_pause';});
$deny('sneaky extra campaign metadata',static function(array &$m):void{$m['campaigns'][0]['account_id']='private';});
$deny('duplicate campaign ref',static function(array &$m):void{$m['campaigns'][1]['ref']=$m['campaigns'][0]['ref'];});
$deny('duplicate channel',static function(array &$m):void{$m['channels'][1]['id']='google_ads';});
$deny('unapproved channel label',static function(array &$m):void{$m['channels'][1]['label']='Meta + extra channel';});
$deny('quota below usage',static function(array &$m):void{$m['channels'][0]['campaigns']['limit']=1;});
$deny('remaining mismatch',static function(array &$m):void{$m['channels'][0]['campaigns']['remaining']=2;});
$deny('negative capacity',static function(array &$m):void{$m['channels'][0]['groups']['used']=-1;});
$deny('fractional usage',static function(array &$m):void{$m['channels'][0]['groups']['used']=0.5;});
$deny('invalid review approval source',static function(array &$m):void{$m['review_summary']['approval_source']='human_production';});
$deny('more denials than audit events',static function(array &$m):void{$m['review_summary']['rejected_actions_demo']=1000;});
$deny('unexpected review identity',static function(array &$m):void{$m['review_summary']['reviewer_email']='email@test.invalid';});
$deny('extra channel secret',static function(array &$m):void{$m['channels'][0]['oauth']='token';});
$deny('unknown quota status',static function(array &$m):void{$m['channels'][0]['capacity_status']='approved_real';});
$deny('automatically upgrade on full quota',static function(array &$m):void{$m['channels'][0]['attention']='upsell_auto';});
$deny('unknown group record',static function(array &$m):void{$m['groups'][0]['managed']=false;});

$check('unknown tenant request blocked',$exporter->export($dto,'perf-demo-b','json'),null);
$check('empty tenant request blocked',$exporter->export($dto,'','csv'),null);
$check('unsupported format html blocked',$exporter->export($dto,'perf-demo-aurora','html'),null);
$check('null DTO blocked',$exporter->export(null,'perf-demo-aurora','json'),null);
putenv('PERF_F56_EXPORT_LAB=0');
$check('flag disabled denies JSON',$exporter->export($dto,'perf-demo-aurora','json'),null);
putenv('PERF_F56_EXPORT_LAB=1');
putenv('APP_ENV=production');
$check('production denies CSV',$exporter->export($dto,'perf-demo-aurora','csv'),null);
putenv('APP_ENV=testing');
putenv('PERF_F53_PORTAL_PREVIEW_LAB=0');
$check('missing projection gate denies export',$exporter->export($dto,'perf-demo-aurora','json'),null);
putenv('PERF_F53_PORTAL_PREVIEW_LAB=1');
$check('restoring synthetic gates yields original JSON',
    $exporter->export($dto,'perf-demo-aurora','json'),$json);
echo "PASS F56 $n CSV/JSON safety, isolation and no-go assertions".PHP_EOL;
