<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;
use Throwable;

/**
 * F125 CI only: F123 verified Portal signature and SQL F100 read,
 * F124 recorded nonce in restored primary, THEN independent F125
 * writer records the nonce in a second PostgreSQL service.
 *
 * Only after BOTH writes succeed may synthetic workbench DTO escape.
 * If the primary is restored and forgets a nonce, the independent
 * witness still rejects its replay. Fail CLOSED on unreachable witness.
 *
 * This does not validate real Portal identity or provide production-grade
 * offsite antirollback; F124 may burn a local nonce even when witness fails.
 */
final class SyntheticExternallyWitnessedPortalPreview
{
    public const SCHEMA='perf.portal.externally-witnessed-demo-preview.v1';

    public function __construct(
        private readonly SyntheticDurableSignedSqlPreviewBridge $localBridge,
        private readonly SyntheticExternalSignedNonceWitness $externalWitness
    ){}

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'synthetic_only'=>true,
            'read_only'=>true,
            'cryptographic_lab_proof_verified'=>false,
            'local_receipt_recorded'=>false,
            'external_witness_recorded'=>false,
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
        ?SyntheticCurrentAuthority $authority,
        int $now
    ):array
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F125_EXTERNAL_NONCE_LAB')!=='1'){
            return self::denied('external_witness_lab_disabled');
        }
        try{
            // F126 opt-in lab optimization: reject a visibly unavailable,
            // read-only, wrong-role or overprivileged witness BEFORE the
            // F124 local nonce receipt can be burned. A race between this
            // preflight and the later external INSERT remains; no XA/2PC.
            if(getenv('PERF_F126_WITNESS_PREFLIGHT_LAB')==='1'
                &&$this->externalWitness->preflight()
                    !=='external_witness_preflight_ready_ci_only'){
                return self::denied('independent_witness_not_ready');
            }
            $local=$this->localBridge->preview(
                $tenantConnection,$signedEnvelope,$authority,$now
            );
            if($local['status']!=='signed_sql_demo_with_local_durable_nonce_only'
                ||$local['cryptographic_lab_proof_verified']!==true
                ||$local['credential_binding_confirmed']!==true
                ||$local['replay_receipt_recorded']!==true
                ||$local['portal_identity_authenticated']!==false
                ||$local['advertiser_approval_verified']!==false
                ||$local['provider_connected']!==false
                ||$local['authorization_eligible']!==false
                ||$local['ready_for_production']!==false
                ||$local['dispatch_enabled']!==false
                ||$local['billing_enabled']!==false){
                return self::denied('local_signed_sql_or_replay_denied');
            }

            // F124 already verified Portal Ed25519 before returning the
            // preview. Read only the strict 128-bit nonce; never reuse raw
            // caller-controlled tenant, SQL, resource, or URL input.
            $packet=json_decode($signedEnvelope,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($packet)
                ||!isset($packet['payload'])
                ||!is_string($packet['payload'])){
                return self::denied('nonce_not_verifiable');
            }
            $raw=base64_decode(strtr($packet['payload'],'-_','+/'),true);
            if(!is_string($raw)){
                return self::denied('nonce_not_verifiable');
            }
            $claims=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
            if(!is_array($claims)
                ||!is_string($claims['nonce']??null)
                ||preg_match('/^[a-f0-9]{32}$/D',$claims['nonce'])!==1){
                return self::denied('nonce_not_verifiable');
            }
            $witness=$this->externalWitness->witness($claims['nonce'],$now);
            if($witness!=='external_witness_recorded_ci_only'){
                return self::denied('independent_witness_rejected_or_unavailable');
            }
            return [
                ...$local,
                'schema_version'=>self::SCHEMA,
                'status'=>'signed_sql_demo_with_independent_witness_only',
                'local_receipt_recorded'=>true,
                'external_witness_recorded'=>true,
            ];
        }catch(Throwable){
            return self::denied('preview_unavailable');
        }
    }
}
