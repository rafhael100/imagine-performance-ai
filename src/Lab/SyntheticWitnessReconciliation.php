<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

/**
 * F128 synthetic reconciliation evaluator. Never grants permission or clears F127 quarantine.
 * Evidence fields are caller-supplied test fixtures: not cryptographic attestations.
 */
final class SyntheticWitnessReconciliation
{
    /**
     * Compare independent receipts after an ambiguous operation.
     * No dispatch, nonce reuse, billing or re-admission is ever returned.
     *
     * @param array<string,mixed> $primary
     * @param array<string,mixed> $witness
     * @return array{classification:string,quarantine:bool,reason:string}
     */
    public function inspect(array $primary, array $witness, int $trustedEpochFloor): array
    {
        $deny = static fn (string $why): array => [
            'classification' => 'ambiguous', 'quarantine' => true, 'reason' => $why
        ];
        if ($trustedEpochFloor < 1) {
            return $deny('missing_epoch_floor');
        }
        foreach ([$primary, $witness] as $evidence) {
            if (!isset($evidence['operation_id'], $evidence['fingerprint'],
                $evidence['epoch'], $evidence['outcome'], $evidence['authority'])) {
                return $deny('missing_fields');
            }
            if (!is_string($evidence['operation_id'])
                || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $evidence['operation_id'])
                || !is_string($evidence['fingerprint'])
                || !preg_match('/^[a-f0-9]{64}$/D', $evidence['fingerprint'])
                || !is_int($evidence['epoch']) || $evidence['epoch'] < $trustedEpochFloor
                || !is_string($evidence['authority'])) {
                return $deny('invalid_or_stale_evidence');
            }
            if (!in_array($evidence['outcome'], ['committed', 'rejected', 'absent'], true)) {
                return $deny('unknown_outcome');
            }
        }
        if ($primary['authority'] !== 'primary' || $witness['authority'] !== 'witness') {
            return $deny('wrong_authority_roles');
        }
        if (!hash_equals($primary['operation_id'], $witness['operation_id'])
            || !hash_equals($primary['fingerprint'], $witness['fingerprint'])) {
            return $deny('different_operation');
        }
        if ($primary['epoch'] !== $witness['epoch']) {
            return $deny('epoch_disagreement');
        }
        if ($primary['outcome'] !== $witness['outcome']) {
            return $deny('outcome_disagreement');
        }
        if ($primary['outcome'] === 'absent') {
            return $deny('absence_is_not_proof_of_nonexecution');
        }
        return [
            'classification' => $primary['outcome'] === 'committed'
                ? 'consistent_committed' : 'consistent_rejected',
            'quarantine' => true,
            'reason' => 'matching_fixture_requires_independent_review',
        ];
    }
}
