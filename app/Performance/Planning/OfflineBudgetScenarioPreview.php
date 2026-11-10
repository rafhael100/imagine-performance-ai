<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/**
 * F1-105: pure, deterministic BRL budget distribution PREVIEW.
 *
 * NO provider API, persistence, money transfer, spend authorization, or client
 * identity assumptions. Currency and channel weights are explicit inputs.
 * All values are integer centavos/basis-points; no binary floating point.
 */
final class OfflineBudgetScenarioPreview
{
    public const SCHEMA = 'perf.planning.offline-budget.synthetic.v1';

    // Technical arithmetic safety cap, NOT a commercial plan, spending cap
    // or permission. Real advertiser limits must be approved separately.
    private const MAX_PREVIEW_CENTAVOS = 2_000_000_000;
    private const MAX_DAYS = 366;

    public static function calculate(array $input): array
    {
        self::requireKeys($input, [
            'schema_version', 'scenario_ref', 'currency',
            'start_date', 'end_date', 'total_centavos', 'channel_split_bp',
        ], 'input');

        if ($input['schema_version'] !== self::SCHEMA
            || !is_string($input['scenario_ref'])
            || preg_match('/^scenario-demo-[a-z0-9-]{1,50}$/D', $input['scenario_ref']) !== 1
            || $input['currency'] !== 'BRL'
            || !is_int($input['total_centavos'])
            || $input['total_centavos'] < 1
            || $input['total_centavos'] > self::MAX_PREVIEW_CENTAVOS) {
            throw new DomainException('Invalid DEMO BRL scenario or integer budget');
        }

        $split = $input['channel_split_bp'];
        if (!is_array($split)) {
            throw new DomainException('Channel split must be an object');
        }
        self::requireKeys($split, ['google_ads', 'meta_ads'], 'channel_split_bp');
        foreach ($split as $weight) {
            if (!is_int($weight) || $weight < 0 || $weight > 10_000) {
                throw new DomainException('Channel shares must be integer basis points');
            }
        }
        if ($split['google_ads'] + $split['meta_ads'] !== 10_000) {
            throw new DomainException('Channel shares must add to exactly 10000 bp');
        }

        $start = self::date($input['start_date'], 'start_date');
        $end = self::date($input['end_date'], 'end_date');
        if ($start > $end) {
            throw new DomainException('End date precedes start date');
        }
        $numberOfDays = (int) $start->diff($end)->days + 1;
        if ($numberOfDays < 1 || $numberOfDays > self::MAX_DAYS) {
            throw new DomainException('Preview must cover between 1 and 366 UTC calendar days');
        }

        $total = $input['total_centavos'];
        $baseDaily = intdiv($total, $numberOfDays);
        $extraDaily = $total % $numberOfDays;
        $daily = [];
        $previousGoogle = 0;
        $cumulative = 0;
        for ($day = 0; $day < $numberOfDays; ++$day) {
            // The first remainder days each receive exactly one extra centavo.
            $dayTotal = $baseDaily + ($day < $extraDaily ? 1 : 0);
            $cumulative += $dayTotal;

            // Cumulative apportionment preserves BOTH exact day totals and
            // exact channel totals, unlike per-day independent rounding.
            // 2_000_000_000 * 10_000 fits 64-bit signed PHP integers.
            $cumulativeGoogle = intdiv(
                $cumulative * $split['google_ads'],
                10_000
            );
            $google = $cumulativeGoogle - $previousGoogle;
            $meta = $dayTotal - $google;
            if ($google < 0 || $meta < 0 || $google + $meta !== $dayTotal) {
                throw new DomainException('Budget allocation invariant violated');
            }

            $daily[] = [
                'date' => $start->modify("+$day days")->format('Y-m-d'),
                'total_centavos' => $dayTotal,
                'google_ads_centavos' => $google,
                'meta_ads_centavos' => $meta,
            ];
            $previousGoogle = $cumulativeGoogle;
        }

        $googleTotal = intdiv($total * $split['google_ads'], 10_000);
        $metaTotal = $total - $googleTotal;
        if ($cumulative !== $total || $previousGoogle !== $googleTotal) {
            throw new DomainException('Final amount did not reconcile');
        }

        return [
            'schema_version' => self::SCHEMA,
            'scenario_ref' => $input['scenario_ref'],
            'currency' => 'BRL',
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'number_of_days' => $numberOfDays,
            'total_centavos' => $total,
            'channel_split_bp' => [
                'google_ads' => $split['google_ads'],
                'meta_ads' => $split['meta_ads'],
            ],
            'channel_totals_centavos' => [
                'google_ads' => $googleTotal,
                'meta_ads' => $metaTotal,
            ],
            'daily' => $daily,
            'simulation_only' => true,
            'advertiser_approval_verified' => false,
            'provider_connected' => false,
            'media_execution_allowed' => false,
            'billing_allowed' => false,
            'ready_for_production' => false,
        ];
    }

    private static function requireKeys(array $input, array $expected, string $name): void
    {
        $actual = array_keys($input);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new DomainException("Unexpected $name keys");
        }
    }

    private static function date(mixed $raw, string $name): DateTimeImmutable
    {
        if (!is_string($raw)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw) !== 1
            || substr($raw, 0, 4) === '0000') {
            throw new DomainException("Invalid $name");
        }
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $raw,
            new DateTimeZone('UTC')
        );
        $warnings = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($warnings !== false && ($warnings['warning_count'] !== 0
                || $warnings['error_count'] !== 0))
            || $date->format('Y-m-d') !== $raw) {
            throw new DomainException("Nonexistent or non-canonical $name");
        }
        return $date;
    }
}
