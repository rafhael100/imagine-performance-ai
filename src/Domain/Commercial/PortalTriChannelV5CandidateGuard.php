<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * Verify Portal PERFORMANCE TRI-CHANNEL V5 candidate only in isolated CI.
 * NO IAM/real provider evidence/billing. Signed v1/v2 cannot be upgraded to v5.
 */
final class PortalTriChannelV5CandidateGuard
{
    private const AUDIENCE = 'imagine-performance-finance-v5-candidate-preview';
    private const CONTRACT = 'performance-trichannel-v5-candidate';
    private const CHANNELS = ['chatgpt_ads', 'google_ads', 'meta_ads'];
    private const MAX_CENTS = 100000000;
    private const MAX_UNSIGNED_BYTES = 8192;

    /**
     * @param array<string,mixed> $signed
     * @param array<string,int> $ownerApprovedMinima expected trusted server contract, NOT from browser
     * @param array<string,int> $actualUnverifiedCents synthetic spend per channel
     * @param callable(string,string):?string $resolveKey trusted key provider
     * @param callable(string,string,string,int):bool $claimNonce atomic durable claim
     * @return array<string,mixed>
     */
    public static function verify(
        array $signed,
        string $expectedTenant,
        string $expectedPeriod,
        string $expectedCatalogSha256,
        array $ownerApprovedMinima,
        array $actualUnverifiedCents,
        int $now,
        callable $resolveKey,
        callable $claimNonce
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PORTAL_TRICHANNEL_V5_LAB') !== '1') {
            throw new RuntimeException('V5 candidate verifier must not run outside testing.');
        }
        self::exactKeys($signed, [
            'audience','billing_period','contract_version','expires_at','issued_at',
            'key_id','nonce','quote','signature','tenant_id'
        ]);
        if (!is_string($signed['tenant_id']) ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $signed['tenant_id']) ||
            !is_string($expectedTenant) ||
            !hash_equals($expectedTenant, $signed['tenant_id']) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $expectedPeriod) ||
            !is_string($signed['billing_period']) ||
            !hash_equals($expectedPeriod, $signed['billing_period']) ||
            $signed['audience'] !== self::AUDIENCE ||
            $signed['contract_version'] !== self::CONTRACT ||
            !is_string($signed['key_id']) ||
            !preg_match('/\Ak_[A-Za-z0-9_-]{4,64}\z/D', $signed['key_id']) ||
            !is_string($signed['nonce']) ||
            !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D', $signed['nonce']) ||
            !is_int($signed['issued_at']) ||
            !is_int($signed['expires_at']) ||
            $signed['issued_at'] < 1 ||
            $signed['issued_at'] > $now + 30 ||
            $signed['expires_at'] <= $now ||
            $signed['expires_at'] <= $signed['issued_at'] ||
            $signed['expires_at'] - $signed['issued_at'] > 300 ||
            !is_string($signed['signature']) ||
            !preg_match('/\A[a-f0-9]{64}\z/D', $signed['signature']) ||
            !is_string($expectedCatalogSha256) ||
            !preg_match('/\A[a-f0-9]{64}\z/D', $expectedCatalogSha256) ||
            !is_array($signed['quote'])) {
            throw new InvalidArgumentException('Invalid V5 candidate scope, signature metadata or TTL.');
        }
        $ownerKeys = array_keys($ownerApprovedMinima);
        sort($ownerKeys, SORT_STRING);
        if ($ownerKeys !== self::CHANNELS) {
            throw new InvalidArgumentException('Owner-approved minimum price list unavailable.');
        }
        foreach ($ownerApprovedMinima as $floor) {
            if (!is_int($floor) || $floor < 1 || $floor > self::MAX_CENTS) {
                throw new InvalidArgumentException('Untrusted candidate price minimum.');
            }
        }

        $unsigned = $signed;
        unset($unsigned['signature']);
        $canonical = json_encode(self::sortMap($unsigned),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($canonical) > self::MAX_UNSIGNED_BYTES) {
            throw new InvalidArgumentException('V5 signed envelope too large.');
        }
        $bytes = "imagine:portal:performance:quote:v5\n" . $canonical;
        $key = $resolveKey($signed['tenant_id'], $signed['key_id']);
        if (!is_string($key) || strlen($key) < 32 ||
            !hash_equals(hash_hmac('sha256', $bytes, $key), $signed['signature'])) {
            throw new InvalidArgumentException('V5 candidate HMAC invalid.');
        }
        // Perform ALL financial/contract checks before claiming the nonce.
        $quote = $signed['quote'];
        self::exactKeys($quote, [
            'catalog_version','product','currency','gateway','real_charge_enabled',
            'media_by_channel_cents','media_paid_directly_to_platforms',
            'management_fee_basis_points','estimated_management_fee_cents',
            'reference_period','amount_now_cents','settlement_policy'
        ]);
        if (!is_string($quote['catalog_version']) ||
            !hash_equals($expectedCatalogSha256, $quote['catalog_version']) ||
            $quote['product'] !== 'performance' ||
            $quote['currency'] !== 'BRL' ||
            $quote['gateway'] !== 'asaas' ||
            $quote['real_charge_enabled'] !== false ||
            $quote['media_paid_directly_to_platforms'] !== true ||
            $quote['management_fee_basis_points'] !== 2000 ||
            $quote['reference_period'] !== 'monthly' ||
            $quote['settlement_policy'] !==
                'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment' ||
            !is_int($quote['estimated_management_fee_cents']) ||
            !is_int($quote['amount_now_cents']) ||
            !is_array($quote['media_by_channel_cents']) ||
            $quote['media_by_channel_cents'] === [] ||
            count($quote['media_by_channel_cents']) > 3) {
            throw new InvalidArgumentException('V5 owner-approved commercial contract mismatch.');
        }
        $plannedKeys = array_keys($quote['media_by_channel_cents']);
        $actualKeys = array_keys($actualUnverifiedCents);
        sort($plannedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        if ($plannedKeys !== $actualKeys ||
            array_diff($plannedKeys, self::CHANNELS) !== []) {
            throw new InvalidArgumentException('Actual spend differs from candidate contracted channels.');
        }
        $plannedTotal = 0;
        $actualTotal = 0;
        foreach ($quote['media_by_channel_cents'] as $channel => $planned) {
            $spend = $actualUnverifiedCents[$channel];
            if (!is_int($planned) || $planned < $ownerApprovedMinima[$channel] ||
                $planned > self::MAX_CENTS ||
                !is_int($spend) || $spend < 0 || $spend > self::MAX_CENTS) {
                throw new InvalidArgumentException('Channel minimum or unverified expense invalid.');
            }
            $plannedTotal += $planned;
            $actualTotal += $spend;
        }
        $advance = self::fee($plannedTotal);
        $earned = self::fee($actualTotal);
        if ($quote['estimated_management_fee_cents'] !== $advance ||
            $quote['amount_now_cents'] !== $advance) {
            throw new InvalidArgumentException('V5 quoted management fee invalid.');
        }
        if ($claimNonce(
            $signed['tenant_id'], $signed['key_id'], $signed['nonce'], $signed['expires_at']
        ) !== true) {
            throw new InvalidArgumentException('V5 quote nonce already claimed or store unavailable.');
        }
        return [
            'tenant_id' => $signed['tenant_id'],
            'billing_period' => $expectedPeriod,
            'candidate_contract_version' => self::CONTRACT,
            'catalog_sha256' => $expectedCatalogSha256,
            'quote_claim_hmac_verified' => true,
            'billing_period_bound' => true,
            'nonce' => $signed['nonce'],
            'key_id' => $signed['key_id'],
            'quote_claim_sha256' => hash('sha256', $bytes),
            'planned_media_cents' => $plannedTotal,
            'unverified_actual_media_cents' => $actualTotal,
            'estimated_advance_fee_cents' => $advance,
            'unverified_earned_fee_cents' => $earned,
            'review_only_adjustment_cents' => $earned - $advance,
            'state' => 'candidate_v5_signed_but_not_portal_checkout_or_verified_ads',
            'portal_catalog_active' => false,
            'portal_iam_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'billable' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }

    private static function fee(int $media): int
    {
        return intdiv($media * 2000 + 5000, 10000);
    }

    /** @param array<string,mixed> $map @param list<string> $fields */
    private static function exactKeys(array $map, array $fields): void
    {
        $actual = array_keys($map);
        sort($actual, SORT_STRING);
        sort($fields, SORT_STRING);
        if ($actual !== $fields) {
            throw new InvalidArgumentException('Unexpected V5 candidate fields.');
        }
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private static function sortMap(array $map): array
    {
        if ($map === [] || array_is_list($map)) {
            throw new InvalidArgumentException('Unsigned map needs named fields.');
        }
        ksort($map, SORT_STRING);
        foreach ($map as $key => $value) {
            if (!is_string($key)) throw new InvalidArgumentException('Invalid map field.');
            if (is_array($value)) {
                $map[$key] = self::sortMap($value);
            } elseif (!is_bool($value) && !is_int($value) && !is_string($value)) {
                throw new InvalidArgumentException('Unexpected signed value type.');
            }
        }
        return $map;
    }
}
