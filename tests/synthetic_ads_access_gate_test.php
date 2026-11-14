<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/SyntheticAdsAccessGate.php';

use Imagine\Performance\Commercial\SyntheticProviderSpendAdapter as Plans;
use Imagine\Performance\Commercial\SyntheticAdsAccessGate as Gate;

function sameAccess(mixed $expected, mixed $actual, string $what): void {
    if ($expected !== $actual) throw new RuntimeException('Mismatch: '.$what);
}
function deniedAccess(callable $f, string $what): void {
    try { $f(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException('Expected denied access: '.$what);
}
$now=1794660000;
$tenant='tenant_access_test1234';
$period='2026-11';
$g='1234567890';
$m='act_987654321';
$googleBinding=[
    'tenant_id'=>$tenant, 'period'=>$period, 'provider'=>'google_ads',
    'account_id'=>$g, 'consent_id'=>'consent_google_123456',
    'credential_reference'=>'ref_credential_google_test12345',
    'consent_status'=>'active',
    'consent_issued_at'=>$now-60,
    'consent_expires_at'=>$now+120,
    'required_oauth_scope'=>'https://www.googleapis.com/auth/adwords',
    'metadata_currency'=>'BRL', 'metadata_time_zone'=>'America/Sao_Paulo',
    'metadata_account_access'=>'reporting',
    'portal_iam_verified'=>false, 'provider_authorization_verified'=>false,
    'google_cloud_project_id'=>'imagine-ads-lab-01',
    'google_api_access_tier'=>'test',
];
$metaBinding=$googleBinding;
unset($metaBinding['google_cloud_project_id'],$metaBinding['google_api_access_tier']);
$metaBinding['provider']='meta_ads';
$metaBinding['account_id']=$m;
$metaBinding['required_oauth_scope']='ads_read';
$metaBinding['meta_app_id']='123456789';
$metaBinding['consent_id']='consent_meta_123456';
$metaBinding['credential_reference']='ref_credential_meta_test123456';
$gp=Plans::googleRequestPlan($g,$period);
$mp=Plans::metaRequestPlan($m,$period);
$out=Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$gp,$now);
sameAccess('googleads.googleapis.com',$out['host'],'Google host whitelist');
sameAccess('POST',$out['method'],'SearchStream is POST but reporting-only');
sameAccess('test',$googleBinding['google_api_access_tier'],'Google access modeled via Cloud project');
sameAccess(false,$out['network_enabled'],'no transport');
sameAccess(false,$out['portal_iam_verified'],'fixture never claims IAM');
sameAccess(false,$out['provider_authorization_verified'],'fixture never claims OAuth authorization');
sameAccess(false,$out['ads_write_enabled'],'cannot mutate ads');
sameAccess(false,$out['asaas_write_enabled'],'cannot charge');

$meta=Gate::inspect($tenant,$period,'meta_ads',$m,$metaBinding,$mp,$now);
sameAccess('graph.facebook.com',$meta['host'],'Meta API host pinned');
sameAccess('GET',$meta['method'],'Meta only read Insights');
sameAccess('ads_read',$meta['required_oauth_scope'],'Meta read scope');

$bad=$gp; $bad['method']='DELETE';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$bad,$now),'Google DELETE');
$bad=$gp; $bad['path']='/v26/customers/'.$g.'/campaigns:mutate';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$bad,$now),'Google mutate');
$bad=$gp; $bad['host']='evil.example';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$bad,$now),'user-supplied host');
$bad=$gp; $bad['body']['query']='SELECT * FROM customer';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$bad,$now),'arbitrary query');
$bad=$gp; $bad['transport_enabled']=true;
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$bad,$now),'enabling network');
$bad=$mp; $bad['path']='/v27.0/'.$m.'/ads';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'meta_ads',$m,$metaBinding,$bad,$now),'Meta other edge');
$bad=$mp; $bad['query']['fields']='spend,access_token';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'meta_ads',$m,$metaBinding,$bad,$now),'secret field');

$bad=$googleBinding; $bad['consent_status']='revoked';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'revoked consent');
$bad=$googleBinding; $bad['consent_expires_at']=$now;
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'expired consent');
$bad=$googleBinding; $bad['tenant_id']='tenant_foreign_123456';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'wrong tenant');
$bad=$googleBinding; $bad['period']='2026-10';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'wrong period');
$bad=$googleBinding; $bad['account_id']='9999999999';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'foreign account');
$bad=$googleBinding; $bad['required_oauth_scope']='https://www.googleapis.com/auth/cloud-platform';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'Google wrong OAuth scope');
$bad=$googleBinding; $bad['developer_token']='not-required-after-2026-09-09';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'legacy developer token not in contract');
$bad=$googleBinding; $bad['google_cloud_project_id']='http://x';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'invalid project identifier');
$bad=$googleBinding; $bad['google_api_access_tier']='unknown';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'unknown API tier');
$bad=$googleBinding; $bad['portal_iam_verified']=true;
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'synthetic false assertion of verified Portal IAM');
$bad=$googleBinding; $bad['provider_authorization_verified']=true;
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'synthetic false assertion of official access');
$bad=$googleBinding; $bad['metadata_currency']='USD';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'account currency mismatch');
$bad=$googleBinding; $bad['metadata_time_zone']='Invalid/Zone';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$bad,$gp,$now),'fake IANA zone');
$bad=$metaBinding; $bad['required_oauth_scope']='ads_management';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'meta_ads',$m,$bad,$mp,$now),'Meta scope too broad');
$bad=$metaBinding; $bad['meta_app_id']='abc';
deniedAccess(fn()=>Gate::inspect($tenant,$period,'meta_ads',$m,$bad,$mp,$now),'bad Meta App');
deniedAccess(fn()=>Gate::inspect('tenant_wrong_123456',$period,'meta_ads',$m,$metaBinding,$mp,$now),'wrong IAM tenant argument');

putenv('PERF_ADS_ACCESS_LAB=0');
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$gp,$now),'disabled flag');
putenv('PERF_ADS_ACCESS_LAB=1');
putenv('APP_ENV=production');
deniedAccess(fn()=>Gate::inspect($tenant,$period,'google_ads',$g,$googleBinding,$gp,$now),'production forbidden');
echo "OK: read-only Google/Meta access plan scope, simulated consent, Google Cloud project, deny mutate, deny real network, no payment\n";
