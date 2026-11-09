<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use Imagine\Performance\Domain\SyntheticCampaignRegistry;
use PDO;
use PDOException;

/**
 * F1-17 TEST ONLY. Before a synthetic campaign operation, compare current
 * Portal service authority with the Performance local snapshot. No real
 * Laravel route, Google/Meta call, commercial grant or external effect.
 *
 * Source and local database share one disposable CI cluster: not production
 * identity, recovery guarantee or independent revocation authority.
 */
final class SyntheticPortalEligibilityGate
{
    public function __construct(
        private readonly PDO $source,
        private readonly PDO $local
    ) {
    }

    public function check(
        string $organization,
        string $tenant,
        string $service
    ): string {
        if (PHP_SAPI !== 'cli'
            || getenv('PERF_F17_ELIGIBILITY_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_relay_main'
            || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab') {
            return 'eligibility_lab_disabled';
        }
        if ($organization !== 'org-demo-b'
            || $tenant !== 'perf-demo-b'
            || $service !== 'svc-demo-b') {
            return 'eligibility_binding_denied';
        }

        try {
            $sourceRole = $this->source
                ->query('SELECT current_database() AS db,current_user AS role')
                ->fetch(PDO::FETCH_ASSOC);
            $localRole = $this->local
                ->query('SELECT current_database() AS db,current_user AS role')
                ->fetch(PDO::FETCH_ASSOC);
            if (!is_array($sourceRole) || !is_array($localRole)
                || $sourceRole['db'] !== 'portal_lab'
                || $sourceRole['role'] !== 'portal_perf_source_reader'
                || $localRole['db'] !== 'perf_test_relay_main'
                || $localRole['role'] !== 'perf_test_wire_ingress') {
                return 'eligibility_wrong_role_denied';
            }
            $statement = $this->source->prepare(
                'SELECT service_ref,organization_ref,tenant_ref,min_revision,active
                 FROM lab_portal_perf_authority WHERE service_ref=?'
            );
            $statement->execute(['svc-demo-b']);
            $upstream = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'eligibility_source_unavailable';
        }
        if (!is_array($upstream)
            || $upstream['service_ref'] !== $service
            || $upstream['organization_ref'] !== $organization
            || $upstream['tenant_ref'] !== $tenant) {
            return 'eligibility_source_not_found';
        }
        try {
            $statement = $this->local->prepare(
                'SELECT organization_ref,tenant_ref,grant_revision,active
                 FROM perf_s2s_lab.mock_service_grants WHERE service_ref=?'
            );
            $statement->execute(['svc-demo-b']);
            $snapshot = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'eligibility_local_unavailable';
        }
        if (!is_array($snapshot)
            || $snapshot['organization_ref'] !== $organization
            || $snapshot['tenant_ref'] !== $tenant) {
            return 'eligibility_local_not_found';
        }
        $srcRevision = (int) $upstream['min_revision'];
        $localRevision = (int) $snapshot['grant_revision'];
        if ($srcRevision < 1 || $localRevision < 1
            || $srcRevision !== $localRevision) {
            // Restore/replication lag: never use the local active bit.
            return 'eligibility_revision_mismatch';
        }
        $srcActive = in_array($upstream['active'], [true,'t','1',1],true);
        if (!$srcActive) {
            return 'eligibility_source_revoked';
        }
        $localActive = in_array($snapshot['active'], [true,'t','1',1],true);
        if (!$localActive) {
            return 'eligibility_local_revoked';
        }
        return 'allowed_synthetic_only';
    }
}

/** Prevent even demo-only state change when eligibility cannot be proven. */
final class SyntheticGuardedCampaignActions
{
    public function __construct(
        private readonly SyntheticPortalEligibilityGate $eligibility,
        private readonly SyntheticCampaignRegistry $registry
    ) {
    }

    public function pause(string $tenant, string $campaign): string
    {
        if ($this->eligibility->check(
            'org-demo-b', $tenant, 'svc-demo-b'
        ) !== 'allowed_synthetic_only') {
            return 'campaign_denied_by_eligibility_demo';
        }
        return $this->registry->pause($tenant, $campaign);
    }

    public function replace(
        string $tenant,
        string $oldCampaign,
        string $newCampaign
    ): string {
        if ($this->eligibility->check(
            'org-demo-b', $tenant, 'svc-demo-b'
        ) !== 'allowed_synthetic_only') {
            return 'campaign_denied_by_eligibility_demo';
        }
        // Even after eligibility, replacement still requires human approval.
        return $this->registry->replace(
            $tenant, $oldCampaign, $newCampaign, false
        );
    }
}
