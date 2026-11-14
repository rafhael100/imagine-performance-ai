<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\SyntheticAdsAccountSpendContract;
use Imagine\Performance\Commercial\SyntheticProviderSpendAdapter;
use InvalidArgumentException;
use RuntimeException;

/**
 * OFFLINE LAB bridge from account-scoped, UNVERIFIED provider-shaped fixtures
 * into existing atomic HMAC/nonce + unbillable PostgreSQL finance draft.
 *
 * No OAuth, HTTP requests, real Ads data, authorization or payment capability.
 */
final class PgProviderShapedFinanceLab
{
    public function __construct(private readonly PgAtomicSignedCloseLab $finance) {}

    /**
     * @param array<string,mixed> $signedQuote
     * @param array<string,array<string,string>> $accountScopes
     * @param array<string,array<string,array<string,mixed>>> $fixtures
     * @return array<string,mixed>
     */
    public static function prepare(
        string $tenant, string $period, array $signedQuote,
        array $accountScopes, array $fixtures
    ): array {
        self::labGate();
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            ($signedQuote['tenant_id'] ?? null) !== $tenant ||
            ($signedQuote['billing_period'] ?? null) !== $period ||
            !is_array($signedQuote['quote'] ?? null) ||
            !is_array($signedQuote['quote']['media_by_channel_cents'] ?? null) ||
            !isset($accountScopes['google_ads']) && !isset($accountScopes['meta_ads'])) {
            throw new InvalidArgumentException('Invalid synthetic provider report scope.');
        }
        $quoteChannels = array_keys($signedQuote['quote']['media_by_channel_cents']);
        $scopeChannels = array_keys($accountScopes);
        $fixtureChannels = array_keys($fixtures);
        sort($quoteChannels, SORT_STRING);
        sort($scopeChannels, SORT_STRING);
        sort($fixtureChannels, SORT_STRING);
        if ($quoteChannels !== $scopeChannels || $fixtureChannels !== $scopeChannels ||
            $quoteChannels === []) {
            throw new InvalidArgumentException('Signed quote and account scopes differ.');
        }

        $records = [];
        foreach ($accountScopes as $channel => $accounts) {
            if (!is_array($accounts) || $accounts === [] ||
                !isset($fixtures[$channel]) || !is_array($fixtures[$channel])) {
                throw new InvalidArgumentException('Missing account fixtures.');
            }
            $accountIds = array_map('strval', array_keys($accounts));
            $providedIds = array_map('strval', array_keys($fixtures[$channel]));
            sort($accountIds, SORT_STRING);
            sort($providedIds, SORT_STRING);
            if ($accountIds !== $providedIds) {
                throw new InvalidArgumentException('Missing, extra or uncontracted Ads account fixture.');
            }
            foreach ($accounts as $rawAccountId => $timeZone) {
                $accountId = (string)$rawAccountId;
                $payload = $fixtures[$channel][$rawAccountId];
                if (!is_string($timeZone) || !is_array($payload)) {
                    throw new InvalidArgumentException('Invalid account metadata/fixture.');
                }
                $item = match ($channel) {
                    'google_ads' => SyntheticProviderSpendAdapter::googleMonthlyFixture(
                        $tenant, $period, $accountId, $timeZone, $payload
                    ),
                    'meta_ads' => SyntheticProviderSpendAdapter::metaMonthlyFixture(
                        $tenant, $period, $accountId, $timeZone, $payload
                    ),
                    default => throw new InvalidArgumentException('Unsupported Ads channel.'),
                };
                foreach (['transport_enabled','provider_source_authenticated',
                          'provider_spend_verified','account_ownership_verified',
                          'pagination_verified_by_provider','finance_approved',
                          'asaas_write_enabled','ads_write_enabled'] as $flag) {
                    if (($item[$flag] ?? null) !== false) {
                        throw new InvalidArgumentException('Synthetic provider report cannot claim verified origin.');
                    }
                }
                $records[] = $item['synthetic_account_report'];
            }
        }
        $summary = SyntheticAdsAccountSpendContract::summarize(
            $tenant, $period, $accountScopes, $records
        );
        foreach (['provider_source_authenticated','provider_spend_verified',
                  'portal_iam_authenticated','finance_approved',
                  'asaas_write_enabled','ads_write_enabled'] as $flag) {
            if (($summary[$flag] ?? null) !== false) {
                throw new InvalidArgumentException('Account summary cannot claim real verification.');
            }
        }

        $byChannel = [];
        foreach ($records as $record) {
            $byChannel[$record['channel']][] = $record;
        }
        ksort($byChannel, SORT_STRING);
        $financeRecords = [];
        foreach ($byChannel as $channel => $accountRecords) {
            usort($accountRecords, static fn(array $a,array $b): int =>
                strcmp($a['account_id'], $b['account_id'])
            );
            $scopeFingerprint = hash('sha256', json_encode(
                $accountRecords, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            $financeRecords[] = [
                'channel' => $channel,
                'period' => $period,
                // Binds the total to WHICH accounts contributed their spend.
                'report_id' => 'accountset_' . substr($scopeFingerprint, 0, 40),
                'source' => 'synthetic_fixture',
                'spend_cents' => $summary['unverified_spend_by_channel_cents'][$channel],
            ];
        }
        return [
            'synthetic_finance_records' => $financeRecords,
            'synthetic_account_summary' => $summary,
            'account_fingerprint_sha256_not_provider_proof' =>
                $summary['fingerprint_sha256_not_provider_proof'],
            'provider_source_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }

    /**
     * @param array<string,mixed> $signedQuote
     * @param array<string,array<string,string>> $accountScopes
     * @param array<string,array<string,array<string,mixed>>> $fixtures
     * @param callable(string,string):?string $keyResolver
     * @return array<string,mixed>
     */
    public function stage(
        array $signedQuote, string $tenant, string $catalogVersion,
        string $period, array $accountScopes, array $fixtures,
        int $now, callable $keyResolver
    ): array {
        self::labGate();
        $prepared = self::prepare($tenant, $period, $signedQuote, $accountScopes, $fixtures);
        $finance = $this->finance->stage(
            $signedQuote, $tenant, $catalogVersion, $period,
            $prepared['synthetic_finance_records'], $now, $keyResolver
        );
        if (($finance['finance_approved'] ?? null) !== false ||
            ($finance['asaas_write_enabled'] ?? null) !== false ||
            ($finance['provider_spend_verified'] ?? null) !== false ||
            ($finance['billable'] ?? null) !== false) {
            throw new RuntimeException('Laboratory finance result must not be billable.');
        }
        return [
            'atomic_close' => $finance,
            'synthetic_finance_records' => $prepared['synthetic_finance_records'],
            'account_fingerprint_sha256_not_provider_proof' =>
                $prepared['account_fingerprint_sha256_not_provider_proof'],
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }

    private static function labGate(): void
    {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_FIN_ATOMIC_LAB') !== '1' ||
            getenv('PERF_ADS_PROVIDER_FIN_LAB') !== '1') {
            throw new RuntimeException('Ads provider shaped finance laboratory disabled.');
        }
    }
}
