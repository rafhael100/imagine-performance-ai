<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\PortalTriChannelV5CandidateGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Isolated CI-only multi-agreement V5 close, separate from the v2/v5 legacy
 * one-draft-per-tenant-month table. No real Portal IAM, Ads or billing.
 * Agreement ID is NOT in V5 HMAC: its identity depends on a trusted
 * server-side, immutable fixture binding to the *exact* signed quote SHA256.
 */
final class PgMultiAgreementV5CloseLab
{
    private const CHANNELS = ['chatgpt_ads', 'google_ads', 'meta_ads'];
    private const MAX_ROWS = 200;
    private const MAX_CENTS = 100000000;

    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signedQuote V5 envelope from pinned Portal issuer
     * @param array<string,int> $ownerMinima pinned commercial decision, NOT browser data
     * @param list<array<string,mixed>> $reports synthetic spend records only
     * @param callable(string,string):?string $resolveKey test-only resolver
     * @return array<string,mixed>
     */
    public function stage(
        array $signedQuote,
        string $expectedTenant,
        string $expectedAgreement,
        string $period,
        string $expectedCatalogSha256,
        array $ownerMinima,
        array $reports,
        int $now,
        callable $resolveKey
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PG_MULTIAGREEMENT_V5_LAB') !== '1' ||
            getenv('PERF_PORTAL_TRICHANNEL_V5_LAB') !== '1' ||
            $this->db->inTransaction() ||
            $this->db->query('SELECT current_user')->fetchColumn() !== 'perf_fin_lab_app' ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $expectedTenant) ||
            !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D', $expectedAgreement) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $period) ||
            ($signedQuote['tenant_id'] ?? null) !== $expectedTenant ||
            ($signedQuote['billing_period'] ?? null) !== $period) {
            throw new InvalidArgumentException('Multiagreement testing scope invalid or disabled.');
        }
        $quoted = $signedQuote['quote']['media_by_channel_cents'] ?? null;
        if (!is_array($quoted) || $quoted === [] || count($quoted) > 3 ||
            count($reports) < 1 || count($reports) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Missing synthetic channel report or signed contract.');
        }
        $keys = array_keys($quoted);
        sort($keys, SORT_STRING);
        if (array_diff($keys, self::CHANNELS) !== []) {
            throw new InvalidArgumentException('Unsupported synthetic Ads channel.');
        }
        $spent = [];
        $seen = [];
        $canonical = [];
        foreach ($reports as $record) {
            if (!is_array($record)) throw new InvalidArgumentException('Invalid spend report.');
            $fields = array_keys($record);
            sort($fields, SORT_STRING);
            if ($fields !== ['channel','period','report_id','source','spend_cents']) {
                throw new InvalidArgumentException('Unexpected synthetic evidence schema.');
            }
            $channel = $record['channel'];
            $id = $record['report_id'];
            $cents = $record['spend_cents'];
            if (!is_string($channel) || !array_key_exists($channel, $quoted) ||
                !in_array($channel, self::CHANNELS, true) ||
                $record['period'] !== $period || $record['source'] !== 'synthetic_fixture' ||
                !is_string($id) ||
                !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $id) ||
                !is_int($cents) || $cents < 0 || $cents > self::MAX_CENTS) {
                throw new InvalidArgumentException('Invalid unverified expense, month or provenance.');
            }
            $dedupe = $channel . "\0" . $id;
            if (isset($seen[$dedupe])) throw new InvalidArgumentException('Duplicate expense reference.');
            $seen[$dedupe] = true;
            $previous = $spent[$channel] ?? 0;
            if ($previous > self::MAX_CENTS - $cents) {
                throw new InvalidArgumentException('Excessive channel spend.');
            }
            $spent[$channel] = $previous + $cents;
            $canonical[] = $record;
        }
        $actualKeys = array_keys($spent);
        sort($actualKeys, SORT_STRING);
        if ($keys !== $actualKeys) {
            throw new InvalidArgumentException('Every contracted channel requires explicit report, including zero.');
        }
        ksort($spent, SORT_STRING);
        usort($canonical, static fn(array $a, array $b): int =>
            [$a['channel'], $a['report_id']] <=> [$b['channel'], $b['report_id']]
        );
        $fingerprint = hash('sha256', json_encode(
            $canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ));

        try {
            $this->db->beginTransaction();
            // RLS here is only a synthetic CI demonstration and NOT authenticated
            // tenant isolation: a malicious SQL principal could set this GUC.
            $this->db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")
                ->execute(['tenant'=>$expectedTenant]);

            $claim = function (string $tenant, string $key, string $nonce, int $expires): bool {
                $sql = $this->db->prepare(
                    'INSERT INTO perf_fin_lab.signed_quote_nonces
                    (tenant_id,nonce,key_id,expires_at)
                    VALUES (:tenant,:nonce,:key,:expires)
                    ON CONFLICT (tenant_id,nonce) DO NOTHING RETURNING nonce'
                );
                $sql->execute(['tenant'=>$tenant,'nonce'=>$nonce,'key'=>$key,'expires'=>$expires]);
                return $sql->fetchColumn() !== false;
            };
            $verified = PortalTriChannelV5CandidateGuard::verify(
                $signedQuote,$expectedTenant,$period,$expectedCatalogSha256,
                $ownerMinima,$spent,$now,$resolveKey,$claim
            );
            foreach ([
                'portal_catalog_active','portal_iam_authenticated',
                'provider_spend_verified','finance_approved','billable',
                'asaas_write_enabled','ads_write_enabled'
            ] as $flag) {
                if (($verified[$flag] ?? null) !== false) {
                    throw new InvalidArgumentException('V5 candidate must never be billable or authorized.');
                }
            }
            if (($verified['quote_claim_hmac_verified'] ?? null) !== true ||
                ($verified['billing_period_bound'] ?? null) !== true) {
                throw new InvalidArgumentException('Unsigned or month-unbound V5 contract.');
            }
            $quoteHash = $verified['quote_claim_sha256'];
            // Bind an independently supplied TEST agreement to the EXACT signed
            // quote. A missing/mismatched binding is not implicitly created.
            $lookup = $this->db->prepare(
                'SELECT quote_claim_sha256,catalog_sha256,is_synthetic
                FROM perf_fin_multi_lab.agreement_quote_bindings
                WHERE tenant_id=:tenant AND agreement_id=:agreement AND period=:period'
            );
            $lookup->execute([
                'tenant'=>$expectedTenant,'agreement'=>$expectedAgreement,'period'=>$period
            ]);
            $binding=$lookup->fetch(PDO::FETCH_ASSOC);
            if (!is_array($binding) ||
                !hash_equals($quoteHash, (string)$binding['quote_claim_sha256']) ||
                !hash_equals($expectedCatalogSha256, (string)$binding['catalog_sha256']) ||
                !in_array($binding['is_synthetic'], [true,1,'1','t'], true)) {
                throw new InvalidArgumentException('Unsigned or wrong-agreement registry binding.');
            }
            $closeId = hash('sha256', 'perf-v5-multiagreement-ci:' .
                $expectedTenant . ':' . $expectedAgreement . ':' . $period . ':' . $quoteHash
            );
            $insert = $this->db->prepare(
                'INSERT INTO perf_fin_multi_lab.agreement_close_drafts
                (tenant_id,agreement_id,period,close_id,catalog_sha256,
                 quote_claim_sha256,spend_claim_sha256,planned_media_cents,
                 unverified_actual_media_cents,advance_fee_cents,
                 unverified_earned_fee_cents,adjustment_cents)
                 VALUES (:tenant,:agreement,:period,:close,:catalog,
                         :quote,:spend,:planned,:actual,:advance,:earned,:adjustment)
                 RETURNING close_id'
            );
            $insert->execute([
                'tenant'=>$expectedTenant,'agreement'=>$expectedAgreement,'period'=>$period,
                'close'=>$closeId,'catalog'=>$expectedCatalogSha256,
                'quote'=>$quoteHash,'spend'=>$fingerprint,
                'planned'=>$verified['planned_media_cents'],
                'actual'=>$verified['unverified_actual_media_cents'],
                'advance'=>$verified['estimated_advance_fee_cents'],
                'earned'=>$verified['unverified_earned_fee_cents'],
                'adjustment'=>$verified['review_only_adjustment_cents'],
            ]);
            if ($insert->fetchColumn() !== $closeId) {
                throw new RuntimeException('Synthetic agreement draft was not persisted.');
            }
            $this->db->commit();
            return [
                'created'=>true,'tenant_id'=>$expectedTenant,'agreement_id'=>$expectedAgreement,
                'period'=>$period,'close_id'=>$closeId,
                'quote_claim_sha256'=>$quoteHash,
                'synthetic_spend_sha256_not_provider_proof'=>$fingerprint,
                'signed_verification'=>$verified,
                'state'=>'test_only_multiagreement_signed_v5_nonbillable_draft',
                'agreement_id_signed_in_v5_envelope'=>false,
                'agreement_binding_proven_by_real_portal_iam'=>false,
                'provider_spend_verified'=>false,'finance_approved'=>false,
                'billable'=>false,'asaas_write_enabled'=>false,'ads_write_enabled'=>false,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Synthetic multiagreement finance close rejected or unavailable.');
        }
    }
}
