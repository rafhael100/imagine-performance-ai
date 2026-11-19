<?php
declare(strict_types=1);

namespace Imagine\Performance\Administration;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Offline account-mandate gate. Its two resolvers MUST be trusted server-side
 * sources, not a request, browser session, body, remote response or JWT claim.
 * This class does not connect to Portal SQL or enable private Ads reads.
 */
final class AdministrativeAdsAccountMandateGate
{
    public const VERSION = 'imagine.performance.ads.account-mandate.v2';
    public const SERVICE_VERSION = 'imagine.performance.ads.service-delegation.v1';
    public const OPERATION = 'reports.read';
    public const PURPOSE = 'ads_reporting_read';

    public function __construct(
        private readonly string $principal,
        #[\SensitiveParameter] private readonly Closure $resolveService,
        #[\SensitiveParameter] private readonly Closure $resolveAccount,
        #[\SensitiveParameter] private readonly ?Closure $clock = null
    ) {
        if (!self::identifier($principal)) self::deny();
    }

    /** No grants, credential refs, callbacks or underlying errors in debug logs. */
    public function __debugInfo(): array
    {
        return ['type' => 'account_mandate_candidate', 'operational' => false];
    }

    /**
     * Re-resolves BOTH scopes on every call. Returns no user data or token.
     * The digest is change detection, NOT an authentication signature.
     */
    public function current(#[\SensitiveParameter] string $company, string $provider, string $account): array
    {
        try {
            if (!self::identifier($company) || !in_array($provider, ['google_ads', 'meta_ads'], true)
                || !preg_match($provider === 'google_ads' ? '/\A[0-9]{10}\z/D' : '/\A[0-9]{5,32}\z/D', $account)) {
                self::deny();
            }
            $now = $this->clock === null ? time() : ($this->clock)();
            if (!is_int($now) || $now <= 0) self::deny();

            $service = ($this->resolveService)($this->principal, $company, self::OPERATION);
            // Do not even query the account authority when delegation is absent
            // or revoked. This is a privileged server-side resolver boundary,
            // not just a check before issuing a final read-only lease.
            self::serviceIsCurrent($service, $this->principal, $company, $now);
            $mandate = ($this->resolveAccount)($company, $provider, $account, self::OPERATION, $this->principal);
            self::mandateIsCurrent($mandate, $this->principal, $company, $provider, $account, $now);

            // The privileged account lookup can outlive or revoke the S2S
            // delegation. A valid snapshot taken BEFORE that lookup is not
            // sufficient: require the same current service snapshot AFTER it.
            // This is a lab-only consistency check, not an atomic SQL read.
            $serviceAfterAccount = ($this->resolveService)($this->principal, $company, self::OPERATION);

            // Resolvers may take long enough for a previously valid service or
            // account mandate to expire. Do not issue a lease from a snapshot
            // that was current only BEFORE those server-side callbacks ran.
            $afterResolvers = $this->clock === null ? time() : ($this->clock)();
            if (!is_int($afterResolvers) || $afterResolvers <= 0
                || $afterResolvers < $now
                || !self::future($service['expires_at'], $afterResolvers)
                || !self::future($mandate['expires_at'], $afterResolvers)) {
                self::deny();
            }
            self::serviceIsCurrent($serviceAfterAccount, $this->principal, $company, $afterResolvers);
            $serviceBeforeComparison = $service;
            $serviceAfterComparison = $serviceAfterAccount;
            ksort($serviceBeforeComparison, SORT_STRING);
            ksort($serviceAfterComparison, SORT_STRING);
            if ($serviceBeforeComparison !== $serviceAfterComparison) self::deny();

            // The source must supply the persisted revisions; merely knowing
            // account_id or holding a token cannot produce a valid grant.
            // Resolver array insertion order is not authority state. Keep the
            // lease stable for identical snapshots returned by different
            // PostgreSQL projections while still binding every validated field.
            $serviceSnapshot = $service;
            $accountSnapshot = $mandate;
            ksort($serviceSnapshot, SORT_STRING);
            ksort($accountSnapshot, SORT_STRING);
            $canonical = json_encode([
                'domain' => 'imagine.performance.ads.account-mandate.lease.v2',
                'service' => $serviceSnapshot,
                'account' => $accountSnapshot,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return [
                'lease' => hash('sha256', $canonical),
                'credential_ref' => $mandate['credential_ref'],
                'consent_reference' => $mandate['consent_reference'],
                'expires_at' => min($service['expires_at'], $mandate['expires_at']),
                'version' => self::VERSION,
            ];
        } catch (Throwable) {
            // No untrusted resolver message, secret, body or previous exception.
            self::deny();
        }
    }

    /** Suitable as a fail-closed authorize callback in a LAB-only composition. */
    public function lease(string $company, string $provider, string $account): ?string
    {
        try {
            return $this->current($company, $provider, $account)['lease'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A secret resolver must check this bound reference BEFORE returning a token.
     * This returns only an opaque reference; it never retrieves the secret.
     */
    public function credentialReference(
        string $company, string $provider, string $account,
        #[\SensitiveParameter] string $expectedLease
    ): ?string {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $expectedLease)) return null;
        try {
            $current = $this->current($company, $provider, $account);
            return hash_equals($expectedLease, $current['lease']) ? $current['credential_ref'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function serviceIsCurrent(
        #[\SensitiveParameter] mixed $grant, string $principal, string $company, int $now
    ): void {
        self::exactKeys($grant, [
            'active', 'company_id', 'expires_at', 'operation', 'principal',
            'revision', 'revoked_at', 'version',
        ]);
        if ($grant['version'] !== self::SERVICE_VERSION || $grant['active'] !== true
            || $grant['principal'] !== $principal || $grant['company_id'] !== $company
            || $grant['operation'] !== self::OPERATION || $grant['revoked_at'] !== null
            || !self::revision($grant['revision']) || !self::future($grant['expires_at'], $now)) {
            self::deny();
        }
    }

    private static function mandateIsCurrent(
        #[\SensitiveParameter] mixed $grant, string $principal, string $company, string $provider, string $account, int $now
    ): void {
        self::exactKeys($grant, [
            'account_id', 'company_id', 'consent_reference', 'credential_ref', 'service_principal',
            'expires_at', 'operation', 'provider', 'purpose', 'revision',
            'revoked_at', 'status', 'version',
        ]);
        if ($grant['version'] !== self::VERSION || $grant['status'] !== 'active'
            || $grant['company_id'] !== $company || $grant['service_principal'] !== $principal
            || $grant['provider'] !== $provider
            || $grant['account_id'] !== $account || $grant['operation'] !== self::OPERATION
            || $grant['purpose'] !== self::PURPOSE || $grant['revoked_at'] !== null
            || !self::revision($grant['revision']) || !self::future($grant['expires_at'], $now)
            || !self::opaqueRef($grant['consent_reference']) || !self::opaqueRef($grant['credential_ref'])) {
            self::deny();
        }
    }

    private static function exactKeys(#[\SensitiveParameter] mixed $value, array $expected): void
    {
        if (!is_array($value) || array_is_list($value)) self::deny();
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) self::deny();
    }

    private static function identifier(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{8,96}\z/D', $value) === 1;
    }

    private static function opaqueRef(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}\z/D', $value) === 1;
    }

    private static function revision(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    private static function future(mixed $epoch, int $now): bool
    {
        return is_int($epoch) && $epoch > $now;
    }

    private static function deny(): never
    {
        throw new RuntimeException('Account mandate denied');
    }
}
