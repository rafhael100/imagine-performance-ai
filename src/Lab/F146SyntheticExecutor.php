<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;
use Throwable;

require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * SYNTHETIC/NO-OP isolated-executor prototype.
 *
 * No Google Ads, Meta Ads, HTTP client, money, network call or OAuth secret.
 * Only Ed25519 grants from F146TerminalGrantIssuer can write one receipt
 * in this executor's own signed journal.
 */
final class F146SyntheticExecutor
{
    public function __construct(
        private F141SignedJournalAuthority $receiptJournal,
        private string $checkpointPath,
        private string $grantPublicKey
    ) {
        if (getenv('APP_ENV')!=='testing'
            || getenv('PERF_F146_LAB')!=='1'
            || getenv('PERF_F146_EXECUTOR_ONLY')!=='1'
            || strlen($grantPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('F146 executor restricted to synthetic CI child process');
        }
        $root=realpath((string)getenv('RUNNER_TEMP'));
        $parent=realpath(dirname($checkpointPath));
        if ($root===false || $parent===false
            || !str_starts_with($parent.'/',rtrim($root,'/').'/')) {
            throw new RuntimeException('F146 receipts must be inside disposable runner workspace');
        }
    }

    /** @return array{status:string,simulated:bool,action_authorized:bool} */
    public function execute(array $grant):array
    {
        $deny=static fn(string $status,bool $simulated=false):array=>[
            'status'=>$status,'simulated'=>$simulated,'action_authorized'=>false,
        ];
        $payload=$grant['payload']??null;
        $b64=$grant['signature']??null;
        if (!is_array($payload) || !is_string($b64)
            || array_keys($payload)!==[
                'kind','operation','tenant','fingerprint','epoch',
                'issued_at','terminal_events','terminal_head','target',
            ]) return $deny('invalid_grant_envelope');
        [$kind,$op,$tenant,$fingerprint,$epoch,$issued,$events,$head,$target]
          = array_values($payload);
        if ($kind!=='F146-synthetic-noop-v1'
            || $target!=='synthetic-noop'
            || !is_string($op) || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$op)
            || !is_string($tenant) || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)
            || !is_int($epoch) || $epoch<1
            || !is_int($events) || $events<2
            || !is_string($head) || !preg_match('/^[a-f0-9]{64}$/D',$head)
            || !is_int($issued) || $issued<time()-30 || $issued>time()+5) {
            return $deny('invalid_grant_claims_or_expired');
        }
        $signedBytes=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $signature=base64_decode($b64,true);
        if ($signature===false || strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached(
                $signature,$signedBytes,$this->grantPublicKey
            )) {
            return $deny('issuer_signature_invalid');
        }

        try {
            return $this->locked(function() use(
                $op,$tenant,$fingerprint,$epoch,$deny
            ):array {
                $this->receiptJournal->bootstrap();
                $before=$this->receiptJournal->inspect();
                if (is_file($this->checkpointPath)) {
                    $this->assertCheckpoint($before);
                } elseif ($before['events']===1 && $before['epoch']===8) {
                    $this->writeCheckpoint($before);
                } else {
                    throw new RuntimeException('F146 checkpoint missing after journal initialization');
                }

                if ($before['epoch']!==$epoch) {
                    return $deny('executor_generation_mismatch');
                }
                if (isset($before['operations'][$op])
                    || isset($before['seen'][$fingerprint])) {
                    return $deny('executor_duplicate_denied');
                }
                if (!$this->receiptJournal->reserve($op,$tenant,$fingerprint,$epoch)) {
                    return $deny('executor_rejected_reservation');
                }
                $after=$this->receiptJournal->inspect();
                if ($after['events']!==$before['events']+1
                    || ($after['bindings'][$op]['tenant']??null)!==$tenant
                    || ($after['bindings'][$op]['fingerprint']??null)!==$fingerprint) {
                    throw new RuntimeException('F146 inconsistent receipt append');
                }
                $this->writeCheckpoint($after);
                // This marks a local synthetic-only event. It DOES NOT send
                // a command to any external provider or grant such authority.
                return $deny('synthetic_noop_recorded',true);
            });
        } catch(Throwable $e) {
            return $deny('executor_unknown_fail_closed');
        }
    }

    private function assertCheckpoint(array $state):void
    {
        $raw=@file_get_contents($this->checkpointPath);
        if (!is_string($raw)) throw new RuntimeException('F146 missing checkpoint');
        $saved=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($saved)
            || ($saved['events']??null)!==$state['events']
            || ($saved['epoch']??null)!==$state['epoch']
            || !is_string($saved['head']??null)
            || !hash_equals($saved['head'],$state['head'])) {
            throw new RuntimeException('F146 high-watermark mismatch');
        }
    }

    private function writeCheckpoint(array $state):void
    {
        $data=json_encode([
            'events'=>$state['events'],
            'epoch'=>$state['epoch'],
            'head'=>$state['head'],
        ],JSON_THROW_ON_ERROR)."\n";
        $temp=$this->checkpointPath.'.new';
        $fd=@fopen($temp,'wb');
        if ($fd===false) throw new RuntimeException('F146 checkpoint not writable');
        try {
            if (fwrite($fd,$data)!==strlen($data)
                || !fflush($fd) || !fsync($fd)) {
                throw new RuntimeException('F146 checkpoint incomplete');
            }
        } finally {
            fclose($fd);
        }
        chmod($temp,0600);
        if (!rename($temp,$this->checkpointPath)) {
            throw new RuntimeException('F146 checkpoint swap failed');
        }
    }

    private function locked(callable $fn):mixed
    {
        $fd=@fopen($this->checkpointPath.'.lock','c+');
        if ($fd===false) throw new RuntimeException('F146 lock unavailable');
        try {
            if (!flock($fd,LOCK_EX))throw new RuntimeException('F146 lock denied');
            return $fn();
        } finally {
            flock($fd,LOCK_UN);
            fclose($fd);
        }
    }
}
