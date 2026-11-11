<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F153OfflinePreviewCsvParity.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F153OfflineReviewFixtureFactory.php';

use Imagine\Performance\Domain\F153OfflinePreviewCsvParity;
use Imagine\Performance\Domain\F153OfflineReviewFixtureFactory;
use Imagine\Performance\Domain\SyntheticManagedScopeLedger;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
 ||getenv('PERF_F152_CSV_LAB')!=='1'
 ||getenv('PERF_F153_PARITY_LAB')!=='1') {
 throw new RuntimeException('F153 requires synthetic CLI flags');
}
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void {
 ++$n;if(!$ok)throw new RuntimeException('F153 FAILED: '.$label);
};
$scope=new SyntheticManagedScopeLedger();
$tenant='perf-demo-aurora';
$scope->setDemoCapacity($tenant,'google_ads',2,3);
$scope->setDemoCapacity($tenant,'meta',1,2);
$scope->includeCampaign($tenant,'google_ads','cam-search-demo');
$scope->includeCampaign($tenant,'meta','cam-social-demo');
$projection=F153OfflineReviewFixtureFactory::build($scope,$tenant);
$check(is_array($projection)&&$projection['review_count']===2,
 'common fictional fixture creates two scoped reviews');
$check(($projection['summary']['pending_demo']??-1)===1
 &&($projection['summary']['changes_requested_demo']??-1)===1,
 'common fiction has one pending and one change-requested review');
$check(F153OfflineReviewFixtureFactory::build($scope,'perf-demo-boreal')===null,
 'shared preview factory refuses wrong tenant fixture');

$env=[
 'APP_ENV'=>'testing','PERF_F52_SCOPE_LAB'=>'1',
 'PERF_F53_PORTAL_PREVIEW_LAB'=>'1','PERF_F55_RENDER_LAB'=>'1',
 'PERF_F151_APPROVAL_LAB'=>'1','PERF_F152_CSV_LAB'=>'1',
 'PERF_F153_PARITY_LAB'=>'1',
];
$build=static function(string $path)use($env):string {
 $proc=proc_open([PHP_BINARY,dirname(__DIR__,2).'/'.$path],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2),$env
 );
 if(!is_resource($proc))throw new RuntimeException('F153 subprocess failed to launch');
 fclose($pipes[0]);
 $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
 $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
 if(proc_close($proc)!==0||!is_string($stdout)) {
  throw new RuntimeException('F153 report generator failed: '.substr((string)$stderr,0,160));
 }
 return $stdout;
};
$html=$build('contracts/demo/f55-render-offline-preview.php');
$csv=$build('contracts/demo/f152-export-creative-reviews.php');
$parity=new F153OfflinePreviewCsvParity();
$match=$parity->compare($html,$csv);
$check(is_array($match)&&$match['ok']===true&&$match['count']===2,
 'both independently generated offline reports have two identical reviews');
$check(is_array($match)&&preg_match('/^[a-f0-9]{64}$/D',$match['digest'])===1,
 'normalized review identity gets deterministic SHA256 digest');
$check($parity->compare($html,$csv)===$match,
 'same offline reports produce identical deterministic digest');
$check($parity->compare($html,substr($csv,3))===null,
 'CSV without UTF-8 BOM rejected');
$check($parity->compare($html,str_replace('NAO E APROVACAO REAL',
 'APROVACAO REAL',$csv))===null,
 'CSV cannot falsely claim real human approval');
$check($parity->compare($html,str_replace('SEM ANUNCIOS',
 'ANUNCIOS PUBLICADOS',$csv))===null,
 'CSV cannot claim provider execution');
$check($parity->compare($html,str_replace('SEM COBRANCA',
 'COBRANCA OK',$csv))===null,
 'CSV cannot claim billing');
$check($parity->compare($html,str_replace('rev-social-demo','rev-falso',
 $csv))===null,'CSV review missing from Portal HTML rejected');
$check($parity->compare($html,str_replace('Ajustes ficticios solicitados',
 'Aprovada somente em teste',$csv))===null,
 'status disagreement between HTML and CSV rejected');
$check($parity->compare($html,str_replace(
 '"approval_source":"none_real"','"approval_source":"portal_live"',$html))===null,
 'HTML cannot claim real approval source');
$check($parity->compare(str_replace('"dispatch_enabled":false',
 '"dispatch_enabled":true',$html),$csv)===null,
 'HTML with enabled dispatch rejected');
$check($parity->compare($html,$csv."\nEXTRA_ROW\n")===null,
 'extra spreadsheet row detected');
$check($parity->compare(str_replace('rev-social-demo','rev-search-demo',$html),
 $csv)===null,'duplicate HTML review identities detected');
$check($parity->compare('', $csv)===null,'missing HTML refused');
$check($parity->compare($html, '')===null,'missing CSV refused');
$check($parity->compare(str_repeat('X',150001),$csv)===null,
 'oversized HTML refused without parsing');
$check(($projection['authorization_eligible']??null)===false
 &&($projection['billing_enabled']??null)===false
 &&($projection['dispatch_enabled']??null)===false
 &&($projection['human_approval_verified']??null)===false,
 'shared fixture never grants operational privileges');
echo "F153 offline HTML/CSV parity: {$n} PASS / 0 FAIL — NO-GO\n";
