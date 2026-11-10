<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F136 CI-only laboratory: postgres_fdw SQL redemption checks a witness row
 * ONLINE from the primary write function. Never authorizes advertising.
 *
 * There is NO distributed 2PC, external durable authority, Portal IAM,
 * business approval, live Ads integration or billing.
 */
final class F136OnlineWitnessRedemption
{
    public function __construct(private PDO $primary, private PDO $witness)
    {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F136_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
            throw new RuntimeException('F136 restricted to disposable CI PostgreSQL');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $operation, string $tenant, string $fingerprint): array
    {
        $outcome = static fn(string $status, bool $registered=false): array => [
            'status'=>$status, 'registered'=>$registered, 'action_authorized'=>false,
        ];

        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $tenant)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return $outcome('invalid_inputs');
        }
        try {
            $epoch=$this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            if (!is_numeric($epoch) || (int)$epoch < 1) {
                return $outcome('unknown_primary_generation');
            }
        } catch (PDOException $e) {
            return $outcome('unknown_primary_generation');
        }

        try {
            $q=$this->witness->prepare(
                'SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)'
            );
            $q->execute([$operation,$tenant,$fingerprint,(int)$epoch]);
            $permit=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($permit)) {
                return $outcome('witness_denied');
            }
        } catch (PDOException $e) {
            return $outcome('witness_unknown');
        }

        try {
            $q=$this->primary->prepare(
                'SELECT CASE WHEN perf_f136_primary.redeem_and_record(?,?,?,?,?,?) THEN 1 ELSE 0 END'
            );
            $q->execute([
                $operation,$tenant,$fingerprint,(int)$epoch,
                (int)$permit['issued_at'],(string)$permit['signature'],
            ]);
            if ((int)$q->fetchColumn() !== 1) {
                return $outcome('primary_or_online_witness_denied');
            }
        } catch (PDOException $e) {
            return $outcome('primary_or_online_witness_unknown');
        }
        return $outcome('registered_lab',true);
    }
}
