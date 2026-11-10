<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * Synthetic F1-41 central facade (CLI-only). Default quarantine blocks ALL
 * calls through this facade; it cannot constrain callers invoking SQL or
 * legacy classes directly. The external table can itself be restored.
 *
 * The mock recovery epoch is stored in the independent SQL domain, but does
 * not provide hardware-backed monotonic storage or workload authentication.
 * No provider operations, scheduler, queue, real approval, routes or workers.
 */
final class SyntheticRecoveryQuarantineGate
{
    public function __construct(
        private readonly PDO $recoveryAuthority,
        private readonly SyntheticFencedLeaseGateway $localLease
    ) {
    }

    private function status():string
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F41_QUARANTINE_LAB')!=='1'
            ||getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'quarantine_lab_disabled';
        }
        if($this->recoveryAuthority->inTransaction()) {
            return 'recovery_transaction_denied';
        }
        try {
            $identity=$this->recoveryAuthority->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                ||$identity['db']!=='perf_f31_nonce_ci'
                ||$identity['role']!=='perf_f41_guard_reader') {
                return 'wrong_recovery_authority';
            }
            $s=$this->recoveryAuthority->query(
                'SELECT perf_f41_recovery.read_state()'
            )->fetchColumn();
            if(!is_string($s)
                ||!preg_match('/^(quarantined|released_mock):[1-9][0-9]*$/D',$s)) {
                return 'recovery_authority_unavailable';
            }
            return $s;
        }catch(Throwable) {
            return 'recovery_authority_unavailable';
        }
    }

    public function state():string
    {
        return $this->status();
    }

    private static function parseReleased(string $value):?int
    {
        if(!preg_match('/^released_mock:([1-9][0-9]*)$/D',$value,$m)) {
            return null;
        }
        $epoch=filter_var($m[1],FILTER_VALIDATE_INT);
        return is_int($epoch)&&$epoch>=1 ? $epoch : null;
    }

    /**
     * @return string claimed_guarded_mock:<outboxId>:<generation>:<epoch>
     */
    public function claim(string $owner,int $now):string
    {
        $before=$this->status();
        $epoch=self::parseReleased($before);
        if($epoch===null) {
            return str_starts_with($before,'quarantined:')
                ?'quarantine_denied':$before;
        }
        $local=$this->localLease->claim($owner,$now);
        if(!preg_match('/^claimed_mock:([1-9][0-9]*):([1-9][0-9]*)$/D',$local,$m)) {
            // Fail closed on any local problem; "none" is not a permit.
            return $local;
        }
        $after=$this->status();
        if($before!==$after) {
            // Lease was reserved, but never becomes an operational command.
            // It will expire; there is no external side effect.
            return 'quarantine_changed_after_claim_denied';
        }
        return 'claimed_guarded_mock:'.$m[1].':'.$m[2].':'.$epoch;
    }

    public function acknowledge(
        int $outboxId,string $owner,int $generation,int $epoch,int $now
    ):string
    {
        $before=$this->status();
        $currentEpoch=self::parseReleased($before);
        if($currentEpoch===null) {
            return str_starts_with($before,'quarantined:')
                ?'quarantine_denied':$before;
        }
        if($currentEpoch!==$epoch) {
            return 'stale_recovery_epoch_denied';
        }
        $result=$this->localLease->acknowledge(
            $outboxId,$owner,$generation,$now
        );
        if($result!=='acknowledged_mock')return $result;
        $after=$this->status();
        if($after!==$before) {
            // Synthetic ACK already committed locally, and cannot be undone.
            // The second read only notices state change, not atomic proof.
            return 'quarantine_changed_after_ack_denied';
        }
        return 'acknowledged_guarded_mock';
    }
}
