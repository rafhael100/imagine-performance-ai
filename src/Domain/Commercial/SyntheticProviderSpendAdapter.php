<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * OFFLINE fixture parser for possible Google Ads GAQL and Meta Insights
 * read-only report shapes. It sends NO network request, reads NO credentials,
 * does NOT attest any upstream authentication, currency, permissions or data.
 */
final class SyntheticProviderSpendAdapter
{
    private const CHANNEL_MAX_CENTS = 100000000;
    private const MAX_DAILY_ROWS = 35;
    private const SOURCE = 'synthetic_readonly_report_fixture';

    /**
     * Deterministic allowlisted read-only request plan; not an HTTP client.
     * @return array<string,mixed>
     */
    public static function googleRequestPlan(string $customerId, string $period): array
    {
        self::assertGoogleId($customerId);
        [$start, $end] = self::monthBounds($period);
        return [
            'provider' => 'google_ads',
            'api_version' => 'v26',
            'method' => 'POST',
            'path' => '/v26/customers/' . $customerId . '/googleAds:searchStream',
            'body' => [
                'query' => "SELECT segments.date, metrics.cost_micros FROM customer " .
                    "WHERE segments.date BETWEEN '{$start}' AND '{$end}'",
            ],
            'read_only_report' => true,
            'transport_enabled' => false,
            'requires_account_metadata_verification' => true,
        ];
    }

    /**
     * Deterministic allowlisted read-only request plan; not an HTTP client.
     * @return array<string,mixed>
     */
    public static function metaRequestPlan(string $accountId, string $period): array
    {
        self::assertMetaId($accountId);
        [$start, $end] = self::monthBounds($period);
        return [
            'provider' => 'meta_ads',
            'api_version' => 'v27.0',
            'method' => 'GET',
            'path' => '/v27.0/' . $accountId . '/insights',
            'query' => [
                'fields' => 'account_id,date_start,date_stop,spend,account_currency',
                'level' => 'account',
                'time_increment' => '1',
                'time_range' => json_encode(['since' => $start, 'until' => $end], JSON_THROW_ON_ERROR),
            ],
            'read_only_report' => true,
            'transport_enabled' => false,
            'requires_account_metadata_verification' => true,
        ];
    }

    /**
     * Accepts a synthetic complete SearchStream response assembled by a fixture.
     * All daily costs are summed in MICRO units then rounded ONCE to BRL cents.
     * @param array<string,mixed> $fixture
     * @return array<string,mixed>
     */
    public static function googleMonthlyFixture(
        string $tenant, string $period, string $customerId, string $timeZone, array $fixture
    ): array {
        self::assertTenant($tenant);
        self::assertGoogleId($customerId);
        self::assertTimeZone($timeZone);
        self::monthBounds($period);
        self::exactFields($fixture,
            ['batches','complete','currency_code','customer_id','time_zone'], 'Google fixture');
        if ($fixture['complete'] !== true ||
            $fixture['currency_code'] !== 'BRL' ||
            $fixture['customer_id'] !== $customerId ||
            $fixture['time_zone'] !== $timeZone ||
            !is_array($fixture['batches']) ||
            count($fixture['batches']) > 20) {
            throw new InvalidArgumentException('Incomplete or mismatched Google account fixture.');
        }

        $dates = [];
        $micros = 0;
        foreach ($fixture['batches'] as $batch) {
            if (!is_array($batch)) {
                throw new InvalidArgumentException('Invalid Google batch.');
            }
            self::exactFields($batch, ['results'], 'Google batch');
            if (!is_array($batch['results'])) {
                throw new InvalidArgumentException('Invalid Google daily results.');
            }
            foreach ($batch['results'] as $result) {
                if (!is_array($result)) throw new InvalidArgumentException('Invalid Google daily row.');
                self::exactFields($result, ['metrics','segments'], 'Google daily row');
                if (!is_array($result['metrics']) || !is_array($result['segments'])) {
                    throw new InvalidArgumentException('Invalid Google metric shape.');
                }
                self::exactFields($result['metrics'], ['costMicros'], 'Google metrics');
                self::exactFields($result['segments'], ['date'], 'Google segments');
                $day = $result['segments']['date'];
                self::assertDay($day, $period);
                if (isset($dates[$day])) {
                    throw new InvalidArgumentException('Duplicate Google daily total.');
                }
                $dates[$day] = true;
                if (count($dates) > self::MAX_DAILY_ROWS) {
                    throw new InvalidArgumentException('Excessive Google result rows.');
                }
                $increment = self::unsignedDecimalInt($result['metrics']['costMicros'], 1000000000000);
                if ($micros > 1000000000000 - $increment) {
                    throw new InvalidArgumentException('Google account spend over laboratory cap.');
                }
                $micros += $increment;
            }
        }
        $cents = intdiv($micros + 5000, 10000);
        if ($cents > self::CHANNEL_MAX_CENTS) {
            throw new InvalidArgumentException('Google spend exceeds allowed cents.');
        }
        return self::fixtureResult($tenant, $period, 'google_ads',
            $customerId, $timeZone, $cents, count($dates), 'google_account_month');
    }

    /**
     * Accepts a synthetic complete one-day-per-row account Insights response.
     * Decimal strings are converted to integer cents WITHOUT floats.
     * @param array<string,mixed> $fixture
     * @return array<string,mixed>
     */
    public static function metaMonthlyFixture(
        string $tenant, string $period, string $accountId, string $timeZone, array $fixture
    ): array {
        self::assertTenant($tenant);
        self::assertMetaId($accountId);
        self::assertTimeZone($timeZone);
        self::monthBounds($period);
        self::exactFields($fixture,
            ['account_id','complete','currency','data','next','time_zone'], 'Meta fixture');
        if ($fixture['account_id'] !== $accountId ||
            $fixture['currency'] !== 'BRL' ||
            $fixture['time_zone'] !== $timeZone ||
            $fixture['complete'] !== true || $fixture['next'] !== null ||
            !is_array($fixture['data']) || count($fixture['data']) > self::MAX_DAILY_ROWS) {
            throw new InvalidArgumentException('Incomplete/paginated or mismatched Meta account fixture.');
        }
        $seen = [];
        $cents = 0;
        $numericAccount = substr($accountId, 4);
        foreach ($fixture['data'] as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Invalid Meta daily row.');
            self::exactFields($row,
                ['account_currency','account_id','date_start','date_stop','spend'], 'Meta row');
            $date = $row['date_start'];
            self::assertDay($date, $period);
            if ($row['date_stop'] !== $date ||
                $row['account_id'] !== $numericAccount ||
                $row['account_currency'] !== 'BRL' ||
                isset($seen[$date])) {
                throw new InvalidArgumentException('Meta account/day/currency mismatch or duplicate.');
            }
            $seen[$date] = true;
            $increment = self::brlDecimalCents($row['spend']);
            if ($cents > self::CHANNEL_MAX_CENTS - $increment) {
                throw new InvalidArgumentException('Meta spend exceeds allowed cents.');
            }
            $cents += $increment;
        }
        return self::fixtureResult($tenant, $period, 'meta_ads', $accountId,
            $timeZone, $cents, count($seen), 'meta_account_month');
    }

    /** @return array<string,mixed> */
    private static function fixtureResult(
        string $tenant, string $period, string $channel, string $account,
        string $timeZone, int $cents, int $days, string $reportPrefix
    ): array {
        $record = [
            'tenant_id' => $tenant, 'channel' => $channel, 'account_id' => $account,
            'report_id' => $reportPrefix . '_' . str_replace('-', '_', $period),
            'period' => $period, 'time_zone' => $timeZone, 'currency' => 'BRL',
            'spend_cents' => $cents, 'source' => self::SOURCE,
        ];
        return [
            'synthetic_account_report' => $record,
            'reported_day_count' => $days,
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

    /** @return array{string,string} */
    private static function monthBounds(string $period): array
    {
        if (!preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])\z/D', $period, $m) ||
            (int)$m[1] < 2000 || (int)$m[1] > 2100) {
            throw new InvalidArgumentException('Invalid billing month.');
        }
        $date = new DateTimeImmutable($period . '-01', new DateTimeZone('UTC'));
        return [$date->format('Y-m-d'), $date->format('Y-m-t')];
    }

    private static function assertDay(mixed $date, string $period): void
    {
        if (!is_string($date) ||
            !preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])-([0-3][0-9])\z/D', $date, $m) ||
            !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ||
            substr($date, 0, 7) !== $period) {
            throw new InvalidArgumentException('Report day falls outside scoped month.');
        }
    }

    private static function assertTenant(string $tenant): void
    {
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant)) {
            throw new InvalidArgumentException('Invalid synthetic tenant.');
        }
    }

    private static function assertGoogleId(string $id): void
    {
        if (!preg_match('/\A[0-9]{10}\z/D', $id)) {
            throw new InvalidArgumentException('Google customer ID must have ten digits.');
        }
    }

    private static function assertMetaId(string $id): void
    {
        if (!preg_match('/\Aact_[0-9]{4,30}\z/D', $id)) {
            throw new InvalidArgumentException('Meta account ID invalid.');
        }
    }

    private static function assertTimeZone(string $zone): void
    {
        if (!in_array($zone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Invalid IANA account time zone.');
        }
    }

    /** @param array<string,mixed> $map @param list<string> $fields */
    private static function exactFields(array $map, array $fields, string $label): void
    {
        $keys = array_keys($map);
        sort($keys, SORT_STRING);
        sort($fields, SORT_STRING);
        if ($keys !== $fields) {
            throw new InvalidArgumentException($label . ' unexpected fields.');
        }
    }

    private static function unsignedDecimalInt(mixed $str, int $max): int
    {
        if (!is_string($str) || !preg_match('/\A(0|[1-9][0-9]{0,12})\z/D', $str)) {
            throw new InvalidArgumentException('Google cost micros must be an unsigned decimal string.');
        }
        $result = (int)$str;
        if ($result > $max) {
            throw new InvalidArgumentException('Google cost micros exceeds cap.');
        }
        return $result;
    }

    private static function brlDecimalCents(mixed $value): int
    {
        if (!is_string($value) ||
            !preg_match('/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?\z/D', $value, $m)) {
            throw new InvalidArgumentException('Meta spend must be a positive BRL decimal string.');
        }
        $fraction = str_pad($m[2] ?? '', 2, '0', STR_PAD_RIGHT);
        $cents = ((int)$m[1]) * 100 + (int)$fraction;
        if ($cents > self::CHANNEL_MAX_CENTS) {
            throw new InvalidArgumentException('Meta spend exceeds cap.');
        }
        return $cents;
    }
}
