<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F124 demo: F123 verified signed Portal + SQL read, THEN independently
 * insert its nonce digest into a PostgreSQL unique ledger before returning.
 *
 * The F29 in-memory replay cache alone is NOT sufficient across workers;
 * this demo ledger gives a local DB-wide single-consumption property.
 *
 * LIMITS: caller-constructible SyntheticCurrentAuthority, no real Portal
 * IAM/MFA/S2S, no independent backup/restore antirollback, token can be
 * consumed before response delivery, and the DB writer can burn arbitrary
 * nonce hashes. Not a production entitlement or API.
 */
final class SyntheticDurableSignedSqlPreviewBridge
{
    public const SCHEMA='perf.portal.sql-signed-durable-nonce-demo.v1';

    public function __construct(
        private readonly SyntheticPortalSqlWorkbenchBridge $signedSql,
        private readonly SyntheticSignedPortalNonceFence $nonceFence,
    ){}

    private static function denied(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
            'synthetic_only'=>true,
            'read_only'=>true,
            'cryptographic_lab_proof_verified'=>false,
            'replay_receipt_recorded'=>false,
            'credential_binding_confirmed'=>false,
            'portal_identity_authenticated'=>false,
            'advertiser_approval_verified'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'accounts'=>[],
            'campaigns'=>[],
            'reviews'=>[],
            'counts'=>[],
        ];
    }

    public function preview(
        PDO $tenantConnection,
        string $signedEnvelope,
        ?SyntheticCurrentAuthority $currentAuthority,
        int $now,
    ):array {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'){
            return self::denied('lab_disabled');
        }

        try{
            $read=$this->signedSql->preview(
                $tenantConnection,$signedEnvelope,$currentAuthority,$now
            );
            if($read['status']!=='signed_portal_plus_sql_demo_preview_only'
                ||$read['cryptographic_lab_proof_verified']!==true
                ||$read['credential_binding_confirmed']!==true
                ||$read['portal_identity_authenticated']!==false
                ||$read['advertiser_approval_verified']!==false
                ||$read['provider_connected']!==false
                ||$read['authorization_eligible']!==false
                ||$read['ready_for_production']!==false
                ||$read['dispatch_enabled']!==false
                ||$read['billing_enabled']!==false){
                return self::denied('signed_or_sql_scope_denied');
            }

            // Only decode the untrusted envelope *after* F123 has verified
            // its Ed25519 detached signature and strict F29 claim schema.
            $packet=json_decode($signedEnvelope,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($packet)
                ||!isset($packet['payload'])
                ||!is_string($packet['payload'])
                ||preg_match('/^[A-Za-z0-9_-]+$/D',$packet['payload'])!==1){
                return self::denied('verified_nonce_not_extractable');
            }
            $json=base64_decode(strtr($packet['payload'],'-_','+/'),true);
            if(!is_string($json)){
                return self::denied('verified_nonce_not_extractable');
            }
            $claim=json_decode($json,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($claim)
                ||!is_string($claim['nonce']??null)
                ||preg_match('/^[a-f0-9]{32}$/D',$claim['nonce'])!==1
                ||!is_int($claim['issued_at']??null)
                ||!is_int($claim['expires_at']??null)
                ||$now<$claim['issued_at']
                ||$now>=$claim['expires_at']){
                return self::denied('verified_nonce_not_extractable');
            }

            $status=$this->nonceFence->claim($claim['nonce'],$now);
            if($status!=='nonce_recorded_ci_only'){
                return self::denied('nonce_already_consumed_or_storage_unavailable');
            }
            // The nonce is persisted BEFORE any DEMO row can leave this
            // wrapper. Even under a new PHP verifier instance the unique
            // PostgreSQL index prevents a second successful result.
            return [
                ...$read,
                'schema_version'=>self::SCHEMA,
                'status'=>'signed_sql_demo_with_local_durable_nonce_only',
                'replay_receipt_recorded'=>true,
            ];
        }catch(Throwable){
            return self::denied('preview_unavailable');
        }
    }
}
