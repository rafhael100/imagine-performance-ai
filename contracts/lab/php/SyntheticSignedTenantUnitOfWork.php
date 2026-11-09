<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use Throwable;

/**
 * F1-27: disposable CLI proof of scoped tenant claims in a transaction.
 *
 * A real identity issuer, secure pool reset, contract checks, key handling,
 * authorization, and effect-atomicity do NOT exist here. No Laravel binding.
 */
final class SyntheticSignedTenantUnitOfWork
{
    private const TEST_DB = 'perf_test_campaign_registry';
    private const TEST_ROLE = 'perf_campaign_test_user';
    private const TEST_TENANT = '11111111-1111-4111-8111-111111111111';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param callable(PDO):mixed $operation
     * @return array{state:string,value:mixed}
     */
    public function execute(string $signedClaim, callable $operation): array
    {
        $out=static fn(string $state,mixed $value=null):array => [
            'state'=>$state,'value'=>$value
        ];
        if (PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F27_UNIT_OF_WORK_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!==self::TEST_DB
            || $signedClaim==='' || strlen($signedClaim)>300) {
            return $out('unit_of_work_disabled');
        }
        if ($this->db->inTransaction()) {
            return $out('nested_transaction_denied');
        }
        try {
            $identity=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['db']!==self::TEST_DB
                || $identity['role']!==self::TEST_ROLE) {
                return $out('wrong_database_role');
            }
            $this->db->beginTransaction();
            $s=$this->db->prepare(
                "SELECT set_config('app.performance_tenant_claim', ?, true)"
            );
            $s->execute([$signedClaim]);
            $tenant=$this->db->query(
                'SELECT perf_f26_identity.verified_tenant()'
            )->fetchColumn();
            if ($tenant!==self::TEST_TENANT) {
                $this->db->rollBack();
                return $out('signed_tenant_denied');
            }
            $value=$operation($this->db);
            $this->db->commit();
            return $out('allowed_synthetic_only',$value);
        } catch(Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $out('transaction_rolled_back');
        }
    }
}
