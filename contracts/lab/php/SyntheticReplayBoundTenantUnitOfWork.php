<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-28 disposable CLI experiment. Do NOT register in Laravel or expose as API.
 * This fence only prevents replay after successful DB commit, never after
 * rollback; it is not a production identity service, key vault, or Ads gate.
 */
final class SyntheticReplayBoundTenantUnitOfWork
{
    private const DB = 'perf_test_campaign_registry';
    private const ROLE = 'perf_campaign_test_user';
    private const TENANT_A = '11111111-1111-4111-8111-111111111111';

    public function __construct(private readonly PDO $db)
    {
    }

    /** @param callable(PDO):mixed $operation
     *  @return array{state:string,value:mixed}
     */
    public function execute(string $signedClaim, callable $operation): array
    {
        $answer = static fn(string $state, mixed $value = null): array =>
            ['state' => $state, 'value' => $value];
        if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F28_REPLAY_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== self::DB
            || $signedClaim === '' || strlen($signedClaim) > 300) {
            return $answer('lab_disabled');
        }
        if ($this->db->inTransaction()) {
            return $answer('nested_transaction_denied');
        }
        try {
            $identity = $this->db->query(
                'SELECT current_database() AS db, current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity) || $identity['db'] !== self::DB
                || $identity['role'] !== self::ROLE) {
                return $answer('wrong_database_role');
            }

            $this->db->beginTransaction();
            $set = $this->db->prepare(
                "SELECT set_config('app.performance_tenant_claim', ?, true)"
            );
            $set->execute([$signedClaim]);
            $verified = $this->db->query(
                'SELECT perf_f26_identity.verified_tenant()'
            )->fetchColumn();
            if ($verified !== self::TENANT_A) {
                $this->db->rollBack();
                return $answer('signed_tenant_denied');
            }

            $consume = $this->db->prepare(
                'SELECT CASE WHEN perf_f28_replay.consume_valid_nonce(CAST(? AS uuid)) THEN 1 ELSE 0 END'
            );
            $consume->execute([self::TENANT_A]);
            if ((int)$consume->fetchColumn() !== 1) {
                $this->db->rollBack();
                return $answer('replay_or_nonce_denied');
            }

            $value = $operation($this->db);
            $this->db->commit();
            return $answer('allowed_synthetic_only', $value);
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $answer('transaction_rolled_back');
        }
    }
}
