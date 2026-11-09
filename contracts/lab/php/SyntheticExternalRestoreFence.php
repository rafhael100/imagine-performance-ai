<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use PDOException;

/**
 * F1-10 disposable lab only. The authority connection is to a SECOND
 * PostgreSQL database not included in the main database pg_dump/restore.
 *
 * Not a distributed atomic protocol: an external revoke may race with the
 * local receipt commit. No external effects are authorized by this class.
 */
final class SyntheticExternalRestoreFence
{
    public function __construct(
        private readonly PDO $mainRuntime,
        private readonly PDO $independentFenceReader,
    ) {
    }

    public function accept(
        mixed $envelope,
        string $ephemeralTestKey,
        string $expectedOrganization,
        string $expectedTenant,
        string $expectedService,
        string $expectedFixtureDigest,
        int $expectedRevision,
        int $syntheticNow,
    ): string {
        // Verify signature, exact intended binding, audience, age and digest
        // before consulting the independent authority.
        $syntheticSignature = (new SyntheticS2SProtocol())->verifyForLab(
            $envelope, $ephemeralTestKey, $expectedOrganization,
            $expectedTenant, $expectedService, $expectedFixtureDigest,
            $expectedRevision, $syntheticNow, true
        );
        if ($syntheticSignature !== 'accepted_synthetic_only') {
            return $syntheticSignature;
        }

        $first = $this->checkExternal($expectedOrganization, $expectedTenant,
            $expectedService, $expectedRevision);
        if ($first !== 'external_fence_current_demo') {
            return $first;
        }

        // Internal claim is still required for SQL replay/tenant/revision
        // checking. Both database roles are least-privilege test accounts.
        $result = (new SyntheticIngressBridge($this->mainRuntime))->accept(
            $envelope, $ephemeralTestKey, $expectedOrganization, $expectedTenant,
            $expectedService, $expectedFixtureDigest, $expectedRevision,
            $syntheticNow, true
        );
        if ($result !== 'accepted_synthetic_only') {
            return $result;
        }

        // Re-check external fence after main receipt commit. This rejects
        // a visible revoke that occurred between the two reads, but is not
        // an atomic distributed fence. No ad, billing or HTTP side effect.
        $second = $this->checkExternal($expectedOrganization, $expectedTenant,
            $expectedService, $expectedRevision);
        return $second === 'external_fence_current_demo'
            ? 'accepted_synthetic_only' : $second;
    }

    private function checkExternal(
        string $org,
        string $tenant,
        string $service,
        int $revision
    ): string {
        try {
            $stmt = $this->independentFenceReader->prepare(
                'SELECT organization_ref, tenant_ref, min_revision, active
                 FROM perf_external_lab.mock_authority_fences
                 WHERE service_ref = ?'
            );
            $stmt->execute([$service]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'external_fence_unavailable';
        }
        if (!is_array($row)
            || $row['organization_ref'] !== $org
            || $row['tenant_ref'] !== $tenant) {
            return 'external_fence_not_found';
        }
        if ($row['active'] !== true && $row['active'] !== 't' && $row['active'] !== '1') {
            return 'external_fence_revoked';
        }
        if ((int) $row['min_revision'] !== $revision) {
            return 'external_fence_revision_stale';
        }
        return 'external_fence_current_demo';
    }
}
