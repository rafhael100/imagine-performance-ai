<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F120 CI-only connection lease: a bounded, tenant-specific read capability
 * pinned to ONE authenticated PostgreSQL backend and a trusted expected
 * identity. Never promote to product IAM without a trusted Portal S2S source.
 *
 * Caller-supplied expectedTenant/expectedLogin are NOT authentication.
 * This is only a regression harness with synthetic SQL logins and tenants.
 */
final class SqlTenantConnectionLease
{
    private const SCHEMA = 'perf.security.sql-login-lease.synthetic.v1';
    private const VIEWS = [
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ];

    private function __construct(
        private PDO $database,
        private string $expectedLogin,
        private string $expectedTenant,
        private string $databaseBackend,
    ) {
    }

    private static function labReady(): bool
    {
        return PHP_SAPI === 'cli'
            && getenv('APP_ENV') === 'testing'
            && getenv('PERF_F120_SQL_LEASE_LAB') === '1'
            && getenv('PGHOST') === '127.0.0.1'
            && getenv('PGPORT') === '5432'
            && getenv('PGDATABASE') === 'perf_test_campaign_registry';
    }

    private static function isLoginAllowed(string $login): bool
    {
        return in_array($login, [
            'perf_f91_reader_a',
            'perf_f91_reader_b',
        ], true);
    }

    private static function isTenantWellFormed(string $tenant): bool
    {
        return preg_match(
            '/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',
            $tenant
        ) === 1;
    }

    private static function databaseIdentity(PDO $db): ?array
    {
        try {
            $record = $db->query(
                'SELECT current_database()::text AS dbname, '
                .'session_user::text AS db_login, '
                .'pg_catalog.pg_backend_pid()::text AS backend_pid, '
                .'public.perf_f91_tenant_for_login()::text AS bound_tenant'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)
                || array_keys($record) !== [
                    'dbname','db_login','backend_pid','bound_tenant',
                ]
                || $record['dbname'] !== 'perf_test_campaign_registry'
                || !is_string($record['backend_pid'])
                || preg_match('/^[1-9][0-9]*$/D', $record['backend_pid']) !== 1
            ) {
                return null;
            }
            return $record;
        } catch (Throwable) {
            return null;
        }
    }

    public static function capture(
        PDO $db,
        string $expectedLogin,
        string $expectedTenant,
    ): ?self {
        if (!self::labReady()
            || !self::isLoginAllowed($expectedLogin)
            || !self::isTenantWellFormed($expectedTenant)) {
            return null;
        }
        try {
            if ($db->inTransaction()
                || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                return null;
            }
            $id = self::databaseIdentity($db);
            if ($id === null
                || $id['db_login'] !== $expectedLogin
                || $id['bound_tenant'] !== $expectedTenant) {
                return null;
            }
            return new self(
                $db,
                $expectedLogin,
                $expectedTenant,
                $id['backend_pid']
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function matchesCapturedIdentity(): bool
    {
        if (!self::labReady()) {
            return false;
        }
        try {
            if ($this->database->inTransaction()) {
                return false;
            }
            $id = self::databaseIdentity($this->database);
            return $id !== null
                && $id['db_login'] === $this->expectedLogin
                && $id['bound_tenant'] === $this->expectedTenant
                && $id['backend_pid'] === $this->databaseBackend;
        } catch (Throwable) {
            return false;
        }
    }

    private static function denied(string $reason): array
    {
        return [
            'schema_version' => self::SCHEMA,
            'status' => $reason,
            'simulation_only' => true,
            'portal_identity_authenticated' => false,
            'advertiser_approval_verified' => false,
            'provider_connected' => false,
            'media_execution_allowed' => false,
            'billing_allowed' => false,
            'ready_for_production' => false,
            'rows' => [],
        ];
    }

    public function readDemoView(string $viewName): array
    {
        if (!in_array($viewName, self::VIEWS, true)) {
            return self::denied('view_not_allowlisted');
        }
        if (!$this->matchesCapturedIdentity()) {
            return self::denied('connection_identity_changed');
        }

        try {
            // No externally supplied identifiers enter this SQL statement.
            $rows = $this->database->query(
                'SELECT * FROM public.'.$viewName
            )->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($rows) || count($rows) > 30) {
                return self::denied('unexpected_view_rows');
            }

            foreach ($rows as $row) {
                if (!is_array($row)
                    || !isset($row['tenant_id'])
                    || $row['tenant_id'] !== $this->expectedTenant) {
                    return self::denied('view_tenant_mismatch');
                }
            }

            // A revocation or rebinding committed while SELECT ran must
            // invalidate the entire response; do not return stale data.
            if (!$this->matchesCapturedIdentity()) {
                return self::denied('connection_identity_changed_during_read');
            }
            return [
                'schema_version' => self::SCHEMA,
                'status' => 'credential_scoped_demo_read_only',
                'simulation_only' => true,
                'portal_identity_authenticated' => false,
                'advertiser_approval_verified' => false,
                'provider_connected' => false,
                'media_execution_allowed' => false,
                'billing_allowed' => false,
                'ready_for_production' => false,
                'rows' => $rows,
            ];
        } catch (Throwable) {
            return self::denied('view_read_unavailable');
        }
    }
}
