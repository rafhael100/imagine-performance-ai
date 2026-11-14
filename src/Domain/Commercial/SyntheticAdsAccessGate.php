<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * OFFLINE, TEST-ONLY approval contract for future Google/Meta read-only access.
 *
 * DOES NOT authenticate the Portal, validate OAuth, look up accounts, retrieve
 * secrets or send a network request. The lab assertions must NOT be trusted
 * when supplied by a browser, client or external API. No actual Ads scopes
 * can guarantee read-only Google access; the dispatcher must whitelist calls.
 */
final class SyntheticAdsAccessGate
{
    private const GOOGLE_OAUTH = 'https://www.googleapis.com/auth/adwords';
    private const META_SCOPE = 'ads_read';

    /**
     * The simulated binding is a statement by a trusted SERVER in the future;
     * it currently originates solely from fixtures.
     *
     * @param array<string,mixed> $binding
     * @param array<string,mixed> $proposedPlan
     * @return array<string,mixed>
     */
    public static function inspect(
        string $tenant, string $period, string $channel, string $accountId,
        array $binding, array $proposedPlan, int $now
    ): array {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_ADS_ACCESS_LAB') !== '1') {
            throw new RuntimeException('Provider account access laboratory is disabled.');
        }
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $period) ||
            $now <= 0 || !in_array($channel, ['google_ads','meta_ads'], true)) {
            throw new InvalidArgumentException('Invalid simulated account access scope.');
        }

        $expected = [
            'tenant_id', 'period', 'provider', 'account_id', 'consent_id',
            'credential_reference', 'consent_status', 'consent_issued_at',
            'consent_expires_at', 'required_oauth_scope',
            'metadata_currency', 'metadata_time_zone',
            'metadata_account_access', 'portal_iam_verified',
            'provider_authorization_verified',
        ];
        if ($channel === 'google_ads') {
            $expected[] = 'google_cloud_project_id';
            $expected[] = 'google_api_access_tier';
        } else {
            $expected[] = 'meta_app_id';
        }
        self::exactFields($binding, $expected);
        if ($binding['tenant_id'] !== $tenant ||
            $binding['period'] !== $period ||
            $binding['provider'] !== $channel ||
            $binding['account_id'] !== $accountId ||
            $binding['consent_status'] !== 'active' ||
            !is_int($binding['consent_issued_at']) ||
            !is_int($binding['consent_expires_at']) ||
            $binding['consent_issued_at'] <= 0 ||
            $binding['consent_issued_at'] > $now ||
            $binding['consent_expires_at'] <= $now ||
            $binding['consent_expires_at'] - $binding['consent_issued_at'] > 31536000 ||
            $binding['metadata_currency'] !== 'BRL' ||
            !is_string($binding['metadata_time_zone']) ||
            !in_array($binding['metadata_time_zone'], \DateTimeZone::listIdentifiers(), true) ||
            $binding['metadata_account_access'] !== 'reporting' ||
            // A fixture must never claim identity or authorization was verified.
            $binding['portal_iam_verified'] !== false ||
            $binding['provider_authorization_verified'] !== false) {
            throw new InvalidArgumentException('Inactive, expired, unverified or cross-account binding.');
        }
        if (!is_string($binding['consent_id']) ||
            !preg_match('/\Aconsent_[A-Za-z0-9_-]{8,64}\z/D', $binding['consent_id']) ||
            !is_string($binding['credential_reference']) ||
            !preg_match('/\Aref_[A-Za-z0-9_-]{12,96}\z/D', $binding['credential_reference'])) {
            throw new InvalidArgumentException('Invalid opaque synthetic consent/credential references.');
        }

        if ($channel === 'google_ads') {
            if ($binding['required_oauth_scope'] !== self::GOOGLE_OAUTH ||
                !is_string($binding['google_cloud_project_id']) ||
                !preg_match('/\A[a-z][a-z0-9-]{5,28}[a-z0-9]\z/D', $binding['google_cloud_project_id']) ||
                !in_array($binding['google_api_access_tier'], ['test','explorer','approved_production'], true)) {
                throw new InvalidArgumentException('Google OAuth/Google Cloud project metadata inconsistent.');
            }
            $expectedPlan = SyntheticProviderSpendAdapter::googleRequestPlan($accountId, $period);
            $host = 'googleads.googleapis.com';
        } else {
            if ($binding['required_oauth_scope'] !== self::META_SCOPE ||
                !is_string($binding['meta_app_id']) ||
                !preg_match('/\A[0-9]{5,24}\z/D', $binding['meta_app_id'])) {
                throw new InvalidArgumentException('Meta reporting scope or app metadata invalid.');
            }
            $expectedPlan = SyntheticProviderSpendAdapter::metaRequestPlan($accountId, $period);
            $host = 'graph.facebook.com';
        }
        // Full structural comparison rejects ad-mutating methods, endpoint
        // substitution, arbitrary URL hosts, query injection and added fields.
        if ($proposedPlan !== $expectedPlan ||
            $expectedPlan['read_only_report'] !== true ||
            $expectedPlan['transport_enabled'] !== false) {
            throw new InvalidArgumentException('Only an exact allowlisted reporting plan may be reviewed.');
        }

        return [
            'tenant_id' => $tenant,
            'period' => $period,
            'channel' => $channel,
            'account_id' => $accountId,
            'host' => $host,
            'method' => $expectedPlan['method'],
            'path' => $expectedPlan['path'],
            'body' => $expectedPlan['body'] ?? null,
            'query' => $expectedPlan['query'] ?? null,
            'consent_reference' => $binding['consent_id'],
            'credential_reference' => $binding['credential_reference'],
            'consent_expires_at' => $binding['consent_expires_at'],
            'required_oauth_scope' => $binding['required_oauth_scope'],
            'read_only_report' => true,
            'network_enabled' => false,
            'portal_iam_verified' => false,
            'provider_authorization_verified' => false,
            'provider_spend_verified' => false,
            'ads_write_enabled' => false,
            'asaas_write_enabled' => false,
            'finance_approved' => false,
            'state' => 'synthetic_permission_review_only_no_network',
        ];
    }

    /** @param array<string,mixed> $input @param list<string> $fields */
    private static function exactFields(array $input, array $fields): void
    {
        $keys = array_keys($input);
        sort($keys, SORT_STRING);
        sort($fields, SORT_STRING);
        if ($keys !== $fields) {
            throw new InvalidArgumentException('Unexpected or missing account authorization metadata.');
        }
    }
}
