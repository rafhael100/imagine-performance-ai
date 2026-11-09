<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use InvalidArgumentException;
use JsonException;

/**
 * LAB-ONLY protocol simulation, intentionally not registered in app autoload or HTTP routes.
 * HMAC is verified with test-generated ephemeral bytes, NOT production workload identity.
 * Replay cache and grants are in memory; do not use this for real S2S authentication.
 */
final class SyntheticS2SProtocol
{
    private const VERSION = 'perf.portal.s2s.synthetic.v1';
    private const PRODUCER = 'portal-imagine-mock';
    private const AUDIENCE = 'performance-ai-mock';
    private const MAX_TTL = 300;

    /** @var array<string,true> */
    private array $seenRequestIds = [];

    /** @var array<string,true> */
    private array $seenNonces = [];

    /** @return array<string,mixed> */
    public static function mintForLab(
        string $organization,
        string $tenant,
        string $service,
        string $requestId,
        string $nonce,
        int $issuedAt,
        int $expiresAt,
        int $grantRevision,
        string $payloadDigest,
        string $testKey
    ): array {
        if (strlen($testKey) < 32) {
            throw new InvalidArgumentException('Synthetic key must be ephemeral and at least 32 bytes.');
        }
        $core = [
            'schema_version' => self::VERSION,
            'producer' => self::PRODUCER,
            'audience' => self::AUDIENCE,
            'organization_ref' => $organization,
            'tenant_ref' => $tenant,
            'service_ref' => $service,
            'request_id' => $requestId,
            'nonce' => $nonce,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'grant_revision' => $grantRevision,
            'payload_sha256' => $payloadDigest,
        ];
        $unsigned = $core + ['signature' => str_repeat('0', 64)];
        if (!self::validShape($unsigned)) {
            throw new InvalidArgumentException('Invalid synthetic envelope fields.');
        }
        return $core + ['signature' => self::mac($core, $testKey)];
    }

    /**
     * The expected binding, grant version and grant status are injected by a
     * test, NOT fetched from Portal, DB, RLS, IAM, OAuth or production auth.
     */
    public function verifyForLab(
        mixed $envelope,
        string $testKey,
        string $expectedOrganization,
        string $expectedTenant,
        string $expectedService,
        string $expectedPayloadDigest,
        int $expectedGrantRevision,
        int $now,
        bool $mockGrantActive
    ): string {
        if (strlen($testKey) < 32) {
            return 'invalid_lab_key';
        }
        if (!is_array($envelope) || !self::validShape($envelope)) {
            return 'invalid_lab_envelope';
        }

        $signed = $envelope;
        unset($signed['signature']);
        try {
            $signature = self::mac($signed, $testKey);
        } catch (JsonException) {
            return 'invalid_lab_envelope';
        }
        if (!hash_equals($signature, $envelope['signature'])) {
            return 'invalid_lab_signature';
        }

        if ($envelope['organization_ref'] !== $expectedOrganization
            || $envelope['tenant_ref'] !== $expectedTenant
            || $envelope['service_ref'] !== $expectedService
            || $envelope['payload_sha256'] !== $expectedPayloadDigest) {
            return 'mock_binding_denied';
        }

        if (!$mockGrantActive || $expectedGrantRevision < 1) {
            return 'mock_grant_revoked';
        }
        if ($envelope['grant_revision'] !== $expectedGrantRevision) {
            return 'mock_grant_revision_stale';
        }

        if ($envelope['issued_at'] > $now || $envelope['expires_at'] <= $now
            || ($now - $envelope['issued_at']) > self::MAX_TTL) {
            return 'mock_message_expired';
        }

        $request = $envelope['producer'].':'.$envelope['request_id'];
        $nonce = $envelope['producer'].':'.$envelope['nonce'];
        if (isset($this->seenRequestIds[$request]) || isset($this->seenNonces[$nonce])) {
            return 'mock_replay_denied';
        }

        $this->seenRequestIds[$request] = true;
        $this->seenNonces[$nonce] = true;
        return 'accepted_synthetic_only';
    }

    /** @param array<string,mixed> $core */
    private static function mac(array $core, string $key): string
    {
        // Strict, stable ordered field list: input key order cannot alter MAC.
        return hash_hmac('sha256', json_encode([
            $core['schema_version'],
            $core['producer'],
            $core['audience'],
            $core['organization_ref'],
            $core['tenant_ref'],
            $core['service_ref'],
            $core['request_id'],
            $core['nonce'],
            $core['issued_at'],
            $core['expires_at'],
            $core['grant_revision'],
            $core['payload_sha256'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $key);
    }

    private static function validShape(array $v): bool
    {
        $fields = ['schema_version', 'producer', 'audience', 'organization_ref',
            'tenant_ref', 'service_ref', 'request_id', 'nonce', 'issued_at',
            'expires_at', 'grant_revision', 'payload_sha256', 'signature'];
        $actual = array_keys($v);
        sort($actual, SORT_STRING);
        sort($fields, SORT_STRING);
        if ($actual !== $fields) {
            return false;
        }

        if ($v['schema_version'] !== self::VERSION
            || $v['producer'] !== self::PRODUCER
            || $v['audience'] !== self::AUDIENCE) {
            return false;
        }
        foreach (['organization_ref' => 'org', 'tenant_ref' => 'perf',
                  'service_ref' => 'svc', 'request_id' => 'req', 'nonce' => 'nonce'] as $name => $prefix) {
            if (!is_string($v[$name])
                || !preg_match('/^'.$prefix.'-demo-[a-z0-9-]{1,80}$/D', $v[$name])) {
                return false;
            }
        }
        if (!is_int($v['issued_at']) || !is_int($v['expires_at'])
            || $v['issued_at'] < 1 || $v['expires_at'] <= $v['issued_at']
            || ($v['expires_at'] - $v['issued_at']) > self::MAX_TTL
            || !is_int($v['grant_revision']) || $v['grant_revision'] < 1) {
            return false;
        }
        return is_string($v['payload_sha256'])
            && (bool) preg_match('/^[0-9a-f]{64}$/D', $v['payload_sha256'])
            && is_string($v['signature'])
            && (bool) preg_match('/^[0-9a-f]{64}$/D', $v['signature']);
    }
}
