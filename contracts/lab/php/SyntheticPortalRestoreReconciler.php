<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use PDOException;
use Throwable;

/**
 * F1-16: CI-only restore reconciliation with the current synthetic Portal
 * grant, NOT an event replay. Never run in a live Laravel process or VPS.
 *
 * The source and destination are in the SAME disposable PostgreSQL cluster.
 * No cross-database transaction, independent failure domain, or real
 * workload identity is provided. Only revocations are supported.
 */
final class SyntheticPortalRestoreReconciler
{
    public function __construct(
        private readonly PDO $portalSource,
        private readonly PDO $performanceMain,
    ) {
    }

    public function reconcileServiceB(): string
    {
        if (PHP_SAPI !== 'cli'
            || getenv('PERF_F16_RESTORE_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_relay_main'
            || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab') {
            return 'restore_lab_disabled';
        }

        try {
            $sourceDatabase = $this->portalSource
                ->query('SELECT current_database() AS database, current_user AS role')
                ->fetch(PDO::FETCH_ASSOC);
            $destinationDatabase = $this->performanceMain
                ->query('SELECT current_database() AS database, current_user AS role')
                ->fetch(PDO::FETCH_ASSOC);
            if (!is_array($sourceDatabase)
                || !is_array($destinationDatabase)
                || $sourceDatabase['database'] !== 'portal_lab'
                || $sourceDatabase['role'] !== 'portal_perf_source_reader'
                || $destinationDatabase['database'] !== 'perf_test_relay_main'
                || $destinationDatabase['role'] !== 'perf_test_wire_ingress') {
                return 'restore_connection_denied';
            }
            $query = $this->portalSource->prepare(
                'SELECT service_ref,organization_ref,tenant_ref,min_revision,active
                 FROM lab_portal_perf_authority WHERE service_ref=?'
            );
            $query->execute(['svc-demo-b']);
            $source = $query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'restore_source_unavailable';
        }

        if (!is_array($source)
            || $source['service_ref'] !== 'svc-demo-b'
            || $source['organization_ref'] !== 'org-demo-b'
            || $source['tenant_ref'] !== 'perf-demo-b'
            || !in_array($source['active'], [false,'f','0',0],true)
            || (int) $source['min_revision'] < 2) {
            return 'restore_authority_not_revoked';
        }
        $revision = (int) $source['min_revision'];
        try {
            $this->performanceMain->beginTransaction();
            $find = $this->performanceMain->prepare(
                'SELECT organization_ref,tenant_ref,grant_revision,active
                 FROM perf_s2s_lab.mock_service_grants
                 WHERE service_ref=? FOR UPDATE'
            );
            $find->execute(['svc-demo-b']);
            $target = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($target)
                || $target['organization_ref'] !== 'org-demo-b'
                || $target['tenant_ref'] !== 'perf-demo-b') {
                $this->performanceMain->rollBack();
                return 'restore_target_binding_denied';
            }
            $localRevision = (int) $target['grant_revision'];
            $localActive = in_array($target['active'], [true,'t','1',1],true);
            if ($localRevision > $revision
                || ($localRevision === $revision && $localActive)) {
                $this->performanceMain->rollBack();
                return 'restore_revision_conflict';
            }
            if ($localRevision === $revision) {
                $this->performanceMain->commit();
                return 'restore_already_reconciled_demo';
            }
            $update = $this->performanceMain->prepare(
                'UPDATE perf_s2s_lab.mock_service_grants
                 SET active=false,grant_revision=?,updated_at=clock_timestamp()
                 WHERE service_ref=? AND organization_ref=? AND tenant_ref=?
                   AND grant_revision < ?'
            );
            $update->execute([
                $revision,'svc-demo-b','org-demo-b','perf-demo-b',$revision
            ]);
            if ($update->rowCount() !== 1) {
                $this->performanceMain->rollBack();
                return 'restore_concurrent_write_denied';
            }
            $this->performanceMain->commit();
            return 'restore_reconciled_demo';
        } catch (Throwable) {
            if ($this->performanceMain->inTransaction()) {
                $this->performanceMain->rollBack();
            }
            return 'restore_target_unavailable';
        }
    }
}
