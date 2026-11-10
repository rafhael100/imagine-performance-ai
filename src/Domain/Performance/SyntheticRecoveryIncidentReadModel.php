<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-77: read-only incident triage of untrusted synthetic evidence.
 * Never grants permission and cannot send messages, Ads, billing or writes.
 */
final class SyntheticRecoveryIncidentReadModel
{
    private const WITNESS = 'perf.recovery.witness.verification.synthetic.v1';
    private const RECONCILIATION = 'perf.provider.reconcile.synthetic.v1';

    public function project(mixed $witness, mixed $reconciliation): array
    {
        $status = 'invalid_evidence_quarantine';
        $reason = 'untrusted_or_malformed_evidence';
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F77_INCIDENT_LAB')!=='1') {
            $status='lab_disabled';
            $reason='lab_disabled';
        } elseif ($this->validWitness($witness)
            && $this->validReconciliation($reconciliation)) {
            if ($witness['status']!=='signed_fixture_only_no_external_trust') {
                $status='security_quarantine';
                $reason='recovery_proof_not_current_or_invalid';
            } elseif ($reconciliation['status']==='conflict_detected'
                || $reconciliation['status']==='invalid_fixture'
                || $reconciliation['status']==='lab_disabled') {
                $status='security_quarantine';
                $reason='reconciliation_conflict_or_invalid';
            } elseif ($reconciliation['status']==='mock_rejected'
                || $reconciliation['status']==='awaiting_mock_receipts') {
                $status='manual_reconciliation_required';
                $reason='mock_receipts_missing_or_rejected';
            } else {
                $status='manual_governance_required';
                $reason='no_authenticated_external_root_or_provider';
            }
        }
        return [
            'schema_version'=>'perf.recovery.incident.synthetic.v1',
            'status'=>$status,
            'reason'=>$reason,
            'simulation_only'=>true,
            'human_review_required'=>true,
            'external_root_trusted'=>false,
            'provider_evidence_trusted'=>false,
            'authorization_eligible'=>false,
            'read_preview_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
        ];
    }

    private function validWitness(mixed $w): bool
    {
        if (!is_array($w) || array_keys($w)!==[
            'schema_version','status','signature_valid','external_root_trusted',
            'monotonicity_proven','authorization_eligible','read_preview_eligible',
            'ready_for_production','dispatch_enabled','billing_enabled'
        ] || $w['schema_version']!==self::WITNESS
          || !is_string($w['status'])
          || !in_array($w['status'],[
            'signed_fixture_only_no_external_trust','lab_disabled','invalid_expected_context',
            'invalid_envelope','invalid_payload','invalid_signature','invalid_signed_claims',
            'wrong_context','not_current','rollback_suspected','epoch_divergence',
            'verification_unavailable'
          ],true)) return false;
        foreach(['external_root_trusted','monotonicity_proven','authorization_eligible',
                 'read_preview_eligible','ready_for_production','dispatch_enabled',
                 'billing_enabled'] as $flag) {
            if ($w[$flag]!==false) return false;
        }
        return is_bool($w['signature_valid'])
            && $w['signature_valid']===($w['status']==='signed_fixture_only_no_external_trust');
    }

    private function validReconciliation(mixed $r): bool
    {
        if (!is_array($r) || array_keys($r)!==[
            'schema_version','status','counts','provider_was_contacted',
            'authenticated_provider_evidence','recovery_monotonicity_proven',
            'authorization_eligible','read_preview_eligible','ready_for_production',
            'dispatch_enabled','billing_enabled'
        ] || $r['schema_version']!==self::RECONCILIATION
          || !is_string($r['status'])
          || !in_array($r['status'],[
              'matched_mock_only','empty_mock_only','awaiting_mock_receipts','mock_rejected',
              'conflict_detected','invalid_fixture','lab_disabled'
          ],true)
          || !is_array($r['counts'])
          || array_keys($r['counts'])!==['intents','receipts','pending']) return false;
        foreach(['provider_was_contacted','authenticated_provider_evidence',
                 'recovery_monotonicity_proven','authorization_eligible','read_preview_eligible',
                 'ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
            if ($r[$flag]!==false) return false;
        }
        foreach(['intents','receipts','pending'] as $key) {
            if (!is_int($r['counts'][$key]) || $r['counts'][$key]<0 || $r['counts'][$key]>100) {
                return false;
            }
        }
        return $r['counts']['pending']<=$r['counts']['intents']
            && $r['counts']['receipts']<=$r['counts']['intents'];
    }
}
