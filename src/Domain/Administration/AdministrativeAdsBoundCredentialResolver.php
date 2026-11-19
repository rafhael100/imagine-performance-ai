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
        #[\SensitiveParameter] private readonly Closure $readByReference
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
            if (!is_array($candidate)
                || !isset($candidate['credential_ref'], $candidate['company_id'],
                    $candidate['provider'], $candidate['account_id'], $candidate['lease'])
                || !is_string($candidate['credential_ref'])
                || !hash_equals($reference, $candidate['credential_ref'])
                || $candidate['company_id'] !== $company
                || $candidate['provider'] !== $provider
                || $candidate['account_id'] !== $account
                || !is_string($candidate['lease'])
                || !hash_equals($expectedLease, $candidate['lease'])) {
                return null;
            }

            // Fail closed when the source changes during a slow secret lookup.
            $latest = $this->gate->credentialReference($company, $provider, $account, $expectedLease);
            return $latest !== null && hash_equals($reference, $latest) ? $candidate : null;
        } catch (Throwable) {
            // Never surface callback exception text or chain to callers.
            return null;
        }
    }
}
