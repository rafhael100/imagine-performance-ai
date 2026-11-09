<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-44 disposable CI: compare EXTERNAL F41 and LOCAL F43 recovery epoch
 * before/after synthetic guarded claims and ACKs. Not atomic across DBs.
 * The local F43 SQL role can still bypass this PHP wrapper directly.
 * No real credential, restore detector, worker, external effect or approval.
 */
final class SyntheticCrossAuthorityAdmission
{
    public function __construct(
        private readonly PDO $externalAuthority,
        private readonly SyntheticSqlQuarantineGateway $local
    ) {
    }

    private function external():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F44_CROSS_AUTHORITY_LAB')!=='1'
           ||getenv('PERF_F43_ATOMIC_SQL_GUARD_LAB')!=='1'
           ||getenv('PGHOST')!=='127.0.0.1'
           ||getenv('PGPORT')!=='5432'
           ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'cross_authority_lab_disabled';
        }
        if($this->externalAuthority->inTransaction()){
            return 'external_transaction_denied';
        }
        try{
            $id=$this->externalAuthority->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($id)||$id['db']!=='perf_f31_nonce_ci'
                ||$id['role']!=='perf_f41_guard_reader'){
                return 'wrong_external_role';
            }
            $value=$this->externalAuthority->query(
                'SELECT perf_f41_recovery.read_state()'
            )->fetchColumn();
            if(!is_string($value)
                ||!preg_match('/^(quarantined|released_mock):[1-9][0-9]*$/D',
                     $value)) {
                return 'external_authority_unavailable';
            }
            return $value;
        }catch(Throwable){
            return 'external_authority_unavailable';
        }
    }

    private function matchedRelease():string
    {
        $remote=$this->external();
        if(!preg_match('/^released_mock:[1-9][0-9]*$/D',$remote)){
            return str_starts_with($remote,'quarantined:')
                ?'external_quarantine_denied':$remote;
        }
        $local=$this->local->state();
        if($local!==$remote)return 'authority_epoch_mismatch_denied';
        return $remote;
    }

    public function status():string
    {
        return $this->matchedRelease();
    }

    public function claim(string $owner,int $now):string
    {
        $before=$this->matchedRelease();
        if(!str_starts_with($before,'released_mock:'))return $before;
        $value=$this->local->claim($owner,$now);
        if(!preg_match(
            '/^claimed_sql_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',
            $value,$m
        ))return $value;
        if($this->matchedRelease()!==$before){
            return 'authority_changed_after_claim_denied';
        }
        return 'claimed_cross_mock:'.$m[1].':'.$m[2].':'.$m[3];
    }

    public function acknowledge(
        int $id,string $owner,int $generation,int $epoch,int $now
    ):string
    {
        $before=$this->matchedRelease();
        if(!str_starts_with($before,'released_mock:'))return $before;
        $value=$this->local->acknowledge($id,$owner,$generation,$epoch,$now);
        if($value!=='acknowledged_sql_mock')return $value;
        if($this->matchedRelease()!==$before){
            // This local SQL ACK may already be committed; fail closed for
            // caller but cannot offer distributed commit or rollback.
            return 'authority_changed_after_ack_denied';
        }
        return 'acknowledged_cross_mock';
    }
}
