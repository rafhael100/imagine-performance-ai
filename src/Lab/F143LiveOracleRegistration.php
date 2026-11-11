<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * F143 CI-only 4th PostgreSQL live redemption oracle.
 * This is NOT offsite, immutable or an operational authority.
 */
final class F143LiveOracleRegistration
{
    public function __construct(
        private PDO $primary,
        private PDO $witness,
        private PDO $anchor,
        private PDO $oracle,
        private F141SignedJournalAuthority $journal,
        private string $key
    ) {
        if (getenv('APP_ENV')!=='testing' || getenv('PERF_F143_LAB')!=='1'
            || getenv('PERF_F141_LAB')!=='1'
            || getenv('PGDATABASE')!=='perf_f132_ci'
            || getenv('WITNESS_DB')!=='perf_f132_witness_ci'
            || getenv('ANCHOR_DB')!=='perf_f138_anchor_ci'
            || getenv('ORACLE_DB')!=='perf_f143_oracle_ci'
            || strlen($key)!==32) {
            throw new RuntimeException('F143 disposable four-DB CI only');
        }
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $op,string $tenant,string $fp):array
    {
        $out=static fn(string $status,bool $registered=false):array=>[
            'status'=>$status,'registered'=>$registered,'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$op)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fp)) return $out('invalid_inputs');

        try {
            $local=$this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            $anchor=$this->anchor->query(
                'SELECT generation FROM perf_f138_anchor.epoch_floor WHERE singleton=1'
            )->fetchColumn();
            $oracle=$this->oracle->query(
                'SELECT generation FROM perf_f143_oracle.floor WHERE singleton=1'
            )->fetchColumn();
            if (!is_numeric($local)||!is_numeric($anchor)||!is_numeric($oracle)
                ||(int)$local<1||(int)$local!==(int)$anchor
                ||(int)$local!==(int)$oracle) return $out('sql_epoch_mismatch');
            $epoch=(int)$local;
        } catch(PDOException $e) {return $out('online_oracle_or_database_unavailable');}

        try {
            if ($this->journal->inspect()['epoch']!==$epoch)
                return $out('journal_epoch_mismatch');
            if (!$this->journal->reserve($op,$tenant,$fp,$epoch))
                return $out('journal_replay_or_epoch_denied');
            $state=$this->journal->inspect();
            if ($state['epoch']!==$epoch || !isset($state['seen'][$fp])
                || !isset($state['operations'][$op]))
                return $out('journal_postreserve_unverifiable');
            $timestamp=time();
            $message=implode('|',[
                'F142-v1',$op,$tenant,$fp,(string)$epoch,
                (string)$timestamp,(string)$state['events'],(string)$state['head'],
            ]);
            $mac=hash_hmac('sha256',$message,$this->key);
        } catch(RuntimeException $e) {return $out('journal_unknown_or_tampered');}

        // Mint in online oracle before any witness or primary SQL registration.
        // A partial error burns identity; never automatically retry.
        try {
            $q=$this->oracle->prepare(
                'SELECT perf_f143_oracle.issue_if_current(?,?,?,?,?,?,?,?)'
            );
            $q->execute([$op,$tenant,$fp,$epoch,$timestamp,$state['events'],$state['head'],$mac]);
            if (!in_array($q->fetchColumn(),[true,'t','1',1],true)) {
                return $out('oracle_issuance_denied_journal_spent');
            }
        } catch(PDOException $e) {return $out('oracle_unknown_journal_spent');}

        try {
            $q=$this->witness->prepare(
                'SELECT issued_at,signature FROM perf_f136_witness.issue_once(?,?,?,?)'
            );
            $q->execute([$op,$tenant,$fp,$epoch]);
            $permit=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($permit)) return $out('witness_denied_authorities_spent');
        } catch(PDOException $e) {return $out('witness_unknown_authorities_spent');}

        try {
            $q=$this->primary->prepare(
                'SELECT CASE WHEN perf_f143_primary.redeem_online_oracle(?,?,?,?,?,?,?,?,?,?) THEN 1 ELSE 0 END'
            );
            $q->execute([
                $op,$tenant,$fp,$epoch,(int)$permit['issued_at'],
                (string)$permit['signature'],$timestamp,
                $state['events'],$state['head'],$mac,
            ]);
            if ((int)$q->fetchColumn()!==1) {
                return $out('primary_or_online_oracle_denied_authorities_spent');
            }
        } catch(PDOException $e) {return $out('primary_or_online_oracle_unknown_authorities_spent');}
        return $out('registered_lab',true);
    }
}
