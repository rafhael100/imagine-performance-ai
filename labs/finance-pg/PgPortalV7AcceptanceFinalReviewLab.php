<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use PDO;
use Throwable;

/**
 * CI-only second-stage barrier before any simulated provider/finance step.
 *
 * A prior successful V7 HMAC and nonce claim are NOT timeless permission:
 * a subsequent synthetic revocation or re-acceptance changes the latest
 * event revision. This adapter checks it AGAIN at the point of action.
 *
 * Strictly review-only: this does not perform any external action and cannot
 * provide atomicity against revocation AFTER the query commits. Production
 * needs authenticated Portal authorities, a revocation fence and audit.
 */
final class PgPortalV7AcceptanceFinalReviewLab
{
    public function __construct(private readonly PDO $db) {}

    public function currentlyMatches(
        string $tenant,
        string $agreement,
        string $period,
        int $expectedAcceptanceRevision,
        string $expectedPlanSha256,
        string $expectedTermsSha256,
        int $now
    ): bool {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('PERF_PG_V6_ROLE_LAB') !== '1'
            || getenv('PERF_PG_V7_ACCEPTANCE_LAB') !== '1'
            || getenv('PERF_PG_V7_FINAL_REVIEW_LAB') !== '1'
            || $this->db->inTransaction()
            || !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant)
            || !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D', $agreement)
            || !preg_match('/\A20[0-9]{2}-(0[1-9]|1[0-2])\z/D', $period)
            || $expectedAcceptanceRevision < 1
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedPlanSha256)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedTermsSha256)
            || $now <= 0) {
            return false;
        }
        try {
            $login = $this->db->query('SELECT session_user')->fetchColumn();
            if (!in_array($login, ['perf_v6_tenant_alpha', 'perf_v6_tenant_beta'], true)) {
                return false;
            }
            $resolvedTenant = $this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if (!is_string($resolvedTenant) || !hash_equals($tenant, $resolvedTenant)) {
                return false;
            }
            // Latest event is read from a NEW READ COMMITTED statement
            // after the earlier nonce claim, not cached within that claim's
            // transaction or taken from the signed envelope.
            $stmt = $this->db->prepare(
                'SELECT state,event_revision,plan_sha256,terms_sha256,
                        mfa_snapshot_verified,full_month_reviewed,
                        event_at,expires_at
                 FROM perf_fin_role_lab.portal_v7_acceptance_events
                 WHERE tenant_id=:tenant AND agreement_id=:agreement
                   AND period=:period
                 ORDER BY event_revision DESC
                 LIMIT 1'
            );
            $stmt->execute([
                'tenant' => $tenant,
                'agreement' => $agreement,
                'period' => $period,
            ]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($event) || $stmt->fetch(PDO::FETCH_ASSOC) !== false) {
                return false;
            }
            return $event['state'] === 'synthetic_accepted'
                && (int)$event['event_revision'] === $expectedAcceptanceRevision
                && is_string($event['plan_sha256'])
                && hash_equals($expectedPlanSha256, $event['plan_sha256'])
                && is_string($event['terms_sha256'])
                && hash_equals($expectedTermsSha256, $event['terms_sha256'])
                && in_array($event['mfa_snapshot_verified'], [true, 1, '1', 't'], true)
                && in_array($event['full_month_reviewed'], [true, 1, '1', 't'], true)
                && is_numeric($event['event_at'])
                && is_numeric($event['expires_at'])
                && (int)$event['event_at'] <= $now
                && (int)$event['expires_at'] > $now;
        } catch (Throwable) {
            // No account/approval/SQL/provider detail escapes to callers.
            return false;
        }
    }

    /** @return array<string,bool> */
    public static function limits(): array
    {
        return [
            'fresh_sql_revocation_checked_in_disposable_ci' => true,
            'real_portal_iam_authenticated' => false,
            'real_customer_acceptance_proven' => false,
            'prevents_revocation_after_check' => false,
            'external_actions_authorized' => false,
        ];
    }
}
