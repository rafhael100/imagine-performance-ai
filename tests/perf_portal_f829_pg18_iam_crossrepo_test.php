<?php
declare(strict_types=1);

// Actual Portal F829 PostgreSQL18 F66/F76/F113 evidence is produced by its
// pinned CI PHP test FIRST. This test independently verifies only a redacted
// synthetic F419/V7 commerce envelope, not a live login or contract.
require_once __DIR__.'/../src/Domain/Commercial/PortalF419SignedActivationReviewV7LabVerifier.php';
require_once __DIR__.'/../src/Domain/Commercial/PortalV7SignedPlanAgreementLabVerifier.php';

use Imagine\Performance\Commercial\PortalV7SignedPlanAgreementLabVerifier as Verify;

function f829Must(bool $ok,string $context):void {
    if(!$ok)throw new RuntimeException('F829 Portal ↔ Performance CI: '.$context);
}
function f829Deny(callable $action,string $context):void {
    try{$action();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('Unsafe signed V7 acceptance: '.$context);
}
foreach([
    'APP_ENV'=>'testing',
    'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
    'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
] as $env=>$required)if(getenv($env)!==$required){
    throw new RuntimeException('Independent test-only contract flags required: '.$env);
}
$path='/tmp/portal-f829-signed-plan-ci.json';
if(!is_file($path)||filesize($path)>65536 || filesize($path)<100){
    throw new RuntimeException('Pinned Portal PG18 signed review fixture absent or oversized.');
}
$data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
$fields=array_keys($data);
sort($fields,SORT_STRING);
$required=[
    'source','signed_plan','activation','expected','planned','channels',
    'review_now_utc','stable_plan_sha256','terms_sha256',
    'approval_revision','actual_customer_acceptance'
];
sort($required,SORT_STRING);
f829Must($fields===$required &&
    $data['source']==='portal_f829_pg18_synthetic_review' &&
    $data['actual_customer_acceptance']===false &&
    $data['approval_revision']===1,
    'only synthetic redacted review data may be consumed');
$envelope=$data['signed_plan'];
$activation=$data['activation'];
$expected=$data['expected'];
$channels=$data['channels'];
$planned=$data['planned'];
$nowUtc=$data['review_now_utc'];
$terms=$data['terms_sha256'];

$canonical=null;
$canonical=static function(array $value)use(&$canonical):array {
    if(array_is_list($value))return array_map(static fn(mixed $v):mixed=>
        is_array($v)?$canonical($v):$v,$value);
    ksort($value,SORT_STRING);
    foreach($value as $key=>$v)if(is_array($v))$value[$key]=$canonical($v);
    return $value;
};
$accepted=$envelope['payload'];
unset($accepted['nonce'],$accepted['issued_at'],$accepted['expires_at'],$accepted['key_id']);
$stable=hash('sha256',"IMAGINE-V7-ACCEPTANCE-STABLE-PLAN-V1\n".
    json_encode($canonical($accepted),
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    )
);
f829Must(hash_equals($data['stable_plan_sha256'],$stable) &&
    preg_match('/\A[a-f0-9]{64}\z/D',$terms)===1,
    'stable commercial fingerprint computed independently, terms format strict');
$consumed=[];
$claim=static function(string $tenant,string $agreement,string $nonce,int $expires)
    use(&$consumed,$expected):bool {
    $key=$tenant.'|'.$agreement.'|'.$nonce;
    if($tenant!==$expected['tenant_id'] ||
       $agreement!==$expected['agreement_id'] ||
       $expires<=0 || isset($consumed[$key]))return false;
    $consumed[$key]=true;
    return true;
};
$resolver=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_f827_plan_01'
        ?str_repeat('Y',48):null;
$verify=static fn(
    ?array $wire=null,?array $scope=null,?array $budget=null,
    ?callable $resolveKey=null
):array=>Verify::verify(
    $wire??$envelope,$activation,$scope??$expected,
    $channels,$budget??$planned,$nowUtc,str_repeat('X',48),
    $resolveKey??$resolver,$claim
);
$good=$verify();
f829Must(($good['hmac_verified_in_lab']??null)===true &&
    $good['tenant_id']===$expected['tenant_id'] &&
    $good['agreement_id']===$expected['agreement_id'] &&
    $good['channels']===['google_ads','meta_ads'] &&
    $good['planned_media_total_cents']===170000 &&
    $good['estimated_management_fee_cents']===37000 &&
    $good['activation_review_cents']===98000 &&
    $good['minimum_management_fee_per_channel_cents']===17000 &&
    $good['management_basis_points']===2000,
    'independent Performance verifies actual PG18-gated Portal F419/V7 money/HMAC');
foreach([
    'real_customer_contract_authenticated','customer_acceptance_proven',
    'portal_iam_authenticated','provider_spend_verified','finance_approved',
    'durable_nonce_verified','billable','asaas_write_enabled',
    'c6_write_enabled','ads_write_enabled','crm_write_enabled',
] as $name)f829Must(($good[$name]??null)===false,
    'successful SQL-shaped lab may not promote real permission: '.$name);
f829Must(count($consumed)===1,'nonce is claimed only once after valid HMAC');

f829Deny(fn()=> $verify(),'signed plan replay denied');
$changed=$envelope;
$changed['payload']['planned_media_total_cents']++;
f829Deny(fn()=> $verify($changed),'signed media cents cannot be edited');
$changed=$envelope;
$changed['payload']['tenant_id']='tenant_forged_12345678';
f829Deny(fn()=> $verify($changed),'tenant in signed plan cannot be edited');
$changed=$envelope;
$changed['payload']['agreement_id']='agreement_forged_12345678';
f829Deny(fn()=> $verify($changed),'agreement cannot be swapped');
$wrongExpected=$expected;
$wrongExpected['tenant_id']='tenant_other_12345678';
f829Deny(fn()=> $verify(null,$wrongExpected),'independently expected tenant mismatch');
$wrongExpected=$expected;
$wrongExpected['crm_review_mac']=str_repeat('0',64);
f829Deny(fn()=> $verify(null,$wrongExpected),'independently expected CRM PSP review mismatch');
$wrongBudget=$planned;
$wrongBudget['google_ads']+=100;
f829Deny(fn()=> $verify(null,null,$wrongBudget),'new media budget requires new signed plan and SQL approval');
f829Deny(fn()=> $verify(null,null,null,static fn():?string=>null),
    'missing independent HMAC verification key');
f829Must(count($consumed)===1,'all invalid reviews fail before a new nonce is claimed');
putenv('APP_ENV=production');
f829Deny(fn()=> $verify(),'even a valid Portal PG18-signed synthetic review cannot be used in production');

echo "PASS: F829 Portal real-shaped PG18 session/MFA/grant/agreement fixture -> signed V7/HMAC F419 -> independent Performance verifier, replay and tampering denied; no actual customer IAM or billing.\n";
