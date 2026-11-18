<?php
declare(strict_types=1);

namespace Imagine\Performance\Administration;

use Closure;
use DateTimeZone;
use Imagine\Performance\Commercial\ExactJsonNumberLexicalParser;
use Imagine\Performance\Commercial\ExactJsonNumberLiteral;
use stdClass;
use Throwable;

/**
 * Google Search / Meta Insights wire codec for the existing injectable connector.
 * No HTTP implementation, credentials, OAuth, retry, or operational composition.
 * The injected callback must return a laboratory_fixture, never official evidence.
 * Caller authorization stays in AdministrativeAdsReadConnector, around every page.
 */
final class AdministrativeAdsReadWireAdapter
{
    private const MAX_BODY_BYTES = 1048576;
    private readonly array $plan;

    public function __construct(
        private readonly string $provider,
        private readonly string $account,
        private readonly string $from,
        private readonly string $until,
        private readonly string $timezone,
        private readonly Closure $httpFixture
    ) {
        $this->plan = AdministrativeAdsReadPlan::prepare($provider, $account, $from, $until);
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            self::fail('timezone_denied');
        }
    }

    /** @return array{source_kind:string,context_timezone:string,rows:array,next_cursor:?string} */
    public function __invoke(#[\SensitiveParameter] array $plan, #[\SensitiveParameter] ?string $cursor): array
    {
        // Structural equality also denies caller-added headers, flags and query fields.
        if ($plan !== $this->plan) self::fail('request_denied');
        if ($cursor !== null && !AdministrativeAdsReadPlan::validCursor($cursor)) {
            self::fail('cursor_denied');
        }
        $source = $this->plan['read_plan'];
        $request = [
            'method' => $source['method'],
            'url' => $source['url'],
            'headers' => ['Accept' => 'application/json'],
            'follow_redirects' => false,
            'max_response_bytes' => self::MAX_BODY_BYTES,
        ];
        if ($this->provider === 'google_ads') {
            $request['headers']['Content-Type'] = 'application/json';
            $request['json'] = $source['json'];
            if ($cursor !== null) $request['json']['pageToken'] = $cursor;
        } else {
            $request['query'] = $source['query'];
            if ($cursor !== null) $request['query']['after'] = $cursor;
        }
        try {
            $http = ($this->httpFixture)($request);
        } catch (Throwable) {
            // The callback exception may contain credentials; do not retain it.
            self::fail('transport_failed');
        }
        return $this->decodeHttp($http);
    }

    private function decodeHttp(#[\SensitiveParameter] mixed $http): array
    {
        $expected = ['body', 'content_type', 'redirected', 'source_kind', 'status'];
        if (!is_array($http)) self::fail('response_denied');
        $keys = array_keys($http);
        sort($keys, SORT_STRING);
        if ($keys !== $expected || $http['source_kind'] !== 'laboratory_fixture'
            || !is_int($http['status']) || $http['status'] < 100 || $http['status'] > 599
            || $http['redirected'] !== false || !is_string($http['content_type'])
            || strlen($http['content_type']) > 128 || !is_string($http['body'])) {
            self::fail('response_denied');
        }
        $status = $http['status'];
        if (strlen($http['body']) > self::MAX_BODY_BYTES) self::fail('response_limit', $status);
        $jsonType = preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD', $http['content_type']) === 1;
        $body = null;
        if ($jsonType && $http['body'] !== '') {
            try {
                $body = ExactJsonNumberLexicalParser::decodeProviderPage($http['body']);
            } catch (Throwable) {
                if ($status === 200) self::fail('invalid_json', $status);
            }
        }
        if ($status !== 200) self::fail($this->errorReason($status, $body), $status);
        if (!$jsonType) self::fail('response_denied', $status);
        if ($http['body'] === '') self::fail('invalid_json', $status);
        if (!$body instanceof stdClass) self::fail('response_denied', $status);
        if (property_exists($body, 'error')) self::fail($this->errorReason($status, $body), $status);
        return $this->provider === 'google_ads' ? $this->google($body) : $this->meta($body);
    }

    private function google(#[\SensitiveParameter] stdClass $body): array
    {
        self::allowedKeys($body, ['results', 'nextPageToken', 'fieldMask', 'totalResultsCount', 'queryResourceConsumption', 'metricAttributes']);
        if (property_exists($body, 'metricAttributes')
            && (!is_array($body->metricAttributes) || !array_is_list($body->metricAttributes))) self::fail('response_denied');
        // ProtoJSON may omit an empty repeated field; this remains an unverified empty result.
        $results = property_exists($body, 'results') ? $body->results : [];
        self::rowList($results);
        $rows = [];
        foreach ($results as $result) {
            if (!($result instanceof stdClass) || !(($result->customer ?? null) instanceof stdClass)
                || !(($result->campaign ?? null) instanceof stdClass)
                || !(($result->segments ?? null) instanceof stdClass)) self::fail('scope_denied');
            $customer = $result->customer;
            $campaign = $result->campaign;
            if (($customer->id ?? null) !== $this->account) self::fail('scope_denied');
            if (($customer->timeZone ?? null) !== $this->timezone) self::fail('timezone_denied');
            if (property_exists($customer, 'resourceName') && $customer->resourceName !== 'customers/'.$this->account) {
                self::fail('scope_denied');
            }
            $campaignId = self::identifier($campaign->id ?? null);
            if (property_exists($campaign, 'resourceName')
                && $campaign->resourceName !== 'customers/'.$this->account.'/campaigns/'.$campaignId) {
                self::fail('scope_denied');
            }
            $metrics = property_exists($result, 'metrics') ? $result->metrics : new stdClass();
            if (!$metrics instanceof stdClass) self::fail('metric_denied');
            $rows[] = [
                'account_id' => $customer->id,
                'campaign_id' => $campaignId,
                'currency' => self::currency($customer->currencyCode ?? null),
                'date' => $this->date($result->segments->date ?? null),
                'spend' => self::metric($metrics->costMicros ?? null, false),
                'impressions' => self::metric($metrics->impressions ?? null, false),
                'clicks' => self::metric($metrics->clicks ?? null, false),
            ];
        }
        $cursor = property_exists($body, 'nextPageToken') ? $body->nextPageToken : '';
        if ($cursor === '') $cursor = null;
        elseif (!AdministrativeAdsReadPlan::validCursor($cursor)) self::fail('pagination_denied');
        return $this->page($rows, $cursor);
    }

    private function meta(#[\SensitiveParameter] stdClass $body): array
    {
        self::allowedKeys($body, ['data', 'paging']);
        if (!property_exists($body, 'data')) self::fail('response_denied');
        self::rowList($body->data);
        $rows = [];
        foreach ($body->data as $result) {
            if (!$result instanceof stdClass || ($result->account_id ?? null) !== $this->account) {
                self::fail('scope_denied');
            }
            $date = $this->date($result->date_start ?? null);
            if (($result->date_stop ?? null) !== $date) self::fail('date_denied');
            $rows[] = [
                'account_id' => $result->account_id,
                'campaign_id' => self::identifier($result->campaign_id ?? null),
                'currency' => self::currency($result->account_currency ?? null),
                'date' => $date,
                'spend' => self::metric($result->spend ?? null, true),
                'impressions' => self::metric($result->impressions ?? null, false),
                'clicks' => self::metric($result->clicks ?? null, false),
            ];
        }
        $cursor = null;
        if (property_exists($body, 'paging')) {
            if (!$body->paging instanceof stdClass) self::fail('pagination_denied');
            self::allowedKeys($body->paging, ['cursors', 'next', 'previous'], 'pagination_denied');
            if (property_exists($body->paging, 'cursors') && !$body->paging->cursors instanceof stdClass) {
                self::fail('pagination_denied');
            }
            // Cursors can appear on the final page; only next means continuation.
            if (property_exists($body->paging, 'next')) {
                $cursor = $body->paging->cursors->after ?? null;
                if (!AdministrativeAdsReadPlan::validCursor($cursor)) self::fail('pagination_denied');
                $this->validateMetaNext($body->paging->next, $cursor);
            }
        }
        return $this->page($rows, $cursor);
    }

    private function validateMetaNext(#[\SensitiveParameter] mixed $next, #[\SensitiveParameter] string $cursor): void
    {
        if (!is_string($next) || strlen($next) > 16384 || preg_match('/[\x00-\x20\x7f]/', $next)) {
            self::fail('pagination_denied');
        }
        $url = parse_url($next);
        if (!is_array($url) || ($url['scheme'] ?? null) !== 'https'
            || ($url['host'] ?? null) !== 'graph.facebook.com'
            || ($url['path'] ?? null) !== '/'.$this->plan['read_plan']['api_version'].'/act_'.$this->account.'/insights'
            || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])
            || !isset($url['query']) || preg_match('/%(?![a-fA-F0-9]{2})/', $url['query'])) {
            self::fail('pagination_denied');
        }
        $after = null;
        $seen = [];
        $pairs = explode('&', $url['query']);
        if (count($pairs) > 32) self::fail('pagination_denied');
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if ($key === '' || isset($seen[$key])) self::fail('pagination_denied');
            $seen[$key] = true;
            if ($key === 'after') $after = urldecode($parts[1] ?? '');
        }
        if ($after !== $cursor) self::fail('pagination_denied');
        // Other URL parameters (possibly access_token) are discarded, never forwarded.
    }

    private function errorReason(int $status, #[\SensitiveParameter] mixed $body): string
    {
        if ($body instanceof stdClass && ($body->error ?? null) instanceof stdClass) {
            if ($this->provider === 'meta_ads') {
                $code = $body->error->code ?? null;
                if ($code instanceof ExactJsonNumberLiteral && preg_match('/\A[0-9]{1,6}\z/D', $code->text)) {
                    $number = (int) $code->text;
                    if (in_array($number, [102, 190], true)) return 'authentication_required';
                    if ($number === 10 || $number >= 200 && $number <= 299) return 'permission_denied';
                    if (in_array($number, [4, 17, 80000], true)) return 'rate_limited';
                    if (in_array($number, [1, 2], true)) return 'upstream_unavailable';
                }
            } else {
                $reason = match ($body->error->status ?? null) {
                    'UNAUTHENTICATED' => 'authentication_required',
                    'PERMISSION_DENIED' => 'permission_denied',
                    'RESOURCE_EXHAUSTED' => 'rate_limited',
                    'UNAVAILABLE', 'DEADLINE_EXCEEDED' => 'upstream_unavailable',
                    default => null,
                };
                if ($reason !== null) return $reason;
            }
        }
        return match (true) {
            $status === 401 => 'authentication_required',
            $status === 403 => 'permission_denied',
            $status === 429 => 'rate_limited',
            $status === 408 || $status >= 500 => 'upstream_unavailable',
            default => 'provider_error',
        };
    }

    private function date(#[\SensitiveParameter] mixed $date): string
    {
        if (!is_string($date) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date)
            || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            || $date < $this->from || $date > $this->until) self::fail('date_denied');
        return $date;
    }

    private static function identifier(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9]{1,32}\z/D', $value)) self::fail('scope_denied');
        return $value;
    }

    private static function currency(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[A-Z]{3}\z/D', $value)) self::fail('scope_denied');
        return $value;
    }

    private static function metric(#[\SensitiveParameter] mixed $value, bool $decimal): ?string
    {
        if ($value === null) return null;
        $pattern = $decimal ? '/\A[0-9]{1,18}(\.[0-9]{1,6})?\z/D' : '/\A[0-9]{1,24}\z/D';
        if (!is_string($value) || !preg_match($pattern, $value)) self::fail('metric_denied');
        return $value;
    }

    private static function rowList(#[\SensitiveParameter] mixed $rows): void
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 10000) self::fail('response_denied');
    }

    private static function allowedKeys(#[\SensitiveParameter] stdClass $object, array $allowed, string $reason = 'response_denied'): void
    {
        foreach (array_keys(get_object_vars($object)) as $key) {
            if (!in_array($key, $allowed, true)) self::fail($reason);
        }
    }

    private function page(#[\SensitiveParameter] array $rows, #[\SensitiveParameter] ?string $cursor): array
    {
        return ['source_kind' => 'laboratory_fixture', 'context_timezone' => $this->timezone, 'rows' => $rows, 'next_cursor' => $cursor];
    }

    private static function fail(string $reason, ?int $httpStatus = null): never
    {
        throw new AdministrativeAdsWireFailure($reason, $httpStatus);
    }
}
