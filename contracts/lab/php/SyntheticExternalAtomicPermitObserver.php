<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-47 test-only authority observation of read-only signed permit.
 * PostgreSQL 5433 simultaneously owns quarantine and one-use receipt.
 * No local 5432 lease, no provider adapter, no worker or actual execution.
 */
final class SyntheticExternalAtomicPermitObserver
{
    public function __construct(private readonly PDO $external)
    {
    }

    public function observe(string $token):string
    {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F47_EXTERNAL_ATOMIC_LAB')!=='1'
           ||getenv('PGHOST')!=='127.0.0.1'
           ||getenv('PGPORT')!=='5432'
           ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
            return 'external_atomic_lab_disabled';
        }
        if($this->external->inTransaction())return 'nested_transaction_denied';
        try{
            $identity=$this->external->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
               ||$identity['db']!=='perf_f31_nonce_ci'
               ||$identity['role']!=='perf_f47_observer'){
                return 'wrong_external_observer_role';
            }
            $s=$this->external->prepare(
                'SELECT perf_f47_authority.observe(?)'
            );
            $s->execute([$token]);
            $v=$s->fetchColumn();
            return is_string($v)?$v:'external_atomic_unavailable';
        }catch(Throwable){
            return 'external_atomic_unavailable';
        }
    }
}
