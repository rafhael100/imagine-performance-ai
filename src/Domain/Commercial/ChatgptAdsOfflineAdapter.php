<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * ChatGPT Ads — READ-ONLY OFFLINE contract laboratory.
 *
 * No HTTP, authentication, credentials, real financial reconciliation, provider
 * attestation, ad changes or billing. Fixture data is NOT real Ads evidence.
 * Live API integration requires separate consent, onboarding and a gated PR.
 */
final class ChatgptAdsOfflineAdapter
{
    private const MAX_ROWS = 35;
    private const MAX_CENTS = 100000000;
    private const SOURCE = 'synthetic_readonly_report_fixture';

    /** @return array<string,mixed> */
    public static function insightsRequestPlan(string $accountId, string $period, string $zone): array
    {
        self::account($accountId);
        self::zone($zone);
        [$start, $end] = self::month($period);

        return [
            'provider' => 'chatgpt_ads',
            'api_version' => 'v1',
            'method' => 'GET',
            'path' => '/v1/ad_account/insights',
            'query' => [
                'aggregation_level' => 'account',
                'time_granularity' => 'daily',
                'time_ranges[]' => json_encode([
                    'type' => 'date_range',
                    'since' => $start,
                    'until' => $end,
                    'timezone' => $zone,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'fields[]' => ['spend', 'impressions', 'clicks'],
            ],
            'account_id_guard' => $accountId,
            'read_only_report' => true,
            'transport_enabled' => false,
            'provider_source_authenticated' => false,
            'account_ownership_verified' => false,
            'ads_write_enabled' => false,
        ];
    }

    /**
     * Parses a CLOSED, fully assembled synthetic month, NOT an API response.
     * Convert official numeric spend to an exact decimal string separately,
     * after a trusted authenticated transport; this laboratory cannot do so.
     *
     * @param array<string,mixed> $fixture
     * @return array<string,mixed>
     */
    public static function monthlyFixture(
        string $tenant, string $period, string $accountId, string $zone, array $fixture
    ): array {
        self::tenant($tenant);
        self::account($accountId);
        self::zone($zone);
        self::month($period);
        self::keys($fixture, ['account_id', 'complete', 'currency_code', 'data', 'has_more', 'time_zone']);
        if ($fixture['account_id'] !== $accountId ||
            $fixture['currency_code'] !== 'BRL' ||
            $fixture['time_zone'] !== $zone ||
            $fixture['complete'] !== true ||
            $fixture['has_more'] !== false ||
            !is_array($fixture['data']) ||
            count($fixture['data']) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Account/currency/timezone mismatch or incomplete ChatGPT fixture.');
        }

        $seen = [];
        $total = 0;
        $impressions = 0;
        $clicks = 0;
        foreach ($fixture['data'] as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid daily Insights row.');
            }
            self::keys($row, ['clicks', 'impressions', 'readable_time', 'spend']);
            $day = $row['readable_time'];
            if (!is_string($day) ||
                !preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])-([0-3][0-9])\z/D', $day, $m) ||
                !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ||
                substr($day, 0, 7) !== $period ||
                isset($seen[$day])) {
                throw new InvalidArgumentException('Duplicate, impossible or out-of-period report date.');
            }
            $seen[$day] = true;
            $cost = self::cents($row['spend']);
            if (!is_int($row['impressions']) || !is_int($row['clicks']) ||
                $row['impressions'] < 0 || $row['clicks'] < 0 ||
                $row['clicks'] > $row['impressions']) {
                throw new InvalidArgumentException('Invalid click or impression counters.');
            }
            if ($total > self::MAX_CENTS - $cost ||
                $impressions > 1000000000 - $row['impressions'] ||
                $clicks > 1000000000 - $row['clicks']) {
                throw new InvalidArgumentException('Laboratory counter exceeded.');
            }
            $total += $cost;
            $impressions += $row['impressions'];
            $clicks += $row['clicks'];
        }

        return [
            'synthetic_account_report' => [
                'tenant_id' => $tenant,
                'channel' => 'chatgpt_ads',
                'account_id' => $accountId,
                'report_id' => 'chatgpt_account_month_' . str_replace('-', '_', $period),
                'period' => $period,
                'time_zone' => $zone,
                'currency' => 'BRL',
                'spend_cents' => $total,
                'source' => self::SOURCE,
            ],
            'reported_day_count' => count($seen),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'transport_enabled' => false,
            'provider_source_authenticated' => false,
            'provider_spend_verified' => false,
            'account_ownership_verified' => false,
            'pagination_verified_by_provider' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }

    private static function cents(mixed $raw): int
    {
        if (!is_string($raw) ||
            !preg_match('/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?\z/D', $raw, $m)) {
            throw new InvalidArgumentException('Synthetic spend must be an exact BRL decimal string.');
        }
        $cents = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0', STR_PAD_RIGHT);
        if ($cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('Account spend exceeds laboratory cap.');
        }
        return $cents;
    }

    /** @return array{string,string} */
    private static function month(string $period): array
    {
        if (!preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])\z/D', $period, $m) ||
            (int)$m[1] < 2000 || (int)$m[1] > 2100) {
            throw new InvalidArgumentException('Expected valid YYYY-MM.');
        }
        $date = new DateTimeImmutable($period . '-01', new DateTimeZone('UTC'));
        return [$date->format('Y-m-d'), $date->format('Y-m-t')];
    }

    private static function tenant(string $tenant): void
    {
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant)) {
            throw new InvalidArgumentException('Invalid tenant.');
        }
    }

    private static function account(string $accountId): void
    {
        if (!preg_match('/\Aadacct_[A-Za-z0-9]{3,64}\z/D', $accountId)) {
            throw new InvalidArgumentException('Invalid ChatGPT ad account ID.');
        }
    }

    private static function zone(string $zone): void
    {
        if (!in_array($zone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Unknown IANA timezone.');
        }
    }

    /** @param array<string,mixed> $input @param list<string> $expected */
    private static function keys(array $input, array $expected): void
    {
        $actual = array_keys($input);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) {
            throw new InvalidArgumentException('Unexpected or missing fixture fields.');
        }
    }
}
