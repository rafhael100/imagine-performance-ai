<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-43 CLI-only: SQL authority enforces its LOCAL quarantine atomically
 * with the F38 demo claim/ack through SECURITY DEFINER.
 *
 * Not sufficient for real operation: LOCAL state is restorable and NOT
 * synchronized/authenticated to the separate Portal recovery authority.
 * The CI-only role has only EXECUTE on guarded functions, never direct F38.
 */
final class SyntheticSqlQuarantineGateway
{
    public function __construct(private readonly PDO $db)
    {
    }

    private function allowed():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F43_ATOMIC_SQL_GUARD_LAB')!=='1'
           ||getenv('PGHOST')!=='127.0.0.1'
           ||getenv('PGPORT')!=='5432'
           ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
            return 'sql_guard_lab_disabled';
        }
        if($this->db->inTransaction())return 'nested_transaction_denied';
        try{
            $identity=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)||$identity['db']!=='portal_perf_auth_lab'
               ||$identity['role']!=='perf_f43_sql_consumer'){
               return 'wrong_sql_guard_role';
            }
        }catch(Throwable){return 'sql_guard_unavailable';}
        return 'sql_guard_allowed';
    }

    public function state():string
    {
        $allowed=$this->allowed();
        if($allowed!=='sql_guard_allowed')return $allowed;
        try{
            $state=$this->db->query(
                'SELECT perf_f43_sql_guard.read_state()'
            )->fetchColumn();
            return is_string($state)?$state:'sql_guard_unavailable';
        }catch(Throwable){return 'sql_guard_unavailable';}
    }

    public function claim(string $owner,int $now):string
    {
        $allowed=$this->allowed();
        if($allowed!=='sql_guard_allowed')return $allowed;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f43_sql_guard.claim(?,CAST(? AS bigint))'
            );
            $s->execute([$owner,$now]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'sql_guard_unavailable';
        }catch(Throwable){return 'sql_guard_unavailable';}
    }

    public function acknowledge(
        int $outboxId,string $owner,int $generation,int $epoch,int $now
    ):string
    {
        $allowed=$this->allowed();
        if($allowed!=='sql_guard_allowed')return $allowed;
        try{
            $s=$this->db->prepare(
                'SELECT perf_f43_sql_guard.ack('
                .'CAST(? AS bigint),?,CAST(? AS bigint),'
                .'CAST(? AS bigint),CAST(? AS bigint))'
            );
            $s->execute([$outboxId,$owner,$generation,$epoch,$now]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'sql_guard_unavailable';
        }catch(Throwable){return 'sql_guard_unavailable';}
    }
}
