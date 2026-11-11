<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F139 disposable lab bridge: SQL-side FDW row-mark protects a third-anchor
 * epoch across a single primary transaction (not cross-db atomic commit).
 */
final class F139LockedAnchorRegistration
{
    public function __construct(
        private PDO $primary,
        private PDO $witness,
        private PDO $anchor
    ) {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F139_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci'
            || getenv('ANCHOR_DB') !== 'perf_f138_anchor_ci') {
            throw new RuntimeException('F139 restricted to three disposable CI databases');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $id,string $tenant,string $fingerprint):array
    {
        $outcome = static fn(string $status,bool $registered=false):array => [
            'status'=>$status,
            'registered'=>$registered,
            'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$id)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            return $outcome('invalid_inputs');
        }
        try {
            $local=$this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            $external=$this->anchor->query(
                'SELECT generation FROM perf_f138_anchor.epoch_floor WHERE singleton=1'
            )->fetchColumn();
            if (!is_numeric($local) || !is_numeric($external)
                || (int)$local<1 || (int)$local!==(int)$external) {
                return $outcome('anchor_epoch_mismatch');
            }
            $epoch=(int)$local;
        } catch(PDOException $e) {
            return $outcome('anchor_or_primary_unavailable');
        }
        try {
            $q=$this->witness->prepare(
                'SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)'
            );
            $q->execute([$id,$tenant,$fingerprint,$epoch]);
            $permit=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($permit)) return $outcome('witness_denied');
        } catch(PDOException $e) {
            return $outcome('witness_unavailable_or_unknown');
        }
        try {
            $q=$this->primary->prepare(
                'SELECT CASE WHEN perf_f139_primary.record_with_locked_anchor(?,?,?,?,?,?) THEN 1 ELSE 0 END'
            );
            $q->execute([
                $id,$tenant,$fingerprint,$epoch,
                (int)$permit['issued_at'],(string)$permit['signature'],
            ]);
            if ((int)$q->fetchColumn()!==1) {
                return $outcome('primary_or_anchor_denied_witness_spent');
            }
        } catch(PDOException $e) {
            return $outcome('primary_or_anchor_unknown_witness_spent');
        }
        return $outcome('registered_lab',true);
    }
}
