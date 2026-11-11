<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;
use Throwable;

require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * F145: independent-in-code, CI-only terminal mock dispatch gate.
 *
 * This boundary does NOT expose an Ads client, network request, payment or
 * action_authorized=true. It serializes "would-dispatch" decisions against
 * its own Ed25519 journal + a separate high-watermark file, outside any
 * PostgreSQL backups. They share ONE runner/user: NOT offsite, WORM or IAM.
 */
final class F145IndependentDispatchBoundary
{
    public function __construct(
        private readonly F141SignedJournalAuthority $authority,
        private readonly string $checkpointPath,
        private readonly string $issuerPublicKey
    ) {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F145_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci'
            || getenv('ANCHOR_DB') !== 'perf_f138_anchor_ci'
            || getenv('ORACLE_DB') !== 'perf_f143_oracle_ci') {
            throw new RuntimeException('F145 is a synthetic CI terminal gate only');
        }
        if (strlen($issuerPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('F145 requires distinct Ed25519 admission public key');
        }
        $root=realpath((string)getenv('RUNNER_TEMP'));
        $folder=realpath(dirname($checkpointPath));
        if ($root===false || $folder===false
            || !str_starts_with($folder.'/',rtrim($root,'/').'/')) {
            throw new RuntimeException('F145 checkpoint must be in disposable runner workspace');
        }
    }

    /**
     * First boot: create genesis and record its high-watermark. If checkpoint
     * is missing while an existing journal is present, fail closed instead
     * of silently regenerating rollback evidence.
     */
    public function bootstrap():void
    {
        $this->locked(function():void {
            $this->authority->bootstrap(8);
            $state=$this->authority->inspect();
            if (is_file($this->checkpointPath)) {
                $this->assertCheckpoint($state);
                return;
            }
            if ($state['events']!==1 || $state['epoch']!==8) {
                throw new RuntimeException('F145 non-genesis journal without checkpoint');
            }
            $this->writeCheckpoint($state);
        });
    }

    /** @return array{status:string,lab_delivered:bool,action_authorized:bool} */
    public function decide(
        string $operation,
        string $tenant,
        string $fingerprint,
        int $epoch,
        array $sqlRegistration,
        array $issuerAttestation
    ):array
    {
        $result=static fn(string $status,bool $delivered=false):array=>[
            'status'=>$status,'lab_delivered'=>$delivered,'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)
            || $epoch<1) return $result('invalid_inputs');

        // A database return is only one input; it NEVER authorizes dispatch.
        if (($sqlRegistration['status']??null)!=='registered_lab'
            || ($sqlRegistration['registered']??null)!==true
            || ($sqlRegistration['action_authorized']??null)!==false) {
            return $result('sql_result_untrusted_or_denied');
        }

        // An untrusted database result cannot mint a dispatch token. A
        // SEPARATE mock issuer must sign an exact operation, tenant, nonce,
        // epoch and short expiry; a forged registration flag is not enough.
        $issuedAt=$issuerAttestation['issued_at']??null;
        $signatureB64=$issuerAttestation['signature']??null;
        if (!is_int($issuedAt) || !is_string($signatureB64)
            || $issuedAt<time()-30 || $issuedAt>time()+5) {
            return $result('issuer_attestation_missing_or_expired');
        }
        $signature=base64_decode($signatureB64,true);
        $payload=implode('|',[
            'F145-v1',$operation,$tenant,$fingerprint,
            (string)$epoch,(string)$issuedAt,
        ]);
        if ($signature===false || strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached(
                $signature,$payload,$this->issuerPublicKey
            )) {
            return $result('issuer_signature_denied');
        }

        try {
            return $this->locked(function()use(
                $operation,$tenant,$fingerprint,$epoch,$result
            ):array {
                $before=$this->authority->inspect();
                $this->assertCheckpoint($before);
                if ($before['epoch']!==$epoch) {
                    return $result('dispatch_epoch_mismatch');
                }
                if (isset($before['operations'][$operation])
                    || isset($before['seen'][$fingerprint])) {
                    return $result('dispatch_replay_denied');
                }
                if (!$this->authority->reserve(
                    $operation,$tenant,$fingerprint,$epoch
                )) {
                    return $result('dispatch_replay_or_epoch_denied');
                }
                $after=$this->authority->inspect();
                if ($after['events']!==$before['events']+1
                    || $after['epoch']!==$epoch
                    || !isset($after['operations'][$operation])
                    || !isset($after['seen'][$fingerprint])) {
                    throw new RuntimeException('F145 post-reservation verification failed');
                }
                // A crash between reserve and checkpoint produces mismatch
                // and denies ALL following mock decisions (fail-closed).
                $this->writeCheckpoint($after);
                return $result('mock_dispatch_recorded',true);
            });
        } catch(Throwable $e) {
            return $result('independent_dispatch_authority_unknown');
        }
    }

    public function advance(int $epoch):bool
    {
        return $this->locked(function()use($epoch):bool {
            $before=$this->authority->inspect();
            $this->assertCheckpoint($before);
            if (!$this->authority->advance($epoch)) return false;
            $after=$this->authority->inspect();
            if ($after['events']!==$before['events']+1 || $after['epoch']!==$epoch) {
                throw new RuntimeException('F145 inconsistent epoch advancement');
            }
            $this->writeCheckpoint($after);
            return true;
        });
    }

    private function assertCheckpoint(array $state):void
    {
        if (!is_file($this->checkpointPath)) {
            throw new RuntimeException('F145 checkpoint missing: deny all');
        }
        $raw=@file_get_contents($this->checkpointPath);
        if (!is_string($raw)) throw new RuntimeException('F145 checkpoint unreadable');
        try {
            $stored=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        } catch(Throwable $e) {
            throw new RuntimeException('F145 checkpoint corrupted',0,$e);
        }
        if (!is_array($stored)
            || ($stored['events']??null)!==$state['events']
            || ($stored['epoch']??null)!==$state['epoch']
            || !is_string($stored['head']??null)
            || !hash_equals($state['head'],$stored['head'])) {
            throw new RuntimeException('F145 high-watermark mismatch: deny all');
        }
    }

    private function writeCheckpoint(array $state):void
    {
        $bytes=json_encode([
            'events'=>$state['events'],
            'epoch'=>$state['epoch'],
            'head'=>$state['head'],
        ],JSON_THROW_ON_ERROR)."\n";
        $tmp=$this->checkpointPath.'.new';
        $fd=@fopen($tmp,'wb');
        if ($fd===false) throw new RuntimeException('F145 unable to write checkpoint');
        try {
            if (fwrite($fd,$bytes)!==strlen($bytes)
                || !fflush($fd) || !fsync($fd)) {
                throw new RuntimeException('F145 checkpoint not durable in lab');
            }
        } finally {
            fclose($fd);
        }
        chmod($tmp,0600);
        if (!rename($tmp,$this->checkpointPath)) {
            throw new RuntimeException('F145 checkpoint swap failed');
        }
    }

    private function locked(callable $action):mixed
    {
        $handle=@fopen($this->checkpointPath.'.lock','c+');
        if ($handle===false) throw new RuntimeException('F145 lock unavailable');
        try {
            if (!flock($handle,LOCK_EX)) throw new RuntimeException('F145 cannot lock');
            return $action();
        } finally {
            flock($handle,LOCK_UN);
            fclose($handle);
        }
    }
}
