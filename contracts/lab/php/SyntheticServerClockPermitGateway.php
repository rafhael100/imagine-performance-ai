<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * Disposable F1-46 CI: server-clock-only mint/consume using F45 HMAC.
 * The claimed clock argument is deliberately sent for negative tests:
 * BOTH SQL functions ignore it and use their own PostgreSQL clock.
 *
 * No trusted workload identity, no real destination, no KMS and no
 * guarantee about two databases' clock synchronization or revocation.
 */
final class SyntheticServerClockPermitGateway
{
    public function __construct(
        private readonly PDO $issuer,
        private readonly PDO $admission
    ) {
    }

    private function preflight():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F46_SERVER_CLOCK_LAB')!=='1'
           ||getenv('PGHOST')!=='127.0.0.1'
           ||getenv('PGPORT')!=='5432'
           ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'server_clock_lab_disabled';
        }
        if($this->issuer->inTransaction()||$this->admission->inTransaction()){
            return 'nested_transaction_denied';
        }
        try{
            $remote=$this->issuer->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $local=$this->admission->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($remote)||!is_array($local)
               ||$remote['db']!=='perf_f31_nonce_ci'
               ||$remote['role']!=='perf_f46_issuer'
               ||$local['db']!=='portal_perf_auth_lab'
               ||$local['role']!=='perf_f46_consumer') {
                return 'wrong_server_clock_roles';
            }
        }catch(Throwable){return 'server_clock_unavailable';}
        return 'allowed_test_only';
    }

    public function mint(
        string $action,int $outbox,string $owner,int $generation,
        string $nonce,int $claimedClock
    ):string
    {
        $guard=$this->preflight();
        if($guard!=='allowed_test_only')return $guard;
        try{
            $stmt=$this->issuer->prepare(
                'SELECT perf_f46_issuer.mint_server(?,CAST(? AS bigint),?,'
                .'CAST(? AS bigint),?,CAST(? AS bigint))'
            );
            $stmt->execute([
                $action,$outbox,$owner,$generation,$nonce,$claimedClock
            ]);
            $value=$stmt->fetchColumn();
            return is_string($value)?$value:'server_issuer_unavailable';
        }catch(Throwable){return 'server_issuer_unavailable';}
    }

    public function consume(string $token,int $claimedClock):string
    {
        $guard=$this->preflight();
        if($guard!=='allowed_test_only')return $guard;
        try{
            $stmt=$this->admission->prepare(
                'SELECT perf_f46_admission.consume_server('
                .'?,CAST(? AS bigint))'
            );
            $stmt->execute([$token,$claimedClock]);
            $value=$stmt->fetchColumn();
            return is_string($value)?$value:'server_admission_unavailable';
        }catch(Throwable){return 'server_admission_unavailable';}
    }
}
