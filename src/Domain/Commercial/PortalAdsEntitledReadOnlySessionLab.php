<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use RuntimeException;

/**
 * Staged gate for CI: check independent agreement/owner/consent snapshot
 * BEFORE durable nonce claim and BEFORE any synthetic provider fixture GET.
 *
 * Production MUST use an authenticated authoritative Portal read, session
 * ownership, auditable consent revocation and trusted S2S workload identity.
 * This class intentionally cannot authenticate IAM or run real network I/O.
 */
final class PortalAdsEntitledReadOnlySessionLab
{
    /**
     * @param array<string,mixed> $signedGrant
     * @param array<string,mixed> $binding
     * @param callable(array<string,string>):array<string,mixed> $resolvePolicy
     * @param callable(string,string):?string $resolveSigningKey
     * @param callable(string,string,int):bool $claimNonce
     * @param callable(array<string,mixed>):array<string,mixed> $fixtureTransport
     * @return array<string,mixed>
     */
    public static function collect(
        array $signedGrant,
        array $binding,
        string $tenant,
        string $agreement,
        string $period,
        string $account,
        string $credentialReference,
        string $consentReference,
        string $timeZone,
        int $now,
        callable $resolvePolicy,
        callable $resolveSigningKey,
        callable $claimNonce,
        callable $fixtureTransport
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB') !== '1' ||
            getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB') !== '1') {
            throw new RuntimeException('Portal entitlement-gated session disabled.');
        }
        $policy = PortalAdsEntitlementSnapshotLab::review(
            $signedGrant, $tenant, $agreement, $period, $account,
            $credentialReference, $consentReference, $now, $resolvePolicy
        );
        // HMAC, TTL, account binding and durable nonce claim are delegated to
        // the existing tested boundary; snapshot alone NEVER allows access.
        $session = PortalAdsGrantedReadOnlySessionLab::collect(
            $signedGrant, $binding, $tenant, $agreement, $period, $account,
            $credentialReference, $consentReference, $timeZone, $now,
            $resolveSigningKey, $claimNonce, $fixtureTransport
        );
        foreach ([
            'portal_iam_authenticated', 'real_s2s_identity_authenticated',
            'real_api_request_authorized', 'provider_account_authenticated',
            'api_key_verified_by_provider', 'actual_network_executed',
            'provider_spend_verified', 'finance_approved', 'billable',
            'asaas_write_enabled', 'ads_write_enabled',
        ] as $flag) {
            if (($session[$flag] ?? null) !== false) {
                throw new RuntimeException('CI-only entitlement gate cannot grant live authority.');
            }
        }
        return array_replace($session, [
            'state' => 'synthetic_entitlement_checked_before_signed_portal_grant',
            'entitlement_snapshot_sha256' => $policy['entitlement_snapshot_sha256'],
            'entitlement_snapshot_verified_in_lab' => true,
            'portal_iam_authenticated' => false,
            'consent_verified_in_real_database' => false,
            'provider_account_owner_verified' => false,
            'real_api_request_authorized' => false,
            'billable' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ]);
    }
}
