<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use PDO;
use Throwable;

/**
 * Test-only durable nonce callback for the separate Portal V7 plan agreement
 * review. Uses authenticated PostgreSQL session_user -> protected tenant
 * registry with FORCE RLS; never trusts SET app.tenant_id.
 *
 * Not a real acceptance ledger, IAM, production migration or permission to
 * charge. Claim succeeds only after its own transaction commits.
 */
final class PgPortalV7SignedPlanNonceLab
{
    public function __construct(private readonly PDO $db) {}

    public function claim(
        string $tenant,
        string $agreement,
        string $nonce,
        int $expiresAt
    ): bool {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
           getenv('PERF_PG_V7_PLAN_NONCE_LAB')!=='1' ||
           $this->db->inTransaction() ||
           !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$tenant) ||
           !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$agreement) ||
           !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$nonce) ||
           $expiresAt<=0) {
            return false;
        }
        try {
            $login=$this->db->query('SELECT session_user')->fetchColumn();
            if(!in_array($login,['perf_v6_tenant_alpha','perf_v6_tenant_beta'],true)) {
                return false;
            }
            $this->db->beginTransaction();
            $mapped=$this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if(!is_string($mapped) || !hash_equals($tenant,$mapped)) {
                $this->db->rollBack();
                return false;
            }
            $stmt=$this->db->prepare(
                'INSERT INTO perf_fin_role_lab.portal_v7_plan_nonces
                    (tenant_id,agreement_id,nonce,expires_at)
                 VALUES (:tenant,:agreement,:nonce,:expires)
                 ON CONFLICT (tenant_id,agreement_id,nonce) DO NOTHING
                 RETURNING nonce'
            );
            $stmt->execute([
                'tenant'=>$tenant,'agreement'=>$agreement,
                'nonce'=>$nonce,'expires'=>$expiresAt,
            ]);
            $ok=$stmt->fetchColumn()===$nonce;
            if(!$ok){
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return false;
        }
    }

    /** @return array{durable_in_disposable_ci:bool,customer_acceptance_verified:bool,psp_enabled:bool} */
    public static function limits():array
    {
        return [
            'durable_in_disposable_ci'=>true,
            'customer_acceptance_verified'=>false,
            'psp_enabled'=>false,
        ];
    }
}
