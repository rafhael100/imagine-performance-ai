<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use Imagine\Performance\Domain\ServiceAdmissionEvidence;
use PDO;
use PDOException;

/**
 * F1-19 disposable adapter: constructs a F1-18 decision input from REAL
 * PostgreSQL rows of the synthetic Portal/Performance laboratories.
 *
 * DO NOT USE AS AN AUTHENTICATION ADAPTER: the actor, scopes and contract
 * status below are FAKE FIXTURE CONSTANTS and do not prove a login,
 * membership, commercial contract, PKI, mTLS or independent authority.
 */
final class SyntheticSqlAdmissionEvidenceReader
{
    public function __construct(
        private readonly PDO $portalReader,
        private readonly PDO $motorReader,
    ) {
    }

    public function read(
        string $org,
        string $tenant,
        string $service,
        int $observedAt,
    ): ?ServiceAdmissionEvidence {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F19_SQL_ADAPTER_LAB') !== '1'
            || getenv('PERF_F19_MOCK_ATTESTED') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_relay_main'
            || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab'
            || $org !== 'org-demo-b'
            || $tenant !== 'perf-demo-b'
            || $service !== 'svc-demo-b'
            || $observedAt <= 0) {
            return null;
        }
        try {
            $sourceRole = $this->portalReader->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $motorRole = $this->motorReader->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($sourceRole) || !is_array($motorRole)
                || $sourceRole['db'] !== 'portal_lab'
                || $sourceRole['role'] !== 'portal_perf_source_reader'
                || $motorRole['db'] !== 'perf_test_relay_main'
                || $motorRole['role'] !== 'perf_test_wire_ingress') {
                return null;
            }
            $sourceQuery = $this->portalReader->prepare(
                'SELECT a.organization_ref,a.tenant_ref,a.service_ref,
                        a.min_revision,a.active,r.epoch,r.state
                 FROM lab_portal_perf_authority a
                 JOIN lab_portal_perf_restore_epochs r
                   ON r.service_ref=a.service_ref
                 WHERE a.service_ref=?'
            );
            $sourceQuery->execute([$service]);
            $source = $sourceQuery->fetch(PDO::FETCH_ASSOC);
            $motorQuery = $this->motorReader->prepare(
                'SELECT g.organization_ref,g.tenant_ref,g.service_ref,
                        g.grant_revision,g.active,r.epoch,r.state
                 FROM perf_s2s_lab.mock_service_grants g
                 JOIN perf_s2s_lab.mock_restore_epochs r
                   ON r.service_ref=g.service_ref
                 WHERE g.service_ref=?'
            );
            $motorQuery->execute([$service]);
            $motor = $motorQuery->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }
        if (!is_array($source) || !is_array($motor)
            || $source['organization_ref'] !== $org
            || $motor['organization_ref'] !== $org
            || $source['tenant_ref'] !== $tenant
            || $motor['tenant_ref'] !== $tenant
            || $source['service_ref'] !== $service
            || $motor['service_ref'] !== $service
            || (int) $source['min_revision'] < 1
            || (int) $motor['grant_revision'] < 1
            || (int) $source['epoch'] < 1
            || (int) $motor['epoch'] < 1) {
            return null;
        }
        $active = static fn (mixed $value): bool =>
            in_array($value, [true, 1, '1', 't'], true);

        return new ServiceAdmissionEvidence(
            organizationRef: $org,
            tenantRef: $tenant,
            serviceRef: $service,
            resourceTenantRef: $tenant,
            resourceServiceRef: $service,
            actorRef: 'actor-demo-b',
            actorTenantRef: $tenant,
            actorStatus: 'active_mock',
            actorScopes: ['campaigns:write'],
            sourceState: $active($source['active']) ? 'active_mock' : 'revoked_mock',
            localState: $active($motor['active']) ? 'active_mock' : 'revoked_mock',
            contractState: 'eligible_mock',
            sourceRevision: (int) $source['min_revision'],
            localRevision: (int) $motor['grant_revision'],
            sourceObservedAt: $observedAt,
            localObservedAt: $observedAt,
            externalRecoveryEpoch: (int) $source['epoch'],
            localRecoveryEpoch: (int) $motor['epoch'],
            recoveryState: (
                $source['state'] === 'ready_mock'
                && $motor['state'] === 'ready_mock'
            ) ? 'ready_mock' : 'restoring_mock',
            sourceAvailable: true,
            localAvailable: true,
            externalFenceAvailable: true,
            workloadAttestedInLab: true
        );
    }
}
