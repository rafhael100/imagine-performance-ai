<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;

require_once __DIR__.'/F147StickyQuarantineExecutor.php';

/**
 * F148: CI-only workload authentication contract.
 *
 * A second, independent Ed25519 signer authenticates the *caller workload*.
 * Its key is not the terminal grant issuer's key. This class only permits
 * no-op receipts; there is no provider SDK, outbound socket or Ads credential.
 *
 * The Docker sandbox provides the network restriction, NOT this PHP class.
 * This is not mTLS, IAM or a production security boundary.
 */
final class F148WorkloadConstrainedService
{
    public function __construct(
        private F147StickyQuarantineExecutor $executor,
        private string $workloadPublicKey
    ) {
        if (getenv('APP_ENV')!=='testing'
            || getenv('PERF_F148_LAB')!=='1'
            || getenv('PERF_F147_LAB')!=='1'
            || getenv('PERF_F146_EXECUTOR_ONLY')!=='1'
            || strlen($workloadPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('F148 restricted to CI sandbox');
        }
    }

    /** @return array{status:string,simulated:bool,action_authorized:bool} */
    public function handle(array $request):array
    {
        $deny=static fn(string $status):array=>[
            'status'=>$status,'simulated'=>false,'action_authorized'=>false,
        ];
        if (array_keys($request)!==['grant','workload','fault']
            || !is_array($request['grant'])
            || !is_array($request['workload'])
            || !is_string($request['fault'])
            || !in_array($request['fault'],['none','ack_lost','target_down'],true)) {
            return $deny('invalid_request_contract');
        }

        $claims=$request['workload'];
        if (array_keys($claims)!==[
            'subject','audience','scope','operation','tenant',
            'fingerprint','issued_at','signature',
        ]) {
            return $deny('invalid_workload_contract');
        }
        $sig=$claims['signature'];
        if (!is_string($sig)) return $deny('invalid_workload_signature');
        $bytes=base64_decode($sig,true);
        if ($bytes===false || strlen($bytes)!==SODIUM_CRYPTO_SIGN_BYTES) {
            return $deny('invalid_workload_signature');
        }
        $payload=[
            'subject'=>$claims['subject'],
            'audience'=>$claims['audience'],
            'scope'=>$claims['scope'],
            'operation'=>$claims['operation'],
            'tenant'=>$claims['tenant'],
            'fingerprint'=>$claims['fingerprint'],
            'issued_at'=>$claims['issued_at'],
        ];
        if ($payload['subject']!=='imagine-f148-lab-workload'
            || $payload['audience']!=='synthetic-noop-executor'
            || $payload['scope']!=='simulate:receipt'
            || !is_string($payload['operation'])
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$payload['operation'])
            || !is_string($payload['tenant'])
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$payload['tenant'])
            || !is_string($payload['fingerprint'])
            || !preg_match('/^[a-f0-9]{64}$/D',$payload['fingerprint'])
            || !is_int($payload['issued_at'])
            || $payload['issued_at']<time()-30
            || $payload['issued_at']>time()+5) {
            return $deny('workload_scope_or_ttl_denied');
        }
        $serialized=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if (!sodium_crypto_sign_verify_detached(
            $bytes,$serialized,$this->workloadPublicKey
        )) {
            return $deny('workload_signature_denied');
        }
        $grant=$request['grant']['payload']??null;
        if (!is_array($grant)
            || ($grant['operation']??null)!==$payload['operation']
            || ($grant['tenant']??null)!==$payload['tenant']
            || ($grant['fingerprint']??null)!==$payload['fingerprint']
            || ($grant['target']??null)!=='synthetic-noop') {
            return $deny('workload_grant_binding_denied');
        }

        return $this->executor->execute(
            $request['grant'],
            $request['fault']==='ack_lost',
            $request['fault']==='target_down'
        );
    }
}
