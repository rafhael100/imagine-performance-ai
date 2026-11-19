<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsAccountMandateGate.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsBoundCredentialResolver.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadHttpTransport.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadWireAdapter.php';

use Imagine\Performance\Administration\AdministrativeAdsReadPlan as Plan;
use Imagine\Performance\Administration\AdministrativeAdsAccountMandateGate as MandateGate;
use Imagine\Performance\Administration\AdministrativeAdsBoundCredentialResolver as BoundGuard;
use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;
use Imagine\Performance\Administration\AdministrativeAdsReadHttpTransport as Transport;
use Imagine\Performance\Administration\AdministrativeAdsReadWireAdapter as Adapter;
use Imagine\Performance\Administration\AdministrativeAdsWireFailure as Failure;

$fixtureDirectory = $argv[1] ?? '';
if (!is_dir($fixtureDirectory) || !is_file($fixtureDirectory.'/ready')) {
    throw new RuntimeException('Synthetic TLS peer must be started by the isolated runner');
}
$assertions = 0;
$caseSequence = 0;
const SYNTHETIC_ACCESS = 'syntheticAccessToken_ABC123';
const SYNTHETIC_DEVELOPER = 'syntheticDeveloperToken_XYZ';

function httpCheck(bool $condition, string $label): void
{
    global $assertions;
    if (!$condition) throw new RuntimeException('Ads HTTPS assertion failed: '.$label);
    $assertions++;
}
function httpSame(mixed $expected, mixed $actual, string $label): void
{
    httpCheck($expected === $actual, $label);
}
function httpFailure(callable $call, string $reason, string $label): Failure
{
    try {
        $call();
    } catch (Failure $failure) {
        httpSame($reason, $failure->reason, $label.' reason');
        httpSame('Ads read failed: '.$reason, $failure->getMessage(), $label.' safe message');
        httpSame(null, $failure->getPrevious(), $label.' no raw exception chain');
        $trace = json_encode($failure->getTrace(), JSON_THROW_ON_ERROR).$failure->getTraceAsString();
        foreach ([SYNTHETIC_ACCESS, SYNTHETIC_DEVELOPER, 'syntheticPrivateBodyMarker'] as $secret) {
            httpCheck(!str_contains($trace, $secret), $label.' trace hides synthetic secret');
        }
        return $failure;
    }
    throw new RuntimeException('Ads HTTPS accepted invalid case: '.$label);
}
function httpRuntimeDenied(callable $call, string $label): void
{
    try { $call(); }
    catch (RuntimeException) { httpCheck(true, $label); return; }
    throw new RuntimeException('Ads HTTPS boundary accepted: '.$label);
}
function httpAccount(string $provider): string
{
    return $provider === 'google_ads' ? '1234567890' : '1234567890123';
}
function httpPlan(string $provider, bool $account = false): array
{
    return $account
        ? Plan::prepareAccount($provider, httpAccount($provider))
        : Plan::prepare($provider, httpAccount($provider), '2026-11-01', '2026-11-30');
}
function httpCredential(string $provider, string $lease): array
{
    $credential = [
        'company_id' => 'company_alfa', 'provider' => $provider, 'account_id' => httpAccount($provider),
        'lease' => $lease, 'credential_ref' => 'synthetic-ci-reference', 'expires_at' => time() + 60,
        'access_token' => SYNTHETIC_ACCESS,
    ];
    if ($provider === 'google_ads') {
        $credential['login_customer_id'] = '1234567890';
    }
    return $credential;
}
function httpTransport(
    string $provider,
    #[\SensitiveParameter] ?Closure $authorize = null,
    #[\SensitiveParameter] ?Closure $resolver = null,
    int $connect = 500,
    int $timeout = 2000,
    int $readBudgetMs = 60000
): Transport {
    $authorize ??= static fn() => str_repeat('a', 64);
    $resolver ??= static fn(string $company, string $givenProvider, string $account, string $lease) => httpCredential($givenProvider, $lease);
    return new Transport(
        'company_alfa', $provider, httpAccount($provider), '2026-11-01', '2026-11-30',
        $authorize, $resolver, $connect, $timeout, $readBudgetMs
    );
}
function httpAdapter(string $provider, Transport $transport): Adapter
{
    return new Adapter($provider, httpAccount($provider), '2026-11-01', '2026-11-30', 'America/Sao_Paulo', $transport);
}
function httpRead(string $provider, Transport $transport, ?Closure $authorize = null): array
{
    $authorize ??= static fn() => str_repeat('a', 64);
    return httpAdapter($provider, $transport)->connector($authorize)->read(
        'company_alfa', $provider, httpAccount($provider), '2026-11-01', '2026-11-30', 'America/Sao_Paulo'
    );
}
function httpMetadata(string $provider): array
{
    return $provider === 'google_ads'
        ? ['results' => [['customer' => ['id' => httpAccount($provider), 'currencyCode' => 'BRL', 'timeZone' => 'America/Sao_Paulo']]]]
        : ['id' => 'act_'.httpAccount($provider), 'account_id' => httpAccount($provider), 'currency' => 'BRL', 'timezone_name' => 'America/Sao_Paulo'];
}
function httpReport(string $provider, int $day, ?string $cursor = null): array
{
    $date = sprintf('2026-11-%02d', $day);
    if ($provider === 'google_ads') {
        $body = ['results' => [[
            'customer' => ['id' => httpAccount($provider), 'currencyCode' => 'BRL', 'timeZone' => 'America/Sao_Paulo'],
            'campaign' => ['id' => '42'], 'segments' => ['date' => $date],
            'metrics' => ['costMicros' => '9007199254740993', 'impressions' => '100', 'clicks' => '2'],
        ]]];
        if ($cursor !== null) $body['nextPageToken'] = $cursor;
        return $body;
    }
    $body = ['data' => [[
        'account_id' => httpAccount($provider), 'account_currency' => 'BRL', 'campaign_id' => '42',
        'date_start' => $date, 'date_stop' => $date, 'spend' => '123.450001',
        'impressions' => '100', 'clicks' => '2',
    ]]];
    if ($cursor !== null) $body['paging'] = [
        'cursors' => ['after' => $cursor],
        'next' => httpPlan($provider)['read_plan']['url'].'?after='.rawurlencode($cursor).'&access_token=synthetic-not-forwarded',
    ];
    return $body;
}
function httpResponse(array|string $body, int $status = 200, array $options = []): array
{
    return ['status' => $status, 'body' => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR)] + $options;
}
function httpExpectation(string $provider, bool $account = false, ?string $cursor = null): array
{
    $request = Plan::wireRequest(httpPlan($provider, $account), $cursor);
    $request['headers']['Authorization'] = 'Bearer '.SYNTHETIC_ACCESS;
    $request['headers']['Accept-Encoding'] = 'identity';
    if ($provider === 'google_ads') {
        $request['headers']['login-customer-id'] = '1234567890';
        $request['absent_headers'] = ['developer-token'];
    } else {
        $request['absent_headers'] = ['developer-token', 'login-customer-id', 'Content-Type'];
    }
    return $request;
}
function httpCase(string $label, #[\SensitiveParameter] array $responses = [], #[\SensitiveParameter] array $expected = [], string $tls = 'trusted'): string
{
    global $fixtureDirectory, $caseSequence;
    $id = 'case-'.(++$caseSequence).'-'.$label;
    $config = ['case_id' => $id, 'tls_mode' => $tls, 'responses' => $responses, 'expected_requests' => $expected];
    file_put_contents($fixtureDirectory.'/control.next', json_encode($config, JSON_THROW_ON_ERROR));
    rename($fixtureDirectory.'/control.next', $fixtureDirectory.'/control.json');
    return $id;
}
function httpState(string $id): array
{
    global $fixtureDirectory;
    $path = $fixtureDirectory.'/state.json';
    $all = is_file($path) ? json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR) : [];
    return $all[$id] ?? ['requests' => 0, 'tls_connections' => 0, 'expectations_ok' => true, 'redirect_hits' => 0];
}
function httpPeer(string $id, int $requests): void
{
    $state = httpState($id);
    httpSame($requests, $state['requests'], $id.' exact request count');
    httpSame(true, $state['expectations_ok'], $id.' peer privately verified host, path, payload and credential headers');
    httpSame(0, $state['redirect_hits'], $id.' no redirect destination visited');
}

httpCheck(extension_loaded('curl'), 'native PHP cURL available in isolated image');
httpSame('0', ini_get('zend.exception_ignore_args'), 'exception argument retention enabled for redaction proof');
foreach (['google_ads', 'meta_ads'] as $provider) {
    $case = httpCase($provider.'-account-and-pages', [
        httpResponse(httpMetadata($provider)),
        httpResponse(httpReport($provider, 1, 'page+/=2')),
        httpResponse(httpReport($provider, 2)),
    ], [httpExpectation($provider, true), httpExpectation($provider), httpExpectation($provider, false, 'page+/=2')]);
    $authorizationContexts = [];
    $resolveContexts = [];
    $authorize = static function(string $company, string $givenProvider, string $account) use (&$authorizationContexts): string {
        $authorizationContexts[] = [$company, $givenProvider, $account];
        return str_repeat('a', 64);
    };
    $resolve = static function(string $company, string $givenProvider, string $account, string $lease) use (&$resolveContexts): array {
        $resolveContexts[] = [$company, $givenProvider, $account, $lease];
        return httpCredential($givenProvider, $lease);
    };
    $transport = httpTransport($provider, $authorize, $resolve);
    httpSame([
        'company_id' => 'company_alfa', 'provider' => $provider, 'account_id' => httpAccount($provider),
        'from' => '2026-11-01', 'until' => '2026-11-30',
    ], $transport->context(), $provider.' fixed context excludes credentials');
    $result = httpRead($provider, $transport, $authorize);
    httpPeer($case, 3);
    httpSame(2, count($result['rows']), $provider.' account and two native HTTP pages composed');
    httpSame('unverified', $result['status'], $provider.' real HTTP is not official evidence');
    httpSame('http_unverified', $result['source_kind'], $provider.' HTTP origin preserved');
    httpSame(true, $result['http_transport_used'], $provider.' native transport observation recorded');
    httpSame('http_unverified', $result['account_metadata']['source_kind'], $provider.' account origin preserved');
    httpSame(null, $result['official_evidence'], $provider.' official evidence remains absent');
    httpSame(false, $result['billable'], $provider.' HTTPS does not authorize billing');
    httpSame(false, $result['external_operations_enabled'], $provider.' HTTPS does not establish product grant');
    httpSame('BRL', $result['currency'], $provider.' account currency survives HTTP');
    httpSame($provider === 'google_ads' ? '9007199254.740993' : '123.450001', $result['rows'][0]['spend'], $provider.' HTTP preserves exact money');
    foreach ($authorizationContexts as $context) {
        httpSame(['company_alfa', $provider, httpAccount($provider)], $context, $provider.' same authorization scope throughout');
    }
    httpSame(3, count($resolveContexts), $provider.' credentials resolved separately for each actual request');
    foreach ($resolveContexts as $context) {
        httpSame(['company_alfa', $provider, httpAccount($provider), str_repeat('a', 64)], $context, $provider.' resolver bound to current lease');
    }

    $case = httpCase($provider.'-empty', [
        httpResponse(httpMetadata($provider)),
        httpResponse($provider === 'google_ads' ? ['results' => []] : ['data' => []]),
    ], [httpExpectation($provider, true), httpExpectation($provider)]);
    $empty = httpRead($provider, httpTransport($provider));
    httpPeer($case, 2);
    httpSame([], $empty['rows'], $provider.' empty HTTP result does not fabricate zero');
    httpSame('BRL', $empty['currency'], $provider.' empty HTTP result retains account currency');
    httpSame('unverified', $empty['status'], $provider.' empty HTTP result remains unverified');
    httpSame(false, $empty['billable'], $provider.' empty HTTP result cannot bill');
}

$accountRequest = Plan::wireRequest(httpPlan('google_ads', true));
$case = httpCase('raw-envelope', [httpResponse(httpMetadata('google_ads'))], [httpExpectation('google_ads', true)]);
$raw = httpTransport('google_ads')($accountRequest);
$keys = array_keys($raw);
sort($keys, SORT_STRING);
httpSame(['body', 'content_type', 'redirected', 'source_kind', 'status'], $keys, 'native response has exact envelope');
httpSame('http_unverified', $raw['source_kind'], 'raw native response origin');
httpSame(false, $raw['redirected'], 'raw native response did not redirect');
httpPeer($case, 1);

// Sunset developer tokens may remain in a legacy server snapshot, but are
// discarded and never sent. New snapshots do not need the obsolete field.
foreach ([SYNTHETIC_DEVELOPER, null] as $legacyDeveloperToken) {
    $case = httpCase('legacy-developer-token-discarded', [httpResponse(httpMetadata('google_ads'))], [httpExpectation('google_ads', true)]);
    $credential = httpCredential('google_ads', str_repeat('a', 64));
    $credential['developer_token'] = $legacyDeveloperToken;
    $raw = httpTransport('google_ads', null, static fn() => $credential)($accountRequest);
    httpSame(200, $raw['status'], 'safe or null legacy developer token does not block account read');
    httpSame('http_unverified', $raw['source_kind'], 'legacy field does not change HTTP provenance');
    httpPeer($case, 1);
}
$directExpectation = httpExpectation('google_ads', true);
unset($directExpectation['headers']['login-customer-id']);
$directExpectation['absent_headers'][] = 'login-customer-id';
$case = httpCase('google-direct-account', [httpResponse(httpMetadata('google_ads'))], [$directExpectation]);
$directCredential = httpCredential('google_ads', str_repeat('a', 64));
$directCredential['login_customer_id'] = null;
$raw = httpTransport('google_ads', null, static fn() => $directCredential)($accountRequest);
httpSame(200, $raw['status'], 'direct Google account without manager header succeeds');
httpSame('http_unverified', $raw['source_kind'], 'direct Google account still has unverified provenance');
httpPeer($case, 1);

// Mutated requests are rejected before authorization, credential resolution or I/O.
$base = Plan::wireRequest(httpPlan('google_ads'));
$mutations = [
    static function(array $r): array { $r['url'] = 'https://graph.facebook.com/redirect-target'; return $r; },
    static function(array $r): array { $r['url'] .= '?access_token='.SYNTHETIC_ACCESS; return $r; },
    static function(array $r): array { $r['method'] = 'DELETE'; return $r; },
    static function(array $r): array { $r['headers']['Authorization'] = 'Bearer '.SYNTHETIC_ACCESS; return $r; },
    static function(array $r): array { $r['json']['query'] .= ' LIMIT 1'; return $r; },
    static function(array $r): array { $r['follow_redirects'] = true; return $r; },
    static function(array $r): array { $r['max_response_bytes']++; return $r; },
    static function(array $r): array { $r['proxy'] = 'http://127.0.0.1:9'; return $r; },
];
foreach ($mutations as $index => $mutate) {
    $case = httpCase('request-mutation-'.$index);
    $authorizeCalls = 0;
    $authorize = static function() use (&$authorizeCalls): string { $authorizeCalls++; return str_repeat('a', 64); };
    httpFailure(fn() => httpTransport('google_ads', $authorize)($mutate($base)), 'request_denied', 'request mutation');
    httpSame(0, $authorizeCalls, 'invalid request has no authority lookup');
    httpPeer($case, 0);
}
foreach ([[99, 100], [100, 99], [2000, 1000], [100, 10001]] as [$connect, $total]) {
    httpFailure(fn() => httpTransport('google_ads', null, null, $connect, $total), 'request_denied', 'invalid timeout bounds');
}
$case = httpCase('missing-resolver');
$missing = new Transport('company_alfa', 'google_ads', httpAccount('google_ads'), '2026-11-01', '2026-11-30', static fn() => str_repeat('a', 64));
httpFailure(fn() => $missing($accountRequest), 'credentials_unavailable', 'no default credential source');
httpPeer($case, 0);
foreach ([static fn() => null, static fn() => throw new RuntimeException(SYNTHETIC_ACCESS)] as $resolve) {
    $case = httpCase('unavailable-resolver');
    httpFailure(fn() => httpTransport('google_ads', null, $resolve)($accountRequest), 'credentials_unavailable', 'missing or failed credential resolution');
    httpPeer($case, 0);
}
$validCredential = httpCredential('google_ads', str_repeat('a', 64));
$invalidCredentials = [
    'company scope' => array_replace($validCredential, ['company_id' => 'company_beta']),
    'provider scope' => array_replace($validCredential, ['provider' => 'meta_ads']),
    'account scope' => array_replace($validCredential, ['account_id' => '9999999999']),
    'lease scope' => array_replace($validCredential, ['lease' => str_repeat('b', 64)]),
    'bad reference' => array_replace($validCredential, ['credential_ref' => '../unsafe']),
    'token newline' => array_replace($validCredential, ['access_token' => SYNTHETIC_ACCESS."\r\nInjected: x"]),
    'token size' => array_replace($validCredential, ['access_token' => str_repeat('a', 4097)]),
    'developer newline' => array_replace($validCredential, ['developer_token' => SYNTHETIC_DEVELOPER."\nInjected"]),
    'manager identity' => array_replace($validCredential, ['login_customer_id' => '12345-other']),
    'expiry type' => array_replace($validCredential, ['expires_at' => 'tomorrow']),
    'extra credential field' => $validCredential + ['endpoint' => 'https://graph.facebook.com/'],
];
foreach ($invalidCredentials as $label => $credential) {
    $case = httpCase('credential-refused');
    httpFailure(fn() => httpTransport('google_ads', null, static fn() => $credential)($accountRequest), 'credentials_denied', $label);
    httpPeer($case, 0);
}
foreach ([time() - 1, time() + 1] as $expiry) {
    $case = httpCase('credential-expiry');
    $credential = array_replace($validCredential, ['expires_at' => $expiry]);
    httpFailure(fn() => httpTransport('google_ads', null, static fn() => $credential)($accountRequest), 'credentials_expired', 'expired or insufficient credential lifetime');
    httpPeer($case, 0);
}
$case = httpCase('authorization-denied');
httpFailure(fn() => httpTransport('google_ads', static fn() => null)($accountRequest), 'authorization_denied', 'no account authorization');
httpPeer($case, 0);
$case = httpCase('authorization-after-resolver');
$lease = str_repeat('a', 64);
$authorize = static function() use (&$lease): string { return $lease; };
$resolve = static function(string $company, string $provider, string $account, string $originalLease) use (&$lease): array {
    $lease = str_repeat('b', 64);
    return httpCredential($provider, $originalLease);
};
httpFailure(fn() => httpTransport('google_ads', $authorize, $resolve)($accountRequest), 'authorization_revoked', 'lease replaced while resolving credentials');
httpPeer($case, 0);
$case = httpCase('authorization-before-wire');
$authorizationCalls = 0;
$authorize = static function() use (&$authorizationCalls): ?string {
    return ++$authorizationCalls < 3 ? str_repeat('a', 64) : null;
};
httpFailure(fn() => httpTransport('google_ads', $authorize)($accountRequest), 'authorization_revoked', 'lease revoked immediately before wire');
httpPeer($case, 0);
$case = httpCase('authorization-after-wire', [httpResponse(httpMetadata('google_ads'))], [httpExpectation('google_ads', true)]);
$authorize = static fn() => httpState($case)['requests'] === 0 ? str_repeat('a', 64) : null;
httpFailure(fn() => httpTransport('google_ads', $authorize)($accountRequest), 'authorization_revoked', 'lease revoked before response release');
httpPeer($case, 1);

// These failures exercise the actual cURL callbacks and TLS handshake.
foreach ([
    ['length-limit', ['body_mode' => 'oversize-length'], 'response_limit'],
    ['chunked-limit', ['body_mode' => 'oversize-chunked'], 'response_limit'],
    ['header-limit', ['header_padding_bytes' => 32769], 'response_headers_denied'],
    ['unexpected-encoding', ['encoding' => 'gzip'], 'response_encoding_denied'],
    ['slow-headers', ['delay_headers_ms' => 1200], 'transport_timeout'],
    ['slow-body', ['delay_body_ms' => 1200], 'transport_timeout'],
] as [$label, $options, $reason]) {
    $case = httpCase($label, [httpResponse(httpMetadata('google_ads'), 200, $options)], [httpExpectation('google_ads', true)]);
    httpFailure(fn() => httpTransport('google_ads', null, null, 200, 300)($accountRequest), $reason, $label);
    httpPeer($case, 1);
}
$case = httpCase('redirect-refused', [
    httpResponse('{}', 302, ['location' => 'https://graph.facebook.com/redirect-target']),
    httpResponse('{}'),
], [httpExpectation('google_ads', true)]);
httpFailure(fn() => httpTransport('google_ads')($accountRequest), 'redirect_denied', 'HTTP redirect cannot forward credentials');
httpPeer($case, 1);
foreach (['wrong-host', 'untrusted'] as $mode) {
    $case = httpCase('tls-'.$mode, [httpResponse(httpMetadata('google_ads'))], [httpExpectation('google_ads', true)], $mode);
    httpFailure(fn() => httpTransport('google_ads')($accountRequest), 'tls_failed', 'TLS '.$mode);
    httpPeer($case, 0);
    httpSame(1, httpState($case)['tls_connections'], $mode.' handshake attempted once without HTTP');
}
foreach ([401 => 'authentication_required', 403 => 'permission_denied', 429 => 'rate_limited', 503 => 'upstream_unavailable'] as $status => $reason) {
    $case = httpCase('http-status-'.$status, [httpResponse('syntheticPrivateBodyMarker', $status)], [httpExpectation('google_ads', true)]);
    httpFailure(fn() => httpRead('google_ads', httpTransport('google_ads')), $reason, 'HTTP status '.$status.' through adapter');
    httpPeer($case, 1);
}
$case = httpCase('malformed-json', [httpResponse('{"syntheticPrivateBodyMarker":')], [httpExpectation('google_ads', true)]);
httpFailure(fn() => httpRead('google_ads', httpTransport('google_ads')), 'invalid_json', 'invalid HTTP JSON withheld');
httpPeer($case, 1);

// A fixture closure cannot claim that the final native HTTP transport ran.
$fakeNativeEnvelope = static fn() => [
    'source_kind' => 'http_unverified', 'status' => 200,
    'content_type' => 'application/json', 'redirected' => false,
    'body' => json_encode(httpMetadata('google_ads'), JSON_THROW_ON_ERROR),
];
$fixtureAdapter = new Adapter('google_ads', httpAccount('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo', $fakeNativeEnvelope);
httpFailure(fn() => $fixtureAdapter->account(httpPlan('google_ads')), 'response_denied', 'fixture callback cannot spoof HTTP envelope');
httpRuntimeDenied(
    fn() => Connector::fromHttpAdapter(static fn() => str_repeat('a', 64), $fixtureAdapter),
    'typed HTTP factory refuses fixture adapter'
);
$spoofedPage = static fn() => ['source_kind' => 'http_unverified', 'rows' => []];
httpRuntimeDenied(
    fn() => (new Connector(static fn() => str_repeat('a', 64), $spoofedPage))->read(
        'company_alfa', 'google_ads', httpAccount('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo'
    ),
    'legacy connector cannot claim native HTTP from labelled closure'
);
$case = httpCase('legacy-extra-argument-spoof');
$forgedMetadata = [
    'source_kind' => 'http_unverified', 'provider' => 'google_ads',
    'api_version' => httpPlan('google_ads')['read_plan']['api_version'],
    'account_id' => httpAccount('google_ads'), 'currency' => 'BRL',
    'timezone' => 'America/Sao_Paulo', 'context_timezone' => 'America/Sao_Paulo',
];
// PHP can ignore excess arguments on user-defined functions. Even the former
// five-argument call shape must not turn closures into observed native HTTP.
httpRuntimeDenied(
    fn() => (new Connector(
        static fn() => str_repeat('a', 64), $spoofedPage,
        static fn() => $forgedMetadata, 'http_unverified', httpTransport('google_ads')->context()
    ))->read(
        'company_alfa', 'google_ads', httpAccount('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo'
    ),
    'extra legacy arguments cannot promote a closure to HTTP origin'
);
httpPeer($case, 0);
$case = httpCase('context-company-mismatch');
$bound = httpAdapter('google_ads', httpTransport('google_ads'))->connector(static fn() => str_repeat('a', 64));
httpRuntimeDenied(
    fn() => $bound->read('company_beta', 'google_ads', httpAccount('google_ads'), '2026-11-01', '2026-11-30', 'America/Sao_Paulo'),
    'native connector company binding cannot be changed'
);
httpPeer($case, 0);


/** Same lease and same ephemeral credential must cover account and every page. */
foreach (['google_ads','meta_ads'] as $provider) {
    foreach (['reference','token'] as $kind) {
        $case = httpCase($provider.'-credential-switch-'.$kind,
            [httpResponse(httpMetadata($provider))], [httpExpectation($provider,true)]);
        $count = 0;
        $resolver = static function(string $company,string $p,string $account,string $lease) use (&$count,$kind): array {
            $c = httpCredential($p,$lease);
            if (++$count === 2) {
                if ($kind === 'reference') $c['credential_ref']='changed-reference';
                else $c['access_token']='changedSyntheticBearer_321';
            }
            return $c;
        };
        httpFailure(fn()=>httpRead($provider,httpTransport($provider,null,$resolver)),
            'credential_changed', $provider.' account/report '.$kind.' switch');
        httpSame(2,$count,$provider.' credential resolver called for both requests');
        httpPeer($case,1);
    }
    $case = httpCase($provider.'-expiry-extended', [
        httpResponse(httpMetadata($provider)),httpResponse(httpReport($provider,1))
    ],[httpExpectation($provider,true),httpExpectation($provider)]);
    $n = 0;
    $resolver = static function(string $company,string $p,string $account,string $lease) use (&$n):array {
        $c=httpCredential($p,$lease);
        $c['expires_at']=time()+(++$n===1?60:120);
        return $c;
    };
    $r=httpRead($provider,httpTransport($provider,null,$resolver));
    httpPeer($case,2);
    httpSame(2,$n,$provider.' expiry extension permitted for same credential');
    httpSame('unverified',$r['status'],$provider.' HTTP still not official');
}

$case=httpCase('google-manager-changed',
    [httpResponse(httpMetadata('google_ads'))],[httpExpectation('google_ads',true)]);
$n=0;
$resolver=static function(string $company,string $p,string $account,string $lease)use(&$n):array {
    $c=httpCredential($p,$lease);
    if(++$n===2)$c['login_customer_id']=null;
    return $c;
};
httpFailure(fn()=>httpRead('google_ads',httpTransport('google_ads',null,$resolver)),
    'credential_changed','Google manager context changed');
httpPeer($case,1);

$case=httpCase('direct-authorization-lease-changed',
    [httpResponse(httpMetadata('google_ads'))],[httpExpectation('google_ads',true)]);
$leaseNow=str_repeat('a',64);
$auth=static function()use(&$leaseNow):string{return $leaseNow;};
$transport=httpTransport('google_ads',$auth);
$transport($accountRequest);
$leaseNow=str_repeat('b',64);
httpFailure(fn()=>$transport(Plan::wireRequest(httpPlan('google_ads'))),
    'authorization_revoked','direct client cannot switch original lease');
httpPeer($case,1);

$case=httpCase('deadline-in-credential-callback');
$slow=static function(string $company,string $p,string $account,string $lease):array {
    usleep(150000);return httpCredential($p,$lease);
};
httpFailure(fn()=>httpTransport('google_ads',null,$slow,500,2000,100)($accountRequest),
    'read_deadline_exceeded','slow callback counts toward total budget');
httpPeer($case,0);

$case=httpCase('deadline-between-pages',
    [httpResponse(httpMetadata('google_ads'))],[httpExpectation('google_ads',true)]);
$transport=httpTransport('google_ads',null,null,500,2000,100);
$transport($accountRequest);
usleep(150000);
httpFailure(fn()=>$transport(Plan::wireRequest(httpPlan('google_ads'))),
    'read_deadline_exceeded','subsequent page cannot reset the budget');
httpPeer($case,1);
foreach([0,99,120001] as $badBudget) {
    httpFailure(fn()=>httpTransport('google_ads',null,null,500,2000,$badBudget),
        'request_denied','invalid read budget');
}


/** Verify two independent server-owned scopes actually gate HTTPS operations. */
$principal = 'imagine_portal_s2s';
$service = [
    'version' => MandateGate::SERVICE_VERSION,
    'principal' => $principal, 'company_id' => 'company_alfa',
    'operation' => MandateGate::OPERATION, 'active' => true,
    'revision' => 4, 'expires_at' => time() + 120, 'revoked_at' => null,
];
$mandate = [
    'version' => MandateGate::VERSION,
    'company_id' => 'company_alfa', 'provider' => 'google_ads',
    'account_id' => httpAccount('google_ads'), 'purpose' => MandateGate::PURPOSE,
    'operation' => MandateGate::OPERATION,
    'consent_reference' => 'consent-20261119-approved',
    'credential_ref' => 'vault-ads-alpha-001', 'status' => 'active',
    'revision' => 9, 'expires_at' => time() + 120, 'revoked_at' => null,
];
$serviceSource = static function(string $p, string $c, string $o) use (&$service): array {
    return $service;
};
$accountSource = static function(string $c, string $p, string $a, string $o) use (&$mandate): array {
    return $mandate;
};
$gate = new MandateGate($principal, $serviceSource, $accountSource);
$authority = static fn(string $c, string $p, string $a): ?string => $gate->lease($c, $p, $a);
// An independent vault-like lab callback receives the mandate-selected
// reference. The guard refuses a different returned reference/scope and
// reconfirms the lease after the secret read, before the HTTP transfer.
$secretLab = static function(string $ref, string $c, string $p, string $a, string $lease): array {
    $token = httpCredential($p, $lease);
    $token['credential_ref'] = $ref;
    return $token;
};
$credentialGuard = new BoundGuard($gate, $secretLab);
$readCredential = static fn(string $c, string $p, string $a, string $lease): ?array
    => $credentialGuard($c, $p, $a, $lease);
$case = httpCase('account-mandate-https', [
    httpResponse(httpMetadata('google_ads')),
    httpResponse(httpReport('google_ads', 1)),
], [httpExpectation('google_ads', true), httpExpectation('google_ads')]);
$result = httpRead('google_ads', httpTransport('google_ads', $authority, $readCredential), $authority);
httpPeer($case, 2);
httpSame('unverified', $result['status'], 'mandate passed HTTPS remains unverified');
httpSame(false, $result['billable'], 'mandate passed HTTPS cannot be billed');
httpSame(null, $result['official_evidence'], 'mandate never makes official evidence');

// A secret callback cannot replace the current opaque ref with one belonging
// to another provider/company while keeping the same signed-like lease digest.
$wrongRefGuard = new BoundGuard($gate, static function(
    string $ref, string $c, string $p, string $a, string $lease
): array {
    $token = httpCredential($p, $lease);
    $token['credential_ref'] = 'vault-other-tenant-001';
    return $token;
});
$wrongRef = static fn(string $c, string $p, string $a, string $lease): ?array
    => $wrongRefGuard($c, $p, $a, $lease);
$case = httpCase('mandate-wrong-secret-reference');
httpFailure(
    fn() => httpRead('google_ads', httpTransport('google_ads', $authority, $wrongRef), $authority),
    'credentials_unavailable', 'wrong vault reference denied before HTTP'
);
httpPeer($case, 0);

// A current company-wide reports.read delegation is NOT an account mandate.
$mandate['status'] = 'revoked';
$case = httpCase('mandate-denied-before-http');
httpRuntimeDenied(
    fn() => httpRead('google_ads', httpTransport('google_ads', $authority, $readCredential), $authority),
    'revoked account mandate denied before HTTP even with S2S reports.read'
);
httpPeer($case, 0);
$mandate['status'] = 'active';
$mandate['company_id'] = 'company_beta';
$case = httpCase('mandate-cross-tenant');
httpRuntimeDenied(
    fn() => httpRead('google_ads', httpTransport('google_ads', $authority, $readCredential), $authority),
    'account mandate for another company must not authorize same account id'
);
httpPeer($case, 0);
$mandate['company_id'] = 'company_alfa';
$service['active'] = false;
$case = httpCase('mandate-service-revoked');
httpRuntimeDenied(
    fn() => httpRead('google_ads', httpTransport('google_ads', $authority, $readCredential), $authority),
    'current account mandate cannot replace revoked S2S delegation'
);
httpPeer($case, 0);
$service['active'] = true;

// Rotation during metadata-to-report transition: the resolver sees the update
// and the original lease is rechecked before report bytes are transferred.
$case = httpCase('mandate-rotates-between-pages', [
    httpResponse(httpMetadata('google_ads')),
], [httpExpectation('google_ads', true)]);
$resolveCalls = 0;
$rotatingResolver = static function(string $c, string $p, string $a, string $lease)
    use (&$resolveCalls, &$mandate, $readCredential): ?array {
    if (++$resolveCalls === 2) $mandate['revision']++;
    return $readCredential($c, $p, $a, $lease);
};
httpFailure(
    fn() => httpRead('google_ads', httpTransport('google_ads', $authority, $rotatingResolver), $authority),
    'authorization_revoked', 'mandate revision change between pages prevents second transfer'
);
httpSame(2, $resolveCalls, 'mandate resolver called before second transfer');
httpPeer($case, 1);

echo "OK: $assertions native HTTPS, TLS, credential custody, consent, bounded I/O and origin assertions; synthetic loopback peers only\n";
