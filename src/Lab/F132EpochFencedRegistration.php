<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F132 CI-only proof: witness epoch floor prevents fresh tokens after
 * a primary-only rollback, even when old primary quarantine was forgotten.
 * This does not authorize an external action or provide atomic cross-DB commits.
 */
final class F132EpochFencedRegistration
{
    public function __construct(private PDO $primary, private PDO $witness)
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F132_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
            throw new RuntimeException('F132: disposable CI environment required');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $operation, string $tenant, string $fingerprint): array
    {
        $outcome = static fn(string $status, bool $registered=false): array =>
            ['status'=>$status,'registered'=>$registered,'action_authorized'=>false];

        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $tenant)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return $outcome('invalid_inputs');
        }
        // Caller cannot supply the generation, but SQL login/DB admin are still
        // laboratory identities, not authenticated Portal workloads.
        try {
            $generation = $this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            if (!is_numeric($generation) || (int)$generation < 1) {
                return $outcome('primary_generation_unknown');
            }
            $epoch = (int)$generation;
        } catch (PDOException $error) {
            return $outcome('primary_generation_unknown');
        }

        try {
            $s = $this->witness->prepare(
                'SELECT CASE WHEN perf_f132_witness.reserve_if_current(?,?) THEN 1 ELSE 0 END'
            );
            $s->execute([$fingerprint, $epoch]);
            if ((int)$s->fetchColumn() !== 1) {
                return $outcome('witness_epoch_or_replay_denied');
            }
        } catch (PDOException $error) {
            return $outcome('witness_unavailable_or_unknown');
        }

        try {
            $s = $this->primary->prepare(
                'SELECT CASE WHEN perf_f132_primary.record_with_generation(?,?,?,?) THEN 1 ELSE 0 END'
            );
            $s->execute([$operation, $tenant, $fingerprint, $epoch]);
            if ((int)$s->fetchColumn() !== 1) {
                return $outcome('primary_denied_witness_spent');
            }
        } catch (PDOException $error) {
            return $outcome('primary_unknown_witness_spent');
        }
        return $outcome('registered_lab', true);
    }
}
