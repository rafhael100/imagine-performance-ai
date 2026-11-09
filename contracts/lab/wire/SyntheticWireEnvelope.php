<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use InvalidArgumentException;

/**
 * TEST-ONLY wire contract between Portal and Performance labs.
 *
 * No credential distribution, workload identity, PKI, OAuth, mTLS or Laravel
 * routes are implemented. Key material is ephemeral per CI run; never store
 * or deploy this as an operational authorization protocol.
 */
final class SyntheticWireEnvelope
{
    public const METHOD = 'POST';
    public const PATH = '/_synthetic/perf/revocation';
    public const VERSION = 'perf.wire.synthetic.v1';
    public const KEY_ID = 'kid-demo-portal-1';
    public const MAX_BYTES = 2048;

    private const VERSION_HEADER = 'X-Imagine-Lab-Version';
    private const KEY_HEADER = 'X-Imagine-Lab-Key-Id';
    private const TIME_HEADER = 'X-Imagine-Lab-Timestamp';
    private const NONCE_HEADER = 'X-Imagine-Lab-Nonce';
    private const DIGEST_HEADER = 'X-Imagine-Lab-Body-SHA256';
    private const SIGNATURE_HEADER = 'X-Imagine-Lab-Signature';

    /**
     * @return array<string,string> Synthetic headers, never live credentials.
     */
    public static function signForLab(
        string $body,
        string $ephemeralKey,
        int $timestamp,
        string $nonce,
    ): array {
        if (strlen($ephemeralKey) < 32) {
            throw new InvalidArgumentException('Lab key must have >=32 bytes.');
        }
        if ($timestamp <= 0 || $timestamp >= 9999999999
            || !preg_match('/^nonce-demo-[a-z0-9-]{8,68}$/D', $nonce)
            || strlen($body) === 0 || strlen($body) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Invalid synthetic transport request.');
        }
        // The test signer requires exact JSON object syntax. Raw bytes are
        // signed, so a body reserialization or whitespace change is detected.
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid synthetic JSON body.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Invalid synthetic JSON object.');
        }

        $digest = hash('sha256', $body);
        $timestampString = (string) $timestamp;
        return [
            self::VERSION_HEADER => self::VERSION,
            self::KEY_HEADER => self::KEY_ID,
            self::TIME_HEADER => $timestampString,
            self::NONCE_HEADER => $nonce,
            self::DIGEST_HEADER => $digest,
            self::SIGNATURE_HEADER => self::signature(
                $ephemeralKey, $timestampString, $nonce, $digest,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $providedHeaders
     * @return string Lab result code only. No state change or side effect.
     */
    public static function verifyForLab(
        string $method,
        string $path,
        string $body,
        array $providedHeaders,
        string $ephemeralKey,
        int $syntheticNow,
    ): string {
        if ($method !== self::METHOD || $path !== self::PATH) {
            return 'wire_wrong_target_demo';
        }
        if (strlen($body) === 0 || strlen($body) > self::MAX_BYTES) {
            return 'wire_invalid_size_demo';
        }
        if (strlen($ephemeralKey) < 32) {
            return 'wire_missing_key_demo';
        }
        $headers = [];
        foreach ($providedHeaders as $name => $value) {
            if (!is_string($name) || !is_string($value)
                || !preg_match('/^X-Imagine-Lab-[A-Za-z0-9-]+$/iD', $name)) {
                return 'wire_invalid_headers_demo';
            }
            $key = strtolower($name);
            if (array_key_exists($key, $headers)) {
                return 'wire_invalid_headers_demo';
            }
            $headers[$key] = $value;
        }
        $keys = array_map('strtolower', [
            self::VERSION_HEADER, self::KEY_HEADER, self::TIME_HEADER,
            self::NONCE_HEADER, self::DIGEST_HEADER, self::SIGNATURE_HEADER,
        ]);
        $actual = array_keys($headers);
        sort($keys, SORT_STRING);
        sort($actual, SORT_STRING);
        if ($keys !== $actual) {
            return 'wire_invalid_headers_demo';
        }
        $get = static fn (string $name): string => $headers[strtolower($name)];
        if ($get(self::VERSION_HEADER) !== self::VERSION
            || $get(self::KEY_HEADER) !== self::KEY_ID) {
            return 'wire_wrong_identity_demo';
        }
        $timestamp = $get(self::TIME_HEADER);
        $nonce = $get(self::NONCE_HEADER);
        if (!preg_match('/^[1-9][0-9]{9}$/D', $timestamp)
            || !preg_match('/^nonce-demo-[a-z0-9-]{8,68}$/D', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/D', $get(self::DIGEST_HEADER))
            || !preg_match('/^[a-f0-9]{64}$/D', $get(self::SIGNATURE_HEADER))) {
            return 'wire_invalid_headers_demo';
        }
        $issuedAt = (int) $timestamp;
        if ($syntheticNow < 1
            || $issuedAt > $syntheticNow + 15
            || $issuedAt < $syntheticNow - 120) {
            return 'wire_expired_demo';
        }
        if (!hash_equals(hash('sha256', $body), $get(self::DIGEST_HEADER))) {
            return 'wire_body_modified_demo';
        }
        $expected = self::signature(
            $ephemeralKey, $timestamp, $nonce, $get(self::DIGEST_HEADER),
        );
        if (!hash_equals($expected, $get(self::SIGNATURE_HEADER))) {
            return 'wire_bad_signature_demo';
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'wire_invalid_body_demo';
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            return 'wire_invalid_body_demo';
        }
        return 'wire_valid_demo';
    }

    private static function signature(
        string $key,
        string $timestamp,
        string $nonce,
        string $bodySha,
    ): string {
        $canonical = implode("\n", [
            self::METHOD,
            self::PATH,
            self::VERSION,
            self::KEY_ID,
            $timestamp,
            $nonce,
            $bodySha,
        ]);
        return hash_hmac('sha256', $canonical, $key);
    }
}
