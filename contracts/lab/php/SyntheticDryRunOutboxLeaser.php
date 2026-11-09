<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-37 synthetic-only dry-run of outbox leasing. No delivery code.
 * Owner and clock come from caller (insecure outside CI); never register this
 * with Laravel, n8n, real Ads or billing.
 */
final class SyntheticDryRunOutboxLeaser
{
    public function __construct(private readonly PDO $db)
    {
    }

    private function allowed():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F37_LEASE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
            return 'lease_lab_disabled';
        }
        if($this->db->inTransaction())return 'nested_transaction_denied';
        try{
            $id=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($id)||$id['db']!=='portal_perf_auth_lab'
                ||$id['role']!=='perf_f37_lease_worker'){
                return 'wrong_database_or_role';
            }
            return 'allowed_to_test_only';
        }catch(Throwable){
            return 'lease_store_unavailable';
        }
    }

    public function claim(string $owner,int $now):string
    {
        $preflight=$this->allowed();
        if($preflight!=='allowed_to_test_only')return $preflight;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f37_dry_run.claim(?,CAST(? AS bigint))'
            );
            $s->execute([$owner,$now]);
            $v=$s->fetchColumn();
            return is_string($v)?$v:'lease_store_unavailable';
        }catch(Throwable){
            return 'lease_store_unavailable';
        }
    }

    public function acknowledge(int $id,string $owner,int $now):string
    {
        $preflight=$this->allowed();
        if($preflight!=='allowed_to_test_only')return $preflight;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f37_dry_run.ack(CAST(? AS bigint),?,CAST(? AS bigint))'
            );
            $s->execute([$id,$owner,$now]);
            $v=$s->fetchColumn();
            return is_string($v)?$v:'lease_store_unavailable';
        }catch(Throwable){
            return 'lease_store_unavailable';
        }
    }
}
