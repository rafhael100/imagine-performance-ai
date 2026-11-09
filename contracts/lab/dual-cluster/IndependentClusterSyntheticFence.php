<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use PDOException;
use Throwable;

/**
 * F1-20: EXCLUSIVE TO PHP CLI ON TWO DISPOSABLE PG16 SERVICE CONTAINERS.
 *
 * The source DB is reached through 127.0.0.1:5432, the destination is
 * 127.0.0.1:5433. Never use as a real service account, API or production
 * authorization check: there is no mTLS/workload identity, distributed
 * transaction, strong read-after-write, lock spanning clusters or remote
 * provider execution. Two containers still share one GitHub runner host.
 */
final class IndependentClusterSyntheticFence
{
    public function __construct(
        private readonly PDO $source,
        private readonly PDO $destination,
    ) {
    }

    /** @return array<string,mixed>|null */
    private function source(): ?array
    {
        try {
            $facts = $this->source->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($facts)
                || $facts['db'] !== 'portal_f20_test'
                || $facts['role'] !== 'portal_f20_reader') {
                return null;
            }
            $query = $this->source->prepare(
                'SELECT organization_ref,tenant_ref,service_ref,
                        current_revision,recovery_epoch,active
                 FROM portal_f20.mock_authority WHERE service_ref=?'
            );
            $query->execute(['svc-demo-b']);
            $row=$query->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    private function destination(): ?array
    {
        try {
            $facts=$this->destination->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($facts)
                || $facts['db'] !== 'perf_f20_test'
                || $facts['role'] !== 'perf_f20_writer') {
                return null;
            }
            $query=$this->destination->prepare(
                'SELECT organization_ref,tenant_ref,service_ref,
                        grant_revision,recovery_epoch,active
                 FROM perf_f20.mock_grants WHERE service_ref=?'
            );
            $query->execute(['svc-demo-b']);
            $row=$query->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException) {
            return null;
        }
    }

    private static function bound(?array $row): bool
    {
        return is_array($row)
            && $row['organization_ref'] === 'org-demo-b'
            && $row['tenant_ref'] === 'perf-demo-b'
            && $row['service_ref'] === 'svc-demo-b';
    }

    private static function active(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't'], true);
    }

    public function admission(): string
    {
        if (!$this->labActive()) {
            return 'cluster_lab_disabled';
        }
        $source=$this->source();
        if (!self::bound($source)) {
            return 'cluster_source_unavailable';
        }
        $destination=$this->destination();
        if (!self::bound($destination)) {
            return 'cluster_local_unavailable';
        }
        if ((int)$source['current_revision'] < 1
            || (int)$destination['grant_revision'] < 1
            || (int)$source['current_revision'] !== (int)$destination['grant_revision']) {
            return 'cluster_revision_mismatch';
        }
        if ((int)$source['recovery_epoch'] < 1
            || (int)$destination['recovery_epoch'] < 1
            || (int)$source['recovery_epoch'] !== (int)$destination['recovery_epoch']) {
            return 'cluster_restore_epoch_mismatch';
        }
        if (!self::active($source['active'])) {
            return 'cluster_source_revoked';
        }
        if (!self::active($destination['active'])) {
            return 'cluster_local_revoked';
        }
        return 'admitted_synthetic_only';
    }

    public function reconcileRevokedB(): string
    {
        if (!$this->labActive()) {
            return 'cluster_lab_disabled';
        }
        $source=$this->source();
        if (!self::bound($source)) {
            return 'cluster_source_unavailable';
        }
        if (self::active($source['active'])
            || (int)$source['current_revision'] < 2
            || (int)$source['recovery_epoch'] < 2) {
            return 'cluster_source_not_revoked';
        }
        try {
            $this->destination->beginTransaction();
            $query=$this->destination->prepare(
                'SELECT service_ref,organization_ref,tenant_ref,
                        grant_revision,recovery_epoch,active
                 FROM perf_f20.mock_grants WHERE service_ref=? FOR UPDATE'
            );
            $query->execute(['svc-demo-b']);
            $current=$query->fetch(PDO::FETCH_ASSOC);
            if (!self::bound(is_array($current)?$current:null)) {
                $this->destination->rollBack();
                return 'cluster_target_binding_denied';
            }
            $targetRev=(int)$current['grant_revision'];
            $sourceRev=(int)$source['current_revision'];
            $localActive=self::active($current['active']);
            if ($targetRev > $sourceRev
                || ($targetRev===$sourceRev && $localActive)) {
                $this->destination->rollBack();
                return 'cluster_revision_conflict';
            }
            if ($targetRev===$sourceRev
                && (int)$current['recovery_epoch']===(int)$source['recovery_epoch']) {
                $this->destination->commit();
                return 'cluster_already_reconciled_demo';
            }
            $update=$this->destination->prepare(
                'UPDATE perf_f20.mock_grants
                 SET active=false,grant_revision=?,recovery_epoch=?
                 WHERE service_ref=? AND organization_ref=? AND tenant_ref=?
                   AND grant_revision <= ?'
            );
            $update->execute([
                $sourceRev,(int)$source['recovery_epoch'],
                'svc-demo-b','org-demo-b','perf-demo-b',$sourceRev
            ]);
            if ($update->rowCount() !== 1) {
                $this->destination->rollBack();
                return 'cluster_concurrent_update_denied';
            }
            $this->destination->commit();
            return 'cluster_reconciled_synthetic_only';
        } catch (Throwable) {
            if ($this->destination->inTransaction()) {
                $this->destination->rollBack();
            }
            return 'cluster_local_unavailable';
        }
    }

    private function labActive(): bool
    {
        return PHP_SAPI === 'cli'
            && getenv('PERF_F20_DUAL_CLUSTER_LAB') === '1'
            && getenv('PORTAL_F20_PORT') === '5432'
            && getenv('PERF_F20_PORT') === '5433'
            && getenv('APP_ENV') === 'testing';
    }
}
