<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F131 proof of witness-first admission with real disposable PostgreSQL connections.
 * It DOES NOT authorize Ads, dispatch, billing, or customer access.
 * No S2S trust, external attestation or cross-database atomic commit.
 */
final class F131WitnessFirstRegistration
{
    public function __construct(private PDO $primary, private PDO $witness)
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F131_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f131_ci'
            || getenv('WITNESS_DB') !== 'perf_f131_witness_ci') {
            throw new RuntimeException('F131 restricted to named disposable CI databases');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $id, string $tenant, string $fingerprint, int $epoch): array
    {
        $outcome = static fn(string $status, bool $registered = false): array =>
            ['status' => $status, 'registered' => $registered, 'action_authorized' => false];

        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $id)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $tenant)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint) || $epoch < 1) {
            return $outcome('invalid_inputs');
        }

        // Witness is the first durable write. If it is down, conflicting, or uncertain,
        // do NOT try the primary, even if it has lost its quarantine during restore.
        try {
            $w = $this->witness->prepare(
                'SELECT CASE WHEN perf_f130_witness.record_fingerprint(?) THEN 1 ELSE 0 END'
            );
            $w->execute([$fingerprint]);
            if ((int) $w->fetchColumn() !== 1) {
                return $outcome('witness_denied');
            }
        } catch (PDOException $error) {
            return $outcome('witness_unknown');
        }

        // A failed primary write burns the witness nonce. Never replay the same
        // fingerprint on reconnect; reconciliation must be a separate reviewed flow.
        try {
            $p = $this->primary->prepare(
                'SELECT CASE WHEN perf_f129_lab.record_intent(?,?,?,?) THEN 1 ELSE 0 END'
            );
            $p->execute([$id, $tenant, $fingerprint, $epoch]);
            if ((int) $p->fetchColumn() !== 1) {
                return $outcome('primary_denied_witness_spent');
            }
        } catch (PDOException $error) {
            return $outcome('primary_unknown_witness_spent');
        }

        return $outcome('registered_lab', true);
    }
}
