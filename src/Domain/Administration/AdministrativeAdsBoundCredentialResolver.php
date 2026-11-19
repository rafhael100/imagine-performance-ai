<?php
declare(strict_types=1);

namespace Imagine\Performance\Administration;

use Closure;
use Throwable;

/**
 * LAB-only custody boundary between the trusted account mandate and a secret
 * provider. Never pass a request-selected reference or a bearer to this gate.
 *
 * This is not a Vault implementation and is NOT wired to the live receiver.
 */
final class AdministrativeAdsBoundCredentialResolver
{
    public function __construct(
        private readonly AdministrativeAdsAccountMandateGate $gate,
        #[\SensitiveParameter] private readonly Closure $readByReference,
        #[\SensitiveParameter] private readonly ?Closure $clock = null
    ) {}

    public function __debugInfo(): array
    {
        return ['type' => 'ads_credential_reference_guard', 'operational' => false];
    }

    /**
     * The secret provider receives only the CURRENT opaque ref plus bound
     * company/provider/account/lease. Both scopes are revalidated after the
     * callback to catch revocation/rotation while credentials are being read.
     * A returned bearer is never logged or exposed by debug output.
     */
    public function __invoke(
        string $company, string $provider, string $account,
        #[\SensitiveParameter] string $expectedLease
    ): ?array {
        try {
            $reference = $this->gate->credentialReference($company, $provider, $account, $expectedLease);
            if ($reference === null) return null;

            $candidate = ($this->readByReference)(
                $reference, $company, $provider, $account, $expectedLease
            );
            // A credential source must not use the bound lookup as a carrier
            // for extra claims, tenant metadata or unvalidated headers.
            // Match the exact read-only transport envelope before returning.
            $expectedFields = [
                'access_token', 'account_id', 'company_id', 'credential_ref',
                'expires_at', 'lease', 'provider',
            ];
            if ($provider === 'google_ads') {
                $expectedFields[] = 'login_customer_id';
                if (is_array($candidate) && array_key_exists('developer_token', $candidate)) {
                    // Legacy Google field is validated but never forwarded.
                    $expectedFields[] = 'developer_token';
                }
            }
            $actualFields = is_array($candidate) ? array_keys($candidate) : [];
            // The injected clock exists only for deterministic, offline tests;
            // production defaults to the local server clock. Validate type.
            $now = $this->clock === null ? time() : ($this->clock)();
            sort($actualFields, SORT_STRING);
            sort($expectedFields, SORT_STRING);
            if (!is_array($candidate) || $actualFields !== $expectedFields
                || !is_string($candidate['credential_ref'])
                || !hash_equals($reference, $candidate['credential_ref'])
                || $candidate['company_id'] !== $company
                || $candidate['provider'] !== $provider
                || $candidate['account_id'] !== $account
                || !is_string($candidate['lease'])
                || !hash_equals($expectedLease, $candidate['lease'])
                || !self::headerToken($candidate['access_token'], 4096)
                || !is_int($candidate['expires_at'])
                || !is_int($now) || $now <= 0
                || $candidate['expires_at'] <= $now
                || ($provider === 'google_ads'
                    && ($candidate['login_customer_id'] !== null
                        && (!is_string($candidate['login_customer_id'])
                            || preg_match('/\A[0-9]{10}\z/D', $candidate['login_customer_id']) !== 1)))
                || ($provider === 'google_ads' && array_key_exists('developer_token', $candidate)
                    && !self::headerToken($candidate['developer_token'], 128))) {
                return null;
            }

            // Fail closed when the source changes during a slow secret lookup.
            $latest = $this->gate->credentialReference($company, $provider, $account, $expectedLease);
            if ($latest === null || !hash_equals($reference, $latest)) return null;

            // A secret may expire while the second S2S/account gate is being
            // checked. Never return an already expired bearer, even if both
            // mandate snapshots remain unchanged.
            $afterGate = $this->clock === null ? time() : ($this->clock)();
            return is_int($afterGate) && $afterGate > 0 && $afterGate >= $now
                && $candidate['expires_at'] > $afterGate ? $candidate : null;
        } catch (Throwable) {
            // Never surface callback exception text or chain to callers.
            return null;
        }
    }
    private static function headerToken(#[\SensitiveParameter] mixed $value, int $max): bool
    {
        return is_string($value) && strlen($value) <= $max
            && preg_match('/\A[A-Za-z0-9._~+\/-]+=*\z/D', $value) === 1;
    }

}
