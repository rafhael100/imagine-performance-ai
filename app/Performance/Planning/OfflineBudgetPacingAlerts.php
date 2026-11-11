<?php
declare(strict_types=1);

namespace App\Performance\Planning;

use DomainException;

/**
 * F201: deterministic notices about F200 SYNTHETIC campaign pacing.
 * Not a real provider alert, spend guard, automation or operational decision.
 */
final class OfflineBudgetPacingAlerts
{
    public const SCHEMA = 'perf.planning.offline-pacing-alerts.synthetic.v1';

    public static function evaluate(array $request): array
    {
        self::keys($request, [
            'schema_version','pacing_input','over_tolerance_bp','under_tolerance_bp'
        ]);
        if ($request['schema_version'] !== self::SCHEMA
            || !is_array($request['pacing_input'])) {
            throw new DomainException('Invalid synthetic alert contract');
        }
        foreach (['over_tolerance_bp','under_tolerance_bp'] as $key) {
            if (!is_int($request[$key]) || $request[$key] < 0
                || $request[$key] > 10_000) {
                throw new DomainException('Tolerance must be integer basis points');
            }
        }

        // Recompute the source report from canonical F200 inputs; never
        // accept a caller-supplied status or precomputed delta as evidence.
        $pacing = OfflineBudgetPacingMonitor::analyze($request['pacing_input']);
        $days = $pacing['observed_days'];
        $planned = $pacing['cumulative_planned_centavos'];
        $observed = $pacing['observed_total_centavos'];
        $delta = $observed - $planned;
        $enough = $days > 0 && $planned > 0;
        // F200 bounds (366 * 2 billion * 10,000) fit signed int64.
        $over = $enough && $delta > 0
            && ($delta * 10_000 > $planned * $request['over_tolerance_bp']);
        $under = $enough && $delta < 0
            && ((-$delta) * 10_000 > $planned * $request['under_tolerance_bp']);
        $budgetExceeded = $days > 0 && $pacing['total_budget_exceeded'];
        $alerts = [];
        if ($budgetExceeded) $alerts[] = 'synthetic_total_above_planned_budget';
        if ($over) $alerts[] = 'synthetic_cumulative_over_tolerance';
        if ($under) $alerts[] = 'synthetic_cumulative_under_tolerance';

        $status = !$enough ? 'insufficient_synthetic_data'
            : ($budgetExceeded ? 'synthetic_total_exceeded'
                : ($over ? 'synthetic_above_tolerance'
                    : ($under ? 'synthetic_below_tolerance'
                        : 'synthetic_within_tolerance')));
        return [
            'schema_version' => self::SCHEMA,
            'source' => 'synthetic_fixture_only',
            'scenario_ref' => $pacing['scenario_ref'],
            'currency' => 'BRL',
            'status' => $status,
            'observed_days' => $days,
            'last_observed_date' => $days === 0 ? null
                : $pacing['daily'][$days-1]['date'],
            'planned_centavos' => $planned,
            'observed_centavos' => $observed,
            'delta_centavos' => $delta,
            'over_tolerance_bp' => $request['over_tolerance_bp'],
            'under_tolerance_bp' => $request['under_tolerance_bp'],
            'alerts' => $alerts,
            'requires_human_review_demo' => count($alerts) > 0,
            'simulation_only' => true,
            'advertiser_approval_verified' => false,
            'provider_connected' => false,
            'media_execution_allowed' => false,
            'automatic_pause_allowed' => false,
            'notification_delivery_allowed' => false,
            'billing_allowed' => false,
            'ready_for_production' => false,
        ];
    }

    private static function keys(array $value, array $expected): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new DomainException('Unexpected alert contract keys');
        }
    }
}
