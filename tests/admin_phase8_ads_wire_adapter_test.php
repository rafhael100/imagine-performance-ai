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
use Imagine\Performance\Commercial\ExactJsonNumberLexicalParser as Json;

$assertions = 0;
function wireCheck(bool $condition, string $label): void
{
    global $assertions;
    if (!$condition) {
        throw new RuntimeException('Ads wire assertion failed: '.$label);
    }
    $assertions++;
}
function wireSame(mixed $expected, mixed $actual, string $label): void
{
    wireCheck($expected === $actual, $label);
}
function wireKeys(array $expected, array $actual, string $label): void
{
    $keys = array_keys($actual);
    sort($keys, SORT_STRING);
    sort($expected, SORT_STRING);
    wireSame($expected, $keys, $label);
}
function wireFailure(callable $call, string $reason, string $label, ?int $status = null): Failure
{
    try {
        $call();
    } catch (Failure $failure) {
        wireSame($reason, $failure->reason, $label.' reason');
        wireSame('Ads read failed: '.$reason, $failure->getMessage(), $label.' safe message');
        wireSame(null, $failure->getPrevious(), $label.' no raw exception chain');
        if ($status !== null) {
            wireSame($status, $failure->httpStatus, $label.' HTTP status');
        }
        return $failure;
    }
    throw new RuntimeException('Ads wire accepted invalid input: '.$label);
}
function wireRuntimeFailure(callable $call, string $label): RuntimeException
{
    try {
        $call();
    } catch (RuntimeException $failure) {
        wireCheck(true, $label);
        return $failure;
    }
    throw new RuntimeException('Ads connector accepted invalid input: '.$label);
}
function wireHttp(array|string $body, int $status = 200): array
{
    return [
        'source_kind' => 'laboratory_fixture',
        'status' => $status,
        'content_type' => 'application/json; charset=UTF-8',
        'redirected' => false,
        'body' => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
    ];
}
function wireAdapter(string $provider, Closure $http): Adapter
{
    return new Adapter(
        $provider,
        $provider === 'google_ads' ? '1234567890' : '1234567890123',
        '2026-11-01',
        '2026-11-30',
        'America/Sao_Paulo',
        $http
    );
}
function wireRead(Adapter $adapter, string $provider, ?Closure $authorize = null): array
{
    $authorize ??= static fn() => str_repeat('a', 64);
    return (new Connector($authorize, Closure::fromCallable($adapter)))->read(
        'company_alfa',
        $provider,
        $provider === 'google_ads' ? '1234567890' : '1234567890123',
        '2026-11-01',
        '2026-11-30',
        'America/Sao_Paulo'
    );
}

$googlePlan = Plan::prepare('google_ads', '1234567890', '2026-11-01', '2026-11-30');
$metaPlan = Plan::prepare('meta_ads', '1234567890123', '2026-11-01', '2026-11-30');
$googleRow = [
    'customer' => [
        'id' => '1234567890',
        'currencyCode' => 'BRL',
        'timeZone' => 'America/Sao_Paulo',
    ],
    'campaign' => ['id' => '42'],
    'segments' => ['date' => '2026-11-01'],
    'metrics' => ['costMicros' => '9007199254740993', 'impressions' => '1000', 'clicks' => '0'],
];
$metaRow = [
    'account_id' => '1234567890123',
    'account_currency' => 'BRL',
    'campaign_id' => '42',
    'date_start' => '2026-11-01',
    'date_stop' => '2026-11-01',
    'spend' => '123.450001',
    'impressions' => '1000',
    'clicks' => '0',
];

// Real composition under test: provider-shaped raw JSON -> adapter -> existing
// consent-aware connector. The callback is an in-memory fixture, not HTTP I/O.
$requests = [];
$googleSecond = $googleRow;
$googleSecond['segments']['date'] = '2026-11-02';
$googleSecond['metrics'] = ['costMicros' => '0'];
$googleToken = 'opaque+/=token';
$google = wireAdapter('google_ads', static function(array $request) use (
    &$requests, $googleRow, $googleSecond, $googleToken
): array {
    $requests[] = $request;
    return count($requests) === 1
        ? wireHttp([
            'results' => [$googleRow],
            'nextPageToken' => $googleToken,
            'metricAttributes' => [['name' => 'metrics.impressions', 'attributes' => []]],
        ])
        : wireHttp(['results' => [$googleSecond], 'nextPageToken' => '']);
});
$result = wireRead($google, 'google_ads');
wireSame(2, count($requests), 'Google two pages');
wireSame('9007199254.740993', $result['rows'][0]['spend'], 'Google micros exact above binary integer precision');
wireSame('0.000000', $result['rows'][1]['spend'], 'reported zero is preserved');
wireSame(null, $result['rows'][1]['clicks'], 'absent metric remains unknown');
wireSame(true, $result['complete'], 'Google final token ends pagination');
wireKeys(
    ['account_id', 'campaign_id', 'currency', 'date', 'spend', 'impressions', 'clicks'],
    $result['rows'][0],
    'Google metric metadata does not add normalized row fields'
);
wireCheck(!array_key_exists('metricAttributes', $result), 'Google metric metadata absent from result envelope');
wireKeys(
    ['method', 'url', 'headers', 'json', 'follow_redirects', 'max_response_bytes'],
    $requests[0],
    'Google request has only supported fields'
);
wireSame('POST', $requests[0]['method'], 'Google read-only search method');
wireSame($googlePlan['read_plan']['url'], $requests[0]['url'], 'Google fixed request endpoint');
wireSame(['Accept' => 'application/json', 'Content-Type' => 'application/json'], $requests[0]['headers'], 'Google credential-free headers');
wireSame($googlePlan['read_plan']['json'], $requests[0]['json'], 'Google first request canonical query');
wireSame($googleToken, $requests[1]['json']['pageToken'], 'opaque Google token preserved');
wireSame($requests[0]['json']['query'], $requests[1]['json']['query'], 'Google query unchanged across pages');
wireSame($requests[0]['url'], $requests[1]['url'], 'Google URL unchanged across pages');
wireSame(false, $requests[0]['follow_redirects'], 'Google redirects disabled');
wireSame(1048576, $requests[0]['max_response_bytes'], 'Google response bound supplied to callback');
wireCheck(!isset($requests[1]['json']['pageSize']), 'Google pageSize not sent');

$requests = [];
$metaToken = 'opaque+/=meta';
$metaSecond = $metaRow;
$metaSecond['date_start'] = $metaSecond['date_stop'] = '2026-11-02';
unset($metaSecond['spend'], $metaSecond['impressions'], $metaSecond['clicks']);
$nextUrl = $metaPlan['read_plan']['url'].'?after='.rawurlencode($metaToken)
    .'';
$meta = wireAdapter('meta_ads', static function(array $request) use (
    &$requests, $metaRow, $metaSecond, $metaToken, $nextUrl
): array {
    $requests[] = $request;
    return count($requests) === 1
        ? wireHttp(['data' => [$metaRow], 'paging' => [
            'cursors' => ['after' => $metaToken], 'next' => $nextUrl,
        ]])
        : wireHttp(['data' => [$metaSecond], 'paging' => [
            'cursors' => ['after' => 'last-page-cursor'],
        ]]);
});
$result = wireRead($meta, 'meta_ads');
wireSame(2, count($requests), 'Meta after without next does not request a third page');
wireSame('123.450001', $result['rows'][0]['spend'], 'Meta decimal string preserved exactly');
wireSame(null, $result['rows'][1]['spend'], 'Meta missing spend remains unknown');
wireSame(true, $result['complete'], 'Meta final page is complete');
wireKeys(
    ['method', 'url', 'headers', 'query', 'follow_redirects', 'max_response_bytes'],
    $requests[0],
    'Meta request has only supported fields'
);
wireSame('GET', $requests[0]['method'], 'Meta read-only method');
wireSame(['Accept' => 'application/json'], $requests[0]['headers'], 'Meta credential-free headers');
wireSame($metaPlan['read_plan']['query'], $requests[0]['query'], 'Meta first request canonical query');
wireSame(1, $requests[0]['query']['time_increment'], 'Meta daily aggregation requested');
wireSame($metaToken, $requests[1]['query']['after'], 'Meta opaque after preserved');
wireSame($metaPlan['read_plan']['query'] + ['after' => $metaToken], $requests[1]['query'], 'Meta next parameters cannot add credentials');
wireSame($metaPlan['read_plan']['url'], $requests[1]['url'], 'Meta reconstructs canonical endpoint');
wireCheck($requests[1]['url'] !== $nextUrl, 'Meta provider next URL is never forwarded');
wireCheck(!str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'synthetic-must-not-forward'), 'Meta next credential marker absent from output');
wireSame(false, $requests[1]['follow_redirects'], 'Meta redirects disabled');
wireSame(1048576, $requests[1]['max_response_bytes'], 'Meta response bound supplied');

$emptyPageCalls = 0;
$emptyThenData = wireAdapter('meta_ads', static function() use (&$emptyPageCalls, $metaPlan, $metaRow): array {
    $emptyPageCalls++;
    return $emptyPageCalls === 1
        ? wireHttp(['data' => [], 'paging' => [
            'cursors' => ['after' => 'empty-next'],
            'next' => $metaPlan['read_plan']['url'].'?after=empty-next',
        ]])
        : wireHttp(['data' => [$metaRow]]);
});
$emptyPageResult = wireRead($emptyThenData, 'meta_ads');
wireSame(2, $emptyPageCalls, 'Meta empty page with next still requests continuation');
wireSame(1, count($emptyPageResult['rows']), 'Meta continuation supplies the actual row');
wireSame(true, $emptyPageResult['complete'], 'Meta empty first page does not truncate report');

foreach ([$result, wireRead(wireAdapter('google_ads', static fn() => wireHttp(['results' => []])), 'google_ads')] as $safeResult) {
    wireSame('laboratory_fixture', $safeResult['source_kind'], 'wire shape does not establish provenance');
    wireSame(null, $safeResult['official_evidence'], 'no official evidence fabricated');
    wireSame(false, $safeResult['external_operations_enabled'], 'external operations remain disabled');
    wireSame(false, $safeResult['billable'], 'wire result is not billable');
}
foreach (['google_ads' => ['results' => []], 'meta_ads' => ['data' => []]] as $provider => $empty) {
    $emptyResult = wireRead(wireAdapter($provider, static fn() => wireHttp($empty)), $provider);
    wireSame([], $emptyResult['rows'], $provider.' empty data remains empty');
    wireSame(null, $emptyResult['currency'], $provider.' empty data does not invent account currency');
    wireSame(false, $emptyResult['billable'], $provider.' empty response is not official zero spend');
}
$nullGoogle = $googleRow;
$nullGoogle['metrics'] = ['costMicros' => null, 'impressions' => null, 'clicks' => null];
$nullMeta = $metaRow;
$nullMeta['spend'] = $nullMeta['impressions'] = $nullMeta['clicks'] = null;
foreach (['google_ads' => ['results' => [$nullGoogle]], 'meta_ads' => ['data' => [$nullMeta]]] as $provider => $nullBody) {
    $unknown = wireRead(wireAdapter($provider, static fn() => wireHttp($nullBody)), $provider);
    foreach (['spend', 'impressions', 'clicks'] as $metric) {
        wireSame(null, $unknown['rows'][0][$metric], $provider.' explicit null remains unknown '.$metric);
    }
}

// Caller-controlled plan/cursor changes cannot reach even the fixture callback.
$callbackCalls = 0;
$guarded = wireAdapter('google_ads', static function() use (&$callbackCalls): array {
    $callbackCalls++;
    return wireHttp(['results' => []]);
});
$mutations = [
    static function(array $p): array { $p['read_plan']['url'] = 'https://attacker.invalid/'; return $p; },
    static function(array $p): array { $p['read_plan']['url'] .= '/mutate'; return $p; },
    static function(array $p): array { $p['read_plan']['method'] = 'DELETE'; return $p; },
    static function(array $p): array { $p['read_plan']['json']['query'] .= ' LIMIT 1'; return $p; },
    static function(array $p): array { $p['read_plan']['json']['pageToken'] = 'injected'; return $p; },
    static function(array $p): array { $p['read_plan']['headers'] = ['Authorization' => 'Bearer never-send-this']; return $p; },
    static function(array $p): array { $p['executable'] = true; return $p; },
    static function(array $p): array { $p['external_operations_enabled'] = true; return $p; },
];
foreach ($mutations as $i => $mutate) {
    wireFailure(fn() => $guarded($mutate($googlePlan), null), 'request_denied', 'tampered request '.$i);
}
foreach (['https://attacker.invalid/page', "token\nheader", 'has space', str_repeat('a', 1025)] as $cursor) {
    wireFailure(fn() => $guarded($googlePlan, $cursor), 'cursor_denied', 'unusable request cursor');
}
wireSame(0, $callbackCalls, 'invalid requests never reach transport callback');
$wrongMeta = $metaPlan;
$wrongMeta['read_plan']['query']['time_increment'] = 30;
wireFailure(
    fn() => wireAdapter('meta_ads', static fn() => throw new RuntimeException('must not run'))($wrongMeta, null),
    'request_denied',
    'Meta aggregate interval injection'
);

// HTTP and callback failures are bounded, sanitized, and never retried.
foreach ([401 => 'authentication_required', 403 => 'permission_denied', 429 => 'rate_limited', 500 => 'upstream_unavailable', 503 => 'upstream_unavailable'] as $status => $reason) {
    $calls = 0;
    $failed = wireAdapter('google_ads', static function() use (&$calls, $status): array {
        $calls++;
        return wireHttp('private-upstream-message-with-token', $status);
    });
    wireFailure(fn() => $failed($googlePlan, null), $reason, 'HTTP '.$status, $status);
    wireSame(1, $calls, 'HTTP '.$status.' not retried');
}
$calls = 0;
$failed = wireAdapter('google_ads', static function() use (&$calls): array {
    $calls++;
    throw new RuntimeException('secret-callback-body https://private.invalid/?access_token=never-log');
});
$failure = wireFailure(fn() => $failed($googlePlan, null), 'transport_failed', 'callback exception');
wireSame(null, $failure->httpStatus, 'callback failure has no invented HTTP status');
wireSame(1, $calls, 'callback exception not retried');
foreach ([
    190 => 'authentication_required', 102 => 'authentication_required',
    10 => 'permission_denied', 200 => 'permission_denied', 299 => 'permission_denied',
    4 => 'rate_limited', 17 => 'rate_limited', 80000 => 'rate_limited',
    99999 => 'provider_error',
] as $code => $reason) {
    $body = ['error' => ['code' => $code, 'message' => 'private-meta-error-with-token']];
    wireFailure(
        fn() => wireAdapter('meta_ads', static fn() => wireHttp($body, 400))($metaPlan, null),
        $reason,
        'Meta Graph error '.$code,
        400
    );
}
wireFailure(
    fn() => wireAdapter('google_ads', static fn() => wireHttp(['error' => ['message' => 'private']], 200))($googlePlan, null),
    'provider_error',
    'Google error object despite HTTP success'
);

$validResponse = wireHttp(['results' => [$googleRow]]);
$badResponses = [];
foreach ([
    'source_kind' => 'official',
    'status' => '200',
    'content_type' => 'text/html',
    'redirected' => true,
    'body' => ['not-a-wire-string'],
] as $field => $value) {
    $bad = $validResponse;
    $bad[$field] = $value;
    $badResponses[$field] = $bad;
}
$bad = $validResponse;
$bad['headers'] = ['Authorization' => 'Bearer never-log'];
$badResponses['extra response field'] = $bad;
foreach ($badResponses as $label => $response) {
    wireFailure(
        fn() => wireAdapter('google_ads', static fn() => $response)($googlePlan, null),
        'response_denied',
        'untrusted HTTP envelope '.$label
    );
}
wireFailure(
    fn() => wireAdapter('google_ads', static fn() => wireHttp(str_repeat(' ', 1048577)))($googlePlan, null),
    'response_limit',
    'response byte ceiling'
);
$tooManyRows = ['results' => array_fill(0, 10001, new stdClass())];
wireFailure(
    fn() => wireAdapter('google_ads', static fn() => wireHttp($tooManyRows))($googlePlan, null),
    'response_denied',
    '10001 row objects rejected before individual row validation'
);
foreach ([
    '{"results":',
    '{"results":[],"results":[]}',
    '{"results":[],"res\u0075lts":[]}',
    '{"results":[]} trailing',
    '{"results":'.str_repeat('[', 40).'0'.str_repeat(']', 40).'}',
] as $body) {
    wireFailure(
        fn() => wireAdapter('google_ads', static fn() => wireHttp($body))($googlePlan, null),
        'invalid_json',
        'ambiguous or malformed JSON'
    );
}

// Raw numeric JSON tokens must never become exact monetary/integer strings.
foreach (['costMicros', 'impressions', 'clicks'] as $metric) {
    foreach ([0, 1.25, true, '1e3', '-1', '', str_repeat('9', 25)] as $invalid) {
        $row = $googleRow;
        $row['metrics'][$metric] = $invalid;
        wireFailure(
            fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$row]]))($googlePlan, null),
            'metric_denied',
            'Google exact string '.$metric
        );
    }
}
foreach (['spend' => [0, 1.25, '1e2', '-1', '1.1234567'], 'impressions' => [0, '1.5'], 'clicks' => [0, '-1']] as $metric => $values) {
    foreach ($values as $invalid) {
        $row = $metaRow;
        $row[$metric] = $invalid;
        wireFailure(
            fn() => wireAdapter('meta_ads', static fn() => wireHttp(['data' => [$row]]))($metaPlan, null),
            'metric_denied',
            'Meta exact string '.$metric
        );
    }
}
$row = $googleRow;
$row['customer']['id'] = '9999999999';
wireFailure(fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$row]]))($googlePlan, null), 'scope_denied', 'Google other account');
$row = $googleRow;
$row['customer']['currencyCode'] = 'brl';
wireFailure(fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$row]]))($googlePlan, null), 'scope_denied', 'Google currency shape');
$row = $googleRow;
$row['customer']['timeZone'] = 'UTC';
wireFailure(fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$row]]))($googlePlan, null), 'timezone_denied', 'Google timezone differs');
$row = $metaRow;
$row['account_id'] = '9999999999999';
wireFailure(fn() => wireAdapter('meta_ads', static fn() => wireHttp(['data' => [$row]]))($metaPlan, null), 'scope_denied', 'Meta other account');
foreach (['2026-10-31', '2026-12-01', '2026-11-31', '2026-11-01extra', 20261101] as $date) {
    $row = $googleRow;
    $row['segments']['date'] = $date;
    wireFailure(fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$row]]))($googlePlan, null), 'date_denied', 'Google report date');
}
$row = $metaRow;
$row['date_stop'] = '2026-11-02';
wireFailure(fn() => wireAdapter('meta_ads', static fn() => wireHttp(['data' => [$row]]))($metaPlan, null), 'date_denied', 'Meta aggregate row not daily');

// A valid adapter response cannot be relabelled with another account timezone
// by composing it with a differently configured connector invocation.
foreach (['google_ads' => ['results' => [$googleRow]], 'meta_ads' => ['data' => [$metaRow]]] as $provider => $timezoneBody) {
    $timezoneAdapter = wireAdapter($provider, static fn() => wireHttp($timezoneBody));
    $timezoneFailure = wireRuntimeFailure(
        fn() => (new Connector(static fn() => str_repeat('a', 64), Closure::fromCallable($timezoneAdapter)))->read(
            'company_alfa',
            $provider,
            $provider === 'google_ads' ? '1234567890' : '1234567890123',
            '2026-11-01',
            '2026-11-30',
            'UTC'
        ),
        $provider.' adapter and connector timezone mismatch'
    );
    wireSame('Provider report timezone denied', $timezoneFailure->getMessage(), $provider.' timezone context preserved');
}

// Safe exception text alone is insufficient when PHP retains function args.
$previousIgnoreArgs = ini_get('zend.exception_ignore_args');
ini_set('zend.exception_ignore_args', '0');
try {
    $secretMarker = 'synthetic-secret-that-must-not-appear-in-a-trace';
    $sensitivePlan = $googlePlan;
    $sensitivePlan['read_plan']['headers'] = ['Authorization' => $secretMarker];
    $traceFailure = wireFailure(fn() => $guarded($sensitivePlan, null), 'request_denied', 'sensitive plan trace');
    wireCheck(!str_contains(json_encode($traceFailure->getTrace(), JSON_THROW_ON_ERROR), $secretMarker), 'plan args absent from exception trace');
    $sensitiveRow = $googleRow;
    $sensitiveRow['metrics']['costMicros'] = $secretMarker;
    $traceFailure = wireFailure(
        fn() => wireAdapter('google_ads', static fn() => wireHttp(['results' => [$sensitiveRow]]))($googlePlan, null),
        'metric_denied',
        'sensitive raw metric trace'
    );
    wireCheck(!str_contains(json_encode($traceFailure->getTrace(), JSON_THROW_ON_ERROR), $secretMarker), 'raw metric absent from exception trace');
} finally {
    if ($previousIgnoreArgs !== false) {
        ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
    }
}

// The URL is untrusted pagination metadata, never a transport destination.
foreach ([
    'https://attacker.invalid/v27.0/act_1234567890123/insights?after=next',
    'http://graph.facebook.com/v27.0/act_1234567890123/insights?after=next',
    'https://user:password@graph.facebook.com/v27.0/act_1234567890123/insights?after=next',
    'https://graph.facebook.com:443/v27.0/act_1234567890123/insights?after=next',
    'https://graph.facebook.com/v27.0/act_9999999999999/insights?after=next',
    'https://graph.facebook.com/v27.0/act_1234567890123/insights?after=other',
    'https://graph.facebook.com/v27.0/act_1234567890123/insights?after=next#fragment',
    'https://graph.facebook.com/v27.0/act_1234567890123/insights?after=next&after=other',
] as $url) {
    $body = ['data' => [$metaRow], 'paging' => ['cursors' => ['after' => 'next'], 'next' => $url]];
    wireFailure(fn() => wireAdapter('meta_ads', static fn() => wireHttp($body))($metaPlan, null), 'pagination_denied', 'Meta pagination destination');
}
$body = ['data' => [$metaRow], 'paging' => ['next' => $metaPlan['read_plan']['url'].'?after=next']];
wireFailure(fn() => wireAdapter('meta_ads', static fn() => wireHttp($body))($metaPlan, null), 'pagination_denied', 'Meta next without independent cursor');

// Consent is checked by the existing connector, including around the callback.
$calls = 0;
$consentAdapter = wireAdapter('google_ads', static function() use (&$calls, $googleRow): array {
    $calls++;
    return wireHttp(['results' => [$googleRow]]);
});
wireRuntimeFailure(fn() => wireRead($consentAdapter, 'google_ads', static fn() => null), 'initial consent denied');
wireSame(0, $calls, 'no transport without initial consent');
$active = true;
$calls = 0;
$consentAdapter = wireAdapter('google_ads', static function() use (&$calls, &$active, $googleRow): array {
    $calls++;
    $active = false;
    return wireHttp(['results' => [$googleRow]]);
});
$authorize = static function() use (&$active): ?string { return $active ? str_repeat('a', 64) : null; };
wireRuntimeFailure(fn() => wireRead($consentAdapter, 'google_ads', $authorize), 'consent revoked during page');
wireSame(1, $calls, 'no retry after consent revocation');

$calls = 0;
$secondPageFailure = wireAdapter('google_ads', static function() use (&$calls, $googleRow): array {
    $calls++;
    return $calls === 1
        ? wireHttp(['results' => [$googleRow], 'nextPageToken' => 'page2'])
        : wireHttp('private-second-page-auth-message', 401);
});
$received = null;
wireFailure(function() use ($secondPageFailure, &$received): void {
    $received = wireRead($secondPageFailure, 'google_ads');
}, 'authentication_required', 'later-page failure invalidates full read', 401);
wireSame(null, $received, 'first page is not returned after later failure');
wireSame(2, $calls, 'later HTTP failure has no third request');

foreach (['duplicate', 'cycle'] as $failureCase) {
    $calls = 0;
    $badPages = wireAdapter('google_ads', static function() use (&$calls, $googleRow, $failureCase): array {
        $calls++;
        $row = $googleRow;
        if ($failureCase === 'cycle') {
            $row['segments']['date'] = sprintf('2026-11-%02d', $calls);
        }
        return wireHttp(['results' => [$row], 'nextPageToken' => 'same-token']);
    });
    wireRuntimeFailure(fn() => wireRead($badPages, 'google_ads'), 'cross-page '.$failureCase.' denied');
    wireSame(2, $calls, 'cross-page '.$failureCase.' ends after second request');
}
$calls = 0;
$boundedPages = wireAdapter('google_ads', static function() use (&$calls, $googleRow): array {
    $calls++;
    $row = $googleRow;
    $row['segments']['date'] = sprintf('2026-11-%02d', $calls);
    return wireHttp(['results' => [$row], 'nextPageToken' => 'page'.($calls + 1)]);
});
$partial = wireRead($boundedPages, 'google_ads');
wireSame(10, $calls, 'pagination stops at ten requests');
wireSame(10, count($partial['rows']), 'bounded partial report retains inspected rows');
wireSame(false, $partial['complete'], 'page limit cannot establish completeness');
wireSame('partial', $partial['status'], 'page limit is reported as partial');
wireSame(false, $partial['billable'], 'partial data remains unbillable');

// The larger provider profile must not silently relax existing callers.
$largeJson = '{"note":"'.str_repeat('a', 70000).'","metric":9007199254740993}';
$decoded = Json::decodeProviderPage($largeJson);
wireSame('9007199254740993', $decoded->metric->text, 'provider parser keeps raw numeric lexeme');
try {
    Json::decode($largeJson);
    throw new RuntimeException('Historical JSON profile byte limit was relaxed');
} catch (InvalidArgumentException) {
    wireCheck(true, 'historical parser still rejects body above its existing bound');
}
$nodeBoundBody = '['.str_repeat('0,', 200000).'0]';
wireCheck(strlen($nodeBoundBody) < 1048576, 'node limit fixture is below provider byte limit');
try {
    Json::decodeProviderPage($nodeBoundBody);
    throw new RuntimeException('Provider JSON node limit was not enforced');
} catch (InvalidArgumentException $failure) {
    wireSame('JSON nesting or node limit exceeded.', $failure->getMessage(), 'provider 200001 numeric items exceed node cap');
}
$oldAllowedNodes = Json::decode('['.str_repeat('0,', 1998).'0]');
wireSame(1999, count($oldAllowedNodes), 'historical root plus 1999 values fits 2000 nodes');
foreach ([
    '['.str_repeat('0,', 1999).'0]' => 'historical root plus 2000 values exceeds node cap',
    str_repeat('[', 13).'0'.str_repeat(']', 13) => 'historical depth 13 exceeds depth cap',
] as $oldBoundBody => $label) {
    try {
        Json::decode($oldBoundBody);
        throw new RuntimeException('Historical JSON resource limit was relaxed');
    } catch (InvalidArgumentException $failure) {
        wireSame('JSON nesting or node limit exceeded.', $failure->getMessage(), $label);
    }
}
wireCheck(is_array(Json::decode(str_repeat('[', 12).'0'.str_repeat(']', 12))), 'historical depth 12 remains accepted');

echo "OK: $assertions Ads wire adapter assertions; injected fixtures only, no provider credentials or operational reader\n";
