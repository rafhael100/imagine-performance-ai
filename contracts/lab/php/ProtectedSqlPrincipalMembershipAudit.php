<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F1-115: audit all PG role memberships touching protected CI principals.
 *
 * A direct SQL login that can SET ROLE or inherit a privileged role may
 * bypass expected view/table grants even when individual ACLs look safe.
 * Only inspect a disposable PG16 database, not production or the VPS.
 */
final class ProtectedSqlPrincipalMembershipAudit
{
    public const SCHEMA = 'perf.security.protected-role-memberships.ci.v1';

    private const PROTECTED_ROLES = [
        'perf_campaign_test_user',
        'perf_f23_writer',
        'perf_f24_reader',
        'perf_f27_journal_reader',
        'perf_f28_app_reader',
        'perf_f88_registrar',
        'perf_f89_link_registrar',
        'perf_f90_projection_reader',
        'perf_f91_reader_a',
        'perf_f91_reader_b',
        'perf_f104_journal_writer',
    ];

    private static function denied(string $status): array
    {
        return [
            'schema_version' => self::SCHEMA,
            'status' => $status,
            'audit_scope' => 'disposable_postgresql_ci_only',
            'audit_complete' => false,
            'protected_principal_count' => 0,
            'unsafe_membership_edges' => [],
            'unsafe_role_attributes' => [],
            'release_allowed' => false,
            'media_execution_allowed' => false,
            'billing_allowed' => false,
        ];
    }

    public function inspect(PDO $db): array
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F115_ROLE_MEMBERSHIP_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }

        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                return self::denied('wrong_database_driver');
            }
            $identity = $db->query(
                'SELECT current_database() AS dbname, '
                .'session_user::text AS login, '
                .'current_setting('."'server_version_num'".')::integer AS server_version'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['dbname'] !== 'perf_test_campaign_registry'
                || $identity['login'] !== 'postgres'
                || (int) $identity['server_version'] < 160000
                || (int) $identity['server_version'] >= 170000) {
                return self::denied('untrusted_catalog_principal');
            }

            // A complete policy must see *all* protected principals.
            $attributes = $db->query(
                'SELECT rolname,rolsuper,rolcanlogin,rolcreaterole,'
                .'rolcreatedb,rolbypassrls,rolreplication,rolinherit '
                .'FROM pg_catalog.pg_roles'
            )->fetchAll(PDO::FETCH_ASSOC);
            $byName = [];
            foreach ($attributes as $r) {
                $byName[$r['rolname']] = $r;
            }
            foreach (self::PROTECTED_ROLES as $role) {
                if (!isset($byName[$role])) {
                    return self::denied('missing_protected_role');
                }
            }

            $active = static fn (mixed $v): bool =>
                in_array($v, [true, 't', '1', 1], true);
            $unsafeAttrs = [];
            foreach (self::PROTECTED_ROLES as $role) {
                $r = $byName[$role];
                foreach ([
                    'rolsuper', 'rolcreaterole', 'rolcreatedb',
                    'rolbypassrls', 'rolreplication',
                ] as $attribute) {
                    if ($active($r[$attribute])) {
                        $unsafeAttrs[] = [
                            'principal' => $role,
                            'attribute' => $attribute,
                            'actual' => true,
                        ];
                    }
                }
                $loginExpected = $role !== 'perf_f104_journal_writer';
                if ($active($r['rolcanlogin']) !== $loginExpected) {
                    $unsafeAttrs[] = [
                        'principal' => $role,
                        'attribute' => 'rolcanlogin',
                        'actual' => $active($r['rolcanlogin']),
                    ];
                }
                if ($role === 'perf_f104_journal_writer'
                    && $active($r['rolinherit'])) {
                    $unsafeAttrs[] = [
                        'principal' => $role,
                        'attribute' => 'rolinherit',
                        'actual' => true,
                    ];
                }
            }

            // PostgreSQL16 exposes admin_option, inherit_option, set_option.
            // Deny ANY edge with a protected role as member OR roleid.
            // This also forbids unknown roles from gaining SET ROLE into F104.
            $graph = $db->query(
                'SELECT granted.rolname AS granted_role, '
                .'member.rolname AS member_role, '
                .'m.admin_option,m.inherit_option,m.set_option '
                .'FROM pg_catalog.pg_auth_members m '
                .'JOIN pg_catalog.pg_roles granted ON granted.oid=m.roleid '
                .'JOIN pg_catalog.pg_roles member ON member.oid=m.member'
            )->fetchAll(PDO::FETCH_ASSOC);
            $edges = [];
            foreach ($graph as $e) {
                if (in_array($e['granted_role'], self::PROTECTED_ROLES, true)
                    || in_array($e['member_role'], self::PROTECTED_ROLES, true)) {
                    $edges[] = [
                        'granted_role' => $e['granted_role'],
                        'member_role' => $e['member_role'],
                        'admin_option' => $active($e['admin_option']),
                        'inherit_option' => $active($e['inherit_option']),
                        'set_option' => $active($e['set_option']),
                    ];
                }
            }
            usort($edges, static fn (array $a, array $b): int =>
                strcmp(
                    $a['granted_role'].'/'.$a['member_role'],
                    $b['granted_role'].'/'.$b['member_role']
                )
            );
            return [
                'schema_version' => self::SCHEMA,
                'status' => $edges === [] && $unsafeAttrs === []
                    ? 'protected_roles_isolated_in_ci'
                    : 'role_membership_or_attribute_drift',
                'audit_scope' => 'disposable_postgresql_ci_only',
                'audit_complete' => true,
                'protected_principal_count' => count(self::PROTECTED_ROLES),
                'unsafe_membership_edges' => $edges,
                'unsafe_role_attributes' => $unsafeAttrs,
                'release_allowed' => false,
                'media_execution_allowed' => false,
                'billing_allowed' => false,
            ];
        } catch (Throwable) {
            return self::denied('catalog_read_unavailable');
        }
    }

    public function assertProtectedRolesIsolated(PDO $db): array
    {
        $report = $this->inspect($db);
        if ($report['status'] !== 'protected_roles_isolated_in_ci'
            || $report['audit_complete'] !== true
            || $report['unsafe_membership_edges'] !== []
            || $report['unsafe_role_attributes'] !== []) {
            throw new \RuntimeException(
                'F115 denies missing, privileged or role-member SQL principals'
            );
        }
        return $report;
    }
}
