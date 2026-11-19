<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsAccountMandateGate.php';

use Imagine\Performance\Administration\AdministrativeAdsAccountMandateGate as Gate;

$checks = 0;
$ok = static function(bool $value, string $label) use (&$checks): void {
    if (!$value) throw new RuntimeException('Mandate test: '.$label);
    $checks++;
};
$denied = static function(callable $run, string $label) use ($ok): void {
    try { $result = $run(); }
    catch (RuntimeException $e) {
        $ok($e->getMessage() === 'Account mandate denied', $label.' safe error');
        $ok($e->getPrevious() === null, $label.' no prior exception');
        return;
    }
    throw new RuntimeException('Mandate allowed forbidden case: '.$label);
};

$now = 2000000000;
$company = 'company_alfa';
$account = '1234567890';
$principal = 'imagine_portal_s2s';
$service = [
    'version' => Gate::SERVICE_VERSION,
    'principal' => $principal,
    'company_id' => $company,
    'operation' => Gate::OPERATION,
    'active' => true,
    'revision' => 3,
    'expires_at' => $now + 500,
    'revoked_at' => null,
];
$mandate = [
    'version' => Gate::VERSION,
    'company_id' => $company,
    'service_principal' => $principal,
    'provider' => 'google_ads',
    'account_id' => $account,
    'purpose' => Gate::PURPOSE,
    'operation' => Gate::OPERATION,
    'consent_reference' => 'consent-20261119-alpha',
    'credential_ref' => 'vault-ads-alpha-001',
    'status' => 'active',
    'revision' => 8,
    'expires_at' => $now + 400,
    'revoked_at' => null,
];

$serviceCalls = 0;
$accountCalls = 0;
$getService = static function(string $p, string $c, string $op) use (&$service, &$serviceCalls): array {
    $serviceCalls++;
    return $service;
};
$getMandate = static function(string $c, string $p, string $a, string $op, string $principal) use (&$mandate, &$accountCalls): array {
    $accountCalls++;
    return $mandate;
};
$gate = new Gate($principal, $getService, $getMandate, static function() use (&$now): int { return $now; });

$initial = $gate->current($company, 'google_ads', $account);
$ok($initial['lease'] === $gate->lease($company, 'google_ads', $account), 'stable lease');
$ok(strlen($initial['lease']) === 64, 'lease digest format');
$ok($initial['version'] === Gate::VERSION, 'versioned response');
$ok($initial['expires_at'] === $now + 400, 'minimum lifetime');
$ok($initial['credential_ref'] === 'vault-ads-alpha-001', 'opaque credential only');
$ok($initial['consent_reference'] === 'consent-20261119-alpha', 'consent reference');
$ok($gate->credentialReference($company, 'google_ads', $account, $initial['lease']) === 'vault-ads-alpha-001', 'credential bound to lease');
$ok($gate->credentialReference($company, 'google_ads', $account, str_repeat('f',64)) === null, 'unrelated lease denied');
$ok($gate->credentialReference($company, 'google_ads', $account, 'invalid') === null, 'malformed lease denied');
$ok($serviceCalls >= 3 && $accountCalls >= 3, 'both sources checked repeatedly');
$ok(!str_contains(json_encode($initial), 'access_token'), 'no bearer token in response');
$ok(!str_contains(json_encode($gate->__debugInfo()), 'vault'), 'no vault refs in debug');

$originalService = $service;
$originalMandate = $mandate;

// A SQL projection or associative resolver may reorder keys without changing
// the persisted revision/consent/credential. This must NOT invalidate a lease
// mid-pagination, whereas every actual change remains covered below.
$service = array_reverse($originalService, true);
$mandate = array_reverse($originalMandate, true);
$ok($gate->lease($company, 'google_ads', $account) === $initial['lease'],
    'reordered service and mandate keys preserve exact scoped lease');
$ok($gate->credentialReference($company, 'google_ads', $account, $initial['lease']) === 'vault-ads-alpha-001',
    'key order alone cannot revoke an unchanged credential reference');
$service = $originalService;
$mandate = array_reverse($originalMandate, true);
$ok($gate->lease($company, 'google_ads', $account) === $initial['lease'],
    'only one resolver reordering still preserves lease');
$service = $originalService;
$mandate = $originalMandate;

foreach ([
    ['active', false], ['revoked_at', $now], ['expires_at', $now],
    ['operation', 'dashboard.read'], ['company_id', 'company_beta'],
    ['principal', 'another_portal_service'], ['revision', 0],
    ['version', 'unknown'],
] as [$field, $value]) {
    $service = $originalService;
    $service[$field] = $value;
    $ok($gate->lease($company, 'google_ads', $account) === null, 'service '.$field.' refused');
}
foreach ([
    ['status', 'suspended'], ['revoked_at', $now], ['expires_at', $now],
    ['purpose', 'ads_write'], ['operation', 'dashboard.read'], ['company_id', 'company_beta'],
    ['provider', 'meta_ads'], ['account_id', '9876543210'],
    ['service_principal', 'imagine_other_s2s'], ['revision', 0],
    ['credential_ref', '../path'], ['consent_reference', 'bad'], ['version', 'unknown'],
] as [$field, $value]) {
    $service = $originalService;
    $mandate = $originalMandate;
    $mandate[$field] = $value;
    $ok($gate->lease($company, 'google_ads', $account) === null, 'account '.$field.' refused');
}
$service = $originalService; $mandate = $originalMandate;
$service['extra'] = 'unexpected';
$ok($gate->lease($company,'google_ads',$account) === null,'extra service fields denied');
$service = $originalService;
$mandate['extra'] = 'unexpected';
$ok($gate->lease($company,'google_ads',$account) === null,'extra account fields denied');
$mandate = $originalMandate;

$ok($gate->lease('company_beta','google_ads',$account) === null,'same provider account cannot cross companies');
$ok($gate->lease($company,'meta_ads',$account) === null,'provider swap denied');

// A denied S2S delegation must not invoke the account authority at all.
// Its callback can be privileged; a missing/grant-revoked principal may not
// enumerate nominal account mandates merely because it supplied account_id.
$accountReadsOnDeniedService = 0;
$deniedAccountSource = static function() use (&$accountReadsOnDeniedService): never {
    $accountReadsOnDeniedService++;
    throw new RuntimeException('ACCOUNT_AUTHORITY_SHOULD_NOT_BE_CALLED');
};
foreach ([
    ['active'=>false],
    ['revoked_at'=>$now],
    ['company_id'=>'company_beta'],
    ['principal'=>'imagine_other_s2s'],
    ['expires_at'=>$now],
] as $badServiceValues) {
    $badService = array_replace($originalService, $badServiceValues);
    $testService = static function() use ($badService): array { return $badService; };
    $deniedGate = new Gate($principal, $testService, $deniedAccountSource,
        static function() use (&$now): int { return $now; });
    $ok($deniedGate->lease($company,'google_ads',$account) === null,
        'invalid delegated principal fails before account lookup');
}
$deniedGate = new Gate($principal,
    static function(): never { throw new RuntimeException('S2S_UNAVAILABLE'); },
    $deniedAccountSource, static function() use (&$now): int { return $now; });
$ok($deniedGate->lease($company,'google_ads',$account) === null,
    'service resolver error denies without account query');
$ok($accountReadsOnDeniedService === 0,
    'six rejected S2S cases never invoked nominal account authority');
// A valid independent S2S resolver must still consult the account source.
$allowedAccountReads = 0;
$allowedAccountSource = static function() use (&$allowedAccountReads,&$originalMandate): array {
    $allowedAccountReads++;
    return $originalMandate;
};
$allowedGate = new Gate($principal,
    static function() use (&$originalService): array { return $originalService; },
    $allowedAccountSource, static function() use (&$now): int { return $now; });
$ok(is_string($allowedGate->lease($company,'google_ads',$account)),
    'valid delegated principal obtains the same read-only scoped lease');
$ok($allowedAccountReads === 1,
    'valid S2S invokes account authority once');

// A valid S2S snapshot can be revoked/rotated WHILE a privileged account
// resolver runs. The second service read must detect this TOCTOU boundary;
// no digest or opaque credential reference is issued from the stale grant.
foreach ([
    ['active' => false],
    ['revoked_at' => $now],
    ['principal' => 'imagine_other_s2s'],
    ['revision' => $originalService['revision'] + 1],
    ['expires_at' => $now],
] as $serviceChangedDuringAccount) {
    $midService = $originalService;
    $midServiceReads = 0;
    $midAccountReads = 0;
    $midServiceResolver = static function() use (&$midService, &$midServiceReads): array {
        $midServiceReads++;
        return $midService;
    };
    $midAccountResolver = static function() use (
        &$midService, &$midAccountReads, $serviceChangedDuringAccount, $originalMandate
    ): array {
        $midAccountReads++;
        $midService = array_replace($midService, $serviceChangedDuringAccount);
        return $originalMandate;
    };
    $midGate = new Gate($principal, $midServiceResolver, $midAccountResolver,
        static function() use (&$now): int { return $now; });
    $denied(fn() => $midGate->current($company,'google_ads',$account),
        'S2S mutation during privileged account lookup denied');
    $ok($midServiceReads === 2 && $midAccountReads === 1,
        'S2S rechecked after exactly one account lookup');
}
// Failure of the second service lookup is also sanitized and cannot
// accidentally accept the initially valid snapshot.
$serviceOnSecondReads = 0;
$serviceOnSecond = static function() use (&$serviceOnSecondReads, $originalService): array {
    if (++$serviceOnSecondReads === 2) throw new RuntimeException('SECRET_S2S_SOURCE_FAILURE');
    return $originalService;
};
$secondAccountReads = 0;
$secondAccountSource = static function() use (&$secondAccountReads, $originalMandate): array {
    $secondAccountReads++;
    return $originalMandate;
};
$secondGate = new Gate($principal, $serviceOnSecond, $secondAccountSource,
    static function() use (&$now): int { return $now; });
$denied(fn() => $secondGate->current($company,'google_ads',$account),
    'second S2S resolver exception sanitized');
$ok($serviceOnSecondReads === 2 && $secondAccountReads === 1,
    'second S2S failure occurs only after the account lookup');
// The second source may reorder its fields without changing its meaning.
$reorderedServiceReads = 0;
$reorderedService = static function() use (&$reorderedServiceReads, $originalService): array {
    return ++$reorderedServiceReads === 1
        ? $originalService : array_reverse($originalService, true);
};
$reorderedAccountReads = 0;
$reorderedAccount = static function() use (&$reorderedAccountReads, $originalMandate): array {
    $reorderedAccountReads++;
    return $originalMandate;
};
$reorderedGate = new Gate($principal, $reorderedService, $reorderedAccount,
    static function() use (&$now): int { return $now; });
$ok($reorderedGate->lease($company,'google_ads',$account) === $initial['lease'],
    'identical reordered S2S snapshot stays authorized');
$ok($reorderedServiceReads === 2 && $reorderedAccountReads === 1,
    'positive case also revalidates S2S exactly twice');

// A company-wide delegated principal MUST NOT inherit a mandate issued to a
// different S2S principal, even for the same company/provider/account.
$otherPrincipal = 'imagine_other_s2s';
$otherService = $originalService;
$otherService['principal'] = $otherPrincipal;
$gateOther = new Gate(
    $otherPrincipal,
    static function(string $p, string $c, string $op) use (&$otherService): array { return $otherService; },
    $getMandate,
    static function() use (&$now): int { return $now; }
);
$ok($gateOther->lease($company,'google_ads',$account) === null,
    'company reports.read is not another S2S principal account mandate');
$mandate['service_principal'] = $otherPrincipal;
$ok($gateOther->lease($company,'google_ads',$account) !== null,
    'explicit same-account mandate for second principal works');
$ok($gate->lease($company,'google_ads',$account) === null,
    'original principal cannot use second principal account mandate');
$mandate = $originalMandate;

$ok($gate->lease($company,'google_ads','1111111111') === null,'account swap denied');
$ok($gate->lease($company,'google_ads','malformed') === null,'invalid account denied');

$originalLease = $gate->lease($company,'google_ads',$account);
$service['revision']++;
$ok($gate->lease($company,'google_ads',$account) !== $originalLease,'service revision rotates lease');
$ok($gate->credentialReference($company,'google_ads',$account,$originalLease) === null,'old service lease cannot resolve ref');
$service = $originalService;
$mandate['revision']++;
$ok($gate->lease($company,'google_ads',$account) !== $originalLease,'account revision rotates lease');
$ok($gate->credentialReference($company,'google_ads',$account,$originalLease) === null,'old consent lease cannot resolve ref');
$mandate = $originalMandate;
$now = $mandate['expires_at'];
$ok($gate->lease($company,'google_ads',$account) === null,'expiry checked at current time');
$now = 2000000000;

$mandate['credential_ref'] = 'vault-ads-alpha-002';
$ok($gate->credentialReference($company,'google_ads',$account,$originalLease) === null,'rotation cannot use old lease');
$mandate = $originalMandate;
$mandate['consent_reference'] = 'consent-20261119-rotated';
$ok($gate->credentialReference($company,'google_ads',$account,$originalLease) === null,'consent replacement cannot use old lease');
$mandate = $originalMandate;

$badResolver = static function(): never { throw new RuntimeException('SECRET_INTERNAL_VALUE'); };
$denied(fn() => (new Gate($principal, $badResolver, $getMandate, static fn():int => 2000000000))
    ->current($company,'google_ads',$account), 'resolver exception sanitized');
$denied(fn() => (new Gate('bad', $getService, $getMandate))
    ->current($company,'google_ads',$account), 'principal validated');

$metaAccount = '987654321';
$mandate['provider']='meta_ads';
$mandate['account_id']=$metaAccount;
$ok($gate->lease($company,'meta_ads',$metaAccount) !== null,'Meta account read mandate accepted');
$ok($gate->lease($company,'google_ads',$account) === null,'Meta mandate does not authorize Google');
$mandate = $originalMandate;

$ok($gate->credentialReference($company,'google_ads',$account,$originalLease)==='vault-ads-alpha-001',
    'current grant restored after negative tests');
// Clock advancement DURING server-side resolver callbacks is a separate
// race from expiry while obtaining a bearer. Both authority sources must
// remain current at the instant a lease is issued.
$service = $originalService;
$mandate = $originalMandate;
$now = 2000000000;
$mandateClockReads = 0;
$expiringMandateClock = static function() use (&$mandateClockReads, &$now, &$originalMandate): int {
    $mandateClockReads++;
    return $mandateClockReads === 1 ? $now : $originalMandate['expires_at'];
};
$gateMandateLate = new Gate($principal, $getService, $getMandate, $expiringMandateClock);
$denied(fn() => $gateMandateLate->current($company,'google_ads',$account),
    'mandate expired while resolving delegated authority');
$ok($mandateClockReads === 2, 'mandate expiry checked after both callbacks');

$serviceSoon = $originalService;
$serviceSoon['expires_at'] = $now + 100;
$serviceClockReads = 0;
$serviceSoonSource = static function(string $p, string $c, string $op) use (&$serviceSoon): array {
    return $serviceSoon;
};
$expiringServiceClock = static function() use (&$serviceClockReads, &$now): int {
    $serviceClockReads++;
    return $serviceClockReads === 1 ? $now : $now + 100;
};
$gateServiceLate = new Gate($principal, $serviceSoonSource, $getMandate, $expiringServiceClock);
$denied(fn() => $gateServiceLate->current($company,'google_ads',$account),
    'S2S delegation expired while resolving account authority');
$ok($serviceClockReads === 2, 'S2S expiry checked after both callbacks');

// A bad second clock reading must not bypass the time-of-use check.
// An unchanged clock and two still-valid snapshots continue to work.
$invalidSecondClockReads = 0;
$invalidSecondClock = static function() use (&$invalidSecondClockReads, &$now): int|string {
    return ++$invalidSecondClockReads === 1 ? $now : 'invalid';
};
$gateBadSecondClock = new Gate($principal, $getService, $getMandate, $invalidSecondClock);
$denied(fn() => $gateBadSecondClock->current($company,'google_ads',$account),
    'invalid time after authority resolution');
$ok($invalidSecondClockReads === 2, 'invalid second clock was observed');
$gateStableClock = new Gate($principal, $getService, $getMandate,
    static function() use (&$now): int { return $now; });
$ok($gateStableClock->lease($company,'google_ads',$account) === $originalLease,
    'valid S2S and account lease stable after second time check');

// An injected or skewed clock must not roll backward during callbacks.
// Without a monotonic final observation, the gate could issue a lease from
// an earlier instant rather than the time after authority resolution.
$rollbackClockReads = 0;
$rollbackClock = static function() use (&$rollbackClockReads, &$now): int {
    return ++$rollbackClockReads === 1 ? $now + 20 : $now;
};
$rollbackGate = new Gate($principal, $getService, $getMandate, $rollbackClock);
$denied(fn() => $rollbackGate->current($company,'google_ads',$account),
    'rollback in post-resolver clock denied even with valid snapshots');
$ok($rollbackClockReads === 2, 'rollback post-resolver clock was observed');

echo "OK: $checks scoped account mandate, S2S delegation, cross-tenant, rotation, revocation and fail-closed assertions; no provider called\n";
