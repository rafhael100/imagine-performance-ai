<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/**
 * F150 release evidence contract, NOT a production IAM verifier.
 * A self-asserted CI manifest can never grant production approval.
 * Independent, out-of-band security and business decisions are REQUIRED.
 */
final class F150ReleaseEvidenceGate
{
    private const REQUIRED = [
        'portal_iam_tenant_mfa',
        'portal_s2s_workload',
        'appsec_independent',
        'dba_rls_independent',
        'external_antirollback',
        'offline_executor_isolation',
        'oauth_google_meta_consent',
        'human_ads_budget_approval',
        'billing_and_idempotency',
        'vps_uat_backup_restore',
        'change_control_main',
        'incident_reconciliation',
    ];

    /** @return array<string,mixed> */
    public function evaluate(array $manifest):array
    {
        if (($manifest['schema_version']??null)!=='perf.release-evidence.synthetic.v1'
            ||($manifest['environment']??null)!=='testing'
            ||($manifest['requested_action']??null)!=='read_only_report'
            ||!array_key_exists('gates',$manifest)
            ||!is_array($manifest['gates'])
            ||count($manifest['gates'])!==count(self::REQUIRED)) {
            throw new InvalidArgumentException('F150 invalid release evidence contract');
        }
        $seen=[];
        $items=[];
        foreach ($manifest['gates'] as $row) {
            if (!is_array($row)
                ||array_keys($row)!==['id','state','evidence']
                ||!is_string($row['id'])
                ||!in_array($row['id'],self::REQUIRED,true)
                ||isset($seen[$row['id']])
                ||!in_array($row['state'],['not_started','pending_external','blocked','lab_passed'],true)
                ||!is_string($row['evidence'])
                ||strlen($row['evidence'])>180
                ||str_contains($row['evidence'],"\n")) {
                throw new InvalidArgumentException('F150 untrusted or duplicate gate');
            }
            $seen[$row['id']]=true;
            $items[$row['id']]=[
                'status'=>$row['state'],
                'evidence'=>$row['evidence'],
                'production_approved'=>false,
            ];
        }
        foreach (self::REQUIRED as $required) {
            if (!isset($seen[$required]))throw new InvalidArgumentException('F150 missing required gate');
        }
        $ordered=[];
        foreach(self::REQUIRED as $id)$ordered[]=['id'=>$id,...$items[$id]];
        return [
            'schema_version'=>'perf.release-evaluation.synthetic.v1',
            'release'=>'NO_GO',
            'can_merge_main'=>false,
            'can_deploy'=>false,
            'can_enable_ads'=>false,
            'can_bill'=>false,
            'external_evidence_verified'=>false,
            'independent_review_completed'=>false,
            'source'=>'self_reported_ci_laboratory_only',
            'gate_count'=>count(self::REQUIRED),
            'gates'=>$ordered,
            'reason'=>'An isolated CI manifest cannot authorize a production release',
        ];
    }
}
