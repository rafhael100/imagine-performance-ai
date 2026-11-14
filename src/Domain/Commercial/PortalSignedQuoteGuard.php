<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * OFFLINE ONLY: validates a signed claim about a Portal Performance quotation.
 * The caller must supply a trusted tenant, pinned catalog version, a server-owned
 * key resolver and an ATOMIC durable nonce claim. No HTTP route or credential
 * provisioning is included. A valid HMAC does not prove real IAM, Ads spend or
 * permission to bill.
 */
final class PortalSignedQuoteGuard
{
    private const MAX_ENVELOPE_BYTES = 8192;
    private const MAX_LIFETIME_SECONDS = 300;
    private const CLOCK_SKEW_SECONDS = 30;
    private const AUDIENCE = 'imagine-performance-finance-preview';

    /** Deterministic bytes for a synthetic Portal signer and verifier.
     * Never sign a user-supplied envelope with a production key in this module.
     * @param array<string,mixed> $envelope
     */
    public static function signingBytes(array $envelope): string
    {
        $expected = ['audience', 'expires_at', 'issued_at', 'key_id', 'nonce', 'quote', 'tenant_id'];
        $keys = array_keys($envelope);
        sort($keys, SORT_STRING);
        if ($keys !== $expected) {
            throw new InvalidArgumentException('Unexpected signed envelope fields.');
        }
        $encoded = json_encode(self::sortMap($envelope), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > self::MAX_ENVELOPE_BYTES) {
            throw new InvalidArgumentException('Oversized signed envelope.');
        }
        return "imagine:portal:performance:quote:v1\n" . $encoded;
    }

    /**
     * @param array<string,mixed> $signed envelope with exact fields and signature
     * @param callable(string,string):?string $resolveKey trusted server-owned resolver (tenant, key_id)
     * @param callable(string,string,string,int):bool $claimNonce ATOMIC and durable once-only claim
     * @param array<string,int> $actualMediaCents unverified offline spend input
     * @return array<string,mixed>
     */
    public static function verifyAndPreview(
        array $signed,
        string $expectedTenant,
        string $expectedCatalogSha256,
        string $period,
        array $actualMediaCents,
        int $now,
        callable $resolveKey,
        callable $claimNonce
    ): array {
        $keys = array_keys($signed);
        sort($keys, SORT_STRING);
        if ($keys !== ['audience', 'expires_at', 'issued_at', 'key_id', 'nonce', 'quote', 'signature', 'tenant_id']) {
            throw new InvalidArgumentException('Invalid signed message shape.');
        }
        $tenant = $signed['tenant_id'];
        $keyId = $signed['key_id'];
        $nonce = $signed['nonce'];
        $issued = $signed['issued_at'];
        $expires = $signed['expires_at'];
        $signature = $signed['signature'];
        if (!is_string($tenant) || !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !is_string($expectedTenant) || !hash_equals($expectedTenant, $tenant) ||
            !is_string($keyId) || !preg_match('/\Ak_[A-Za-z0-9_-]{4,64}\z/D', $keyId) ||
            !is_string($nonce) || !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D', $nonce) ||
            !is_int($issued) || !is_int($expires) || !is_int($now) ||
            $issued < 0 || $issued > $now + self::CLOCK_SKEW_SECONDS ||
            $expires <= $now || $expires <= $issued || $expires - $issued > self::MAX_LIFETIME_SECONDS ||
            $signed['audience'] !== self::AUDIENCE ||
            !is_string($signature) || !preg_match('/\A[a-f0-9]{64}\z/D', $signature) ||
            !is_array($signed['quote']) ||
            !preg_match('/\A[a-f0-9]{64}\z/D', $expectedCatalogSha256)) {
            throw new InvalidArgumentException('Invalid signature scope or expiration.');
        }

        $unsigned = $signed;
        unset($unsigned['signature']);
        $bytes = self::signingBytes($unsigned);
        $key = $resolveKey($tenant, $keyId);
        if (!is_string($key) || strlen($key) < 32 ||
            !hash_equals(hash_hmac('sha256', $bytes, $key), $signature)) {
            throw new InvalidArgumentException('Invalid Portal quote signature.');
        }
        $quote = $signed['quote'];
        if (!isset($quote['catalog_version']) || !is_string($quote['catalog_version']) ||
            !hash_equals($expectedCatalogSha256, $quote['catalog_version'])) {
            throw new InvalidArgumentException('Portal catalog version not approved.');
        }
        // Domain checks MUST occur before claiming nonce.
        $preview = PortalQuoteReconciliationPreview::build($quote, $period, $actualMediaCents);
        if ($claimNonce($tenant, $keyId, $nonce, $expires) !== true) {
            throw new InvalidArgumentException('Portal signed message already used or nonce store unavailable.');
        }
        return [
            'tenant_id' => $tenant,
            'quote_claim_hmac_verified' => true,
            'key_id' => $keyId,
            'nonce' => $nonce,
            'quote_claim_sha256' => hash('sha256', $bytes),
            'state' => 'signed_claim_only_requires_portal_iam_real_spend_finance_review',
            'portal_iam_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
            'preview' => $preview,
        ];
    }

    /** @param array<string,mixed> $value */
    private static function sortMap(array $value): array
    {
        if ($value === [] || array_is_list($value)) {
            throw new InvalidArgumentException('Signed map must have named fields.');
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Signed map keys must be strings.');
            }
            if (is_array($item)) {
                $value[$key] = self::sortMap($item);
            } elseif (!is_string($item) && !is_int($item) && !is_bool($item)) {
                throw new InvalidArgumentException('Signed values must be primitive and deterministic.');
            }
        }
        return $value;
    }
}
