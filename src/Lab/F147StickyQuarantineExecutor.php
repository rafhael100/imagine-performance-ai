<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use RuntimeException;
use Throwable;

require_once __DIR__.'/F146SyntheticExecutor.php';

/**
 * F147 CI-only quarantine policy around the no-op executor.
 *
 * The quarantine marker is sticky; there is NO release or recovery method.
 * All evidence resides on the same ephemeral runner, not offsite/WORM.
 */
final class F147StickyQuarantineExecutor
{
    public function __construct(
        private F146SyntheticExecutor $executor,
        private string $quarantinePath
    ) {
        if (getenv('APP_ENV')!=='testing'
            || getenv('PERF_F147_LAB')!=='1'
            || getenv('PERF_F146_EXECUTOR_ONLY')!=='1') {
            throw new RuntimeException('F147 CI-only quarantine');
        }
        $root=realpath((string)getenv('RUNNER_TEMP'));
        $parent=realpath(dirname($quarantinePath));
        if ($root===false || $parent===false
            || !str_starts_with($parent.'/',rtrim($root,'/').'/')) {
            throw new RuntimeException('F147 quarantine outside runner temp');
        }
    }

    /** @return array{status:string,simulated:bool,action_authorized:bool} */
    public function execute(
        array $grant,
        bool $simulateAckLost=false,
        bool $simulateTargetOutage=false
    ):array {
        $response=static fn(string $status,bool $recorded=false):array=>[
            'status'=>$status,'simulated'=>$recorded,'action_authorized'=>false,
        ];
        $handle=@fopen($this->quarantinePath.'.lock','c+');
        if ($handle===false) return $response('quarantine_lock_unavailable');
        try {
            if (!flock($handle,LOCK_EX)) return $response('quarantine_lock_unavailable');

            if (is_file($this->quarantinePath)) {
                return $response('executor_quarantined_no_auto_retry');
            }
            if ($simulateTargetOutage) {
                // This models a known failure BEFORE any synthetic receipt.
                // It invokes no provider and spends no nonce.
                return $response('synthetic_target_unavailable');
            }

            $result=$this->executor->execute($grant);
            if ($result['status']==='executor_unknown_fail_closed') {
                $this->quarantine('ambiguous_executor_receipt');
                return $response('in_doubt_quarantined');
            }
            if ($result['status']==='synthetic_noop_recorded'
                && $result['simulated']===true
                && $simulateAckLost) {
                // Only the MOCK acknowledgement is discarded here. This
                // case intentionally quarantines even though the receipt
                // was already written. Never retry blindly.
                $this->quarantine('synthetic_ack_lost_after_receipt');
                return $response('in_doubt_quarantined');
            }
            return $result;
        } catch(Throwable $e) {
            try {
                $this->quarantine('exception_or_unknown');
            } catch(Throwable $ignored) {
                // inability to quarantine is STILL a hard denial.
            }
            return $response('executor_unknown_fail_closed');
        } finally {
            flock($handle,LOCK_UN);
            fclose($handle);
        }
    }

    private function quarantine(string $reason):void
    {
        $body=json_encode([
            'incident'=>'F147-ci-quarantine',
            'reason'=>$reason,
            'created_at'=>gmdate('c'),
            'release'=>'manual_independent_review_only',
        ],JSON_THROW_ON_ERROR)."\n";
        $temp=$this->quarantinePath.'.new';
        $fd=@fopen($temp,'wb');
        if ($fd===false) throw new RuntimeException('F147 quarantine write unavailable');
        try {
            if (fwrite($fd,$body)!==strlen($body)
                || !fflush($fd) || !fsync($fd)) {
                throw new RuntimeException('F147 quarantine not durable');
            }
        } finally {
            fclose($fd);
        }
        chmod($temp,0600);
        if (!rename($temp,$this->quarantinePath)) {
            throw new RuntimeException('F147 quarantine rename failed');
        }
    }
}
