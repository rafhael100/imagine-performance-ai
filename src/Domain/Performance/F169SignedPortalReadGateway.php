<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use RuntimeException;

require_once __DIR__.'/F166Pg16ReadOnlyDashboard.php';

/**
 * F169 CI-only signed read capability boundary, NOT operational Portal IAM.
 *
 * Detached Ed25519 public key is pinned by the consumer's trusted setup.
 * Never accept a public key or algorithm from the untrusted request JSON.
 * The PG16 tenant reader makes the final identity/tenant determination.
 */
final class F169SignedPortalReadGateway
{
    private const KEYS=[
        'subject','audience','scope','tenant','issued_at',
        'expires_at','request_id',
    ];

    public function __construct(
        private F166Pg16ReadOnlyDashboard $reader,
        private string $trustedPublicKey
    ){
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F169_SIGNED_READ_LAB')!=='1'
            ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
            ||!extension_loaded('sodium')
            ||strlen($trustedPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES){
            throw new RuntimeException('F169 signed-read gateway restricted to disposable CI');
        }
    }

    /** @return array<string,mixed> */
    public function read(string $envelopeJson):array
    {
        $deny=static function(string $status):array{
            return [
                'status'=>$status,'report'=>null,
                'read_only'=>true,'action_authorized'=>false,
                'human_approval_verified'=>false,
                'can_enable_ads'=>false,'can_bill'=>false,
                'ready_for_production'=>false,
            ];
        };
        if(strlen($envelopeJson)<2||strlen($envelopeJson)>4096){
            return $deny('untrusted_request_denied');
        }
        try{
            $body=json_decode($envelopeJson,true,8,JSON_THROW_ON_ERROR);
        }catch(\Throwable $e){
            return $deny('untrusted_request_denied');
        }
        if(!is_array($body)||array_keys($body)!==['claims','signature']
            ||!is_array($body['claims'])
            ||!is_string($body['signature'])){
            return $deny('untrusted_request_denied');
        }
        $claims=$body['claims'];
        $keys=array_keys($claims);sort($keys);
        $expected=self::KEYS;sort($expected);
        if($keys!==$expected)return $deny('untrusted_request_denied');
        $now=time();
        if(($claims['subject']??null)!=='imagine-portal-synthetic-s2s'
            ||($claims['audience']??null)!=='imagine-performance-readonly-v1'
            ||($claims['scope']??null)!=='performance:demo:read'
            ||!in_array($claims['tenant']??null,[
                'perf-demo-aurora','perf-demo-boreal',
            ],true)
            ||!is_string($claims['request_id'])
            ||!preg_match('/^[a-z0-9_-]{16,64}$/D',$claims['request_id'])){
            return $deny('scope_or_audience_denied');
        }
        if(!is_int($claims['issued_at'])
            ||!is_int($claims['expires_at'])
            ||$claims['issued_at']<$now-30
            ||$claims['issued_at']>$now+5
            ||$claims['expires_at']<=$now
            ||$claims['expires_at']<=$claims['issued_at']
            ||$claims['expires_at']>$claims['issued_at']+60){
            return $deny('expired_or_out_of_window_denied');
        }
        $canonical=[];
        foreach(self::KEYS as $key)$canonical[$key]=$claims[$key];
        try{
            $payload=json_encode($canonical,
                JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        }catch(\Throwable $e){
            return $deny('untrusted_request_denied');
        }
        $signature=base64_decode($body['signature'],true);
        if(!is_string($signature)
            ||strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
            ||!sodium_crypto_sign_verify_detached(
                $signature,$payload,$this->trustedPublicKey)){
            return $deny('signature_denied');
        }
        // RLS tenant identity is authoritative even for a VALID signature.
        // A signature cannot make a connection bound to A read tenant B.
        $report=$this->reader->read($claims['tenant']);
        if(!is_array($report)
            ||($report['tenant_ref']??null)!==$claims['tenant']
            ||($report['iam_verified']??null)!==false
            ||($report['s2s_verified']??null)!==false
            ||($report['dispatch_enabled']??null)!==false
            ||($report['billing_enabled']??null)!==false
            ||($report['ready_for_production']??null)!==false){
            return $deny('tenant_session_or_report_denied');
        }
        return [
            'status'=>'accepted_readonly_demo','report'=>$report,
            'read_only'=>true,'action_authorized'=>false,
            'human_approval_verified'=>false,
            'can_enable_ads'=>false,'can_bill'=>false,
            'ready_for_production'=>false,
        ];
    }
}
