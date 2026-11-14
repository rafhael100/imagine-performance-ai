<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Commercial/SyntheticProviderSpendAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';
require_once __DIR__.'/../src/Domain/Commercial/AdsProviderReadOnlyBoundary.php';

use Imagine\Performance\Commercial\SyntheticProviderSpendAdapter as Provider;
use Imagine\Performance\Commercial\ChatgptAdsOfflineAdapter as OpenaiAds;
use Imagine\Performance\Commercial\AdsProviderReadOnlyBoundary as Boundary;

function sameBoundary(mixed $a,mixed $b,string $name):void {
    if($a!==$b)throw new RuntimeException('Ads boundary mismatch: '.$name);
}
function denyBoundary(callable $f,string $name):void {
    try{$f();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Unsafe Ads boundary accepted: '.$name);
}
$tenant='tenant_provider_lab_123456';
$otherTenant='tenant_other_lab_99999999';
$agreement='agreement_provider_lab_123456';
$otherAgreement='agreement_other_lab_123456';
$month='2026-11';
$now=1794660000;
$zone='America/Sao_Paulo';
$common=[
    'tenant_id'=>$tenant,
    'agreement_id'=>$agreement,
    'period'=>$month,
    'credential_reference'=>'ref_offlinevault1234567890',
    'consent_reference'=>'consent_offline123456789',
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-600,
    'consent_expires_at'=>$now+600,
    'account_currency'=>'BRL',
    'account_time_zone'=>$zone,
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
];
$accounts=[
    'google_ads'=>[
        'id'=>'1234567890',
        'extra'=>[
            'google_cloud_project_id'=>'imagine-perf-cloud-12345',
            'google_project_access_level'=>'explorer',
            'permission'=>'https://www.googleapis.com/auth/adwords'
        ],
        'expectedHost'=>'googleads.googleapis.com','expectedMethod'=>'POST',
        'expectedPath'=>'/v26/customers/1234567890/googleAds:searchStream',
        'plan'=>Provider::googleRequestPlan('1234567890',$month)
    ],
    'meta_ads'=>[
        'id'=>'act_987654321',
        'extra'=>['meta_app_id'=>'987654321','permission'=>'ads_read'],
        'expectedHost'=>'graph.facebook.com','expectedMethod'=>'GET',
        'expectedPath'=>'/v27.0/act_987654321/insights',
        'plan'=>Provider::metaRequestPlan('act_987654321',$month)
    ],
    'chatgpt_ads'=>[
        'id'=>'adacct_123456',
        'extra'=>[
            'openai_ads_account_status'=>'synthetic_eligible',
            'permission'=>'advertiser_api_key'
        ],
        'expectedHost'=>'api.ads.openai.com','expectedMethod'=>'GET',
        'expectedPath'=>'/v1/ad_account/insights',
        'plan'=>OpenaiAds::insightsRequestPlan('adacct_123456',$month,$zone)
    ],
];
foreach($accounts as $channel=>$cfg){
    $binding=$common+['provider'=>$channel,'account_id'=>$cfg['id']]+$cfg['extra'];
    $plan=$cfg['plan'];
    $call=static function(
        ?array $b=null, ?array $p=null, ?string $expectedTenant=null,
        ?string $expectedAgreement=null, ?string $expectedPeriod=null, ?int $clock=null
    ) use($binding,$plan,$tenant,$agreement,$month,$now,$channel,$cfg):array {
        return Boundary::inspect(
            $expectedTenant??$tenant,$expectedAgreement??$agreement,
            $expectedPeriod??$month,$channel,$cfg['id'],
            $b??$binding,$p??$plan,$clock??$now
        );
    };
    $out=$call();
    sameBoundary($cfg['expectedHost'],$out['host'],$channel.' allowlisted host');
    sameBoundary($cfg['expectedMethod'],$out['method'],$channel.' request verb');
    sameBoundary($cfg['expectedPath'],$out['path'],$channel.' only insights/reporting');
    sameBoundary($agreement,$out['agreement_id'],$channel.' agreement bound');
    sameBoundary('ref_offlinevault1234567890',$out['credential_reference'],$channel.' opaque reference');
    foreach([
        'network_enabled','credentials_read_from_vault',
        'portal_iam_authenticated','provider_authorization_verified',
        'account_ownership_verified','provider_spend_verified',
        'finance_approved','billable','asaas_write_enabled','ads_write_enabled'
    ] as $flag){
        sameBoundary(false,$out[$flag],$channel.' '.$flag.' must be false');
    }
    $asText=json_encode($out,JSON_THROW_ON_ERROR);
    if(str_contains($asText,'Bearer ')||str_contains($asText,'client_secret')||
       str_contains($asText,'access_token')){
        throw new RuntimeException('Credential or secret material leaked to output.');
    }

    $bad=$binding;$bad['provider']='meta_ads';
    denyBoundary(fn()=>$call($bad),$channel.' provider swapping');
    $bad=$binding;$bad['account_id']='another_account';
    denyBoundary(fn()=>$call($bad),$channel.' wrong account');
    $bad=$binding;$bad['agreement_id']=$otherAgreement;
    denyBoundary(fn()=>$call($bad),$channel.' tenant agreement swapping');
    $bad=$binding;$bad['account_currency']='USD';
    denyBoundary(fn()=>$call($bad),$channel.' USD forbidden');
    $bad=$binding;$bad['account_time_zone']='UTC';
    denyBoundary(fn()=>$call($bad),$channel.' timezone guard');
    $bad=$binding;$bad['consent_state']='revoked';
    denyBoundary(fn()=>$call($bad),$channel.' revoked');
    $bad=$binding;$bad['consent_expires_at']=$now;
    denyBoundary(fn()=>$call($bad),$channel.' expired');
    $bad=$binding;$bad['consent_issued_at']=$now+1;
    denyBoundary(fn()=>$call($bad),$channel.' consent from future');
    $bad=$binding;$bad['credential_reference']='Bearer hidden-token';
    denyBoundary(fn()=>$call($bad),$channel.' inline bearer token');
    $bad=$binding;$bad['secret_material_embedded']=true;
    denyBoundary(fn()=>$call($bad),$channel.' secret material embedded');
    $bad=$binding;$bad['provider_authorization_verified']=true;
    denyBoundary(fn()=>$call($bad),$channel.' false official OAuth claim');
    $bad=$binding;$bad['portal_iam_authenticated']=true;
    denyBoundary(fn()=>$call($bad),$channel.' fake trusted Portal');
    $bad=$binding;$bad['account_ownership_verified']=true;
    denyBoundary(fn()=>$call($bad),$channel.' fake account owner');
    $bad=$binding;$bad['Authorization']='Bearer foo';
    denyBoundary(fn()=>$call($bad),$channel.' header injection');
    $bad=$plan;$bad['method']='DELETE';
    denyBoundary(fn()=>$call($binding,$bad),$channel.' DELETE method');
    $bad=$plan;$bad['path']='/v1/campaigns';
    denyBoundary(fn()=>$call($binding,$bad),$channel.' alter ads path');
    $bad=$plan;$bad['transport_enabled']=true;
    denyBoundary(fn()=>$call($binding,$bad),$channel.' network activation');
    $bad=$plan;$bad['headers']=['Authorization'=>'Bearer test'];
    denyBoundary(fn()=>$call($binding,$bad),$channel.' request arbitrary credentials');
    $bad=$plan;$bad['host']='evil.example';
    denyBoundary(fn()=>$call($binding,$bad),$channel.' arbitrary host');
    $bad=$plan;$bad['query']=['fields'=>'spend,access_token'];
    denyBoundary(fn()=>$call($binding,$bad),$channel.' query injection');
    denyBoundary(fn()=>$call($binding,$plan,$otherTenant),$channel.' cross tenant');
    denyBoundary(fn()=>$call($binding,$plan,$tenant,$otherAgreement),$channel.' cross agreement');
    denyBoundary(fn()=>$call($binding,$plan,$tenant,$agreement,'2026-12'),$channel.' cross month');
    if($channel==='google_ads'){
        $bad=$binding;$bad['permission']='ads_read';
        denyBoundary(fn()=>$call($bad),'Google OAuth scope mismatch');
        $bad=$binding;$bad['google_project_access_level']='superuser';
        denyBoundary(fn()=>$call($bad),'Google Cloud access tier forged');
        $bad=$plan;$bad['body']['query']='SELECT * FROM campaign';
        denyBoundary(fn()=>$call($binding,$bad),'GAQL mutation/excessive reporting blocked');
    }
    if($channel==='meta_ads'){
        $bad=$binding;$bad['permission']='ads_management';
        denyBoundary(fn()=>$call($bad),'Meta write-permission substitution');
        $bad=$plan;$bad['query']['fields']='spend,adcreative';
        denyBoundary(fn()=>$call($binding,$bad),'Meta expanded field query');
    }
    if($channel==='chatgpt_ads'){
        $bad=$binding;$bad['openai_ads_account_status']='active';
        denyBoundary(fn()=>$call($bad),'real account status cannot self-attest');
        $bad=$plan;$bad['path']='/v1/campaigns/cmpn_123/activate';
        denyBoundary(fn()=>$call($binding,$bad),'ChatGPT ad activation denied');
        $bad=$plan;$bad['query']['time_granularity']='monthly';
        denyBoundary(fn()=>$call($binding,$bad),'ChatGPT report scope changed');
    }
}
putenv('PERF_ADS_READONLY_BOUNDARY_LAB=0');
denyBoundary(fn()=>Boundary::inspect($tenant,$agreement,$month,'google_ads',
    '1234567890',$common+['provider'=>'google_ads','account_id'=>'1234567890']+
    $accounts['google_ads']['extra'],$accounts['google_ads']['plan'],$now),'lab disabled');
putenv('PERF_ADS_READONLY_BOUNDARY_LAB=1');
putenv('APP_ENV=production');
denyBoundary(fn()=>Boundary::inspect($tenant,$agreement,$month,'google_ads',
    '1234567890',$common+['provider'=>'google_ads','account_id'=>'1234567890']+
    $accounts['google_ads']['extra'],$accounts['google_ads']['plan'],$now),'production denied');
echo "OK: exact Google/Meta/ChatGPT report-only plans, agreement/tenant/month/vault-ref binding; adversarial auth/Ads mutations denied, no network, no billing\n";
