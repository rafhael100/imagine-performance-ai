<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/F169SignedPortalReadGateway.php';

/**
 * F170: one-use signed read on disposable PG16.
 *
 * First verify Ed25519 and the tenant's actual RLS session via F169.
 * THEN consume (tenant,request_id) through a *separate* gateway SQL role.
 * An unauthorized request never consumes or returns a tenant report.
 *
 * No operational Portal identity, IAM, HTTP, Ads, billing, or WORM.
 */
final class F170NonceBoundPortalReadGateway
{
    public function __construct(
        private F169SignedPortalReadGateway $signedReader,
        private PDO $nonceDb
    ){
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F170_NONCE_LAB')!=='1'
            ||getenv('PERF_F169_SIGNED_READ_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_f162_ci'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'){
            throw new RuntimeException('F170 requires a disposable local PG16 nonce lab');
        }
        $identity=$nonceDb->query('SELECT current_database() AS database_name,
            session_user AS sql_role')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($identity)
            ||$identity['database_name']!=='perf_f162_ci'
            ||$identity['sql_role']!=='perf_f170_gateway'){
            throw new RuntimeException('F170 nonce connection requires separate gateway-only SQL role');
        }
    }

    /** @return array<string,mixed> */
    public function read(string $signedEnvelope):array
    {
        $deny=static fn(string $reason):array=>[
            'status'=>$reason,'report'=>null,
            'read_only'=>true,'action_authorized'=>false,
            'human_approval_verified'=>false,
            'can_enable_ads'=>false,'can_bill'=>false,
            'ready_for_production'=>false,'nonce_consumed_demo'=>false,
        ];
        $accepted=$this->signedReader->read($signedEnvelope);
        if(($accepted['status']??null)!=='accepted_readonly_demo'
            ||!is_array($accepted['report']??null)){
            // Signature/subject/audience and PostgreSQL tenant gate decide
            // first; no nonce gets burned by invalid/scope-mismatched request.
            return $deny((string)($accepted['status']??'untrusted_request_denied'));
        }
        try{
            $envelope=json_decode($signedEnvelope,true,8,JSON_THROW_ON_ERROR);
            $claims=$envelope['claims'];
            $tenant=$accepted['report']['tenant_ref'];
            if(!is_string($tenant)
                ||($claims['tenant']??null)!==$tenant
                ||!is_string($claims['request_id']??null)
                ||!is_int($claims['expires_at']??null))return $deny('nonce_claim_denied');
            $statement=$this->nonceDb->prepare(
                'SELECT perf_f162.f170_consume_signed_read_nonce(?,?,?)');
            $statement->bindValue(1,$tenant,PDO::PARAM_STR);
            $statement->bindValue(2,$claims['request_id'],PDO::PARAM_STR);
            $statement->bindValue(3,$claims['expires_at'],PDO::PARAM_INT);
            $statement->execute();
            $result=$statement->fetchColumn();
            if(!in_array($result,[true,'t',1,'1'],true)){
                return $deny('replay_or_nonce_denied');
            }
            return [...$accepted,'nonce_consumed_demo'=>true];
        } catch(PDOException $e) {
            return $deny('nonce_store_unavailable_fail_closed');
        } catch(\Throwable $e) {
            return $deny('nonce_claim_denied');
        }
    }
}
