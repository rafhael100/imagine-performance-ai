<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F134 isolated laboratory gate. A witness-signed permit is checked again
 * inside the primary PostgreSQL SECURITY DEFINER function.
 *
 * This still does NOT solve two-database atomicity, simultaneous rollback,
 * independent IAM identity, witness operator compromise or Ads authorization.
 */
final class F134SignedWitnessRegistration
{
    public function __construct(private PDO $primary, private PDO $witness)
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_F134_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci') {
            throw new RuntimeException('F134 may run only in disposable CI labs');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $operation, string $tenant, string $fingerprint): array
    {
        $result = static fn(string $status,bool $registered=false): array => [
            'status'=>$status,'registered'=>$registered,'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $tenant)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return $result('invalid_inputs');
        }
        try {
            $epoch = $this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            if (!is_numeric($epoch) || (int)$epoch < 1) {
                return $result('primary_epoch_unknown');
            }
        } catch (PDOException $error) {
            return $result('primary_epoch_unknown');
        }
        try {
            $statement = $this->witness->prepare(
                'SELECT issued_at,signature FROM perf_f134_witness.issue_permit(?,?,?,?)'
            );
            $statement->execute([$operation,$tenant,$fingerprint,(int)$epoch]);
            $permit = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($permit)) {
                return $result('witness_denied');
            }
        } catch (PDOException $error) {
            return $result('witness_unknown');
        }

        try {
            $statement = $this->primary->prepare(
                'SELECT CASE WHEN perf_f134_primary.record_signed(?,?,?,?,?,?) THEN 1 ELSE 0 END'
            );
            $statement->execute([
                $operation,$tenant,$fingerprint,(int)$epoch,
                (int)$permit['issued_at'],(string)$permit['signature'],
            ]);
            if ((int)$statement->fetchColumn() !== 1) {
                return $result('primary_denied_witness_spent');
            }
        } catch (PDOException $error) {
            return $result('primary_unknown_witness_spent');
        }
        return $result('registered_lab',true);
    }
}
