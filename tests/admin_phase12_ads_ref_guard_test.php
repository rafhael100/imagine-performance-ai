<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsAccountMandateGate.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsBoundCredentialResolver.php';

use Imagine\Performance\Administration\AdministrativeAdsAccountMandateGate as Gate;
use Imagine\Performance\Administration\AdministrativeAdsBoundCredentialResolver as Guard;

$checks = 0;
$ok = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Ads ref guard test: '.$label);
    $checks++;
};

$now = 2000000000;
$company = 'company_alfa';
$principal = 'imagine_portal_s2s';
$account = '1234567890';
$service = [
    'version'=>Gate::SERVICE_VERSION,'principal'=>$principal,'company_id'=>$company,
    'operation'=>Gate::OPERATION,'active'=>true,'revision'=>1,
    'expires_at'=>$now+500,'revoked_at'=>null,
];
$mandate = [
    'version'=>Gate::VERSION,'company_id'=>$company,'service_principal'=>$principal,'provider'=>'google_ads',
    'account_id'=>$account,'purpose'=>Gate::PURPOSE,'operation'=>Gate::OPERATION,
    'consent_reference'=>'synthetic-consent-001','credential_ref'=>'synthetic-vault-001',
    'status'=>'active','revision'=>5,'expires_at'=>$now+400,'revoked_at'=>null,
];
// The mutable fixture is deliberately captured by reference.
$serviceSource = static function (string $p,string $c,string $op) use (&$service): array { return $service; };
$accountSource = static function (string $c,string $p,string $a,string $op) use (&$mandate): array { return $mandate; };
$gate = new Gate($principal, $serviceSource, $accountSource, static function () use (&$now): int { return $now; });

$originalService = $service;
$originalMandate = $mandate;
$lease = $gate->lease($company,'google_ads',$account);
$ok(is_string($lease) && strlen($lease)===64, 'initial lease acquired');

$secrets = [
    'company_id'=>$company,'provider'=>'google_ads','account_id'=>$account,
    'credential_ref'=>'synthetic-vault-001','lease'=>$lease,
    'access_token'=>'synthetic_token_private_marker',
    'expires_at'=>$now+300,'login_customer_id'=>null,
];
$readCount=0;
$observedRefs=[];
$readByReference = static function (
    string $ref, string $c, string $p, string $a, string $expected
) use (&$readCount,&$observedRefs,&$secrets): array {
    $readCount++;
    $observedRefs[]=$ref;
    return $secrets;
};
$guard = new Guard($gate,$readByReference);
$result = $guard($company,'google_ads',$account,$lease);
$ok($result === $secrets,'valid scoped reference permits only laboratory token return');
$ok($observedRefs === ['synthetic-vault-001'], 'secret callback receives current persisted opaque ref');
$ok($readCount === 1,'valid lookup calls secret source exactly once');
$ok(!str_contains(json_encode($guard->__debugInfo(),JSON_THROW_ON_ERROR),'synthetic_token_private_marker'),
    'debug info never includes bearer');
$ok(!str_contains(json_encode($guard->__debugInfo(),JSON_THROW_ON_ERROR),'synthetic-vault-001'),
    'debug info never includes opaque reference');

// Rejected by the custody boundary itself (not delegated to HTTP validation).
$validEnvelope = $secrets;
foreach ([
    ['access_token','contains whitespace'],
    ['access_token',"synthetic\r\nheader"],
    ['access_token',[]],
    ['expires_at','2000000300'],
    ['expires_at',time()-1],
    ['login_customer_id','bad-login'],
    ['login_customer_id',1234567890],
] as [$field,$bad]) {
    $secrets[$field]=$bad;
    $ok($guard($company,'google_ads',$account,$lease) === null,
        'malformed custody envelope '.$field.' denied');
    $secrets=$validEnvelope;
}
$secrets['unexpected_scope_claim']='forbidden';
$ok($guard($company,'google_ads',$account,$lease) === null,
    'additional untrusted credential claim denied');
$secrets=$validEnvelope;
unset($secrets['access_token']);
$ok($guard($company,'google_ads',$account,$lease) === null,
    'missing access token denied before transport');
$secrets=$validEnvelope;
$secrets['developer_token']='invalid developer token';
$ok($guard($company,'google_ads',$account,$lease) === null,
    'legacy developer token shape checked before transport');
$secrets=$validEnvelope;
$secrets['developer_token']='syntheticDeveloperToken_XYZ';
$ok($guard($company,'google_ads',$account,$lease) === $secrets,
    'valid legacy Google field accepted only in expected envelope');
$secrets=$validEnvelope;

// A spoofed payload is not trusted merely because company/account/lease match.
foreach ([
    ['credential_ref','synthetic-vault-other'],
    ['credential_ref','syntheticVault'],
    ['company_id','company_beta'],
    ['provider','meta_ads'],
    ['account_id','9876543210'],
    ['lease',str_repeat('f',64)],
] as [$field,$value]) {
    $secrets[$field]=$value;
    $ok($guard($company,'google_ads',$account,$lease) === null,'credential mismatch '.$field.' denied');
    $secrets[$field]=match($field) {
        'credential_ref'=>'synthetic-vault-001','company_id'=>$company,
        'provider'=>'google_ads','account_id'=>$account,'lease'=>$lease,
    };
}
unset($secrets['credential_ref']);
$ok($guard($company,'google_ads',$account,$lease)===null,'missing credential reference denied');
$secrets['credential_ref']='synthetic-vault-001';
$readsBefore=$readCount;
$ok($guard($company,'google_ads','9876543210',$lease)===null,'different account denied before secret read');
$ok($guard('company_beta','google_ads',$account,$lease)===null,'same account other company denied before secret read');
$ok($guard($company,'meta_ads',$account,$lease)===null,'provider swap denied before secret read');
$ok($guard($company,'google_ads',$account,str_repeat('0',64))===null,'stale lease denied');
$ok($readCount===$readsBefore,'unauthorized scopes never invoked secret source');

$service['active']=false;
$readsBefore=$readCount;
$ok($guard($company,'google_ads',$account,$lease)===null,'S2S suspended denies secret access');
$ok($readCount===$readsBefore,'suspended service cannot read secret source');
$service=$originalService;

$mandate['status']='revoked';
$readsBefore=$readCount;
$ok($guard($company,'google_ads',$account,$lease)===null,'revoked account denies secret access');
$ok($readCount===$readsBefore,'revoked account never reads secret source');
$mandate=$originalMandate;

// A rotation or revocation DURING the secret lookup invalidates the second check.
$changeMidLookup = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$mandate,&$secrets): array {
    $mandate['revision']++;
    return $secrets;
});
$ok($changeMidLookup($company,'google_ads',$account,$lease) === null,'revision rotated mid-read denied');
$mandate=$originalMandate;
$changeConsent = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$mandate,&$secrets): array {
    $mandate['consent_reference']='synthetic-consent-002';
    return $secrets;
});
$ok($changeConsent($company,'google_ads',$account,$lease) === null,'consent replaced mid-read denied');
$mandate=$originalMandate;
$changeCredential = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$mandate,&$secrets): array {
    $mandate['credential_ref']='synthetic-vault-002';
    return $secrets;
});
$ok($changeCredential($company,'google_ads',$account,$lease) === null,'credential ref rotated mid-read denied');
$mandate=$originalMandate;
$expireMidRead = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$now,&$mandate,&$secrets): array {
    $now=$mandate['expires_at'];
    return $secrets;
});
$ok($expireMidRead($company,'google_ads',$account,$lease) === null,'mandate expiry mid-read denied');
$now=2000000000;
$changeService = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$service,&$secrets): array {
    $service['revision']++;
    return $secrets;
});
$ok($changeService($company,'google_ads',$account,$lease) === null,'S2S rotated mid-read denied');
$service=$originalService;
$revokeMidRead = new Guard($gate, static function (
    string $ref,string $c,string $p,string $a,string $expected
) use (&$mandate,&$secrets): array {
    $mandate['status']='revoked';
    $mandate['revoked_at']=2000000001;
    return $secrets;
});
$ok($revokeMidRead($company,'google_ads',$account,$lease) === null,'revoked during lookup denied');
$mandate=$originalMandate;

$throwsSecret = new Guard($gate, static function (): never {
    throw new RuntimeException('SECRET_PROVIDER_DO_NOT_REVEAL');
});
$ok($throwsSecret($company,'google_ads',$account,$lease) === null,'secret provider exception denied without chain');
$ok($guard($company,'google_ads',$account,$lease) === $secrets, 'recovered valid scope still succeeds');

// Credential expiry can change while the second mandate/lease check runs.
// The first clock observation is valid, the second equals expires_at.
// The secret must be denied AFTER revalidation even when both grants are stable.
$secretClockCalls = 0;
$advancingClock = static function() use (&$secretClockCalls, &$now): int {
    $secretClockCalls++;
    return $secretClockCalls === 1 ? $now : $now + 300;
};
$expiryAfterGate = new Guard($gate, $readByReference, $advancingClock);
$ok($expiryAfterGate($company,'google_ads',$account,$lease) === null,
    'credential expires during second mandate validation denied');
$ok($secretClockCalls === 2, 'boundary checked bearer lifetime again after lease');
$validClock = new Guard($gate, $readByReference, static function() use (&$now): int { return $now; });
$ok($validClock($company,'google_ads',$account,$lease) === $validEnvelope,
    'valid unexpired token still allowed with deterministic clock');
$invalidClock = new Guard($gate, $readByReference, static fn(): string => 'invalid');
$ok($invalidClock($company,'google_ads',$account,$lease) === null,
    'invalid custody clock fails closed without leaked exception');

// Meta has an independent account scope and a smaller custody envelope.
// Google-only login and legacy developer claims must NEVER be accepted for Meta.
$mandate['provider']='meta_ads';
$mandate['account_id']='987654321';
$mandate['consent_reference']='synthetic-consent-meta-001';
$mandate['credential_ref']='synthetic-vault-meta-001';
$mandate['revision']++;
$metaAccount='987654321';
$metaLease=$gate->lease($company,'meta_ads',$metaAccount);
$ok(is_string($metaLease) && $metaLease!==$lease, 'Meta account has independent lease');
$metaSecrets = [
    'company_id'=>$company,'provider'=>'meta_ads','account_id'=>$metaAccount,
    'credential_ref'=>'synthetic-vault-meta-001','lease'=>$metaLease,
    'access_token'=>'synthetic_meta_token_private','expires_at'=>$now+300,
];
$secrets=$metaSecrets;
$ok($guard($company,'meta_ads',$metaAccount,$metaLease)===$metaSecrets,
    'Meta allows only correctly bound custody envelope');
$ok($guard($company,'google_ads',$account,$lease)===null,
    'Meta mandate never authorizes old Google account');
$secrets['login_customer_id']=null;
$ok($guard($company,'meta_ads',$metaAccount,$metaLease)===null,
    'Meta forbids Google login customer claim even when null');
$secrets=$metaSecrets;
$secrets['developer_token']='syntheticDeveloperToken_XYZ';
$ok($guard($company,'meta_ads',$metaAccount,$metaLease)===null,
    'Meta forbids legacy Google developer token claim');
$secrets=$metaSecrets;
$secrets['account_id']=$account;
$ok($guard($company,'meta_ads',$metaAccount,$metaLease)===null,
    'Meta callback cannot substitute Google account');
$secrets=$metaSecrets;
$secrets['credential_ref']='synthetic-vault-001';
$ok($guard($company,'meta_ads',$metaAccount,$metaLease)===null,
    'Meta callback cannot use Google opaque reference');
$secrets=$metaSecrets;
$mandate=$originalMandate;
$secrets=$validEnvelope;
$ok($guard($company,'google_ads',$account,$lease)===$validEnvelope,
    'valid Google mandate remains accessible after Meta negative checks');

echo "OK: $checks offline credential reference/scope/lease/rotation and no-secret-leak checks; no live vault or Ads API\n";
