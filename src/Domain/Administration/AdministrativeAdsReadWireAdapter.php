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
 * A closure is fixture-only; the native transport retains http_unverified origin.
 * No credentials, OAuth, retry, official evidence or operational composition here.
 * Authorization stays in the connector, around each account/report operation.
 */
final class AdministrativeAdsReadWireAdapter
{
    private const MAX_BODY_BYTES = 1048576;
    private readonly array $plan;
    private readonly array $accountPlan;
    private readonly string $sourceKind;
    private readonly ?array $boundContext;

    public function __construct(
        private readonly string $provider,
        private readonly string $account,
        private readonly string $from,
        private readonly string $until,
        private readonly string $timezone,
        #[\SensitiveParameter] private readonly Closure|AdministrativeAdsReadHttpTransport $httpFixture
    ) {
        $this->plan = AdministrativeAdsReadPlan::prepare($provider, $account, $from, $until);
        $this->accountPlan = AdministrativeAdsReadPlan::prepareAccount($provider, $account);
        if ($this->accountPlan['read_plan']['api_version'] !== $this->plan['read_plan']['api_version']) {
            self::fail('request_denied');
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            self::fail('timezone_denied');
        }
        $this->sourceKind = $httpFixture instanceof AdministrativeAdsReadHttpTransport
            ? 'http_unverified' : 'laboratory_fixture';
        $this->boundContext = $httpFixture instanceof AdministrativeAdsReadHttpTransport
            ? $httpFixture->context() : null;
        if ($this->boundContext !== null) {
            $keys = array_keys($this->boundContext);
            sort($keys, SORT_STRING);
            if ($keys !== ['account_id', 'company_id', 'from', 'provider', 'until']
                || !is_string($this->boundContext['company_id']) || $this->boundContext['company_id'] === ''
                || $this->boundContext['provider'] !== $provider || $this->boundContext['account_id'] !== $account
                || $this->boundContext['from'] !== $from || $this->boundContext['until'] !== $until) {
                self::fail('request_denied');
            }
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
        return $this->exchange(AdministrativeAdsReadPlan::wireRequest($this->plan, $cursor));
    }

    /** Compose metadata and report reads under the connector's single consent lease. */
    public function connector(#[\SensitiveParameter] Closure $authorize): AdministrativeAdsReadConnector
    {
        if ($this->boundContext !== null) {
            return AdministrativeAdsReadConnector::fromHttpAdapter($authorize, $this);
        }
        return new AdministrativeAdsReadConnector(
            $authorize,
            Closure::fromCallable($this),
            Closure::fromCallable([$this, 'account'])
        );
    }

    /** Native scope only; no credential, lease or configurable source label. */
    public function httpContext(): ?array
    {
        return $this->boundContext;
    }

    /** Account request receives the bound report plan; no caller-controlled account URL. */
    public function account(#[\SensitiveParameter] array $reportPlan): array
    {
        if ($reportPlan !== $this->plan) self::fail('request_denied');
        return $this->exchange(AdministrativeAdsReadPlan::wireRequest($this->accountPlan), true);
    }

    private function exchange(#[\SensitiveParameter] array $request, bool $accountMetadata = false): array
    {
        try {
            $http = ($this->httpFixture)($request);
        } catch (AdministrativeAdsWireFailure $failure) {
            if ($this->httpFixture instanceof AdministrativeAdsReadHttpTransport) {
                // Native failures already carry a finite classification; never chain their trace.
                self::fail($failure->reason, $failure->httpStatus);
            }
            self::fail('transport_failed');
        } catch (Throwable) {
            // The callback exception may contain credentials; do not retain it.
            self::fail('transport_failed');
        }
        return $this->decodeHttp($http, $accountMetadata);
    }

    private function decodeHttp(#[\SensitiveParameter] mixed $http, bool $accountMetadata): array
    {
        $expected = ['body', 'content_type', 'redirected', 'source_kind', 'status'];
        if (!is_array($http)) self::fail('response_denied');
        $keys = array_keys($http);
        sort($keys, SORT_STRING);
        if ($keys !== $expected || $http['source_kind'] !== $this->sourceKind
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
        if ($accountMetadata) return $this->provider === 'google_ads' ? $this->googleAccount($body) : $this->metaAccount($body);
        return $this->provider === 'google_ads' ? $this->google($body) : $this->meta($body);
    }

    private function googleResults(#[\SensitiveParameter] stdClass $body): array
    {
        // These optional provider metadata fields are neither consumed nor
        // independently authenticated by this offline reader. Accepting and
        // discarding arbitrary values could conceal foreign tenants or secret
        // material in a provider-shaped fixture. An explicit reviewed schema
        // is required before supporting them; never upgrade this lab gate to
        // an operational Google contract by inference.
        self::allowedKeys($body, ['results', 'nextPageToken', 'metricAttributes']);
        if (property_exists($body, 'metricAttributes')) {
            // Google provider metadata must not become a side channel for
            // credential, tenant or unrequested metrics. Empty attributes are
            // the only lab profile approved until an official field contract
            // has received independent review.
            if (!is_array($body->metricAttributes) || !array_is_list($body->metricAttributes)
                || count($body->metricAttributes) > 3) self::fail('response_denied');
            $seenMetrics = [];
            foreach ($body->metricAttributes as $attribute) {
                if (!$attribute instanceof stdClass) self::fail('response_denied');
                self::allowedKeys($attribute, ['name', 'attributes']);
                $name = $attribute->name ?? null;
                if (!is_string($name)
                    || !in_array($name, ['metrics.impressions', 'metrics.clicks', 'metrics.costMicros'], true)
                    || isset($seenMetrics[$name])
                    || !property_exists($attribute, 'attributes')
                    || !is_array($attribute->attributes)
                    || !array_is_list($attribute->attributes)
                    || $attribute->attributes !== []) self::fail('response_denied');
                $seenMetrics[$name] = true;
            }
        }
        // ProtoJSON may omit an empty repeated field; this remains an unverified empty result.
        $results = property_exists($body, 'results') ? $body->results : [];
        self::rowList($results);
        return $results;
    }

    private function google(#[\SensitiveParameter] stdClass $body): array
    {
        $results = $this->googleResults($body);
        $rows = [];
        foreach ($results as $result) {
            if (!($result instanceof stdClass) || !(($result->customer ?? null) instanceof stdClass)
                || !(($result->campaign ?? null) instanceof stdClass)
                || !(($result->segments ?? null) instanceof stdClass)) self::fail('scope_denied');
            $customer = $result->customer;
            $campaign = $result->campaign;
            // A synthetically supplied provider JSON row must not smuggle
            // tenant identifiers, bearer material or undeclared nested fields
            // through a normalizer that would silently discard them.
            self::allowedKeys($result, ['customer', 'campaign', 'segments', 'metrics'], 'scope_denied');
            self::allowedKeys($customer, ['id', 'currencyCode', 'timeZone', 'resourceName'], 'scope_denied');
            self::allowedKeys($campaign, ['id', 'name', 'status', 'resourceName'], 'scope_denied');
            self::allowedKeys($result->segments, ['date'], 'scope_denied');
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
            self::allowedKeys($metrics, ['costMicros', 'impressions', 'clicks'], 'metric_denied');
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

    private function googleAccount(#[\SensitiveParameter] stdClass $body): array
    {
        $results = $this->googleResults($body);
        if (count($results) !== 1 || !($results[0] instanceof stdClass)
            || !(($results[0]->customer ?? null) instanceof stdClass)) self::fail('scope_denied');
        if (property_exists($body, 'nextPageToken') && $body->nextPageToken !== '') self::fail('pagination_denied');
        self::allowedKeys($results[0], ['customer'], 'scope_denied');
        $customer = $results[0]->customer;
        self::allowedKeys($customer, ['id', 'currencyCode', 'timeZone', 'resourceName'], 'scope_denied');
        if (($customer->id ?? null) !== $this->account
            || (property_exists($customer, 'resourceName') && $customer->resourceName !== 'customers/'.$this->account)) {
            self::fail('scope_denied');
        }
        if (($customer->timeZone ?? null) !== $this->timezone) self::fail('timezone_denied');
        return $this->accountContext(self::currency($customer->currencyCode ?? null), $customer->timeZone);
    }

    private function metaAccount(#[\SensitiveParameter] stdClass $body): array
    {
        self::allowedKeys($body, ['id', 'account_id', 'currency', 'timezone_name'], 'scope_denied');
        if (($body->id ?? null) !== 'act_'.$this->account || ($body->account_id ?? null) !== $this->account) {
            self::fail('scope_denied');
        }
        if (($body->timezone_name ?? null) !== $this->timezone) self::fail('timezone_denied');
        return $this->accountContext(self::currency($body->currency ?? null), $body->timezone_name);
    }

    private function accountContext(string $currency, string $timezone): array
    {
        return ['source_kind' => $this->sourceKind, 'provider' => $this->provider,
            'api_version' => $this->accountPlan['read_plan']['api_version'], 'account_id' => $this->account,
            'currency' => $currency, 'timezone' => $timezone, 'context_timezone' => $this->timezone];
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
            self::allowedKeys($result, [
                'account_id', 'account_currency', 'campaign_id', 'campaign_name',
                'date_start', 'date_stop', 'spend', 'impressions', 'clicks',
            ], 'scope_denied');
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
            if (property_exists($body->paging, 'cursors')) {
                if (!$body->paging->cursors instanceof stdClass) self::fail('pagination_denied');
                // A provider cursor object is still untrusted data, including
                // on the last page where its contents are otherwise discarded.
                // Reject undeclared tenant or credential fields, not just an
                // invalid "after" value on a page with "next".
                self::allowedKeys($body->paging->cursors, ['after', 'before'], 'pagination_denied');
                foreach (['after', 'before'] as $direction) {
                    if (property_exists($body->paging->cursors, $direction)
                        && !AdministrativeAdsReadPlan::validCursor($body->paging->cursors->$direction)) {
                        self::fail('pagination_denied');
                    }
                }
            }
            // Previous links are never followed, but they are still untrusted
            // tenant-scoped URLs: do not silently ignore malformed or foreign links.
            if (property_exists($body->paging, 'previous')) {
                $previousCursor = $body->paging->cursors->before ?? null;
                if (!AdministrativeAdsReadPlan::validCursor($previousCursor)) self::fail('pagination_denied');
                $this->validateMetaPrevious($body->paging->previous, $previousCursor);
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
        $this->validateMetaPagingLink($next, 'after', $cursor);
    }

    private function validateMetaPrevious(#[\SensitiveParameter] mixed $previous, #[\SensitiveParameter] string $cursor): void
    {
        $this->validateMetaPagingLink($previous, 'before', $cursor);
    }

    /** Provider URLs are metadata, NEVER outbound transport targets. Both
     * directions must be scoped to the same fixed HTTPS account endpoint and
     * the matching validated cursor. No URLs, credentials or params returned. */
    private function validateMetaPagingLink(
        #[\SensitiveParameter] mixed $link,
        string $direction,
        #[\SensitiveParameter] string $cursor
    ): void {
        if (!is_string($link) || strlen($link) > 16384 || preg_match('/[\\x00-\\x20\\x7f]/', $link)) {
            self::fail('pagination_denied');
        }
        $url = parse_url($link);
        if (!is_array($url) || ($url['scheme'] ?? null) !== 'https'
            || ($url['host'] ?? null) !== 'graph.facebook.com'
            || ($url['path'] ?? null) !== '/'.$this->plan['read_plan']['api_version'].'/act_'.$this->account.'/insights'
            || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])
            || !isset($url['query']) || preg_match('/%(?![a-fA-F0-9]{2})/', $url['query'])) {
            self::fail('pagination_denied');
        }
        $found = null;
        $seen = [];
        $pairs = explode('&', $url['query']);
        if (count($pairs) > 32) self::fail('pagination_denied');
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            // Until a provider-approved query schema exists, a paging link is
            // ONLY an assertion about the matching cursor. Even though the
            // link is never followed, reject opaque tokens, tenant selectors,
            // credential references and any other discarded query metadata.
            if ($key !== $direction || isset($seen[$key])) self::fail('pagination_denied');
            $seen[$key] = true;
            $found = urldecode($parts[1] ?? '');
        }
        if ($found !== $cursor) self::fail('pagination_denied');
        // Links are never transport targets; the cursor-only laboratory
        // profile is not a verified official Meta Graph API contract.
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
        return ['source_kind' => $this->sourceKind, 'context_timezone' => $this->timezone, 'rows' => $rows, 'next_cursor' => $cursor];
    }

    private static function fail(string $reason, ?int $httpStatus = null): never
    {
        throw new AdministrativeAdsWireFailure($reason, $httpStatus);
    }
}
