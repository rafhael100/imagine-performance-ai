<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-29 TWO DISTINCT disposable PostgreSQL instances: consumer at 5432 and
 * durable-for-rollback nonce fence at 5433. Not a production authenticator.
 * Fence commits BEFORE local transaction, trading retry availability for
 * prevention of replay after rollback. Never dispatch external side effects.
 */
final class SyntheticIndependentReplayFence
{
    private const TENANT = '11111111-1111-4111-8111-111111111111';

    public function __construct(
        private readonly PDO $performance,
        private readonly PDO $authority
    ) {
    }

    /** @param callable(PDO):mixed $operation
     *  @return array{state:string,value:mixed}
     */
    public function execute(string $claim, callable $operation): array
    {
        $result = static fn(string $state, mixed $value = null): array =>
            ['state' => $state, 'value' => $value];
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F29_INDEPENDENT_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry'
            || $claim === '' || strlen($claim) > 300) {
            return $result('lab_disabled');
        }
        if ($this->performance->inTransaction() || $this->authority->inTransaction()) {
            return $result('nested_transaction_denied');
        }
        try {
            $p = $this->performance->query(
                'SELECT current_database() AS db, current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            $a = $this->authority->query(
                'SELECT current_database() AS db, current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($p) || !is_array($a)
                || $p['db'] !== 'perf_test_campaign_registry'
                || $p['role'] !== 'perf_campaign_test_user'
                || $a['db'] !== 'perf_f29_authority_ci'
                || $a['role'] !== 'perf_f29_consumer') {
                return $result('wrong_database_or_role');
            }
        } catch (Throwable) {
            return $result('authority_unavailable');
        }

        try {
            // The authority connection is always in AUTOCOMMIT here.
            // Its durable nonce is not part of the Performance transaction.
            $s = $this->authority->prepare(
                'SELECT CASE WHEN perf_f29_authority.consume_claim(?, CAST(? AS uuid)) THEN 1 ELSE 0 END'
            );
            $s->execute([$claim, self::TENANT]);
            if ((int)$s->fetchColumn() !== 1) {
                return $result('authority_denied_or_replay');
            }
        } catch (Throwable) {
            return $result('authority_unavailable');
        }

        try {
            $this->performance->beginTransaction();
            $s = $this->performance->prepare(
                "SELECT set_config('app.performance_tenant_claim', ?, true)"
            );
            $s->execute([$claim]);
            $tenant = $this->performance->query(
                'SELECT perf_f26_identity.verified_tenant()'
            )->fetchColumn();
            if ($tenant !== self::TENANT) {
                $this->performance->rollBack();
                return $result('performance_verification_denied');
            }
            $value = $operation($this->performance);
            $this->performance->commit();
            return $result('allowed_synthetic_only', $value);
        } catch (Throwable) {
            if ($this->performance->inTransaction()) {
                $this->performance->rollBack();
            }
            return $result('performance_rolled_back_nonce_retained');
        }
    }
}
