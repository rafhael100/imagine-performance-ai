<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/F139LockedAnchorRegistration.php';
require_once __DIR__.'/F141SignedJournalAuthority.php';

/**
 * F141 admission gateway demonstration ONLY. The external signed journal is
 * consulted BEFORE invoking the F139 guarded SQL path. The SQL role itself
 * is not cryptographically coupled to this external filesystem journal;
 * direct SQL calls remain a KNOWN BYPASS, explicitly tested as negative.
 *
 * Under no circumstances interpret registered_lab as Ads authorization.
 */
final class F141JournalGatedRegistration
{
    private F139LockedAnchorRegistration $downstream;

    public function __construct(
        private PDO $primary,
        private PDO $witness,
        private PDO $anchor,
        private F141SignedJournalAuthority $authority
    ) {
        if (getenv('APP_ENV')!=='testing' || getenv('PERF_F141_LAB')!=='1'
            || getenv('PERF_F139_LAB')!=='1') {
            throw new RuntimeException('F141 lab-only gateway');
        }
        $this->downstream=new F139LockedAnchorRegistration(
            $primary,$witness,$anchor
        );
    }

    /** @return array{status:string,registered:bool,action_authorized:bool} */
    public function register(string $operation,string $tenant,string $fingerprint):array
    {
        $deny=static fn(string $status,bool $registered=false):array=>[
            'status'=>$status,'registered'=>$registered,'action_authorized'=>false,
        ];
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$operation)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$tenant)
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            return $deny('invalid_inputs');
        }
        try {
            $local=$this->primary->query(
                'SELECT perf_f132_primary.current_generation()'
            )->fetchColumn();
            $anchor=$this->anchor->query(
                'SELECT generation FROM perf_f138_anchor.epoch_floor WHERE singleton=1'
            )->fetchColumn();
            if (!is_numeric($local) || !is_numeric($anchor)
                || (int)$local<1 || (int)$local!==(int)$anchor) {
                return $deny('db_epoch_mismatch');
            }
            $epoch=(int)$local;
            $external=$this->authority->inspect();
            if ($external['epoch']!==$epoch) {
                return $deny('external_epoch_mismatch');
            }
        } catch(PDOException $e) {
            return $deny('database_preflight_unknown');
        } catch(RuntimeException $e) {
            return $deny('external_authority_unknown');
        }

        try {
            // File lock ensures a second lab client cannot reserve the same
            // operation/fingerprint. Reserve is irreversible when SQL fails.
            if (!$this->authority->reserve($operation,$tenant,$fingerprint,$epoch)) {
                return $deny('external_replay_or_epoch_denied');
            }
        } catch(RuntimeException $e) {
            return $deny('external_authority_unknown');
        }

        $result=$this->downstream->register($operation,$tenant,$fingerprint);
        if ($result['status']!=='registered_lab' || !$result['registered']) {
            return $deny('downstream_denied_external_spent');
        }
        return $deny('registered_lab',true);
    }
}
