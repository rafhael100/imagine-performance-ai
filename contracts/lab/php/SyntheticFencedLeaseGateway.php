<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * CI laboratory only: dry-run generation-fenced claim/ACK.
 *
 * The number is monotonic ONLY in this one PostgreSQL database; a backup
 * rollback can reuse generations. Worker identity and clock are caller-supplied.
 * This class cannot authorize or dispatch any real operation.
 */
final class SyntheticFencedLeaseGateway
{
    public function __construct(private readonly PDO $db)
    {
    }

    private function preflight():string
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F38_FENCED_LEASE_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='portal_perf_auth_lab'){
            return 'lease_lab_disabled';
        }
        if($this->db->inTransaction())return 'nested_transaction_denied';
        try{
            $row=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||$row['db']!=='portal_perf_auth_lab'
                ||$row['role']!=='perf_f38_fenced_worker'){
                return 'wrong_database_or_role';
            }
        }catch(Throwable){return 'lease_store_unavailable';}
        return 'allowed_lab_only';
    }

    public function claim(string $owner,int $now):string
    {
        $check=$this->preflight();
        if($check!=='allowed_lab_only')return $check;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f38_fenced.claim(?,CAST(? AS bigint))'
            );
            $s->execute([$owner,$now]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'lease_store_unavailable';
        }catch(Throwable){return 'lease_store_unavailable';}
    }

    public function acknowledge(
        int $outboxId,string $owner,int $generation,int $now
    ):string
    {
        $check=$this->preflight();
        if($check!=='allowed_lab_only')return $check;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f38_fenced.ack(CAST(? AS bigint),?,'
                .'CAST(? AS bigint),CAST(? AS bigint))'
            );
            $s->execute([$outboxId,$owner,$generation,$now]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'lease_store_unavailable';
        }catch(Throwable){return 'lease_store_unavailable';}
    }
}
