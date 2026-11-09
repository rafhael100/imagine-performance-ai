<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-39 negative/positive experimental high-watermark.
 * Caller supplies id/generation; authority DOES NOT authenticate caller.
 * Fail-closed if second DB is unavailable or role is wrong. Only CI fixtures.
 */
final class SyntheticIndependentLeaseHighwater
{
    public function __construct(private readonly PDO $authority)
    {
    }

    public function observe(int $id,int $generation):string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F39_EXTERNAL_FENCE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'external_fence_lab_disabled';
        }
        if($this->authority->inTransaction()) {
            return 'external_transaction_denied';
        }
        try{
            $identity=$this->authority->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
              ||$identity['db']!=='perf_f31_nonce_ci'
              ||$identity['role']!=='perf_f39_fence_consumer'){
                return 'wrong_external_authority';
            }
            $stmt=$this->authority->prepare(
                'SELECT perf_f39_fence.advance(CAST(? AS bigint),CAST(? AS bigint))'
            );
            $stmt->execute([$id,$generation]);
            $result=$stmt->fetchColumn();
            return is_string($result)?$result:'external_fence_unavailable';
        }catch(Throwable){
            return 'external_fence_unavailable';
        }
    }
}
