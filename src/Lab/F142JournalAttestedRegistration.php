<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * F142 CI lab: no restricted SQL principal can call the F139 writer.
 * The F142 writer checks an HMAC bound to a signed-journal state.
 * This is NOT an online check of a WORM/offsite journal by PostgreSQL.
 */
final class F142JournalAttestedRegistration
{
    public function __construct(
        private PDO $primary,
        private PDO $witness,
        private PDO $anchor,
        private F141SignedJournalAuthority $authority,
        private string $ciMacKey
    ) {
        if (getenv('APP_ENV')!=='testing'
            || getenv('PERF_F141_LAB')!=='1'
            || getenv('PERF_F142_LAB')!=='1'
            || getenv('PGDATABASE')!=='perf_f132_ci'
            || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
            || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
            || strlen($ciMacKey)!==32) {
            throw new RuntimeException('F142: disposable CI databases and key only');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $operation,string $tenant,string $fingerprint):array
    {
        $outcome=static fn(string $status,bool $registered=false):array=>[
            'status'=>$status,'registered'=>$registered,'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            return $outcome('invalid_inputs');
        }
        try {
            $local=$this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            $floor=$this->anchor->query(
                'SELECT generation FROM perf_f138_anchor.epoch_floor WHERE singleton=1'
            )->fetchColumn();
            if (!is_numeric($local) || !is_numeric($floor)
                || (int)$local<1 || (int)$local!==(int)$floor) {
                return $outcome('database_epoch_mismatch');
            }
            $epoch=(int)$local;
        } catch(PDOException $e) {
            return $outcome('database_preflight_unavailable');
        }

        try {
            $state=$this->authority->inspect();
            if ($state['epoch']!==$epoch) return $outcome('journal_epoch_mismatch');
            if (!$this->authority->reserve($operation,$tenant,$fingerprint,$epoch)) {
                return $outcome('journal_replay_or_epoch_denied');
            }
            $state=$this->authority->inspect();
            if ($state['epoch']!==$epoch
                || !isset($state['seen'][$fingerprint])
                || !isset($state['operations'][$operation])) {
                return $outcome('journal_postreserve_unverifiable');
            }
            $timestamp=time();
            $message=implode('|',[
                'F142-v1',$operation,$tenant,$fingerprint,
                (string)$epoch,(string)$timestamp,
                (string)$state['events'],(string)$state['head'],
            ]);
            $mac=hash_hmac('sha256',$message,$this->ciMacKey);
        } catch(RuntimeException $e) {
            return $outcome('journal_unavailable_or_tampered');
        }

        try {
            $witness=$this->witness->prepare(
                'SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)'
            );
            $witness->execute([$operation,$tenant,$fingerprint,$epoch]);
            $permit=$witness->fetch(PDO::FETCH_ASSOC);
            if (!is_array($permit)) return $outcome('witness_denied_journal_spent');
        } catch(PDOException $e) {
            return $outcome('witness_unknown_journal_spent');
        }

        try {
            $primary=$this->primary->prepare(
                'SELECT CASE WHEN perf_f142_primary.record_with_journal_attestation(?,?,?,?,?,?,?,?,?,?) THEN 1 ELSE 0 END'
            );
            $primary->execute([
                $operation,$tenant,$fingerprint,$epoch,
                (int)$permit['issued_at'],(string)$permit['signature'],
                $timestamp,$state['events'],$state['head'],$mac,
            ]);
            if ((int)$primary->fetchColumn()!==1) {
                return $outcome('primary_or_online_witness_denied_journal_spent');
            }
        } catch(PDOException $e) {
            return $outcome('primary_or_online_witness_unknown_journal_spent');
        }
        return $outcome('registered_lab',true);
    }
}
