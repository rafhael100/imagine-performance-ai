<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-74. Pure, untrusted recovery evidence diagnostic.
 * Never an authorization gate: caller-supplied witness is not a trust root.
 */
final class SyntheticRecoveryAdmissionPolicy
{
    private const VERSION = 'perf.recovery.admission.synthetic.v1';

    /** @param mixed $witness Caller-provided, NOT authenticated. */
    public function inspect(mixed $witness, mixed $databaseEpoch, mixed $envelopeEpoch): array
    {
        $status = 'evidence_invalid';
        if (is_array($witness)
            && array_keys($witness) === ['schema_version', 'authority_id', 'minimum_epoch', 'nonce']
            && ($witness['schema_version'] ?? null) === 'perf.recovery.witness.untrusted.v1'
            && is_string($witness['authority_id'])
            && preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D', $witness['authority_id']) === 1
            && is_string($witness['nonce'])
            && preg_match('/^[a-f0-9]{32}$/D', $witness['nonce']) === 1
            && $this->epoch($witness['minimum_epoch'])
            && $this->epoch($databaseEpoch)
            && $this->epoch($envelopeEpoch)) {
            $min = $witness['minimum_epoch'];
            $status = ($databaseEpoch < $min || $envelopeEpoch < $min)
                ? 'rollback_suspected'
                : ($databaseEpoch !== $envelopeEpoch
                    ? 'epoch_divergence'
                    : 'untrusted_witness_cannot_authorize');
        }
        return [
            'schema_version' => self::VERSION,
            'status' => $status,
            'witness_is_authenticated' => false,
            'monotonicity_proven' => false,
            'read_preview_eligible' => false,
            'authorization_eligible' => false,
            'ready_for_production' => false,
            'dispatch_enabled' => false,
            'billing_enabled' => false,
        ];
    }

    private function epoch(mixed $epoch): bool
    {
        return is_int($epoch) && $epoch >= 1 && $epoch <= 1000000;
    }
}
