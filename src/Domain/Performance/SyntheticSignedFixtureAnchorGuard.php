<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use Throwable;

/**
 * FAST-PERF F1-73 — signed synthetic checkpoint envelope surviving
 * two PostgreSQL data restores only when held OUTSIDE those restores.
 *
 * Security NOTE: public key and minimum epoch are handed in by CI. There
 * is NO trusted monotonic store/KMS/WORM/real Portal identity. If caller
 * controls these inputs, it can accept an earlier signed checkpoint.
 * Strictly educational signed-fixture recovery gate; never a dispatch gate.
 */
final class SyntheticSignedFixtureAnchorGuard
{
    private static function deny(string $status):array
    {
        return [
            'schema_version'=>'perf.anchor.signed.synthetic.v1',
            'status'=>$status,
            'matched_signed_fixture'=>false,
            'read_preview_eligible'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
        ];
    }

    public function verify(
        PDO $primary,
        PDO $checkpoint,
        string $envelopeJson,
        string $publicHex,
        ?int $trustedMinimumEpoch
    ):array {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('GITHUB_ACTIONS')!=='true'
           ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
           ||getenv('PERF_F71_INDEPENDENT_RESTORE_LAB')!=='1'
           ||getenv('PERF_F73_SIGNED_ANCHOR_LAB')!=='1'){
            return self::deny('lab_disabled');
        }
        if($trustedMinimumEpoch===null||$trustedMinimumEpoch<1
           ||$trustedMinimumEpoch>1000000
           ||strlen($envelopeJson)>25000
           ||!preg_match('/^[a-f0-9]{64}$/D',$publicHex)){
            return self::deny('trusted_input_missing_or_invalid');
        }
        try{
            $envelope=json_decode($envelopeJson,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($envelope)
               ||array_keys($envelope)!==[
                   'schema_version','payload_b64','signature_hex',
               ]||$envelope['schema_version']!=='perf.anchor.envelope.synthetic.v1'
               ||!is_string($envelope['payload_b64'])
               ||!is_string($envelope['signature_hex'])
               ||!preg_match('/^[a-f0-9]{128}$/D',$envelope['signature_hex'])){
                return self::deny('invalid_envelope');
            }
            $signedBytes=base64_decode($envelope['payload_b64'],true);
            if(!is_string($signedBytes)||strlen($signedBytes)>15000){
                return self::deny('invalid_signed_payload');
            }
            $pub=sodium_hex2bin($publicHex);
            $sig=sodium_hex2bin($envelope['signature_hex']);
            if(!sodium_crypto_sign_verify_detached($sig,$signedBytes,$pub)){
                return self::deny('invalid_signature');
            }
            $body=json_decode($signedBytes,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($body)
               ||array_keys($body)!==[
                   'schema_version','tenant_role','epoch','claims',
               ]||$body['schema_version']!=='perf.anchor.payload.synthetic.v1'
               ||!in_array($body['tenant_role'],[
                   'perf_f66_tenant_a','perf_f66_tenant_b',
               ],true)
               ||!is_int($body['epoch'])||$body['epoch']<1
               ||$body['epoch']>1000000
               ||!is_array($body['claims'])){
                return self::deny('invalid_signed_claims');
            }
            if($body['epoch']<$trustedMinimumEpoch){
                return self::deny('signed_epoch_below_external_minimum');
            }
            $base=(new SyntheticPgIndependentRecoveryGuard())->inspect(
                $primary,$checkpoint
            );
            if(($base['consistent_mock_only']??null)!==true
               ||($base['tenant_role']??null)!==$body['tenant_role']
               ||($base['authorization_eligible']??null)!==false
               ||($base['dispatch_enabled']??null)!==false){
                return self::deny('primary_or_independent_guard_denied');
            }
            $rows=$checkpoint->query(
                'SELECT kind,ref,channel,old_ref,new_ref'
                .' FROM perf_f71_guard.checkpoints ORDER BY kind,ref'
            )->fetchAll(PDO::FETCH_ASSOC);
            if(!is_array($rows)||count($rows)<1||count($rows)>50
               ||count($rows)!==count($body['claims'])){
                return self::deny('signed_claim_count_mismatch');
            }
            foreach($rows as $i=>$r){
                $claim=$body['claims'][$i]??null;
                if(!is_array($claim)||array_keys($claim)!==[
                    'kind','ref','channel','old_ref','new_ref',
                ]||$claim!==$r){
                    return self::deny('signed_claims_diverged');
                }
            }
            return [
                'schema_version'=>'perf.anchor.signed.synthetic.v1',
                'status'=>'signed_fixture_matches',
                'tenant_role'=>$body['tenant_role'],
                'signed_epoch'=>$body['epoch'],
                'minimum_epoch'=>$trustedMinimumEpoch,
                'checked_signed_claims'=>count($rows),
                'matched_signed_fixture'=>true,
                'read_preview_eligible'=>true,
                'authorization_eligible'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
            ];
        }catch(Throwable){
            return self::deny('signature_or_checkpoint_unavailable');
        }
    }

    public static function signFixture(
        array $claims,string $tenant,int $epoch,string $secretKeyHex
    ):string {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('GITHUB_ACTIONS')!=='true'
           ||getenv('PERF_F73_SIGNED_ANCHOR_LAB')!=='1'
           ||!in_array($tenant,['perf_f66_tenant_a','perf_f66_tenant_b'],true)
           ||$epoch<1||$epoch>1000000||count($claims)<1||count($claims)>50
           ||!preg_match('/^[a-f0-9]{128}$/D',$secretKeyHex)){
            throw new \RuntimeException('F73 fixture signer disabled or invalid');
        }
        $payload=[
            'schema_version'=>'perf.anchor.payload.synthetic.v1',
            'tenant_role'=>$tenant,
            'epoch'=>$epoch,
            'claims'=>$claims,
        ];
        $signedBytes=json_encode($payload,JSON_UNESCAPED_SLASHES
            |JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $signature=sodium_crypto_sign_detached(
            $signedBytes,sodium_hex2bin($secretKeyHex)
        );
        return json_encode([
            'schema_version'=>'perf.anchor.envelope.synthetic.v1',
            'payload_b64'=>base64_encode($signedBytes),
            'signature_hex'=>sodium_bin2hex($signature),
        ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }
}
