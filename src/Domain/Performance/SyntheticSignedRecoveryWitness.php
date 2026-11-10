<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use Throwable;

/**
 * FAST-PERF F1-75: cryptographically validate a synthetic, externally supplied
 * recovery witness. Caller supplies public key and floor; neither is a trusted
 * production root. NEVER promote this result to permission/dispatch.
 */
final class SyntheticSignedRecoveryWitness
{
    private const ENVELOPE = 'perf.recovery.witness.envelope.synthetic.v1';
    private const PAYLOAD = 'perf.recovery.witness.payload.synthetic.v1';

    private static function denied(string $status): array
    {
        return [
            'schema_version'=>'perf.recovery.witness.verification.synthetic.v1',
            'status'=>$status,
            'signature_valid'=>false,
            'external_root_trusted'=>false,
            'monotonicity_proven'=>false,
            'authorization_eligible'=>false,
            'read_preview_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
        ];
    }

    public function verify(
        string $envelopeJson,
        string $publicKeyHex,
        string $expectedTenant,
        string $expectedRecoveryId,
        int $expectedDatabaseEpoch,
        int $trustedMinEpoch,
        int $observedAt
    ): array {
        if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F75_SIGNED_WITNESS_LAB') !== '1'
            || !extension_loaded('sodium')) {
            return self::denied('lab_disabled');
        }
        if (strlen($envelopeJson)>10000
            || preg_match('/^[a-f0-9]{64}$/D',$publicKeyHex)!==1
            || preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$expectedTenant)!==1
            || preg_match('/^[a-f0-9]{32}$/D',$expectedRecoveryId)!==1
            || $expectedDatabaseEpoch<1 || $trustedMinEpoch<1
            || $expectedDatabaseEpoch>1000000 || $trustedMinEpoch>1000000
            || $observedAt<1) {
            return self::denied('invalid_expected_context');
        }
        try {
            $e=json_decode($envelopeJson,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($e) || array_keys($e)!==['schema_version','payload_b64','signature_hex']
                || $e['schema_version']!==self::ENVELOPE
                || !is_string($e['payload_b64'])
                || !is_string($e['signature_hex'])
                || preg_match('/^[a-f0-9]{128}$/D',$e['signature_hex'])!==1) {
                return self::denied('invalid_envelope');
            }
            $bytes=base64_decode($e['payload_b64'],true);
            if (!is_string($bytes) || strlen($bytes)>4000 || strlen($bytes)<20) {
                return self::denied('invalid_payload');
            }
            if (!sodium_crypto_sign_verify_detached(
                sodium_hex2bin($e['signature_hex']),$bytes,sodium_hex2bin($publicKeyHex)
            )) {
                return self::denied('invalid_signature');
            }
            $p=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($p) || array_keys($p)!==[
                'schema_version','tenant','recovery_id','epoch','not_before','expires_at','nonce'
            ] || $p['schema_version']!==self::PAYLOAD
                || !is_string($p['tenant']) || !is_string($p['recovery_id'])
                || !is_string($p['nonce'])
                || preg_match('/^[a-f0-9]{32}$/D',$p['nonce'])!==1
                || preg_match('/^[a-f0-9]{32}$/D',$p['recovery_id'])!==1
                || !is_int($p['epoch']) || $p['epoch']<1 || $p['epoch']>1000000
                || !is_int($p['not_before']) || !is_int($p['expires_at'])
                || $p['not_before']<1 || $p['expires_at']<=$p['not_before']
                || $p['expires_at']-$p['not_before']>300) {
                return self::denied('invalid_signed_claims');
            }
            if ($p['tenant']!==$expectedTenant || $p['recovery_id']!==$expectedRecoveryId) {
                return self::denied('wrong_context');
            }
            if ($observedAt<$p['not_before'] || $observedAt>=$p['expires_at']) {
                return self::denied('not_current');
            }
            if ($p['epoch']<$trustedMinEpoch || $expectedDatabaseEpoch<$trustedMinEpoch) {
                return self::denied('rollback_suspected');
            }
            if ($p['epoch']!==$expectedDatabaseEpoch) {
                return self::denied('epoch_divergence');
            }
            return [
                'schema_version'=>'perf.recovery.witness.verification.synthetic.v1',
                'status'=>'signed_fixture_only_no_external_trust',
                'signature_valid'=>true,
                'external_root_trusted'=>false,
                'monotonicity_proven'=>false,
                'authorization_eligible'=>false,
                'read_preview_eligible'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
            ];
        } catch(Throwable) {
            return self::denied('verification_unavailable');
        }
    }

    /** Generates a lab-only fixture; deliberately not a production signer. */
    public static function signFixture(array $payload,string $secretKeyHex): string
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F75_SIGNED_WITNESS_LAB')!=='1'
            || preg_match('/^[a-f0-9]{128}$/D',$secretKeyHex)!==1) {
            throw new \RuntimeException('Synthetic witness signing disabled');
        }
        $bytes=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return json_encode([
            'schema_version'=>self::ENVELOPE,
            'payload_b64'=>base64_encode($bytes),
            'signature_hex'=>sodium_bin2hex(sodium_crypto_sign_detached(
                $bytes,sodium_hex2bin($secretKeyHex)
            )),
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
}
