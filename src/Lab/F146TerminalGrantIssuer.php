<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;

require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * F146 issuer for SYNTHETIC NO-OP grants only. The separate child process
 * receives only this issuer's public key, never its private signing key.
 * A caller-supplied success flag alone is insufficient: we also verify the
 * F145 terminal's signed journal and its out-of-band checkpoint.
 */
final class F146TerminalGrantIssuer
{
    public function __construct(
        private F141SignedJournalAuthority $terminalJournal,
        private string $terminalCheckpointPath,
        private string $issuerSecretKey
    ) {
        if (getenv('APP_ENV')!=='testing'
            || getenv('PERF_F146_LAB')!=='1'
            || strlen($issuerSecretKey)!==SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('F146 grant issuer CI-only');
        }
    }

    /** @return array{payload:array<string,mixed>,signature:string} */
    public function issue(
        string $operation,string $tenant,string $fingerprint,
        int $generation,array $terminalResult,?int $issuedAt=null
    ):array {
        if (($terminalResult['status']??null)!=='mock_dispatch_recorded'
            || ($terminalResult['lab_delivered']??null)!==true
            || ($terminalResult['action_authorized']??null)!==false) {
            throw new RuntimeException('F146 terminal approval missing');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)
            || $generation<1) {
            throw new RuntimeException('F146 invalid grant identity');
        }
        $state=$this->terminalJournal->inspect();
        $checkpoint=@file_get_contents($this->terminalCheckpointPath);
        if (!is_string($checkpoint)) {
            throw new RuntimeException('F146 missing terminal high-watermark');
        }
        $mark=json_decode($checkpoint,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($mark)
            || ($mark['events']??null)!==$state['events']
            || ($mark['epoch']??null)!==$state['epoch']
            || !is_string($mark['head']??null)
            || !hash_equals($mark['head'],$state['head'])
            || $state['epoch']!==$generation
            || !isset($state['operations'][$operation])
            || !isset($state['seen'][$fingerprint])
            || ($state['bindings'][$operation]['tenant']??null)!==$tenant
            || ($state['bindings'][$operation]['fingerprint']??null)!==$fingerprint) {
            throw new RuntimeException('F146 unverified or stale terminal state');
        }
        $stamp=$issuedAt??time();
        $payload=[
            'kind'=>'F146-synthetic-noop-v1',
            'operation'=>$operation,
            'tenant'=>$tenant,
            'fingerprint'=>$fingerprint,
            'epoch'=>$generation,
            'issued_at'=>$stamp,
            'terminal_events'=>$state['events'],
            'terminal_head'=>$state['head'],
            'target'=>'synthetic-noop',
        ];
        $bytes=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return [
            'payload'=>$payload,
            'signature'=>base64_encode(
                sodium_crypto_sign_detached($bytes,$this->issuerSecretKey)
            ),
        ];
    }
}
