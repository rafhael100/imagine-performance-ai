<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-79. An intentionally one-way NO-GO auditor.
 *
 * A checked-in JSON file, this script or a successful CI run is NOT an
 * authorization root. This guard only reports known blockers; it has no
 * capability to approve any deploy or provider operation.
 */
final class SyntheticReleaseNoGoAuditor
{
    public function assess(mixed $policy,mixed $bootstrapPhp):array
    {
        $invalid=[
            'schema_version'=>'perf.release.audit.synthetic.v1',
            'decision'=>'INVALID_EVIDENCE_NO_GO',
            'known_blockers'=>[],
            'review_required'=>true,
            'eligible_to_merge_main'=>false,
            'eligible_to_deploy'=>false,
            'eligible_to_dispatch'=>false,
            'eligible_to_bill'=>false,
            'ready_for_production'=>false,
        ];
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F79_RELEASE_AUDIT_LAB')!=='1') {
            $invalid['decision']='LAB_DISABLED_NO_GO';
            return $invalid;
        }
        if(!is_array($policy) || !is_string($bootstrapPhp)
           || strlen($bootstrapPhp)>50000
           || ($policy['schema_version']??null)!=='perf.release.no_go.lab.v1'
           || ($policy['mode']??null)!=='synthetic_ci_only'
           || ($policy['release_state']??null)!=='blocked') {
            return $invalid;
        }
        foreach(['operational_activation_allowed','external_provider_dispatch_allowed',
                 'billing_allowed','automatic_merge_allowed',
                 'real_portal_or_crm_authority_connected'] as $flag) {
            if (!array_key_exists($flag,$policy) || $policy[$flag]!==false) {
                return $invalid;
            }
        }
        $gates=$policy['blocking_gates']??null;
        if(!is_array($gates)
           || ($gates['P0']??null)!=='open'
           || ($gates['HT-R01']??null)!=='open'
           || ($gates['SEC-R03/T3']??null)!=='open'
           || ($gates['AppSec-DBA']??null)!=='not_approved'
           || ($gates['restore_epoch_antirollback']??null)!=='unresolved'
           || ($gates['provider_idempotency']??null)!=='unproven'
           || ($gates['human_approval']??null)!=='mock_only') {
            return $invalid;
        }
        // This is a narrow assertion about the present test skeleton,
        // not an assurance about all possible PHP code or infrastructure.
        if (!str_contains($bootstrapPhp,'->withRouting(commands:')
            || preg_match('/\b(?:api|web|health|then)\s*:/',$bootstrapPhp)===1
            || !str_contains($bootstrapPhp,'->create()')) {
            return $invalid;
        }
        return [
            'schema_version'=>'perf.release.audit.synthetic.v1',
            'decision'=>'BLOCKED_AS_EXPECTED_NO_GO',
            'known_blockers'=>[
                'P0','HT-R01','SEC-R03/T3','AppSec-DBA',
                'restore_epoch_antirollback','provider_idempotency',
                'human_approval','real_portal_authority','external_ads_oauth',
                'staging_and_disaster_recovery',
            ],
            'review_required'=>true,
            'eligible_to_merge_main'=>false,
            'eligible_to_deploy'=>false,
            'eligible_to_dispatch'=>false,
            'eligible_to_bill'=>false,
            'ready_for_production'=>false,
        ];
    }
}
