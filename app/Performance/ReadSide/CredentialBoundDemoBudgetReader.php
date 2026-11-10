<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use App\Performance\Planning\OfflineBudgetScenarioPreview;
use PDO;
use Throwable;

/**
 * F1-107: protected PostgreSQL budget DEMO reader reusing the exact F105
 * centavo allocation. Tenant comes from an authenticated SQL login (F91),
 * never request JSON, a URL, customer-provided GUC or any method parameter.
 *
 * CI-only read model, not Portal IAM, Ads approval or a payment service.
 */
final class CredentialBoundDemoBudgetReader
{
    private const SCHEMA = 'perf.portal.credential-bound-budgets.synthetic.v1';

    private static function denied(string $status): array
    {
        return [
            'schema_version' => self::SCHEMA,
            'status' => $status,
            'simulation_only' => true,
            'credential_binding_confirmed' => false,
            'portal_identity_authenticated' => false,
            'advertiser_approval_verified' => false,
            'provider_connected' => false,
            'media_execution_allowed' => false,
            'billing_allowed' => false,
            'ready_for_production' => false,
            'scenarios' => [],
        ];
    }

    public function read(PDO $db): array
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F107_BUDGET_READER_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }

        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                return self::denied('invalid_database_driver');
            }
            $identity = $db->query(
                'SELECT current_database() AS dbname, '
                .'session_user::text AS db_login, '
                .'public.perf_f91_tenant_for_login()::text AS bound_tenant'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['dbname'] !== 'perf_test_campaign_registry'
                || !in_array($identity['db_login'], [
                    'perf_f91_reader_a', 'perf_f91_reader_b',
                ], true)) {
                return self::denied('untrusted_database_login');
            }
            $tenant = $identity['bound_tenant'];
            if ($tenant === null || $tenant === '') {
                return self::denied('database_login_revoked');
            }
            if (!is_string($tenant)
                || preg_match(
                    '/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',
                    $tenant
                ) !== 1) {
                return self::denied('invalid_database_binding');
            }

            $rows = $db->query(
                'SELECT tenant_id,portal_service_ref,scenario_ref,currency,'
                .'start_date,end_date,total_centavos,google_ads_bp,meta_ads_bp,'
                .'source,provider_connected,media_execution_allowed,'
                .'billing_allowed,advertiser_approval_verified '
                .'FROM public.perf_f106_credential_budget_demo_read '
                .'ORDER BY scenario_ref'
            )->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows) || count($rows) > 20) {
                return self::denied('invalid_budget_rows');
            }

            $scenarios = [];
            $seen = [];
            foreach ($rows as $row) {
                if (!is_array($row)
                    || array_keys($row) !== [
                        'tenant_id','portal_service_ref','scenario_ref','currency',
                        'start_date','end_date','total_centavos','google_ads_bp',
                        'meta_ads_bp','source','provider_connected',
                        'media_execution_allowed','billing_allowed',
                        'advertiser_approval_verified'
                    ]
                    || $row['tenant_id'] !== $tenant
                    || $row['portal_service_ref'] !== 'svc-demo-f88'
                    || $row['currency'] !== 'BRL'
                    || $row['source'] !== 'offline_demo_fixture'
                    || !is_string($row['scenario_ref'])
                    || preg_match('/^scenario-demo-[a-z0-9-]{1,50}$/D',
                        $row['scenario_ref']) !== 1
                    || isset($seen[$row['scenario_ref']])) {
                    return self::denied('invalid_budget_rows');
                }

                foreach ([
                    'provider_connected','media_execution_allowed',
                    'billing_allowed','advertiser_approval_verified'
                ] as $flag) {
                    if (!in_array($row[$flag], [false,'f','0',0], true)) {
                        return self::denied('operational_budget_flag_detected');
                    }
                }

                $total = self::integerFromSql($row['total_centavos'], 1, 2_000_000_000);
                $google = self::integerFromSql($row['google_ads_bp'], 0, 10_000);
                $meta = self::integerFromSql($row['meta_ads_bp'], 0, 10_000);
                if ($total === null || $google === null || $meta === null
                    || $google + $meta !== 10_000) {
                    return self::denied('invalid_budget_numbers');
                }

                $scenario = OfflineBudgetScenarioPreview::calculate([
                    'schema_version' => OfflineBudgetScenarioPreview::SCHEMA,
                    'scenario_ref' => $row['scenario_ref'],
                    'currency' => 'BRL',
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'total_centavos' => $total,
                    'channel_split_bp' => [
                        'google_ads' => $google,
                        'meta_ads' => $meta,
                    ],
                ]);
                if ($scenario['media_execution_allowed'] !== false
                    || $scenario['billing_allowed'] !== false
                    || $scenario['ready_for_production'] !== false
                    || $scenario['advertiser_approval_verified'] !== false
                    || $scenario['provider_connected'] !== false) {
                    return self::denied('unsafe_budget_planner');
                }
                $scenarios[] = $scenario;
                $seen[$row['scenario_ref']] = true;
            }

            return [
                'schema_version' => self::SCHEMA,
                'status' => 'credential_scoped_demo_budget_preview_only',
                'simulation_only' => true,
                'credential_binding_confirmed' => true,
                'portal_identity_authenticated' => false,
                'advertiser_approval_verified' => false,
                'provider_connected' => false,
                'media_execution_allowed' => false,
                'billing_allowed' => false,
                'ready_for_production' => false,
                'scenarios' => $scenarios,
            ];
        } catch (Throwable) {
            return self::denied('read_unavailable');
        }
    }

    private static function integerFromSql(mixed $raw, int $min, int $max): ?int
    {
        if (is_int($raw)) {
            return $raw >= $min && $raw <= $max ? $raw : null;
        }
        if (!is_string($raw)
            || preg_match('/^(0|[1-9][0-9]{0,9})$/D', $raw) !== 1) {
            return null;
        }
        $result = (int) $raw;
        return $result >= $min && $result <= $max ? $result : null;
    }
}
