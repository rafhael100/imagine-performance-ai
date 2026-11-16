<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use Imagine\Performance\Commercial\MonthlyManagementFeeFloorV7Candidate;
use InvalidArgumentException;
/** Read/reconciliation contracts only. No real provider is implemented by this candidate. */
final class AdministrativeOperationalReview
{
 public static function unavailable(string $source,string $reason):array
 {return ['source'=>$source,'status'=>'unavailable','reason'=>$reason,'metrics'=>null,'official_evidence'=>null,'synthetic'=>false];}
 public static function financial(string $period,array $channels,string $coverage,string $evidenceStatus,int $revision,?int $reviewedRevision):array
 {
  if(!in_array($coverage,['full_month','partial_month','channel_change'],true)||!in_array($evidenceStatus,['missing','synthetic','official_unverified','official_verified'],true)||$revision<1)throw new InvalidArgumentException('Invalid review scope');
  $base=['version'=>'imagine.performance.finance.review.v1','period'=>$period,'revision'=>$revision,'billable'=>false,'charge_enabled'=>false,'ads_write_enabled'=>false,'media_separate'=>true,'setup_cents_per_channel'=>49000];
  if($coverage!=='full_month')return $base+['status'=>'contract_policy_required','management_fee_cents'=>null,'reason'=>'Partial period and channel changes require a versioned accepted policy'];
  if($evidenceStatus!=='official_verified')return $base+['status'=>'official_statement_required','management_fee_cents'=>null];
  $preview=MonthlyManagementFeeFloorV7Candidate::preview($period,$channels);
  return $base+['status'=>$reviewedRevision===$revision?'reviewed_no_charge':'independent_revision_review_required','management_fee_cents'=>$preview['unverified_earned_fee_cents'],'calculation'=>$preview];
 }
 public static function sessionAllowed(array $user,array $grant,string $company,int $sessionEpoch,int $now):bool
 {return ($user['active']??false)===true&&($user['session_epoch']??null)===$sessionEpoch&&($grant['company_id']??null)===$company&&($grant['revoked_at']??null)===null&&($grant['expires_at']??0)>$now&&in_array($grant['role']??null,['superadmin','operations','analyst','ai_supervisor','finance','auditor'],true);}
}
