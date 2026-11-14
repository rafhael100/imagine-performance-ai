<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Test-only, fail-closed entitlement decision against an INDEPENDENT simulated
 * Portal registry snapshot. A signed grant is not proof of an active agreement,
 * account ownership or an unrevoked advertiser consent.
 *
 * A real integration MUST replace the fixture resolver with authenticated Portal
 * IAM, trusted database ownership, consent/revocation reads and an audit trail.
 */
final class PortalAdsEntitlementSnapshotLab
{
    private const FIELDS = [
        'source','tenant_id','agreement_id','period','provider','account_id',
        'credential_reference','consent_reference','agreement_status',
        'agreement_valid_from','agreement_valid_until','account_status',
        'owner_tenant_id','consent_status','consent_granted_at',
        'consent_expires_at','consent_revoked_at','permission','observed_at',
    ];

    /**
     * @param array<string,mixed> $signedGrant UNTRUSTED until later HMAC verification
     * @param callable(array<string,string>):array<string,mixed> $resolveIndependentSnapshot
     * @return array{entitlement_snapshot_sha256:string,entitlement_snapshot_verified_in_lab:bool,portal_iam_authenticated:bool,consent_verified_in_real_database:bool,provider_account_owner_verified:bool,real_api_request_authorized:bool}
     */
    public static function review(
        array $signedGrant,
        string $tenant,
        string $agreement,
        string $period,
        string $account,
        string $credentialReference,
        string $consentReference,
        int $now,
        callable $resolveIndependentSnapshot
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB') !== '1') {
            throw new RuntimeException('Portal entitlement lab disabled.');
        }
        if ($now <= 0) {
            throw new InvalidArgumentException('Invalid entitlement evaluation time.');
        }
        // The lookup selector is built from independently expected values,
        // NOT the tenant/account claimed by an untrusted signed envelope.
        $selector = [
            'tenant_id' => $tenant,
            'agreement_id' => $agreement,
            'period' => $period,
            'account_id' => $account,
        ];
        foreach ([
            'tenant_id' => $tenant,
            'agreement_id' => $agreement,
            'period' => $period,
            'provider' => 'chatgpt_ads',
            'ad_account_id' => $account,
            'credential_reference' => $credentialReference,
            'consent_reference' => $consentReference,
            'permission' => 'ads.insights.read',
        ] as $name => $expected) {
            if (!is_string($expected) ||
                !is_string($signedGrant[$name] ?? null) ||
                !hash_equals($expected, $signedGrant[$name])) {
                throw new InvalidArgumentException('Portal entitlement scope mismatch.');
            }
        }
        if (!is_int($signedGrant['issued_at'] ?? null) ||
            $signedGrant['issued_at'] > $now + 30 ||
            $signedGrant['issued_at'] <= 0) {
            throw new InvalidArgumentException('Invalid grant issuance context.');
        }
        try {
            $snapshot = $resolveIndependentSnapshot($selector);
        } catch (Throwable) {
            throw new RuntimeException('Independent entitlement registry unavailable.');
        }
        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Invalid entitlement snapshot.');
        }
        $keys = array_keys($snapshot);
        $expectedKeys = self::FIELDS;
        sort($keys, SORT_STRING);
        sort($expectedKeys, SORT_STRING);
        if ($keys !== $expectedKeys) {
            throw new InvalidArgumentException('Unexpected entitlement snapshot schema.');
        }
        foreach ([
            'source' => 'synthetic_registry',
            'tenant_id' => $tenant,
            'agreement_id' => $agreement,
            'period' => $period,
            'provider' => 'chatgpt_ads',
            'account_id' => $account,
            'credential_reference' => $credentialReference,
            'consent_reference' => $consentReference,
            'agreement_status' => 'synthetic_active',
            'account_status' => 'synthetic_active',
            'owner_tenant_id' => $tenant,
            'consent_status' => 'synthetic_granted',
            'permission' => 'ads.insights.read',
        ] as $field => $expected) {
            if (!is_string($snapshot[$field]) ||
                !hash_equals($expected, $snapshot[$field])) {
                throw new InvalidArgumentException('Inactive or mismatched entitlement.');
            }
        }
        foreach ([
            'agreement_valid_from', 'agreement_valid_until', 'consent_granted_at',
            'consent_expires_at', 'observed_at',
        ] as $field) {
            if (!is_int($snapshot[$field]) || $snapshot[$field] <= 0) {
                throw new InvalidArgumentException('Invalid entitlement timestamp.');
            }
        }
        if ($snapshot['consent_revoked_at'] !== null ||
            $snapshot['agreement_valid_from'] > $signedGrant['issued_at'] ||
            $snapshot['agreement_valid_from'] > $now ||
            $snapshot['agreement_valid_until'] <= $now ||
            $snapshot['consent_granted_at'] > $signedGrant['issued_at'] ||
            $snapshot['consent_expires_at'] <= $now ||
            $snapshot['consent_expires_at'] <= $snapshot['consent_granted_at'] ||
            $snapshot['observed_at'] > $now ||
            $now - $snapshot['observed_at'] > 5) {
            throw new InvalidArgumentException('Expired, stale or revoked simulated entitlement.');
        }
        // A safe fingerprint for CI review; no names, URLs, tokens or PII.
        ksort($snapshot, SORT_STRING);
        return [
            'entitlement_snapshot_sha256' => hash(
                'sha256',
                json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            ),
            'entitlement_snapshot_verified_in_lab' => true,
            'portal_iam_authenticated' => false,
            'consent_verified_in_real_database' => false,
            'provider_account_owner_verified' => false,
            'real_api_request_authorized' => false,
        ];
    }
}
