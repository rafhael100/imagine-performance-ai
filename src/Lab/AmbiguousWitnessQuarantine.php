<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

/**
 * F127 laboratory-only state machine. No SQL, external authority, HTTP, Ads or billing.
 * In production, a durable independent witness and a trusted recovery epoch are required.
 */
final class AmbiguousWitnessQuarantine
{
    private const PENDING = 'pending';
    private const QUARANTINED = 'quarantined';
    private const CONFIRMED = 'confirmed';
    private const REJECTED = 'rejected';
    /** @var array<string,array{fingerprint:string, state:string, generation:int}> */
    private array $entries = [];
    private bool $quarantined = false;
    private int $generation = 1;

    public function begin(string $operationId, string $fingerprint): bool
    {
        if ($this->quarantined || !preg_match('/^[a-zA-Z0-9_-]{8,128}$/D', $operationId)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return false;
        }
        // Repeated operation IDs (including a completed operation) never create a new admission.
        if (isset($this->entries[$operationId])) {
            return false;
        }
        $this->entries[$operationId] = [
            'fingerprint' => $fingerprint,
            'state' => self::PENDING,
            'generation' => $this->generation,
        ];
        return true;
    }

    public function witnessResult(string $operationId, string $fingerprint, string $result): bool
    {
        $entry = $this->entries[$operationId] ?? null;
        if ($entry === null || !hash_equals($entry['fingerprint'], $fingerprint)
            || $entry['state'] !== self::PENDING || $entry['generation'] !== $this->generation) {
            $this->quarantined = true; // Unknown or contradictory evidence is not positive evidence.
            return false;
        }
        if ($result === 'committed') {
            $this->entries[$operationId]['state'] = self::CONFIRMED;
            return true;
        }
        if ($result === 'rejected') {
            $this->entries[$operationId]['state'] = self::REJECTED;
            return false;
        }
        // Timeout, disconnect, unknown outcome, unavailable witness: never retry blindly.
        $this->entries[$operationId]['state'] = self::QUARANTINED;
        $this->quarantined = true;
        return false;
    }

    /** Observability only; never grants permission or reopens the circuit. */
    public function snapshot(): array
    {
        return ['generation' => $this->generation, 'quarantined' => $this->quarantined,
            'entries' => $this->entries];
    }

    /**
     * Recovery is deliberately impossible from within this demo object.
     * A separate independently reviewed authority must reconcile durable receipts,
     * fence older epochs, and construct a fresh instance after verified recovery.
     */
    public function requestLocalRecovery(): bool
    {
        return false;
    }
}
