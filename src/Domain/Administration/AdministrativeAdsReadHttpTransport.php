<?php
declare(strict_types=1);

namespace Imagine\Performance\Administration;

use Closure;
use CurlHandle;
use Throwable;

/**
 * Bounded native HTTPS for the two fixed Ads read plans. Not registered in runtime.
 * Credential resolution and current consent are server dependencies, never request input.
 * HTTP bytes are always unverified; TLS does not make a report financial evidence.
 */
final class AdministrativeAdsReadHttpTransport
{
    private const MAX_BODY_BYTES = 1048576;
    private const MAX_HEADER_BYTES = 32768;
    private readonly array $reportPlan;
    private readonly array $accountPlan;

    public function __construct(
        private readonly string $company,
        private readonly string $provider,
        private readonly string $account,
        private readonly string $from,
        private readonly string $until,
        #[\SensitiveParameter] private readonly Closure $authorize,
        #[\SensitiveParameter] private readonly ?Closure $resolveCredential = null,
        private readonly int $connectTimeoutMs = 3000,
        private readonly int $timeoutMs = 10000
    ) {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $company)
            || $connectTimeoutMs < 100 || $timeoutMs < $connectTimeoutMs || $timeoutMs > 10000) {
            self::fail('request_denied');
        }
        try {
            $this->reportPlan = AdministrativeAdsReadPlan::prepare($provider, $account, $from, $until);
            $this->accountPlan = AdministrativeAdsReadPlan::prepareAccount($provider, $account);
        } catch (Throwable) {
            self::fail('request_denied');
        }
    }

    /** Fixed binding for the codec/connector. Contains no credential or lease. */
    public function context(): array
    {
        return ['company_id' => $this->company, 'provider' => $this->provider,
            'account_id' => $this->account, 'from' => $this->from, 'until' => $this->until];
    }

    public function __debugInfo(): array
    {
        return ['transport' => 'fixed_ads_https', 'source_kind' => 'http_unverified'];
    }

    /** Internal wire envelope only; callers must not log or expose its raw body. */
    public function __invoke(#[\SensitiveParameter] array $request): array
    {
        $this->validateRequest($request);
        $lease = $this->currentLease();
        if ($this->resolveCredential === null) self::fail('credentials_unavailable');
        try {
            $credential = ($this->resolveCredential)($this->company, $this->provider, $this->account, $lease);
        } catch (Throwable) {
            self::fail('credentials_unavailable');
        }
        $this->currentLease($lease);
        if ($credential === null) self::fail('credentials_unavailable');
        $this->validateCredential($credential, $lease);
        $headers = ['Accept: application/json', 'Accept-Encoding: identity',
            'Authorization: Bearer '.$credential['access_token']];
        if ($this->provider === 'google_ads') {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Expect:';
            $headers[] = 'developer-token: '.$credential['developer_token'];
            if ($credential['login_customer_id'] !== null) $headers[] = 'login-customer-id: '.$credential['login_customer_id'];
        }
        $expiry = $credential['expires_at'];
        unset($credential);
        try {
            return $this->perform($request, $headers, $lease, $expiry);
        } finally {
            unset($headers);
        }
    }

    private function validateRequest(#[\SensitiveParameter] array $request): void
    {
        $cursor = null;
        $values = $request[$this->provider === 'google_ads' ? 'json' : 'query'] ?? null;
        $field = $this->provider === 'google_ads' ? 'pageToken' : 'after';
        if (is_array($values) && array_key_exists($field, $values)) {
            if (!AdministrativeAdsReadPlan::validCursor($values[$field])) self::fail('request_denied');
            $cursor = $values[$field];
        }
        // Includes exact method/host/path/headers/fields/flags, not just an allowlisted host.
        if ($request !== AdministrativeAdsReadPlan::wireRequest($this->accountPlan)
            && $request !== AdministrativeAdsReadPlan::wireRequest($this->reportPlan, $cursor)) {
            self::fail('request_denied');
        }
    }

    private function currentLease(#[\SensitiveParameter] ?string $expected = null): string
    {
        try {
            $lease = ($this->authorize)($this->company, $this->provider, $this->account);
        } catch (Throwable) {
            self::fail($expected === null ? 'authorization_denied' : 'authorization_revoked');
        }
        if (!is_string($lease) || !preg_match('/\A[a-f0-9]{64}\z/D', $lease)
            || ($expected !== null && !hash_equals($expected, $lease))) {
            self::fail($expected === null ? 'authorization_denied' : 'authorization_revoked');
        }
        return $lease;
    }

    private function validateCredential(#[\SensitiveParameter] mixed $credential, #[\SensitiveParameter] string $lease): void
    {
        if (!is_array($credential)) self::fail('credentials_denied');
        $expected = ['access_token', 'account_id', 'company_id', 'credential_ref', 'expires_at', 'lease', 'provider'];
        if ($this->provider === 'google_ads') {
            $expected[] = 'developer_token'; $expected[] = 'login_customer_id';
        }
        $keys = array_keys($credential); sort($keys, SORT_STRING); sort($expected, SORT_STRING);
        if ($keys !== $expected || $credential['company_id'] !== $this->company
            || $credential['provider'] !== $this->provider || $credential['account_id'] !== $this->account
            || !is_string($credential['lease']) || !hash_equals($lease, $credential['lease'])
            || !is_string($credential['credential_ref'])
            || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $credential['credential_ref'])
            || !self::headerToken($credential['access_token'], 4096)
            || !is_int($credential['expires_at'])) self::fail('credentials_denied');
        if ($this->provider === 'google_ads'
            && (!self::headerToken($credential['developer_token'], 128)
                || ($credential['login_customer_id'] !== null
                    && (!is_string($credential['login_customer_id'])
                        || !preg_match('/\A[0-9]{10}\z/D', $credential['login_customer_id']))))) {
            self::fail('credentials_denied');
        }
        $this->checkExpiry($credential['expires_at'], true);
    }

    private static function headerToken(#[\SensitiveParameter] mixed $token, int $max): bool
    {
        return is_string($token) && strlen($token) <= $max
            && preg_match('/\A[A-Za-z0-9._~+\/-]+=*\z/D', $token) === 1;
    }

    private function checkExpiry(int $expiry, bool $before): void
    {
        $reserve = $before ? (int) ceil($this->timeoutMs / 1000) : 0;
        if ($expiry <= time() + $reserve) self::fail('credentials_expired');
    }

    private function perform(
        #[\SensitiveParameter] array $request,
        #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] string $lease,
        int $expiry
    ): array {
        if (!function_exists('curl_init') || !function_exists('curl_version')
            || (curl_version()['features'] & CURL_VERSION_ASYNCHDNS) === 0) {
            self::fail('transport_unavailable');
        }
        $body = ''; $contentType = ''; $headerBytes = 0; $status = 0;
        $abort = null; $handle = null; $error = null; $ok = false; $errno = 0; $observedStatus = 0;
        $finalSeen = false; $seenHeaders = []; $contentLength = null; $chunked = false;
        $writer = static function (#[\SensitiveParameter] CurlHandle $handle, #[\SensitiveParameter] string $chunk) use (&$body, &$abort): int {
            $length = strlen($chunk);
            if ($abort !== null) return 0;
            if ($length > self::MAX_BODY_BYTES - strlen($body)) {
                $abort = 'response_limit'; return 0;
            }
            $body .= $chunk;
            return $length;
        };
        $headerReader = static function (#[\SensitiveParameter] CurlHandle $handle, #[\SensitiveParameter] string $line)
            use (&$abort, &$headerBytes, &$status, &$contentType, &$finalSeen, &$seenHeaders, &$contentLength, &$chunked): int {
            $length = strlen($line); $headerBytes += $length;
            if ($abort !== null) return 0;
            if ($headerBytes > self::MAX_HEADER_BYTES) { $abort = 'response_headers_denied'; return 0; }
            if (preg_match('/\AHTTP\/(?:1\.[01]|2|3) ([1-5][0-9]{2})(?: [^\r\n]*)?\r\n\z/D', $line, $match)) {
                if ($finalSeen) { $abort = 'response_headers_denied'; return 0; }
                $status = (int) $match[1]; $contentType = ''; $seenHeaders = []; $contentLength = null; $chunked = false;
                if ($status >= 300 && $status < 400) { $abort = 'redirect_denied'; return 0; }
                if ($status < 200 && !in_array($status, [100, 103], true)) { $abort = 'response_headers_denied'; return 0; }
                $finalSeen = $status >= 200;
                return $length;
            }
            if ($line === "\r\n") return $length;
            if ($status === 0 || !preg_match('/\A([!#$%&\x27*+.^_`|~0-9A-Za-z-]+):[ \t]*([^\r\n]*)\r\n\z/D', $line, $match)) {
                $abort = 'response_headers_denied'; return 0;
            }
            $name = strtolower($match[1]); $value = trim($match[2], " \t");
            if (in_array($name, ['content-type', 'content-length', 'content-encoding', 'transfer-encoding'], true)) {
                if (isset($seenHeaders[$name])) { $abort = 'response_headers_denied'; return 0; }
                $seenHeaders[$name] = true;
            }
            if ($name === 'content-type') {
                if (strlen($value) > 128) { $abort = 'response_headers_denied'; return 0; }
                $contentType = $value;
            } elseif ($name === 'content-encoding' && strtolower($value) !== 'identity') {
                $abort = 'response_encoding_denied'; return 0;
            } elseif ($name === 'content-length') {
                if (!preg_match('/\A[0-9]{1,20}\z/D', $value) || $chunked) { $abort = 'response_headers_denied'; return 0; }
                $digits = ltrim($value, '0'); $max = (string) self::MAX_BODY_BYTES;
                if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
                    $abort = 'response_limit'; return 0;
                }
                $contentLength = (int) $value;
            } elseif ($name === 'transfer-encoding') {
                if (strtolower($value) !== 'chunked' || $contentLength !== null) { $abort = 'response_headers_denied'; return 0; }
                $chunked = true;
            }
            return $length;
        };
        try {
            $handle = curl_init();
            if (!$handle instanceof CurlHandle) self::fail('transport_unavailable');
            $url = $request['url'];
            if ($this->provider === 'meta_ads') $url .= '?'.http_build_query($request['query'], '', '&', PHP_QUERY_RFC3986);
            $options = [
                CURLOPT_URL => $url, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_NETRC => CURL_NETRC_IGNORED,
                CURLOPT_UNRESTRICTED_AUTH => false, CURLOPT_DISALLOW_USERNAME_IN_URL => true,
                CURLOPT_HTTPAUTH => CURLAUTH_NONE, CURLOPT_AUTOREFERER => false,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs, CURLOPT_TIMEOUT_MS => $this->timeoutMs,
                CURLOPT_NOSIGNAL => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true,
                CURLOPT_RETURNTRANSFER => false, CURLOPT_HEADER => false, CURLOPT_VERBOSE => false,
                CURLOPT_FAILONERROR => false, CURLOPT_HTTP_CONTENT_DECODING => false,
                CURLOPT_HEADERFUNCTION => $headerReader, CURLOPT_WRITEFUNCTION => $writer,
            ];
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $options[CURLOPT_PROTOCOLS_STR] = 'https'; $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
            } else {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS; $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            if ($this->provider === 'google_ads') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($request['json'], JSON_THROW_ON_ERROR);
            } else {
                $options[CURLOPT_HTTPGET] = true;
            }
            if (!curl_setopt_array($handle, $options)) self::fail('transport_unavailable');
            unset($options, $headers);
            $this->currentLease($lease); $this->checkExpiry($expiry, true);
            $ok = curl_exec($handle);
            $errno = curl_errno($handle);
            $observedStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } catch (AdministrativeAdsWireFailure $failure) {
            $error = $failure->reason;
        } catch (Throwable) {
            $error = 'transport_failed';
        } finally {
            if ($handle instanceof CurlHandle) curl_reset($handle);
            unset($handle, $headers, $writer, $headerReader);
        }
        $this->currentLease($lease); $this->checkExpiry($expiry, false);
        if ($error !== null) self::fail($error);
        if ($abort !== null) self::fail($abort, $status >= 100 ? $status : null);
        if ($ok !== true || $errno !== 0) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) self::fail('transport_timeout');
            // PHP exposes the CA verification code under its historical name.
            if (in_array($errno, [CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CACERT, CURLE_SSL_PEER_CERTIFICATE, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CACERT_BADFILE], true)) self::fail('tls_failed');
            self::fail('transport_failed');
        }
        if (!$finalSeen || $observedStatus !== $status || $status < 200 || $status > 599) self::fail('response_headers_denied');
        return ['source_kind' => 'http_unverified', 'status' => $status, 'content_type' => $contentType,
            'redirected' => false, 'body' => $body];
    }

    private static function fail(string $reason, ?int $status = null): never
    {
        throw new AdministrativeAdsWireFailure($reason, $status);
    }
}
