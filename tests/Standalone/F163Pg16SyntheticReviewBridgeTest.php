<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F163Pg16SyntheticReviewRepository.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F163Pg16SyntheticReviewRepository;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F162_PG_LAB')!=='1'
 ||getenv('PERF_F163_PHP_BRIDGE_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432')
 throw new RuntimeException('F163 CI only');

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$connect=static fn(string $user,string $pass):PDO=>
 new PDO($dsn,$user,$pass,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
$admin=$connect('postgres','postgres');
$pdoA=$connect('perf_f162_a','ci-only-perf-f162-a');
$pdoB=$connect('perf_f162_b','ci-only-perf-f162-b');
$scope=new SyntheticManagedScopeLedger();
$a='perf-demo-aurora';$b='perf-demo-boreal';
$scope->setDemoCapacity($a,'google_ads',3,4);
$scope->setDemoCapacity($b,'meta',3,4);
$scope->includeCampaign($a,'google_ads','cam-f163-search');
$scope->includeCampaign($b,'meta','cam-f163-social');
$repoA=new F163Pg16SyntheticReviewRepository($pdoA,$scope);
$repoB=new F163Pg16SyntheticReviewRepository($pdoB,$scope);
$n=0;
$check=static function(bool $condition,string $why)use(&$n):void{
 ++$n;if(!$condition)throw new RuntimeException('F163 FAILED: '.$why);
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(RuntimeException $e){return true;}
};
$check($throws(static fn()=>new F163Pg16SyntheticReviewRepository($admin,$scope)),
 'superuser cannot impersonate a reviewed synthetic SQL login in PHP bridge');
$check($repoA->submit($a,'rev-f163-search','cam-f163-search','google_ads',
 'Criativo pesquisa')==='submitted_demo',
 'A submits fictional creative to real PG role-bound registry');
$check($repoB->submit($a,'rev-f163-other','cam-f163-search','google_ads',
 'B pretending A')==='tenant_identity_denied',
 'B cannot claim A by passing caller-controlled tenant');
$check($repoA->submit($b,'rev-f163-other','cam-f163-social','meta',
 'A pretending B')==='tenant_identity_denied',
 'A cannot claim B by passing caller-controlled tenant');
$check($repoA->submit($a,'rev-f163-search','cam-f163-search','google_ads',
 'Duplicate')==='duplicate_review_denied',
 'database primary key denies duplicate review after PHP restart');
$check($repoA->submit($a,'rev-f163-wrong','cam-f163-search','meta',
 'Wrong channel')==='campaign_out_of_scope_denied',
 'campaign/channel checked against F52 domain ledger');
$check($repoA->submit($a,'rev-%sql','cam-f163-search','google_ads',
 'Injected')==='invalid_fixture_denied',
 'bad SQL reference format denied before prepared statement');
$check($repoA->submit($a,'rev-f163-script','cam-f163-search','google_ads',
 '<script>alert(1)</script>')==='invalid_fixture_denied',
 'script markup title rejected');
$check($repoA->project($b)===null && $repoB->project($a)===null,
 'PHP cannot query another tenant just by changing a parameter');

$projA=$repoA->project($a);
$check(is_array($projA)&&$projA['review_count']===1
 &&$projA['audit_entries_demo']===1,
 'A project returns committed item and database-triggered audit event');
$check($projA['iam_verified']===false &&$projA['s2s_verified']===false
 &&$projA['dispatch_enabled']===false &&$projA['billing_enabled']===false
 &&$projA['human_approval_verified']===false,
 'PG-backed PHP projection cannot claim operational authorization');

$check($repoA->decide($a,'rev-f163-search',1,'request_changes')
 ==='changes_requested_demo','A requests fictional changes via prepared update');
$check($repoA->decide($a,'rev-f163-search',1,'approve_mock')
 ==='stale_or_not_pending_denied','stale approval denied after initial decision');
$check($repoA->revise($a,'rev-f163-search',2,'Criativo atualizado')
 ==='resubmitted_demo','A resubmits only after fictional requested changes');
$check($repoA->revise($a,'rev-f163-search',2,'Repetido')
 ==='stale_or_not_pending_denied','duplicate resubmission denied with SQL expected-version');
$check($repoA->decide($a,'rev-f163-search',3,'approve_mock')
 ==='approved_mock_only','A reaches final mock-only state');
$check($repoA->decide($a,'rev-f163-search',4,'reject_mock')
 ==='stale_or_not_pending_denied','terminal mock approval cannot be modified');

$restart=new F163Pg16SyntheticReviewRepository(
 $connect('perf_f162_a','ci-only-perf-f162-a'),$scope);
$persisted=$restart->project($a);
$check($persisted['review_count']===1
 &&$persisted['summary']['approved_mock_only']===1
 &&$persisted['reviews'][0]['version']===4
 &&$persisted['audit_entries_demo']===4,
 'new PostgreSQL connection reads persisted review and four trigger events');

$check($repoB->submit($b,'rev-f163-search','cam-f163-social','meta',
 'B independent')==='submitted_demo',
 'B may use same review id in separate tenant');
$check($repoB->decide($b,'rev-f163-search',1,'reject_mock')
 ==='rejected_mock_only','B decision is independent of A');
$check($repoB->project($b)['review_count']===1
 &&$repoB->project($b)['summary']['rejected_mock_only']===1
 &&$repoB->project($b)['audit_entries_demo']===2,
 'B has isolated state and events');
$check($restart->project($a)['summary']['approved_mock_only']===1,
 'B changes cannot alter A state');
$check($repoB->decide($a,'rev-f163-search',4,'approve_mock')
 ==='tenant_identity_denied','B cannot modify A by spoofing version and tenant');

$admin->exec('REVOKE INSERT ON perf_f162.reviews FROM perf_f162_a');
$check($repoA->submit($a,'rev-f163-revoked','cam-f163-search','google_ads',
 'No permission')==='database_unknown_fail_closed',
 'revoked SQL INSERT privilege leads to fail-closed bridge');
$admin->exec('GRANT INSERT ON perf_f162.reviews TO perf_f162_a');
$check($repoA->submit($a,'rev-f163-restored','cam-f163-search','google_ads',
 'Restored')==='submitted_demo',
 'restoring synthetic privilege permits new pending mock review');
$check($repoA->decide($b,'rev-f163-search',1,'approve_mock')
 ==='tenant_identity_denied','bridge checks authenticated tenant before SQL update');

$check($repoA->submit($a,'rev-f163-revise','cam-f163-search','google_ads',
 'Out of scope soon')==='submitted_demo','seed review before scope retirement');
$check($repoA->decide($a,'rev-f163-revise',1,'request_changes')
 ==='changes_requested_demo','request fictional changes before campaign retirement');
$check($scope->releaseCampaign($a,'cam-f163-search',true)
 ==='released_synthetic_only','F52 ledger removes A managed campaign');
$check($repoA->revise($a,'rev-f163-revise',2,'Outside scope')
 ==='campaign_out_of_scope_denied','PHP cannot resubmit archived scoped campaign');
$check($repoA->project($a)===null,
 'read projection fails closed on rows whose campaign no longer managed');
$check($repoB->project($b)!==null,'B remains available after A campaign retirement');
echo "F163 PHP PostgreSQL16 review bridge: {$n} PASS / 0 FAIL — NO-GO\n";
