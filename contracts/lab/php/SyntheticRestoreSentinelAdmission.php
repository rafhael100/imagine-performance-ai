<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-50 laboratory-only three-domain restore sentinel. Every call to this
 * facade checks the PRESENT F41 state, and a third un-restored SQL domain
 * stores an epoch floor plus one-use nonce. Calls to F47.observe remain
 * directly possible for privileged roles (expected-negative CI).
 *
 * No distributed commit, monotonic hardware, signed workload identity,
 * provider action, or production runtime is supplied by this class.
 */
final class SyntheticRestoreSentinelAdmission
{
    public function __construct(
        private readonly PDO $externalAuthority,
        private readonly PDO $independentSentinel
    ) {
    }

    private function preflight():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F50_RESTORE_SENTINEL_LAB')!=='1'
           ||getenv('PGHOST')!=='127.0.0.1'
           ||getenv('PGPORT')!=='5432'
           ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'restore_sentinel_lab_disabled';
        }
        if($this->externalAuthority->inTransaction()
           ||$this->independentSentinel->inTransaction()) {
            return 'nested_transaction_denied';
        }
        try{
            $remote=$this->externalAuthority->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $sentinel=$this->independentSentinel->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($remote)||!is_array($sentinel)
               ||$remote['db']!=='perf_f31_nonce_ci'
               ||$remote['role']!=='perf_f47_observer'
               ||$sentinel['db']!=='perf_f50_sentinel_ci'
               ||$sentinel['role']!=='perf_f50_observer'){
                return 'wrong_sentinel_roles';
            }
        }catch(Throwable){return 'sentinel_source_unavailable';}
        return 'ci_only_allowed';
    }

    private function sourceState():string
    {
        try{
            $state=$this->externalAuthority->query(
                'SELECT perf_f41_recovery.read_state()'
            )->fetchColumn();
            if(is_string($state)
              &&preg_match('/^(quarantined|released_mock):[1-9][0-9]*$/D',$state)){
                return $state;
            }
        }catch(Throwable){}
        return 'sentinel_source_unavailable';
    }

    public function observe(string $signedToken):string
    {
        $guard=$this->preflight();
        if($guard!=='ci_only_allowed')return $guard;
        if(strlen($signedToken)>500)return 'malformed_sentinel_permit_denied';

        $parts=explode('|',$signedToken);
        if(count($parts)!==10||$parts[0]!=='f45'
          ||$parts[1]!=='claim'
          ||!preg_match('/^[1-9][0-9]{0,15}$/D',$parts[5])
          ||!preg_match('/^f45-[a-z0-9-]{12,44}$/D',$parts[8])){
            return 'malformed_sentinel_permit_denied';
        }
        $epoch=(int)$parts[5];
        $nonce=$parts[8];
        $before=$this->sourceState();
        if(str_starts_with($before,'quarantined:')) {
            return 'external_quarantine_denied';
        }
        if(!str_starts_with($before,'released_mock:')){
            return $before;
        }
        if($before!=='released_mock:'.$epoch){
            return 'source_epoch_mismatch_denied';
        }

        // The third SQL domain detects source restore AND prior nonces before
        // even trying the external authority's potentially restored receipts.
        try{
            $reserve=$this->independentSentinel->prepare(
                'SELECT perf_f50_sentinel.check_and_reserve('
                .'CAST(? AS bigint),?)'
            );
            $reserve->execute([$epoch,$nonce]);
            $r=$reserve->fetchColumn();
            if(!is_string($r))return 'sentinel_unavailable';
        }catch(Throwable){return 'sentinel_unavailable';}
        if($r!=='reserved_sentinel_mock:'.$nonce.':'.$epoch)return $r;

        try{
            $observe=$this->externalAuthority->prepare(
                'SELECT perf_f47_authority.observe(?)'
            );
            $observe->execute([$signedToken]);
            $result=$observe->fetchColumn();
            if(!is_string($result))return 'sentinel_external_unavailable';
        }catch(Throwable){return 'sentinel_external_unavailable';}
        if($result!=='observed_external_mock:'.$nonce.':'.$epoch){
            // A failed external observation can leave the third-domain nonce
            // consumed: availability tradeoff, not a valid external action.
            return $result;
        }
        if($this->sourceState()!==$before){
            // F47 may already have persisted an observation. The distributed
            // CHECK IS NOT ATOMIC; caller denied but receipts can differ.
            return 'source_changed_after_observe_denied';
        }
        return 'observed_with_sentinel_mock:'.$nonce.':'.$epoch;
    }
}
