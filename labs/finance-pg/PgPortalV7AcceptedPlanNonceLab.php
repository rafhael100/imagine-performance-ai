<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use PDO;
use Throwable;

/**
 * CI-only adapter: the SAME SQL statement checks the latest admin-seeded
 * synthetic acceptance/revocation event AND inserts a unique V7 plan nonce.
 * It uses the independently authenticated session_user => protected tenant
 * registry plus FORCE RLS; app.tenant_id is never authoritative.
 *
 * This is NOT Portal IAM, legal consent, signed customer acceptance or an
 * operational concurrency guarantee. A revocation after COMMIT must still
 * be checked before any eventual real external action.
 */
final class PgPortalV7AcceptedPlanNonceLab
{
    public function __construct(private readonly PDO $db) {}

    public function claim(
        string $tenant,
        string $agreement,
        string $period,
        string $planSha256,
        string $termsSha256,
        string $nonce,
        int $expiresAt,
        int $now
    ): bool {
        if (getenv('APP_ENV')!=='testing' ||
            getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
            getenv('PERF_PG_V7_PLAN_NONCE_LAB')!=='1' ||
            getenv('PERF_PG_V7_ACCEPTANCE_LAB')!=='1' ||
            $this->db->inTransaction() ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$tenant) ||
            !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$agreement) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$period) ||
            !preg_match('/\A[a-f0-9]{64}\z/D',$planSha256) ||
            !preg_match('/\A[a-f0-9]{64}\z/D',$termsSha256) ||
            !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$nonce) ||
            $now<=0 || $expiresAt<=$now || $expiresAt>$now+300) {
            return false;
        }
        try {
            $login=$this->db->query('SELECT session_user')->fetchColumn();
            if(!in_array($login,['perf_v6_tenant_alpha','perf_v6_tenant_beta'],true)){
                return false;
            }
            $this->db->beginTransaction();
            $bound=$this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if(!is_string($bound) || !hash_equals($tenant,$bound)){
                $this->db->rollBack();
                return false;
            }
            // One SQL statement: read latest revision for this agreement and
            // period and atomically write a unique signed-plan nonce only if
            // the latest event is fresh, accepted, MFA-reviewed and matches
            // independently expected plan/terms fingerprints.
            $sql=<<<'SQL'
WITH latest AS (
    SELECT state,plan_sha256,terms_sha256,mfa_snapshot_verified,
           full_month_reviewed,event_at,expires_at
      FROM perf_fin_role_lab.portal_v7_acceptance_events
     WHERE tenant_id=:accept_tenant AND agreement_id=:accept_agreement
       AND period=:accept_period
     ORDER BY event_revision DESC LIMIT 1
)
INSERT INTO perf_fin_role_lab.portal_v7_plan_nonces
    (tenant_id,agreement_id,nonce,expires_at)
SELECT :insert_tenant,:insert_agreement,:insert_nonce,:insert_expires
WHERE EXISTS (
    SELECT 1 FROM latest
     WHERE state='synthetic_accepted'
       AND plan_sha256=:expected_plan
       AND terms_sha256=:expected_terms
       AND mfa_snapshot_verified=true
       AND full_month_reviewed=true
       AND event_at<=:eval_now
       AND expires_at>:eval_now_expiry
)
ON CONFLICT (tenant_id,agreement_id,nonce) DO NOTHING
RETURNING nonce
SQL;
            $stmt=$this->db->prepare($sql);
            $stmt->execute([
                'accept_tenant'=>$tenant,
                'accept_agreement'=>$agreement,
                'accept_period'=>$period,
                'insert_tenant'=>$tenant,
                'insert_agreement'=>$agreement,
                'insert_nonce'=>$nonce,
                'insert_expires'=>$expiresAt,
                'expected_plan'=>$planSha256,
                'expected_terms'=>$termsSha256,
                'eval_now'=>$now,
                'eval_now_expiry'=>$now,
            ]);
            $ok=$stmt->fetchColumn()===$nonce;
            if(!$ok){
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        } catch(Throwable) {
            if($this->db->inTransaction())$this->db->rollBack();
            return false;
        }
    }

    /** @return array<string,bool> */
    public static function limits():array {
        return [
            'synthetic_acceptance_checked_with_nonce_in_disposable_db'=>true,
            'portal_iam_authenticated'=>false,
            'real_customer_acceptance_verified'=>false,
            'production_revocation_race_eliminated'=>false,
            'payments_enabled'=>false,
            'ads_enabled'=>false,
        ];
    }
}
