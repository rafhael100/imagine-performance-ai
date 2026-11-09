<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use PDOException;
use Throwable;

/**
 * F1-11: Synchronous, TEST-ONLY, in-process revocation relay.
 *
 * This reads a SIMULATED Portal authority in a second DB and reconciles the
 * Performance test grant. It has NO HTTP, queue, real Portal credential,
 * certificate, workload identity, commercial authorization or remote effect.
 * Never register in Laravel, a production worker, or an API route.
 */
final class SyntheticRevocationRelay
{
    public function __construct(
        private readonly PDO $mockAuthorityReader,
        private readonly PDO $mockMainUpdater,
        private readonly PDO $mockOutboxAcker,
    ) {
    }

    public function deliver(
        string $eventId,
        bool $transportAvailable = true,
        bool $simulateAckLoss = false,
    ): string {
        if (!preg_match('/^evt-demo-[a-z0-9-]{1,80}$/D', $eventId)) {
            return 'invalid_lab_event_id';
        }
        if (!$transportAvailable) {
            // Transport loss must never silently advance an outbox cursor.
            return 'relay_deferred_demo';
        }

        try {
            $statement = $this->mockAuthorityReader->prepare(
                'SELECT event_id, service_ref, organization_ref, tenant_ref,
                        source_revision, event_type, status
                 FROM perf_external_lab.mock_revocation_outbox
                 WHERE event_id = ?'
            );
            $statement->execute([$eventId]);
            $event = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($event)) {
                return 'relay_unknown_event';
            }

            $authority = $this->mockAuthorityReader->prepare(
                'SELECT service_ref, organization_ref, tenant_ref,
                        min_revision, active
                 FROM perf_external_lab.mock_authority_fences
                 WHERE service_ref = ?'
            );
            $authority->execute([$event['service_ref']]);
            $current = $authority->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'relay_authority_unavailable';
        }

        if (!is_array($current)) {
            return 'relay_authority_missing';
        }
        if ($event['event_type'] !== 'service.authorization_changed.synthetic'
            || $event['organization_ref'] !== $current['organization_ref']
            || $event['tenant_ref'] !== $current['tenant_ref']
            || !is_numeric($event['source_revision'])
            || (int) $event['source_revision'] < 1
            || (int) $event['source_revision'] > (int) $current['min_revision']) {
            return 'relay_untrusted_binding';
        }
        if (!in_array($current['active'], [true, false, 't', 'f', '1', '0', 1, 0], true)
            || (int) $current['min_revision'] < 1) {
            return 'relay_authority_invalid';
        }

        // A stale event is only a wake-up notification. We NEVER apply its
        // historical state/revision: we read CURRENT authority on each retry.
        $active = in_array($current['active'], [true, 't', '1', 1], true);
        $revision = (int) $current['min_revision'];
        $updated = false;
        try {
            $this->mockMainUpdater->beginTransaction();

            $select = $this->mockMainUpdater->prepare(
                'SELECT organization_ref, tenant_ref, grant_revision, active
                 FROM perf_s2s_lab.mock_service_grants
                 WHERE service_ref = ? FOR UPDATE'
            );
            $select->execute([$event['service_ref']]);
            $main = $select->fetch(PDO::FETCH_ASSOC);

            if (!is_array($main)) {
                $this->mockMainUpdater->rollBack();
                return 'relay_main_missing';
            }
            if ($main['organization_ref'] !== $current['organization_ref']
                || $main['tenant_ref'] !== $current['tenant_ref']) {
                $this->mockMainUpdater->rollBack();
                return 'relay_main_binding_denied';
            }

            $previousRevision = (int) $main['grant_revision'];
            if ($previousRevision > $revision) {
                $this->mockMainUpdater->rollBack();
                return 'relay_authority_stale_denied';
            }
            if ($previousRevision === $revision) {
                $mainActive = in_array($main['active'], [true, 't', '1', 1], true);
                if ($mainActive !== $active) {
                    $this->mockMainUpdater->rollBack();
                    return 'relay_same_revision_conflict';
                }
            } else {
                $update = $this->mockMainUpdater->prepare(
                    'UPDATE perf_s2s_lab.mock_service_grants
                     SET grant_revision = ?, active = ?, updated_at = clock_timestamp()
                     WHERE service_ref = ? AND organization_ref = ?
                       AND tenant_ref = ? AND grant_revision < ?'
                );
                $update->execute([
                    $revision, $active, $event['service_ref'],
                    $current['organization_ref'], $current['tenant_ref'], $revision,
                ]);
                if ($update->rowCount() !== 1) {
                    $this->mockMainUpdater->rollBack();
                    return 'relay_concurrent_update_denied';
                }
                $updated = true;
            }
            $this->mockMainUpdater->commit();
        } catch (Throwable) {
            if ($this->mockMainUpdater->inTransaction()) {
                $this->mockMainUpdater->rollBack();
            }
            return 'relay_main_unavailable';
        }

        // The main update and outbox ACK are in DIFFERENT databases, so a
        // crash can occur here. In that case, leave event pending and retry:
        // the monotonic main update is idempotent (not an external side effect).
        if ($simulateAckLoss) {
            return 'relay_ack_deferred_demo';
        }

        try {
            $ack = $this->mockOutboxAcker->prepare(
                "UPDATE perf_external_lab.mock_revocation_outbox
                 SET status = 'acked', attempts = attempts + 1
                 WHERE event_id = ? AND status = 'pending'"
            );
            $ack->execute([$eventId]);
        } catch (PDOException) {
            return 'relay_ack_unavailable';
        }

        return $updated ? 'relay_synced_demo' : 'relay_already_synced_demo';
    }
}
