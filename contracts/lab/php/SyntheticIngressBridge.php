<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use PDO;
use PDOException;

/**
 * LAB ONLY: combines the synthetic HMAC verifier and PostgreSQL claim into a
 * single TEST invocation. Never register as HTTP endpoint, Laravel provider,
 * worker handler or a production authentication adapter.
 *
 * All expected grant facts and the secret are supplied by synthetic test code.
 * The SQL claim function itself does not authenticate a workload.
 */
final class SyntheticIngressBridge
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function accept(
        mixed $envelope,
        string $ephemeralTestKey,
        string $expectedOrganization,
        string $expectedTenant,
        string $expectedService,
        string $expectedFixtureDigest,
        int $expectedGrantRevision,
        int $syntheticNow,
        bool $mockGrantActive = true
    ): string {
        // Fresh verifier each invocation forces the lab to prove DB durability
        // rather than succeeding due to PHP's in-memory replay cache alone.
        $decision = (new SyntheticS2SProtocol())->verifyForLab(
            $envelope,
            $ephemeralTestKey,
            $expectedOrganization,
            $expectedTenant,
            $expectedService,
            $expectedFixtureDigest,
            $expectedGrantRevision,
            $syntheticNow,
            $mockGrantActive
        );
        if ($decision !== 'accepted_synthetic_only') {
            return $decision;
        }

        try {
            $statement = $this->database->prepare(
                'SELECT perf_s2s_lab.mock_claim(?, ?, ?, ?, ?, ?, ?, ?) AS result'
            );
            $statement->execute([
                $envelope['organization_ref'],
                $envelope['tenant_ref'],
                $envelope['service_ref'],
                $envelope['grant_revision'],
                $envelope['producer'],
                $envelope['request_id'],
                $envelope['nonce'],
                $envelope['payload_sha256'],
            ]);
            $result = $statement->fetchColumn();
            return in_array($result, [
                'accepted_synthetic_only',
                'mock_replay_denied',
                'not_found',
                'mock_grant_revoked',
                'mock_grant_revision_stale',
                'invalid_lab_input',
            ], true) ? $result : 'mock_storage_denied';
        } catch (PDOException) {
            // No fallback to in-memory accept if the database is unavailable.
            return 'mock_storage_unavailable';
        }
    }
}
