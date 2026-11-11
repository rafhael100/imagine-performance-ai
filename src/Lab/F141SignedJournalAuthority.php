<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;
use Throwable;

/**
 * F141 CI-ONLY file journal. The file is intentionally OUTSIDE snapshots of
 * three ephemeral PostgreSQL instances; it is NOT WORM, offsite, durable, or
 * an enforceable SQL authorization authority. Lab-only Ed25519 signatures
 * detect edits, but cannot detect truncation to a previously valid prefix.
 */
final class F141SignedJournalAuthority
{
    public function __construct(
        private readonly string $journalPath,
        private readonly string $secretKey,
        private readonly string $publicKey
    ) {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F141_LAB') !== '1'
            || getenv('PGDATABASE') !== 'perf_f132_ci'
            || getenv('WITNESS_DB') !== 'perf_f132_witness_ci'
            || getenv('ANCHOR_DB') !== 'perf_f138_anchor_ci'
            || getenv('RUNNER_TEMP') === false) {
            throw new RuntimeException('F141: no production/runtime access permitted');
        }
        $runnerRoot = realpath((string)getenv('RUNNER_TEMP'));
        $parent = realpath(dirname($journalPath));
        if ($runnerRoot === false || $parent === false
            || !str_starts_with($parent.'/', rtrim($runnerRoot, '/').'/')) {
            throw new RuntimeException('F141 journal must live beneath RUNNER_TEMP');
        }
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !hash_equals(sodium_crypto_sign_publickey_from_secretkey($secretKey),$publicKey)) {
            throw new RuntimeException('F141 missing/mismatched test-only signing key');
        }
    }

    public function bootstrap(int $epoch = 8): void
    {
        if ($epoch !== 8) throw new RuntimeException('F141 CI bootstrap is fixed to generation 8');
        $this->locked(function() use($epoch):void {
            if (is_file($this->journalPath) && filesize($this->journalPath) > 0) {
                $this->readVerified();
                return;
            }
            $this->append([
                'seq'=>0,'type'=>'init','epoch'=>$epoch,
                'operation'=>'','tenant'=>'','fingerprint'=>'',
                'prev'=>str_repeat('0',64),
            ]);
        });
    }

    /** @return array{epoch:int,events:int,seen:array<string,bool>} */
    public function inspect(): array
    {
        return $this->locked(fn():array => $this->readVerified());
    }

    /** @return bool The epoch is strictly advanced, never lowered. */
    public function advance(int $nextEpoch): bool
    {
        return $this->locked(function() use($nextEpoch):bool {
            $s = $this->readVerified();
            if ($nextEpoch <= $s['epoch'] || $nextEpoch > 1000000) return false;
            $this->append([
                'seq'=>$s['events'],'type'=>'advance','epoch'=>$nextEpoch,
                'operation'=>'','tenant'=>'','fingerprint'=>'',
                'prev'=>$s['head'],
            ]);
            return true;
        });
    }

    /**
     * Reserve first; an ambiguous downstream SQL failure deliberately burns
     * this identity. There is NO "unreserve" method or automatic retry.
     */
    public function reserve(string $operation,string $tenant,string $fingerprint,int $epoch):bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            return false;
        }
        return $this->locked(function()use($operation,$tenant,$fingerprint,$epoch):bool {
            $s = $this->readVerified();
            if ($s['epoch'] !== $epoch
                || isset($s['seen'][$fingerprint])
                || isset($s['operations'][$operation])) return false;
            $this->append([
                'seq'=>$s['events'],'type'=>'reserve','epoch'=>$epoch,
                'operation'=>$operation,'tenant'=>$tenant,
                'fingerprint'=>$fingerprint,'prev'=>$s['head'],
            ]);
            return true;
        });
    }

    private function locked(callable $task): mixed
    {
        $handle = fopen($this->journalPath.'.lock','c+');
        if ($handle === false) throw new RuntimeException('F141 lock file unavailable');
        try {
            if (!flock($handle,LOCK_EX)) throw new RuntimeException('F141 cannot lock journal');
            return $task();
        } finally {
            flock($handle,LOCK_UN);
            fclose($handle);
        }
    }

    private function append(array $payload):void
    {
        $canonical=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $hash=hash('sha256',$canonical);
        $record=['payload'=>$payload,'hash'=>$hash,
            'signature'=>base64_encode(sodium_crypto_sign_detached($hash,$this->secretKey))];
        $line=json_encode($record,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        $h=fopen($this->journalPath,'ab');
        if ($h === false) throw new RuntimeException('F141 journal not writable');
        try {
            $bytes=fwrite($h,$line);
            if ($bytes === false || $bytes !== strlen($line) || !fflush($h)
                || !fsync($h)) throw new RuntimeException('F141 journal append incomplete');
        } finally {
            fclose($h);
        }
    }

    /**
     * Detects corruption and forged entries; DOES NOT detect deletion of an
     * intact signed suffix. Only independent immutable high-watermark solves it.
     * @return array{epoch:int,events:int,head:string,seen:array<string,bool>,operations:array<string,bool>}
     */
    private function readVerified():array
    {
        $raw=@file_get_contents($this->journalPath);
        if ($raw === false || $raw === '' || !str_ends_with($raw,"\n"))
            throw new RuntimeException('F141 missing/incomplete journal: deny all');
        $lines=explode("\n",rtrim($raw,"\n"));
        $epoch=0;$seq=0;$prev=str_repeat('0',64);
        $seen=[];$operations=[];$bindings=[];
        foreach($lines as $line) {
            try {
                $entry=json_decode($line,true,512,JSON_THROW_ON_ERROR);
                if (!is_array($entry) || !isset($entry['payload'],$entry['hash'],$entry['signature'])
                    || !is_array($entry['payload'])) throw new RuntimeException('invalid row shape');
                $p=$entry['payload'];
                $expected=['seq','type','epoch','operation','tenant','fingerprint','prev'];
                if (array_keys($p)!==$expected || !is_int($p['seq']) || $p['seq']!==$seq
                    || !is_int($p['epoch']) || !is_string($p['prev'])
                    || !hash_equals($prev,$p['prev'])
                    || !is_string($p['operation']) || !is_string($p['tenant'])
                    || !is_string($p['fingerprint'])) throw new RuntimeException('bad chain position');
                $canonical=json_encode($p,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $digest=hash('sha256',$canonical);
                if (!is_string($entry['hash']) || !hash_equals($digest,$entry['hash']))
                    throw new RuntimeException('changed payload');
                $sig=base64_decode((string)$entry['signature'],true);
                if ($sig===false || strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                    || !sodium_crypto_sign_verify_detached($sig,$digest,$this->publicKey))
                    throw new RuntimeException('invalid Ed25519 signature');
                if ($seq===0) {
                    if ($p['type']!=='init' || $p['epoch']!==8
                        || $p['operation']!=='' || $p['tenant']!==''
                        || $p['fingerprint']!=='') throw new RuntimeException('invalid genesis');
                    $epoch=8;
                } elseif ($p['type']==='advance') {
                    if ($p['epoch']<=$epoch || $p['operation']!=='' || $p['tenant']!==''
                        || $p['fingerprint']!=='') throw new RuntimeException('non-monotonic epoch');
                    $epoch=$p['epoch'];
                } elseif ($p['type']==='reserve') {
                    if ($p['epoch']!==$epoch
                        || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$p['operation'])
                        || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$p['tenant'])
                        || !preg_match('/^[a-f0-9]{64}$/D',$p['fingerprint'])
                        || isset($seen[$p['fingerprint']]) || isset($operations[$p['operation']])) {
                        throw new RuntimeException('nonce/operation replay or invalid epoch');
                    }
                    $seen[$p['fingerprint']]=true;
                    $operations[$p['operation']]=true;
                    $bindings[$p['operation']]=[
                        'tenant'=>$p['tenant'],
                        'fingerprint'=>$p['fingerprint'],
                    ];
                } else throw new RuntimeException('unsupported event type');
                $prev=$digest;++$seq;
            } catch(Throwable $e) {
                throw new RuntimeException('F141 tampered/unverifiable authority ledger',0,$e);
            }
        }
        return [
            'epoch'=>$epoch,'events'=>$seq,'head'=>$prev,
            'seen'=>$seen,'operations'=>$operations,'bindings'=>$bindings,
        ];
    }
}
