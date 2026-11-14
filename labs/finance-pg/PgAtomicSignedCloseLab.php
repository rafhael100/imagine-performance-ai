<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\MonthlySpendEvidencePreview;
use Imagine\Performance\Commercial\PortalSignedQuoteGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * LAB-ONLY atomic quote claim + finance draft. A trusted Portal IAM, real
 * credential lifecycle and official Ads spend are NOT provided here.
 * This class deliberately has no HTTP, PSP or Ads client.
 */
final class PgAtomicSignedCloseLab
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signed Synthetic, Portal-issued signed quote
     * @param list<array<string,mixed>> $records Synthetic spend rows
     * @param callable(string,string):?string $trustedKeyResolver CI-only key resolver
     * @return array<string,mixed> Non-billable draft result
     */
    public function stage(
        array $signed,
        string $expectedTenant,
        string $approvedCatalogVersion,
        string $period,
        array $records,
        int $now,
        callable $trustedKeyResolver
    ): array {
        if (getenv('APP_ENV') !== 'testing' || getenv('PERF_FIN_ATOMIC_LAB') !== '1') {
            throw new RuntimeException('Atomic finance laboratory disabled.');
        }
        if ($this->db->inTransaction() ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $expectedTenant) ||
            !is_array($signed['quote'] ?? null)) {
            throw new InvalidArgumentException('Invalid trusted laboratory scope.');
        }
        // Unverified rows are validated independently before entering SQL.
        $spend = MonthlySpendEvidencePreview::build($signed['quote'], $period, $records);
        if ($spend['provider_spend_verified'] !== false ||
            $spend['source_authenticated'] !== false ||
            $spend['finance_approved'] !== false ||
            $spend['automatic_charge_or_credit_enabled'] !== false ||
            $spend['ads_mutation_enabled'] !== false) {
            throw new InvalidArgumentException('Lab input cannot claim external spend or payment authority.');
        }
        if ($this->db->query('SELECT current_user')->fetchColumn() !== 'perf_fin_lab_app') {
            throw new InvalidArgumentException('Lab requires non-owner app role.');
        }
        try {
            $this->db->beginTransaction();
            $this->db->prepare("SELECT set_config('app.tenant_id', :tenant, true)")
                ->execute(['tenant' => $expectedTenant]);

            // This callback inserts into the SAME transaction as the draft.
            // If the draft fails, the nonce claim rolls back too.
            $claim = function (string $tenant, string $key, string $nonce, int $expires): bool {
                $statement = $this->db->prepare(
                    'INSERT INTO perf_fin_lab.signed_quote_nonces
                     (tenant_id, nonce, key_id, expires_at)
                     VALUES (:tenant,:nonce,:key,:expires)
                     ON CONFLICT (tenant_id, nonce) DO NOTHING RETURNING nonce'
                );
                $statement->execute([
                    'tenant' => $tenant, 'nonce' => $nonce, 'key' => $key,
                    'expires' => $expires,
                ]);
                return $statement->fetchColumn() !== false;
            };
            $verified = PortalSignedQuoteGuard::verifyAndPreview(
                $signed, $expectedTenant, $approvedCatalogVersion, $period,
                $spend['unverified_spend_by_channel_cents'], $now,
                $trustedKeyResolver, $claim
            );
            if (($verified['quote_claim_hmac_verified'] ?? false) !== true ||
                ($verified['portal_iam_authenticated'] ?? null) !== false ||
                ($verified['finance_approved'] ?? null) !== false ||
                ($verified['asaas_write_enabled'] ?? null) !== false ||
                ($verified['ads_write_enabled'] ?? null) !== false ||
                ($verified['preview'] ?? null) !== $spend['preview']) {
                throw new InvalidArgumentException('Verified quote differs from spend preview.');
            }
            $preview = $verified['preview'];
            $claimSha = $verified['quote_claim_sha256'];
            $closeId = hash('sha256', 'perf-close-lab:v1:' . $expectedTenant . ':' . $period . ':' . $claimSha);
            $insert = $this->db->prepare(
                'INSERT INTO perf_fin_lab.reconciliation_drafts
                (tenant_id, close_id, period, quote_claim_sha256, spend_claim_sha256,
                 planned_media_cents, unverified_actual_media_cents, advance_fee_cents,
                 unverified_earned_fee_cents, adjustment_cents)
                 VALUES (:tenant,:close,:period,:quote,:spend,:planned,:actual,:advance,:earned,:delta)
                 RETURNING close_id'
            );
            $insert->execute([
                'tenant' => $expectedTenant,
                'close' => $closeId,
                'period' => $period,
                'quote' => $claimSha,
                'spend' => $spend['records_fingerprint_sha256_not_proof'],
                'planned' => $preview['planned_media_cents'],
                'actual' => $preview['unverified_actual_media_cents'],
                'advance' => $preview['estimated_advance_fee_cents'],
                'earned' => $preview['unverified_earned_fee_cents'],
                'delta' => $preview['review_only_adjustment_cents'],
            ]);
            if ($insert->fetchColumn() !== $closeId) {
                throw new RuntimeException('Draft was not persisted.');
            }
            $this->db->commit();
            return [
                'created' => true,
                'close_id' => $closeId,
                'period' => $period,
                'status' => 'lab_draft_unverified_spend',
                'billable' => false,
                'finance_approved' => false,
                'portal_iam_authenticated' => false,
                'provider_spend_verified' => false,
                'asaas_write_enabled' => false,
                'ads_write_enabled' => false,
                'signed_verification' => $verified,
                'synthetic_spend' => $spend,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // No SQL detail, secret, tenant or provider record in caller-visible error.
            throw new RuntimeException('Atomic laboratory finance intake rejected or unavailable.');
        }
    }
}
