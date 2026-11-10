<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F124 TEST-ONLY PostgreSQL receipt service. No SELECT on its table or
 * on any Performance campaign/budget table. The role is incapable of
 * deleting already-consumed nonce digests.
 *
 * The argument must come only AFTER the signed Portal F29 proof succeeds.
 * A caller could pass a nonce directly to burn it (DoS); there is no
 * independent verified IAM source, so never use this class in production.
 *
 * Backup rollback of this SAME DB can lose receipts. For production a
 * separate independent anchor/epoch and restore denial are mandatory.
 */
final class SyntheticSignedPortalNonceFence
{
    public function __construct(private readonly PDO $writer){}

    public function claim(string $nonce,int $now):string
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return 'lab_disabled';
        }
        if(preg_match('/^[a-f0-9]{32}$/D',$nonce)!==1
            ||$now<1700000000||$now>2100000000){
            return 'invalid_nonce_or_time';
        }
        try{
            if($this->writer->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||$this->writer->inTransaction()){
                return 'invalid_receipt_writer_state';
            }
            $identity=$this->writer->query(
                'SELECT current_database() AS dbname,'
                .'session_user::text AS db_login'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                ||$identity['dbname']!=='perf_test_campaign_registry'
                ||$identity['db_login']!=='perf_f124_receipt_writer'){
                return 'untrusted_receipt_writer';
            }
            // Domain separation prevents the ledger digest from being a
            // reusable raw representation of the signed Portal nonce.
            $digest=hash('sha256',"IMAGINE-PERF-F124-SIGNED-NONCE\x00".$nonce);
            // PostgreSQL ON CONFLICT may require additional SELECT
            // privileges on the target. Never expand the dedicated SQL
            // writer to SELECT. Instead INSERT once and classify ONLY the
            // nonce_sha256 primary-key violation (SQLSTATE 23505) as replay.
            $stmt=$this->writer->prepare(
                'INSERT INTO public.performance_signed_portal_demo_receipts '
                .'(nonce_sha256,seen_epoch) VALUES (CAST(? AS char(64)),'
                .'CAST(? AS bigint))'
            );
            try{
                $stmt->execute([$digest,$now]);
            }catch(\PDOException $error){
                if((string)$error->getCode()==='23505'){
                    return 'nonce_replay_denied';
                }
                throw $error;
            }
            return $stmt->rowCount()===1
                ?'nonce_recorded_ci_only'
                :'receipt_storage_unavailable';
        }catch(Throwable $error){
            // CI-only diagnostics; do not print signed packets or credentials.
            if(getenv('PERF_F124_SQL_DIAGNOSTICS_LAB')==='1'){
                fwrite(STDERR,'F124 SQL receipt diagnostic: '
                    .$error->getCode().' '.$error->getMessage().PHP_EOL);
            }
            return 'receipt_storage_unavailable';
        }
    }
}
