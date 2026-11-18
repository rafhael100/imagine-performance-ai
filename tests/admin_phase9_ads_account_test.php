<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadWireAdapter.php';

use Imagine\Performance\Administration\AdministrativeAdsReadPlan as Plan;
use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;
use Imagine\Performance\Administration\AdministrativeAdsReadWireAdapter as Adapter;
use Imagine\Performance\Administration\AdministrativeAdsWireFailure as Failure;

$assertions = 0;
function accountCheck(bool $condition, string $label): void
{
    global $assertions;
    if (!$condition) throw new RuntimeException('Ads account assertion failed: '.$label);
    $assertions++;
}
function accountSame(mixed $expected, mixed $actual, string $label): void
{
    accountCheck($expected === $actual, $label);
}
function accountKeys(array $expected, array $actual, string $label): void
{
    $keys = array_keys($actual);
    sort($keys, SORT_STRING);
    sort($expected, SORT_STRING);
    accountSame($expected, $keys, $label);
}
function accountWireDenied(callable $call, string $label, ?string $reason = null): Failure
{
    try {
        $call();
    } catch (Failure $failure) {
        if ($reason !== null) accountSame($reason, $failure->reason, $label.' reason');
        accountSame('Ads read failed: '.$failure->reason, $failure->getMessage(), $label.' safe message');
        accountSame(null, $failure->getPrevious(), $label.' no raw exception chain');
        return $failure;
    }
    throw new RuntimeException('Ads account wire accepted invalid input: '.$label);
}
function accountConnectorDenied(callable $call, string $label): RuntimeException
{
    try {
        $call();
    } catch (RuntimeException $failure) {
        accountCheck(true, $label);
        return $failure;
    }
    throw new RuntimeException('Ads account connector accepted invalid input: '.$label);
}
function accountId(string $provider): string
{
    return $provider === 'google_ads' ? '1234567890' : '1234567890123';
}
function accountReportPlan(string $provider): array
{
    return Plan::prepare($provider, accountId($provider), '2026-11-01', '2026-11-30');
}
function accountHttp(array|string $body, int $status = 200): array
{
    return [
        'source_kind' => 'laboratory_fixture',
        'status' => $status,
        'content_type' => 'application/json',
        'redirected' => false,
        'body' => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
    ];
}
function accountMetadataBody(string $provider): array
{
    $id = accountId($provider);
    return $provider === 'google_ads'
        ? ['results' => [['customer' => [
            'id' => $id, 'resourceName' => 'customers/'.$id,
            'currencyCode' => 'BRL', 'timeZone' => 'America/Sao_Paulo',
        ]]]]
        : ['id' => 'act_'.$id, 'account_id' => $id, 'currency' => 'BRL', 'timezone_name' => 'America/Sao_Paulo'];
}
function accountReportBody(string $provider, int $day, ?string $cursor = null): array
{
    $id = accountId($provider);
    $date = sprintf('2026-11-%02d', $day);
    if ($provider === 'google_ads') {
        $body = ['results' => [[
            'customer' => ['id' => $id, 'currencyCode' => 'BRL', 'timeZone' => 'America/Sao_Paulo'],
            'campaign' => ['id' => '42'],
            'segments' => ['date' => $date],
            'metrics' => ['costMicros' => '1000000', 'impressions' => '100', 'clicks' => '2'],
        ]]];
        if ($cursor !== null) $body['nextPageToken'] = $cursor;
        return $body;
    }
    $body = ['data' => [[
        'account_id' => $id, 'account_currency' => 'BRL', 'campaign_id' => '42',
        'date_start' => $date, 'date_stop' => $date,
        'spend' => '1.00', 'impressions' => '100', 'clicks' => '2',
    ]]];
    if ($cursor !== null) {
        $body['paging'] = [
            'cursors' => ['after' => $cursor],
            'next' => accountReportPlan($provider)['read_plan']['url'].'?after='.rawurlencode($cursor),
        ];
    }
    return $body;
}
function accountAdapter(string $provider, Closure $http): Adapter
{
    return new Adapter($provider, accountId($provider), '2026-11-01', '2026-11-30', 'America/Sao_Paulo', $http);
}
function accountRun(Adapter $adapter, string $provider, ?Closure $authorize = null): array
{
    $authorize ??= static fn() => str_repeat('a', 64);
    return $adapter->connector($authorize)->read(
        'company_alfa', $provider, accountId($provider),
        '2026-11-01', '2026-11-30', 'America/Sao_Paulo'
    );
}

foreach (['google_ads', 'meta_ads'] as $provider) {
    $plan = Plan::prepareAccount($provider, accountId($provider));
    foreach (['executable', 'external_operations_enabled', 'billable'] as $flag) {
        accountSame(false, $plan[$flag], $provider.' metadata plan '.$flag);
    }
    accountSame('unavailable', $plan['status'], $provider.' metadata plan unavailable');
    accountSame(null, $plan['metrics'], $provider.' metadata plan does not contain metrics');
    accountSame(null, $plan['official_evidence'], $provider.' metadata plan is not evidence');
    accountCheck(!str_contains(json_encode($plan, JSON_THROW_ON_ERROR), 'access_token'), $provider.' metadata plan has no token');
    if ($provider === 'google_ads') {
        accountSame('POST', $plan['read_plan']['method'], 'Google account method');
        accountSame(accountReportPlan($provider)['read_plan']['url'], $plan['read_plan']['url'], 'Google account uses fixed Search route');
        accountSame(
            ['query' => 'SELECT customer.id, customer.currency_code, customer.time_zone FROM customer'],
            $plan['read_plan']['json'],
            'Google account query is fixed and unsegmented'
        );
    } else {
        accountSame('GET', $plan['read_plan']['method'], 'Meta account method');
        accountSame('https://graph.facebook.com/'.$plan['read_plan']['api_version'].'/act_'.accountId($provider), $plan['read_plan']['url'], 'Meta account node route');
        accountSame(['fields' => 'id,account_id,currency,timezone_name'], $plan['read_plan']['query'], 'Meta account fields are fixed');
    }

    // The constructor-bound report plan also binds the account metadata call.
    $direct = accountAdapter($provider, static fn() => accountHttp(accountMetadataBody($provider)));
    $metadata = $direct->account(accountReportPlan($provider));
    accountKeys(['source_kind', 'provider', 'api_version', 'account_id', 'currency', 'timezone', 'context_timezone'], $metadata, $provider.' metadata page fields');
    accountSame('laboratory_fixture', $metadata['source_kind'], $provider.' metadata provenance remains synthetic');
    accountSame($provider, $metadata['provider'], $provider.' metadata provider bound');
    accountSame($plan['read_plan']['api_version'], $metadata['api_version'], $provider.' metadata version bound');
    accountSame(accountId($provider), $metadata['account_id'], $provider.' metadata account bound');
    accountSame('BRL', $metadata['currency'], $provider.' metadata currency');
    accountSame('America/Sao_Paulo', $metadata['timezone'], $provider.' metadata timezone');
    accountSame($metadata['timezone'], $metadata['context_timezone'], $provider.' metadata context consistent');

    // Full consumer path: one account read precedes two reporting pages.
    $requests = [];
    $authorizations = [];
    $adapter = accountAdapter($provider, static function(array $request) use (&$requests, $provider): array {
        $requests[] = $request;
        return match (count($requests)) {
            1 => accountHttp(accountMetadataBody($provider)),
            2 => accountHttp(accountReportBody($provider, 1, 'page2')),
            3 => accountHttp(accountReportBody($provider, 2)),
            default => throw new RuntimeException('Unexpected extra fixture call'),
        };
    });
    $authorize = static function(string $company, string $givenProvider, string $account) use (&$authorizations): string {
        $authorizations[] = [$company, $givenProvider, $account];
        return str_repeat('a', 64);
    };
    $result = accountRun($adapter, $provider, $authorize);
    accountSame(3, count($requests), $provider.' account then two report calls');
    accountSame($plan['read_plan']['method'], $requests[0]['method'], $provider.' first method is metadata method');
    accountSame($plan['read_plan']['url'], $requests[0]['url'], $provider.' metadata called first');
    accountSame(
        $provider === 'google_ads' ? $plan['read_plan']['json'] : $plan['read_plan']['query'],
        $provider === 'google_ads' ? $requests[0]['json'] : $requests[0]['query'],
        $provider.' first payload is metadata payload'
    );
    accountSame(false, $requests[0]['follow_redirects'], $provider.' metadata redirects disabled');
    accountSame(1048576, $requests[0]['max_response_bytes'], $provider.' metadata response bounded');
    accountSame(accountReportPlan($provider)['read_plan']['url'], $requests[1]['url'], $provider.' metrics use report endpoint');
    accountSame(2, count($result['rows']), $provider.' both report pages included');
    accountSame(true, $result['complete'], $provider.' metadata plus complete report');
    accountSame('BRL', $result['currency'], $provider.' report currency initialized from metadata');
    accountKeys(['source_kind', 'provider', 'api_version', 'account_id', 'currency', 'timezone'], $result['account_metadata'], $provider.' sanitized metadata fields');
    $expectedMetadata = $metadata;
    unset($expectedMetadata['context_timezone']);
    foreach ($expectedMetadata as $field => $value) {
        accountSame($value, $result['account_metadata'][$field], $provider.' sanitized account fact '.$field);
    }
    foreach ($authorizations as $context) {
        accountSame(['company_alfa', $provider, accountId($provider)], $context, $provider.' authorization context unchanged');
    }
    accountCheck(count($authorizations) > 1, $provider.' authority is rechecked');
    foreach (['billable', 'external_operations_enabled'] as $flag) {
        accountSame(false, $result[$flag], $provider.' account facts do not change '.$flag);
    }
    accountSame(null, $result['official_evidence'], $provider.' account facts are not official evidence');

    $emptyCalls = 0;
    $empty = accountAdapter($provider, static function() use (&$emptyCalls, $provider): array {
        $emptyCalls++;
        return accountHttp($emptyCalls === 1
            ? accountMetadataBody($provider)
            : ($provider === 'google_ads' ? ['results' => []] : ['data' => []]));
    });
    $emptyResult = accountRun($empty, $provider);
    accountSame(2, $emptyCalls, $provider.' empty report still requires account metadata');
    accountSame([], $emptyResult['rows'], $provider.' empty report does not invent a zero-spend row');
    accountSame('BRL', $emptyResult['currency'], $provider.' empty report preserves metadata currency');
    accountSame('America/Sao_Paulo', $emptyResult['timezone'], $provider.' empty report preserves scoped timezone');
    accountSame(false, $emptyResult['billable'], $provider.' empty report is not billable');
    accountSame(null, $emptyResult['official_evidence'], $provider.' empty report does not attest zero spend');

    $calls = 0;
    $mismatch = accountAdapter($provider, static function() use (&$calls, $provider): array {
        $calls++;
        if ($calls !== 1) return accountHttp(accountReportBody($provider, 1));
        $body = accountMetadataBody($provider);
        if ($provider === 'google_ads') $body['results'][0]['customer']['currencyCode'] = 'USD';
        else $body['currency'] = 'USD';
        return accountHttp($body);
    });
    $failure = accountConnectorDenied(fn() => accountRun($mismatch, $provider), $provider.' first report row currency differs from account');
    accountSame('Mixed currency denied', $failure->getMessage(), $provider.' metadata currency is authoritative within fixture');
    accountSame(2, $calls, $provider.' currency mismatch found in first report page');

    $wrongZone = accountMetadataBody($provider);
    if ($provider === 'google_ads') $wrongZone['results'][0]['customer']['timeZone'] = 'UTC';
    else $wrongZone['timezone_name'] = 'UTC';
    accountWireDenied(
        fn() => accountAdapter($provider, static fn() => accountHttp($wrongZone))->account(accountReportPlan($provider)),
        $provider.' metadata timezone mismatch',
        'timezone_denied'
    );
    $wrongAccount = accountMetadataBody($provider);
    if ($provider === 'google_ads') $wrongAccount['results'][0]['customer']['id'] = '9999999999';
    else $wrongAccount['account_id'] = '9999999999999';
    accountWireDenied(
        fn() => accountAdapter($provider, static fn() => accountHttp($wrongAccount))->account(accountReportPlan($provider)),
        $provider.' metadata account mismatch',
        'scope_denied'
    );

    $calls = 0;
    $badPlanAdapter = accountAdapter($provider, static function() use (&$calls, $provider): array {
        $calls++;
        return accountHttp(accountMetadataBody($provider));
    });
    $badPlan = accountReportPlan($provider);
    $badPlan['read_plan']['url'] .= '/other';
    accountWireDenied(fn() => $badPlanAdapter->account($badPlan), $provider.' metadata report plan tampering', 'request_denied');
    accountSame(0, $calls, $provider.' tampered metadata plan has no callback');
}

$googleMetadata = accountMetadataBody('google_ads');
foreach ([
    'empty account selection' => ['results' => []],
    'multiple account rows' => ['results' => [$googleMetadata['results'][0], $googleMetadata['results'][0]]],
    'unexpected account continuation' => $googleMetadata + ['nextPageToken' => 'next'],
] as $label => $body) {
    accountWireDenied(
        fn() => accountAdapter('google_ads', static fn() => accountHttp($body))->account(accountReportPlan('google_ads')),
        $label
    );
}
$metaMetadata = accountMetadataBody('meta_ads');
foreach ([
    'missing Meta account object' => [],
    'mismatched Meta resource ID' => array_replace($metaMetadata, ['id' => 'act_9999999999999']),
    'Meta account pagination' => $metaMetadata + ['paging' => ['next' => 'https://graph.facebook.com/next']],
] as $label => $body) {
    accountWireDenied(
        fn() => accountAdapter('meta_ads', static fn() => accountHttp($body))->account(accountReportPlan('meta_ads')),
        $label
    );
}
$calls = 0;
$unauthenticated = accountAdapter('google_ads', static function() use (&$calls): array {
    $calls++;
    return accountHttp('private-account-auth-error', 401);
});
accountWireDenied(fn() => accountRun($unauthenticated, 'google_ads'), 'metadata authentication failure', 'authentication_required');
accountSame(1, $calls, 'metadata authentication error cannot reach reports or retry');

// Account callbacks use the same immutable authorization lease as report pages.
foreach ([null, str_repeat('b', 64)] as $newLease) {
    $lease = str_repeat('a', 64);
    $httpCalls = 0;
    $adapter = accountAdapter('google_ads', static function() use (&$httpCalls, &$lease, $newLease): array {
        $httpCalls++;
        $lease = $newLease;
        return accountHttp(accountMetadataBody('google_ads'));
    });
    $authorize = static function() use (&$lease): ?string { return $lease; };
    $failure = accountConnectorDenied(fn() => accountRun($adapter, 'google_ads', $authorize), 'lease revoked or replaced during metadata');
    accountSame('Account consent revoked', $failure->getMessage(), 'metadata cannot renew an authorization lease');
    accountSame(1, $httpCalls, 'revoked or replaced lease has zero reporting calls');
}
$httpCalls = 0;
$authorizationCalls = 0;
$adapter = accountAdapter('google_ads', static function() use (&$httpCalls): array {
    $httpCalls++;
    return accountHttp(accountMetadataBody('google_ads'));
});
$authorize = static function() use (&$authorizationCalls): string {
    return ++$authorizationCalls === 1 ? str_repeat('a', 64) : str_repeat('b', 64);
};
$failure = accountConnectorDenied(fn() => accountRun($adapter, 'google_ads', $authorize), 'lease replaced before metadata callback');
accountSame('Account consent revoked', $failure->getMessage(), 'pre-metadata lease must stay identical');
accountSame(0, $httpCalls, 'lease replacement is not silently accepted before metadata');

$httpCalls = 0;
$checksAfterMetadata = 0;
$adapter = accountAdapter('google_ads', static function() use (&$httpCalls): array {
    $httpCalls++;
    return accountHttp($httpCalls === 1 ? accountMetadataBody('google_ads') : accountReportBody('google_ads', 1));
});
$authorize = static function() use (&$httpCalls, &$checksAfterMetadata): ?string {
    if ($httpCalls === 0) return str_repeat('a', 64);
    return ++$checksAfterMetadata === 1 ? str_repeat('a', 64) : null;
};
$failure = accountConnectorDenied(fn() => accountRun($adapter, 'google_ads', $authorize), 'revocation after metadata and before report');
accountSame('Account consent revoked', $failure->getMessage(), 'report needs the unchanged account-read lease');
accountSame(1, $httpCalls, 'post-metadata revocation has zero reporting calls');

// The optional callback is an untrusted boundary even without the wire adapter.
$reportPlan = accountReportPlan('google_ads');
$validMetadata = [
    'source_kind' => 'laboratory_fixture', 'provider' => 'google_ads',
    'api_version' => $reportPlan['read_plan']['api_version'], 'account_id' => accountId('google_ads'),
    'currency' => 'BRL', 'timezone' => 'America/Sao_Paulo', 'context_timezone' => 'America/Sao_Paulo',
];
$normalized = [
    'account_id' => accountId('google_ads'), 'campaign_id' => '42', 'currency' => 'BRL',
    'date' => '2026-11-01', 'spend' => '1000000', 'impressions' => '100', 'clicks' => '2',
];
$invalidMetadata = [
    'null metadata' => null,
    'official metadata' => array_replace($validMetadata, ['source_kind' => 'official']),
    'wrong provider with same account ID' => array_replace($validMetadata, ['provider' => 'meta_ads']),
    'wrong API version with same account ID' => array_replace($validMetadata, ['api_version' => 'v999']),
    'wrong metadata account' => array_replace($validMetadata, ['account_id' => '9999999999']),
    'invalid metadata currency' => array_replace($validMetadata, ['currency' => 'brl']),
    'mismatched metadata timezone' => array_replace($validMetadata, ['timezone' => 'UTC']),
    'mismatched context timezone' => array_replace($validMetadata, ['context_timezone' => 'UTC']),
    'unapproved metadata field' => $validMetadata + ['raw_response' => 'synthetic-private-response'],
];
$missingContext = $validMetadata;
unset($missingContext['context_timezone']);
$invalidMetadata['missing metadata context'] = $missingContext;
foreach ($invalidMetadata as $label => $metadata) {
    $reportCalls = 0;
    $connector = new Connector(
        static fn() => str_repeat('a', 64),
        static function() use (&$reportCalls, $normalized): array {
            $reportCalls++;
            return ['source_kind' => 'laboratory_fixture', 'rows' => [$normalized]];
        },
        static fn() => $metadata
    );
    $failure = accountConnectorDenied(
        fn() => $connector->read('company_alfa', 'google_ads', accountId('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo'),
        $label
    );
    accountSame('Provider account metadata denied', $failure->getMessage(), $label.' safe denial');
    accountSame(0, $reportCalls, $label.' stops before report callback');
}
$legacy = (new Connector(
    static fn() => str_repeat('a', 64),
    static fn() => ['source_kind' => 'laboratory_fixture', 'rows' => [$normalized]]
))->read('company_alfa', 'google_ads', accountId('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo');
accountSame(null, $legacy['account_metadata'], 'legacy connector does not invent account metadata');
accountSame(1, count($legacy['rows']), 'legacy injected report remains compatible');
accountSame(false, $legacy['billable'], 'legacy report remains unbillable');

echo "OK: $assertions account metadata, immutable consent lease and report coherence assertions; fixture callbacks only\n";
