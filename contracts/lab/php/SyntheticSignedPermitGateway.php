<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * TEST-ONLY bearer permit bridge for F1-45. This calls TWO disposable SQL
 * databases; it neither signs using PHP nor stores any HMAC key in PHP.
 * Workload roles, clock and owner are synthetic. No network/API dispatch.
 *
 * Not production-safe: HMAC verifier holds signing secret, time is
 * supplied by caller, a previously minted permit may outlive revocation,
 * and the two databases can be rolled back together.
 */
final class SyntheticSignedPermitGateway
{
    public function __construct(
        private readonly PDO $issuer,
        private readonly PDO $admission
    ) {
    }

    private function allowed():string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F45_SIGNED_PERMIT_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'signed_lab_disabled';
        }
        if($this->issuer->inTransaction()||$this->admission->inTransaction()){
            return 'nested_transaction_denied';
        }
        try{
            $r=$this->issuer->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $l=$this->admission->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)||!is_array($l)
                ||$r['db']!=='perf_f31_nonce_ci'
                ||$r['role']!=='perf_f45_issuer'
                ||$l['db']!=='portal_perf_auth_lab'
                ||$l['role']!=='perf_f45_consumer') {
                return 'wrong_signed_roles';
            }
        }catch(Throwable) {
            return 'signed_authority_unavailable';
        }
        return 'lab_allowed';
    }

    public function mint(
        string $action,int $outbox,string $owner,int $generation,
        int $now,string $nonce
    ):string
    {
        $check=$this->allowed();
        if($check!=='lab_allowed')return $check;
        try{
            $s=$this->issuer->prepare(
                'SELECT perf_f45_issuer.mint(?,CAST(? AS bigint),?,'
                .'CAST(? AS bigint),CAST(? AS bigint),?)'
            );
            $s->execute([$action,$outbox,$owner,$generation,$now,$nonce]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'permit_issuer_unavailable';
        }catch(Throwable) {
            return 'permit_issuer_unavailable';
        }
    }

    public function consume(string $token,int $now):string
    {
        $check=$this->allowed();
        if($check!=='lab_allowed')return $check;
        try{
            $s=$this->admission->prepare(
                'SELECT perf_f45_admission.consume(?,CAST(? AS bigint))'
            );
            $s->execute([$token,$now]);
            $value=$s->fetchColumn();
            return is_string($value)?$value:'permit_admission_unavailable';
        }catch(Throwable) {
            return 'permit_admission_unavailable';
        }
    }
}
