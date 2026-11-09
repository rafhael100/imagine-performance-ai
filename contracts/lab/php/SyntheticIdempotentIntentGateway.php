<?php
declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-35 only: reserve a READ-ONLY, SYNTHETIC intent and disabled outbox event.
 * Nothing is dispatched. This is not a real authority, Laravel service or
 * production authorization check. Direct SQL by a compromised role is
 * still outside this wrapper's control.
 */
final class SyntheticIdempotentIntentGateway
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function reserve(
        string $requestRef,
        string $manifestSha256,
        string $tenant='perf-demo-a',
        string $service='svc-demo-a',
        string $actor='actor-demo-a',
        string $permission='campaigns:read'
    ): string {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F35_INTENT_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'intent_lab_disabled';
        }
        if($this->db->inTransaction()) {
            return 'nested_transaction_denied';
        }
        try {
            $current=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($current)
                ||$current['db']!=='portal_perf_auth_lab'
                ||$current['role']!=='perf_f35_writer') {
                return 'wrong_database_or_role';
            }
            $statement=$this->db->prepare(
                'SELECT perf_f35_intents.reserve(?,?,?,?,?,?)'
            );
            $statement->execute([
                $tenant,$service,$actor,$requestRef,
                $permission,$manifestSha256
            ]);
            $state=$statement->fetchColumn();
            return is_string($state)?$state:'intent_store_unavailable';
        } catch(Throwable) {
            return 'intent_store_unavailable';
        }
    }
}
