<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__ . '/../portal-v5-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceTriChannelV5LabIssuer.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalTriChannelV5CandidateGuard.php';
require_once __DIR__ . '/../labs/finance-pg/PgTriChannelV5ReviewHtml.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as Catalog;
use App\Homologation\Commerce\PortalPerformanceTriChannelV5LabIssuer as Issuer;
use Imagine\Performance\Commercial\Lab\PgTriChannelV5ReviewHtml as Html;

function seeV5(bool $ok,string $what):void {
    if (!$ok) throw new RuntimeException('V5 finance HTML failed: '.$what);
}
function blockV5Html(callable $f,string $what):void {
    try { $f(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('V5 finance HTML accepted invalid: '.$what);
}
foreach(['PERF_LAB_PG_DSN','PERF_LAB_PG_USER','PERF_LAB_PG_PASSWORD'] as $p){
    if(!getenv($p)) throw new RuntimeException('Missing synthetic PG credentials.');
}
if(getenv('APP_ENV')!=='testing' ||
    getenv('PERF_PORTAL_TRICHANNEL_V5_LAB')!=='1' ||
    getenv('PERF_PG_TRICHANNEL_V5_HTML_LAB')!=='1' ||
    getenv('PORTAL_PERF_TRICHANNEL_V5_LAB')!=='1'){
    throw new RuntimeException('Review HTML lab must be explicitly enabled.');
}
$db=new PDO(getenv('PERF_LAB_PG_DSN'),getenv('PERF_LAB_PG_USER'),getenv('PERF_LAB_PG_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
]);
$cat=new Catalog();
$reader=new Html($db);
$now=1794660000;
$period='2026-11';
$key='k_perf_pg_v5_test';
$secret=str_repeat('U',48);
$media=['meta_ads'=>70000,'chatgpt_ads'=>120000,'google_ads'=>100000];
$resolve=static fn(string $tenant,string $keyId):?string=>
    in_array($tenant,['tenant_v5_finance_alpha1234','tenant_v5_finance_beta5678'],true) &&
    $keyId==='k_perf_pg_v5_test'?str_repeat('U',48):null;
$sign=static fn(string $tenant,string $nonce)=>
    Issuer::issue($cat,$media,$tenant,'2026-11',$key,$nonce,$now-3,$now+120,$secret);
$a='tenant_v5_finance_alpha1234';
$signed=$sign($a,'nonce_pg_v5_original_12345678');
$records=[
 ['channel'=>'chatgpt_ads','period'=>$period,'report_id'=>'accountset_chat_12345','source'=>'synthetic_fixture','spend_cents'=>90000],
 ['channel'=>'google_ads','period'=>$period,'report_id'=>'accountset_google_12345','source'=>'synthetic_fixture','spend_cents'=>150000],
 ['channel'=>'meta_ads','period'=>$period,'report_id'=>'accountset_meta_12345','source'=>'synthetic_fixture','spend_cents'=>50000],
];
$digest=hash('sha256',json_encode($records,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$actual=['chatgpt_ads'=>90000,'google_ads'=>150000,'meta_ads'=>50000];
$render=static fn(?array $signedInput=null,?string $tenant=null,?array $unverified=null,?string $sha=null,
 string $reviewPeriod='2026-11')=>
    $reader->render($signedInput??$signed,$tenant??$a,$reviewPeriod,$cat->version(),$cat->minima(),
        $unverified??$actual,$sha??$digest,$now,$resolve);
$html=$render();
foreach([
 'Imagine Performance AI','DEMONSTRAÇÃO NÃO HOMOLOGADA',
 'ChatGPT Ads','Google Ads','Meta Ads','R$ 2.900,00','R$ 580,00','R$ 900,00',
 'R$ 1.500,00','R$ 500,00','Ajuste apenas para conferência'
] as $needle){
    seeV5(str_contains($html,$needle),'expected read-only HTML contents: '.$needle);
}
foreach(['<script','<form','<iframe','href=','fetch(','http://','https://','<button'] as $forbidden){
    seeV5(!str_contains($html,$forbidden),'must not contain external UI or actions: '.$forbidden);
}
seeV5(str_contains($html,'#F9941D'),'brand color matches Imagine');
seeV5(!$db->inTransaction(),'HTML reader must clear its transaction');

$bad=$signed;$bad['quote']['estimated_management_fee_cents']++;
blockV5Html(fn()=> $render($bad),'HMAC tampered');
blockV5Html(fn()=> $render($signed,'tenant_v5_finance_beta5678'),'other tenant must not see Alpha');
blockV5Html(fn()=> $render($signed,$a,$actual,str_repeat('f',64)),'evidence changed');
$changed=$actual;$changed['chatgpt_ads']=80000;$changed['google_ads']=160000;
blockV5Html(fn()=> $render($signed,$a,$changed,$digest),'changed source/amount cannot copy original draft');
blockV5Html(fn()=> $render($signed,$a,$actual,$digest,'2026-12'),'different competence');
$missing=$actual;unset($missing['chatgpt_ads']);
blockV5Html(fn()=> $render($signed,$a,$missing,$digest),'missing ChatGPT is not zero');

$b='tenant_v5_finance_beta5678';
$signedB=$sign($b,'nonce_pg_v5_beta_1234567890');
$zeroRecords=[
 ['channel'=>'chatgpt_ads','period'=>$period,'report_id'=>'zero_chat','source'=>'synthetic_fixture','spend_cents'=>0],
 ['channel'=>'google_ads','period'=>$period,'report_id'=>'zero_google','source'=>'synthetic_fixture','spend_cents'=>0],
 ['channel'=>'meta_ads','period'=>$period,'report_id'=>'zero_meta','source'=>'synthetic_fixture','spend_cents'=>0],
];
$zeroSha=hash('sha256',json_encode($zeroRecords,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$zeroHtml=$render($signedB,$b,['chatgpt_ads'=>0,'google_ads'=>0,'meta_ads'=>0],$zeroSha);
seeV5(str_contains($zeroHtml,'R$ 0,00'),'zero actual spend shown');
seeV5(str_contains($zeroHtml,'−R$ 580,00'),'negative adjustment is not refund');
seeV5(str_contains($zeroHtml,$b) && !str_contains($zeroHtml,$a),'isolation of review tenant');

foreach([$a,$b] as $tenant){
    $db->beginTransaction();
    $db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")->execute(['tenant'=>$tenant]);
    seeV5((int)$db->query('SELECT count(*) FROM perf_fin_lab.reconciliation_drafts')->fetchColumn()===1,
        'HTML review must not write finance drafts');
    seeV5((int)$db->query('SELECT count(*) FROM perf_fin_lab.signed_quote_nonces')->fetchColumn()===1,
        'HTML review must not claim new nonces');
    $db->rollBack();
}
$save=getenv('PERF_V5_REVIEW_HTML_PATH');
if(is_string($save) && $save!==''){
    if(file_put_contents($save,$html)===false)throw new RuntimeException('Cannot write CI-only HTML artifact.');
}
putenv('PERF_PG_TRICHANNEL_V5_HTML_LAB=0');
blockV5Html(fn()=> $render(),'review disabled by flag');
putenv('PERF_PG_TRICHANNEL_V5_HTML_LAB=1');
putenv('APP_ENV=production');
blockV5Html(fn()=> $render(),'production cannot render lab draft');
echo "OK: PG18 persisted signed V5 quote -> read-only branded HTML; three channels, HMAC, tenant, evidence, no actions/PII/payment\n";
