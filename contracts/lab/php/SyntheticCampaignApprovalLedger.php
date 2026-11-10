<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * F1-23, intentionally CLI+CI ONLY. Test tenant/actor references are fixed
 * synthetic fixtures, NOT real user authentication or contract eligibility.
 * No routes, jobs, Ads/PSP credentials or provider side effects.
 */
final class SyntheticCampaignApprovalLedger
{
    private const TENANT = '11111111-1111-4111-8111-111111111111';
    private const SERVICE = 'svc-demo-a';
    private const PROPOSER = 'actor-demo-a';
    private const REVIEWER = 'reviewer-demo-a';

    public function __construct(private readonly PDO $db)
    {
    }

    public function propose(
        string $tenant,
        string $campaign,
        string $service,
        string $operation,
        string $requestRef,
        string $actor,
    ): string {
        if (!$this->lab()) {
            return 'ledger_lab_disabled';
        }
        if ($tenant !== self::TENANT || $service !== self::SERVICE
            || $actor !== self::PROPOSER
            || !preg_match('/^req-demo-[a-z0-9-]{1,80}$/D', $requestRef)
            || !in_array($operation, [
                'campaign.create', 'campaign.pause',
                'campaign.replace', 'campaign.release'
            ], true)) {
            return 'proposal_scope_denied';
        }
        try {
            $this->db->beginTransaction();
            $this->setTenant($tenant);
            $existing = $this->db->prepare(
                'SELECT campaign_id,portal_service_ref,operation,proposer_actor_ref,
                        payload_sha256
                 FROM performance_campaign_intents WHERE request_ref=? FOR UPDATE'
            );
            $existing->execute([$requestRef]);
            $previous = $existing->fetch(PDO::FETCH_ASSOC);
            $payload = hash('sha256', implode("\n",[
                $tenant,$campaign,$service,$operation,$requestRef,$actor,'manifest-demo-v1'
            ]));
            if (is_array($previous)) {
                $consistent = $previous['campaign_id'] === $campaign
                    && $previous['portal_service_ref'] === $service
                    && $previous['operation'] === $operation
                    && $previous['proposer_actor_ref'] === $actor
                    && $previous['payload_sha256'] === $payload;
                $this->db->commit();
                return $consistent ? 'already_proposed_demo' : 'request_key_conflict';
            }

            $row = $this->db->prepare(
                'SELECT tenant_id,portal_service_ref,dispatch_state
                 FROM performance_managed_campaigns WHERE id=?'
            );
            $row->execute([$campaign]);
            $target=$row->fetch(PDO::FETCH_ASSOC);
            if (!is_array($target) || $target['tenant_id'] !== $tenant
                || $target['portal_service_ref'] !== $service
                || $target['dispatch_state'] !== 'disabled') {
                $this->db->rollBack();
                return 'proposal_campaign_denied';
            }

            $id = (string) Str::uuid();
            $insert=$this->db->prepare(
                "INSERT INTO performance_campaign_intents
                 (id,tenant_id,campaign_id,portal_service_ref,operation,review_state,
                  proposer_actor_ref,manifest_version,request_ref,payload_sha256,dispatch_state,
                  created_at,updated_at)
                 VALUES (?,?,?,?,?,'awaiting_approval',?,?,?,?,'disabled',
                         clock_timestamp(),clock_timestamp())"
            );
            $insert->execute([
                $id,$tenant,$campaign,$service,$operation,$actor,
                'manifest-demo-v1',$requestRef,$payload
            ]);
            $audit=$this->db->prepare(
                "INSERT INTO audit_events
                  (id,tenant_id,actor_ref,action,resource_ref,decision,reason_code,
                   correlation_ref,metadata,occurred_at)
                 VALUES (?,?,?,?,?,?,?,?,?::jsonb,clock_timestamp())"
            );
            $audit->execute([
                (string) Str::uuid(),$tenant,$actor,'campaign.intent.propose',
                $campaign,'pending','awaiting_review',$requestRef,
                json_encode(['lab_only'=>true], JSON_THROW_ON_ERROR)
            ]);
            $this->db->commit();
            return 'proposal_pending_demo';
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return 'proposal_transaction_denied';
        }
    }

    public function review(
        string $tenant,
        string $requestRef,
        string $reviewer,
        bool $approve,
    ): string {
        if (!$this->lab()) {
            return 'ledger_lab_disabled';
        }
        if ($tenant !== self::TENANT
            || !preg_match('/^req-demo-[a-z0-9-]{1,80}$/D', $requestRef)
            || $reviewer !== self::REVIEWER) {
            return 'review_scope_denied';
        }
        try {
            $this->db->beginTransaction();
            $this->setTenant($tenant);

            $membership = $this->db->prepare(
                'SELECT role,status,scopes::text AS scopes,expires_at
                 FROM operational_memberships
                 WHERE tenant_id=? AND actor_ref=? AND role=?'
            );
            $membership->execute([$tenant,$reviewer,'approver']);
            $member=$membership->fetch(PDO::FETCH_ASSOC);
            if (!is_array($member)
                || $member['status'] !== 'active'
                || $member['expires_at'] !== null
                || !in_array(
                    'proposals:approve',
                    json_decode($member['scopes'], true, 512, JSON_THROW_ON_ERROR),
                    true
                )) {
                $this->db->rollBack();
                return 'review_membership_denied';
            }

            $pending = $this->db->prepare(
                'SELECT id,campaign_id,portal_service_ref,proposer_actor_ref,
                        reviewer_actor_ref,review_state,dispatch_state
                 FROM performance_campaign_intents
                 WHERE tenant_id=? AND request_ref=? FOR UPDATE'
            );
            $pending->execute([$tenant,$requestRef]);
            $intent = $pending->fetch(PDO::FETCH_ASSOC);
            if (!is_array($intent) || $intent['portal_service_ref'] !== self::SERVICE
                || $intent['proposer_actor_ref'] === $reviewer
                || $intent['dispatch_state'] !== 'disabled') {
                $this->db->rollBack();
                return 'review_intent_denied';
            }
            $next = $approve ? 'approved_internal' : 'rejected';
            if ($intent['review_state'] !== 'awaiting_approval') {
                $same = $intent['review_state'] === $next
                    && $intent['reviewer_actor_ref'] === $reviewer;
                $this->db->commit();
                return $same ? 'already_reviewed_demo' : 'review_conflict';
            }

            $change=$this->db->prepare(
                'UPDATE performance_campaign_intents
                 SET review_state=?, reviewer_actor_ref=?,updated_at=clock_timestamp()
                 WHERE id=? AND tenant_id=? AND review_state=? AND dispatch_state=?'
            );
            $change->execute([
                $next,$reviewer,$intent['id'],$tenant,'awaiting_approval','disabled'
            ]);
            if ($change->rowCount() !== 1) {
                $this->db->rollBack();
                return 'review_concurrent_change';
            }
            $audit=$this->db->prepare(
                "INSERT INTO audit_events
                 (id,tenant_id,actor_ref,action,resource_ref,decision,reason_code,
                  correlation_ref,metadata,occurred_at)
                 VALUES (?,?,?,?,?,?,?,?,?::jsonb,clock_timestamp())"
            );
            $audit->execute([
                (string) Str::uuid(),$tenant,$reviewer,'campaign.intent.review',
                $intent['campaign_id'],$next,'internal_review_only',$requestRef,
                json_encode(['lab_only'=>true], JSON_THROW_ON_ERROR)
            ]);
            $this->db->commit();
            return $approve ? 'review_approved_internal_demo' : 'review_rejected_demo';
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return 'review_transaction_denied';
        }
    }

    private function setTenant(string $tenant): void
    {
        $stmt=$this->db->prepare(
            "SELECT set_config('app.performance_tenant_id', ?, true)"
        );
        $stmt->execute([$tenant]);
    }

    private function lab(): bool
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('RUN_PERF_F22_PG_LAB') !== '1'
            || getenv('PERF_F23_LEDGER_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
            return false;
        }
        try {
            $row=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            return is_array($row)
                && $row['db'] === 'perf_test_campaign_registry'
                && $row['role'] === 'perf_f23_writer';
        } catch (Throwable) {
            return false;
        }
    }
}
