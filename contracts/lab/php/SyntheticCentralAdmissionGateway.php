<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-42 CLI-only privilege-cutover experiment.
 *
 * The F38 legacy SQL consumer loses EXECUTE; only the NEW synthetic role
 * perf_f42_admission may call F38 SQL. This facade checks F41 quarantine
 * before and after that call. Any holder of the NEW SQL credential could
 * still bypass the facade: expected-negative CI demonstrates this.
 * This is NOT a production admission service, IAM or security guarantee.
 */
final class SyntheticCentralAdmissionGateway
{
    public function __construct(
        private readonly PDO $externalGuard,
        private readonly PDO $internalLease
    ) {
    }

    private function state():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F42_CENTRAL_CUTOVER_LAB')!=='1'
            ||getenv('PERF_F41_QUARANTINE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'central_lab_disabled';
        }
        if($this->externalGuard->inTransaction()
            ||$this->internalLease->inTransaction()) {
            return 'central_transaction_denied';
        }
        try {
            $remote=$this->externalGuard->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $local=$this->internalLease->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($remote)||!is_array($local)
                ||$remote['db']!=='perf_f31_nonce_ci'
                ||$remote['role']!=='perf_f41_guard_reader'
                ||$local['db']!=='portal_perf_auth_lab'
                ||$local['role']!=='perf_f42_admission') {
                return 'wrong_central_roles';
            }
            $state=$this->externalGuard->query(
                'SELECT perf_f41_recovery.read_state()'
            )->fetchColumn();
            if(!is_string($state)
                ||!preg_match('/^(quarantined|released_mock):[1-9][0-9]*$/D',$state)){
                return 'central_authority_unavailable';
            }
            return $state;
        } catch(Throwable) {
            return 'central_authority_unavailable';
        }
    }

    private static function releasedEpoch(string $state):?int
    {
        if(!preg_match('/^released_mock:([1-9][0-9]*)$/D',$state,$m)){
            return null;
        }
        $epoch=filter_var($m[1],FILTER_VALIDATE_INT);
        return is_int($epoch)&&$epoch>0?$epoch:null;
    }

    public function status():string
    {
        return $this->state();
    }

    public function claim(string $owner,int $now):string
    {
        $before=$this->state();
        $epoch=self::releasedEpoch($before);
        if($epoch===null){
            return str_starts_with($before,'quarantined:')
                ?'quarantine_denied':$before;
        }
        try {
            $sql=$this->internalLease->prepare(
                'SELECT perf_f38_fenced.claim(?,CAST(? AS bigint))'
            );
            $sql->execute([$owner,$now]);
            $value=$sql->fetchColumn();
        }catch(Throwable){
            return 'central_local_unavailable';
        }
        if(!is_string($value)
            ||!preg_match('/^claimed_mock:([1-9][0-9]*):([1-9][0-9]*)$/D',
                $value,$m)) {
            return is_string($value)?$value:'central_local_unavailable';
        }
        if($this->state()!==$before){
            return 'central_quarantine_changed_after_claim';
        }
        return 'claimed_central_mock:'.$m[1].':'.$m[2].':'.$epoch;
    }

    public function acknowledge(
        int $outboxId,string $owner,int $generation,int $epoch,int $now
    ):string
    {
        $before=$this->state();
        $current=self::releasedEpoch($before);
        if($current===null) {
            return str_starts_with($before,'quarantined:')
                ?'quarantine_denied':$before;
        }
        if($epoch!==$current)return 'stale_recovery_epoch_denied';
        try{
            $sql=$this->internalLease->prepare(
                'SELECT perf_f38_fenced.ack('
                .'CAST(? AS bigint),?,CAST(? AS bigint),CAST(? AS bigint))'
            );
            $sql->execute([$outboxId,$owner,$generation,$now]);
            $value=$sql->fetchColumn();
        }catch(Throwable){
            return 'central_local_unavailable';
        }
        if($value!=='acknowledged_mock'){
            return is_string($value)?$value:'central_local_unavailable';
        }
        if($this->state()!==$before){
            return 'central_quarantine_changed_after_ack';
        }
        return 'acknowledged_central_mock';
    }
}
