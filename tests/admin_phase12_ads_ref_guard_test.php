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
    'version'=>Gate::VERSION,'company_id'=>$company,'provider'=>'google_ads',
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

echo "OK: $checks offline credential reference/scope/lease/rotation and no-secret-leak checks; no live vault or Ads API\n";
