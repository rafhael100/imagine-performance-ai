<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\PortalTriChannelV5CandidateGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * ISOLATED PG18 TEST ONLY — V5 Portal HMAC + nonce + unbillable finance
 * draft in ONE transaction. Reuses disposable lab schema; no PSP or Ads API.
 * app.tenant_id is a forgeable GUC: NOT a production IAM/RLS boundary.
 */
final class PgTriChannelV5CandidateCloseLab
{
    private const CHANNELS = ['chatgpt_ads','google_ads','meta_ads'];
    private const MAX_ROWS = 200;
    private const MAX_CENTS = 100000000;

    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signedPortalQuote
     * @param array<string,int> $approvedMinima trusted pinned Portal policy
     * @param list<array<string,mixed>> $syntheticRecords
     * @param callable(string,string):?string $keyResolver
     * @return array<string,mixed>
     */
    public function stage(
        array $signedPortalQuote,
        string $expectedTenant,
        string $expectedPeriod,
        string $catalogSha256,
        array $approvedMinima,
        array $syntheticRecords,
        int $now,
        callable $keyResolver
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PG_TRICHANNEL_V5_LAB') !== '1' ||
            getenv('PERF_PORTAL_TRICHANNEL_V5_LAB') !== '1' ||
            $this->db->inTransaction() ||
            $this->db->query('SELECT current_user')->fetchColumn() !== 'perf_fin_lab_app' ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $expectedTenant) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $expectedPeriod) ||
            ($signedPortalQuote['tenant_id'] ?? null) !== $expectedTenant ||
            ($signedPortalQuote['billing_period'] ?? null) !== $expectedPeriod) {
            throw new InvalidArgumentException('Candidate finance lab scope disabled or invalid.');
        }

        $quotedChannels = $signedPortalQuote['quote']['media_by_channel_cents'] ?? null;
        if (!is_array($quotedChannels) || $quotedChannels === [] ||
            count($quotedChannels) > 3 ||
            count($syntheticRecords) < 1 ||
            count($syntheticRecords) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Required quoted channels and synthetic rows absent.');
        }
        $keys = array_keys($quotedChannels);
        sort($keys, SORT_STRING);
        if (array_diff($keys, self::CHANNELS) !== []) {
            throw new InvalidArgumentException('Unrecognized V5 channel.');
        }

        $channelSpend = [];
        $counts = [];
        $seen = [];
        $canonical = [];
        foreach ($syntheticRecords as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Synthetic record must be a map.');
            }
            $fields = array_keys($record);
            sort($fields, SORT_STRING);
            if ($fields !== ['channel','period','report_id','source','spend_cents']) {
                throw new InvalidArgumentException('Unexpected synthetic finance record fields.');
            }
            $channel = $record['channel'];
            $period = $record['period'];
            $id = $record['report_id'];
            $spend = $record['spend_cents'];
            if (!is_string($channel) || !array_key_exists($channel,$quotedChannels) ||
                !in_array($channel,self::CHANNELS,true) ||
                $period !== $expectedPeriod ||
                $record['source'] !== 'synthetic_fixture' ||
                !is_string($id) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D',$id) ||
                !is_int($spend) || $spend < 0 || $spend > self::MAX_CENTS) {
                throw new InvalidArgumentException('Off-period or unexpected synthetic report.');
            }
            $idKey = $channel."\0".$id;
            if (isset($seen[$idKey])) throw new InvalidArgumentException('Duplicate report ID.');
            $seen[$idKey] = true;
            $current = $channelSpend[$channel] ?? 0;
            if ($current > self::MAX_CENTS - $spend) {
                throw new InvalidArgumentException('Channel spend exceeds cap.');
            }
            $channelSpend[$channel] = $current + $spend;
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
            $canonical[] = [
                'channel'=>$channel,
                'period'=>$period,
                'report_id'=>$id,
                'source'=>'synthetic_fixture',
                'spend_cents'=>$spend,
            ];
        }
        $actualKeys=array_keys($channelSpend);
        sort($actualKeys,SORT_STRING);
        if ($actualKeys !== $keys) {
            // All contracted channels require at least one explicit report/zero.
            throw new InvalidArgumentException('Missing synthetic report for quoted channel.');
        }
        ksort($channelSpend,SORT_STRING);
        usort($canonical, static fn(array $a,array $b):int =>
            [$a['channel'],$a['report_id']] <=> [$b['channel'],$b['report_id']]);
        $spendFingerprint = hash('sha256',json_encode(
            $canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ));

        try {
            $this->db->beginTransaction();
            $this->db->prepare("SELECT set_config('app.tenant_id', :tenant, true)")
                ->execute(['tenant'=>$expectedTenant]);
            $nonceClaim = function(string $tenant,string $key,string $nonce,int $expires):bool {
                $query=$this->db->prepare(
                    'INSERT INTO perf_fin_lab.signed_quote_nonces
                     (tenant_id,nonce,key_id,expires_at)
                     VALUES (:tenant,:nonce,:key,:expires)
                     ON CONFLICT (tenant_id,nonce) DO NOTHING RETURNING nonce'
                );
                $query->execute([
                    'tenant'=>$tenant,
                    'nonce'=>$nonce,
                    'key'=>$key,
                    'expires'=>$expires,
                ]);
                return $query->fetchColumn() !== false;
            };
            $verified=PortalTriChannelV5CandidateGuard::verify(
                $signedPortalQuote,$expectedTenant,$expectedPeriod,$catalogSha256,
                $approvedMinima,$channelSpend,$now,$keyResolver,$nonceClaim
            );
            if (($verified['quote_claim_hmac_verified'] ?? null) !== true ||
                ($verified['billing_period_bound'] ?? null) !== true ||
                ($verified['portal_catalog_active'] ?? null) !== false ||
                ($verified['portal_iam_authenticated'] ?? null) !== false ||
                ($verified['provider_spend_verified'] ?? null) !== false ||
                ($verified['finance_approved'] ?? null) !== false ||
                ($verified['billable'] ?? null) !== false ||
                ($verified['asaas_write_enabled'] ?? null) !== false ||
                ($verified['ads_write_enabled'] ?? null) !== false) {
                throw new InvalidArgumentException('Candidate V5 unexpectedly grants authority.');
            }
            $claimSha=$verified['quote_claim_sha256'];
            $closeId=hash('sha256','perf-trichannel-v5-candidate-close:'.$expectedTenant.':'.$expectedPeriod.':'.$claimSha);
            $stmt=$this->db->prepare(
                'INSERT INTO perf_fin_lab.reconciliation_drafts
                (tenant_id,close_id,period,quote_claim_sha256,spend_claim_sha256,
                 planned_media_cents,unverified_actual_media_cents,advance_fee_cents,
                 unverified_earned_fee_cents,adjustment_cents)
                 VALUES (:tenant,:close,:period,:quote,:spend,:planned,:actual,:advance,:earned,:adjustment)
                 RETURNING close_id'
            );
            $stmt->execute([
                'tenant'=>$expectedTenant,
                'close'=>$closeId,
                'period'=>$expectedPeriod,
                'quote'=>$claimSha,
                'spend'=>$spendFingerprint,
                'planned'=>$verified['planned_media_cents'],
                'actual'=>$verified['unverified_actual_media_cents'],
                'advance'=>$verified['estimated_advance_fee_cents'],
                'earned'=>$verified['unverified_earned_fee_cents'],
                'adjustment'=>$verified['review_only_adjustment_cents'],
            ]);
            if ($stmt->fetchColumn() !== $closeId) {
                throw new RuntimeException('V5 close draft missing.');
            }
            $this->db->commit();
            return [
                'created'=>true,
                'close_id'=>$closeId,
                'period'=>$expectedPeriod,
                'state'=>'unverified_v5_candidate_pg18_draft',
                'signed_verification'=>$verified,
                'synthetic_spend_fingerprint_sha256_not_proof'=>$spendFingerprint,
                'source_authenticated'=>false,
                'provider_spend_verified'=>false,
                'finance_approved'=>false,
                'billable'=>false,
                'asaas_write_enabled'=>false,
                'ads_write_enabled'=>false,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // No SQL, keys, tenant or synthetic record details in public error.
            throw new RuntimeException('V5 candidate atomic finance intake rejected or unavailable.');
        }
    }
}
