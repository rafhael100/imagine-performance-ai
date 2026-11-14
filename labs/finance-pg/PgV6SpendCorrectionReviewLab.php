<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\PortalV6AgreementSignedGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Append-only finance CORRECTION CANDIDATE, CI/testing only. Does not modify
 * the signed V6 base draft or claim a second nonce. No official provider
 * spending proof, invoice, refund, credit, approval or network interaction.
 */
final class PgV6SpendCorrectionReviewLab
{
    private const CHANNELS=['chatgpt_ads','google_ads','meta_ads'];
    private const MAX_ROWS=200;
    private const MAX_CHANNEL_SPEND_CENTS=100000000;

    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signedV6 trusted Portal-issued candidate
     * @param array<string,int> $ownerMinima trusted offline commercial policy
     * @param list<array<string,mixed>> $revisedSyntheticReports ORIGINAL report rows, never client hash
     * @param callable(string,string):?string $resolveKey CI-only HMAC resolver
     * @return array<string,mixed>
     */
    public function propose(
        array $signedV6,
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $catalogSha256,
        array $ownerMinima,
        array $revisedSyntheticReports,
        int $expectedRevision,
        string $noteCode,
        int $now,
        callable $resolveKey
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PG_V6_ROLE_LAB')!=='1' ||
           getenv('PERF_PG_V6_CORRECTION_LAB')!=='1' ||
           getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1' ||
           $this->db->inTransaction() ||
           !in_array($this->db->query('SELECT session_user')->fetchColumn(),
               ['perf_v6_tenant_alpha','perf_v6_tenant_beta'],true) ||
           !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$expectedTenant) ||
           !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$expectedAgreement) ||
           !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$expectedPeriod) ||
           ($signedV6['tenant_id']??null)!==$expectedTenant ||
           ($signedV6['agreement_id']??null)!==$expectedAgreement ||
           ($signedV6['billing_period']??null)!==$expectedPeriod ||
           $expectedRevision<1 || $expectedRevision>100 ||
           !in_array($noteCode,['late_spend_fixture','account_allocation_fixture'],true)){
            throw new InvalidArgumentException('Synthetic correction review disabled or invalid scope.');
        }
        $planned=$signedV6['quote']['media_by_channel_cents']??null;
        if(!is_array($planned)||$planned===[]||
           count($revisedSyntheticReports)<1||
           count($revisedSyntheticReports)>self::MAX_ROWS) {
            throw new InvalidArgumentException('Original synthetic report rows required.');
        }
        $reportKeys=[];
        $sums=[];
        $canonical=[];
        foreach($revisedSyntheticReports as $report){
            if(!is_array($report))throw new InvalidArgumentException('Bad report.');
            $fields=array_keys($report);
            sort($fields,SORT_STRING);
            if($fields!==['channel','period','report_id','source','spend_cents']){
                throw new InvalidArgumentException('Unknown correction report field.');
            }
            $channel=$report['channel'];
            $id=$report['report_id'];
            $cents=$report['spend_cents'];
            if(!is_string($channel) ||
               !in_array($channel,self::CHANNELS,true) ||
               !array_key_exists($channel,$planned) ||
               $report['period']!==$expectedPeriod ||
               $report['source']!=='synthetic_fixture' ||
               !is_string($id) ||
               !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D',$id) ||
               !is_int($cents) || $cents<0 ||
               $cents>self::MAX_CHANNEL_SPEND_CENTS){
                throw new InvalidArgumentException('Invalid synthetic correction evidence.');
            }
            $dedupe=$channel."\0".$id;
            if(isset($reportKeys[$dedupe])){
                throw new InvalidArgumentException('Duplicate correction evidence ID.');
            }
            $reportKeys[$dedupe]=true;
            $current=$sums[$channel]??0;
            if($current>self::MAX_CHANNEL_SPEND_CENTS-$cents) {
                throw new InvalidArgumentException('Synthetic channel spend too large.');
            }
            $sums[$channel]=$current+$cents;
            $canonical[]=$report;
        }
        $expectedChannels=array_keys($planned);
        $actualChannels=array_keys($sums);
        sort($expectedChannels,SORT_STRING);
        sort($actualChannels,SORT_STRING);
        if($expectedChannels!==$actualChannels) {
            throw new InvalidArgumentException('Missing explicit channel report, including zero.');
        }
        ksort($sums,SORT_STRING);
        usort($canonical,static fn(array $a,array $b):int=>
            [$a['channel'],$a['report_id']] <=> [$b['channel'],$b['report_id']]
        );
        $spendSha=hash('sha256',json_encode(
            $canonical,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
        ));

        try {
            $this->db->beginTransaction();
            $mapped=$this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if(!is_string($mapped)||!hash_equals($expectedTenant,$mapped)){
                throw new InvalidArgumentException('Tenant does not match authenticated DB role.');
            }
            $knownNonce=function(string $t,string $key,string $nonce,int $expires):bool {
                $stmt=$this->db->prepare(
                    'SELECT expires_at FROM perf_fin_role_lab.signed_quote_nonces
                      WHERE tenant_id=:t AND key_id=:k AND nonce=:n'
                );
                $stmt->execute(['t'=>$t,'k'=>$key,'n'=>$nonce]);
                $saved=$stmt->fetchColumn();
                return $saved!==false&&(int)$saved===$expires;
            };
            $verified=PortalV6AgreementSignedGuard::verify(
                $signedV6,$expectedTenant,$expectedAgreement,$expectedPeriod,
                $catalogSha256,$ownerMinima,$sums,$now,$resolveKey,$knownNonce
            );
            foreach(['portal_catalog_active','portal_iam_authenticated',
                     'provider_spend_verified','finance_approved','billable',
                     'asaas_write_enabled','ads_write_enabled'] as $flag) {
                if(($verified[$flag]??null)!==false) {
                    throw new InvalidArgumentException('Correction cannot inherit production privileges.');
                }
            }
            if(($verified['quote_claim_hmac_verified']??null)!==true||
               ($verified['agreement_id_signed_in_envelope']??null)!==true) {
                throw new InvalidArgumentException('V6 agreement signature missing.');
            }
            $baseQuery=$this->db->prepare(
                'SELECT close_id,quote_claim_sha256,spend_claim_sha256,
                        unverified_earned_fee_cents,period,agreement_id,
                        source_verified,finance_approved,billable
                   FROM perf_fin_role_lab.agreement_close_drafts
                  WHERE tenant_id=:tenant AND agreement_id=:agreement AND period=:period'
            );
            $baseQuery->execute([
                'tenant'=>$expectedTenant,
                'agreement'=>$expectedAgreement,
                'period'=>$expectedPeriod
            ]);
            $base=$baseQuery->fetch(PDO::FETCH_ASSOC);
            if(!is_array($base) ||
               !hash_equals($verified['quote_claim_sha256'],(string)$base['quote_claim_sha256']) ||
               $base['period']!==$expectedPeriod ||
               $base['agreement_id']!==$expectedAgreement ||
               !self::dbFalse($base['billable']) ||
               !self::dbFalse($base['finance_approved']) ||
               !self::dbFalse($base['source_verified'])) {
                throw new InvalidArgumentException('Verified immutable base finance draft unavailable.');
            }
            $previous=$this->db->prepare(
                'SELECT revision_index,event_sha256,new_spend_claim_sha256,
                        unverified_new_earned_fee_cents
                   FROM perf_fin_role_lab.spend_correction_events
                  WHERE tenant_id=:tenant AND agreement_id=:agreement AND period=:period
                  ORDER BY revision_index DESC LIMIT 1'
            );
            $previous->execute([
                'tenant'=>$expectedTenant,
                'agreement'=>$expectedAgreement,
                'period'=>$expectedPeriod
            ]);
            $prior=$previous->fetch(PDO::FETCH_ASSOC);
            $lastRevision=is_array($prior)?(int)$prior['revision_index']:0;
            if($expectedRevision!==$lastRevision+1) {
                throw new InvalidArgumentException('Correction revision number must be next exactly.');
            }
            $oldSpendSha=is_array($prior)?
                (string)$prior['new_spend_claim_sha256']:(string)$base['spend_claim_sha256'];
            if(hash_equals($oldSpendSha,$spendSha)) {
                throw new InvalidArgumentException('No change in original synthetic evidence.');
            }
            $previousHash=is_array($prior)?
                (string)$prior['event_sha256']:
                hash('sha256','perf-v6-initial-draft-ci:'.
                    $base['close_id'].':'.$base['spend_claim_sha256']);
            $oldFee=is_array($prior)?
                (int)$prior['unverified_new_earned_fee_cents']:
                (int)$base['unverified_earned_fee_cents'];
            $newEarned=$verified['unverified_earned_fee_cents'];
            $delta=$newEarned-$oldFee;
            $eventFields=[
                'tenant_id'=>$expectedTenant,'agreement_id'=>$expectedAgreement,
                'period'=>$expectedPeriod,'revision_index'=>$expectedRevision,
                'base_close_id'=>$base['close_id'],
                'quote_claim_sha256'=>$verified['quote_claim_sha256'],
                'previous_event_sha256'=>$previousHash,
                'new_spend_claim_sha256'=>$spendSha,
                'unverified_new_spend_cents'=>$verified['unverified_actual_media_cents'],
                'unverified_new_earned_fee_cents'=>$newEarned,
                'delta_earned_fee_cents'=>$delta,
                'note_code'=>$noteCode
            ];
            $eventSha=hash('sha256','perf:v6:correction:ci:v1:'.
                json_encode($eventFields,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)
            );
            $sql=$this->db->prepare(
                'INSERT INTO perf_fin_role_lab.spend_correction_events
                (tenant_id,agreement_id,period,revision_index,base_close_id,
                 quote_claim_sha256,previous_event_sha256,event_sha256,
                 new_spend_claim_sha256,unverified_new_spend_cents,
                 unverified_new_earned_fee_cents,delta_earned_fee_cents,note_code)
                VALUES (:tenant,:agreement,:period,:revision,:base,
                        :quote,:previous,:event,:spend,:media,:fee,:delta,:note)
                RETURNING event_sha256'
            );
            $sql->execute([
                'tenant'=>$expectedTenant,'agreement'=>$expectedAgreement,
                'period'=>$expectedPeriod,'revision'=>$expectedRevision,
                'base'=>$base['close_id'],'quote'=>$verified['quote_claim_sha256'],
                'previous'=>$previousHash,'event'=>$eventSha,
                'spend'=>$spendSha,
                'media'=>$verified['unverified_actual_media_cents'],
                'fee'=>$newEarned,'delta'=>$delta,'note'=>$noteCode
            ]);
            if($sql->fetchColumn()!==$eventSha) {
                throw new RuntimeException('Correction event did not persist.');
            }
            $this->db->commit();
            return $eventFields+[
                'event_sha256'=>$eventSha,
                'state'=>'synthetic_spend_correction_review_only',
                'source_verified'=>false,'finance_approved'=>false,'billable'=>false,
                'asaas_write_enabled'=>false,'ads_write_enabled'=>false,
                'prior_monthly_invoice_modified'=>false,
                'automated_refund_or_credit_enabled'=>false
            ];
        } catch(Throwable) {
            if($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Unverified correction preview rejected or unavailable.');
        }
    }

    private static function dbFalse(mixed $v):bool {
        return in_array($v,[false,0,'0','f'],true);
    }
}
