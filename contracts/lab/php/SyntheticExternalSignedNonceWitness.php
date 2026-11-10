<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use PDOException;
use Throwable;

/**
 * F125: a second, independently running PostgreSQL16 CI service (port 5433)
 * persists a hash of each synthetic signed Portal nonce outside the restored
 * primary database (port 5432). The witness principal can INSERT ONLY.
 *
 * This is a dry-run negative restore test, NOT production offsite durability,
 * a trusted Portal IAM assertion, a quorum, or backup/disaster recovery.
 */
final class SyntheticExternalSignedNonceWitness
{
    public function __construct(private readonly PDO $externalWriter){}

    public function witness(string $signedNonce, int $now):string
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F125_EXTERNAL_NONCE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||getenv('PERF_F125_EXTERNAL_DB')!=='perf_f125_witness_ci'
            ||getenv('PERF_F125_EXTERNAL_PORT')!=='5433'){
            return 'external_witness_lab_disabled';
        }
        if(preg_match('/^[a-f0-9]{32}$/D',$signedNonce)!==1
            ||$now<1700000000||$now>2100000000){
            return 'invalid_external_nonce';
        }
        try {
            if($this->externalWriter->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||$this->externalWriter->inTransaction()){
                return 'invalid_external_connection_state';
            }
            $identity=$this->externalWriter->query(
                'SELECT current_database()::text AS db,'
                .'session_user::text AS db_login'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                ||$identity['db']!=='perf_f125_witness_ci'
                ||$identity['db_login']!=='perf_f125_witness_writer'){
                return 'untrusted_external_writer';
            }
            $digest=hash('sha256',
                "IMAGINE-PERF-F125-EXTERNAL-SIGNED-NONCE\x00".$signedNonce
            );
            $insert=$this->externalWriter->prepare(
                'INSERT INTO public.perf_f125_signed_portal_witness '
                .'(nonce_digest,claimed_epoch) VALUES '
                .'(CAST(? AS char(64)),CAST(? AS bigint))'
            );
            try {
                $insert->execute([$digest,$now]);
            }catch(PDOException $exception){
                if((string)$exception->getCode()==='23505'){
                    return 'independent_witness_replay_denied';
                }
                throw $exception;
            }
            return $insert->rowCount()===1
                ?'external_witness_recorded_ci_only'
                :'external_witness_unavailable';
        }catch(Throwable){
            return 'external_witness_unavailable';
        }
    }
}
