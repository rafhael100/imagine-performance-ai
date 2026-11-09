<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use JsonException;
use PDO;
use Throwable;

/**
 * F1-31 laboratory ONLY. Cross-process replay receipt in independent PG.
 * This cannot grant actual permissions or execute Google/Meta/CRM commands.
 *
 * The SQL function accepts a nonce passed by the caller; consequently it is
 * vulnerable to preconsumption/DoS when a nonce is known. Real operation needs
 * authenticated workload-to-workload calls and bound proof at the authority.
 */
final class SyntheticPortalEd25519DurableReplayGate
{
    public function __construct(
        private readonly string $trustedPublicKeyHex,
        private readonly PDO $nonceAuthority
    ) {
    }

    /**
     * @param callable():?SyntheticCurrentAuthority $resolveCurrentPortalAuthority
     */
    public function verifyOnce(
        string $packet,
        callable $resolveCurrentPortalAuthority,
        int $now
    ): string {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F29_SIGNED_PORTAL_LAB') !== '1'
            || getenv('PERF_F31_ED25519_REPLAY_LAB') !== '1'
            || !extension_loaded('sodium')
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'portal_perf_auth_lab') {
            return 'deployment_gate_closed';
        }
        if ($this->nonceAuthority->inTransaction()) {
            return 'nonce_authority_transaction_denied';
        }
        try {
            $identity = $this->nonceAuthority->query(
                'SELECT current_database() AS db, current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['db'] !== 'perf_f31_nonce_ci'
                || $identity['role'] !== 'perf_f31_consumer') {
                return 'wrong_nonce_authority';
            }
        } catch (Throwable) {
            return 'nonce_authority_unavailable';
        }

        try {
            $current = $resolveCurrentPortalAuthority();
        } catch (Throwable) {
            return 'portal_source_unavailable';
        }
        if (!$current instanceof SyntheticCurrentAuthority) {
            return 'portal_source_unavailable';
        }

        // This gate checks Ed25519 against a separately configured public key,
        // binding, recent source revisions, expiration and test-only permission.
        $verdict = (new SignedPortalCapabilityGate($this->trustedPublicKeyHex))
            ->check($packet, $current, $now);
        if ($verdict !== 'verified_synthetic_only') {
            return $verdict;
        }
        try {
            // Safe ONLY because the signed packet and canonical claim were
            // verified immediately above, not because this JSON is trusted.
            $envelope = json_decode($packet, true, 16, JSON_THROW_ON_ERROR);
            $base64 = strtr((string)$envelope['payload'], '-_', '+/');
            $decoded = base64_decode($base64, true);
            if (!is_string($decoded)) {
                return 'malformed_verified_packet';
            }
            $claims = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($claims)) {
                return 'malformed_verified_packet';
            }
            // The nonce authority itself is a second Postgres instance and
            // its statement autocommits BEFORE a caller may consume this result.
            $consume = $this->nonceAuthority->prepare(
                'SELECT CASE WHEN perf_f31_receipts.consume_validated_nonce('
                .'?, ?, ?, CAST(? AS bigint), CAST(? AS bigint)) '
                .'THEN 1 ELSE 0 END'
            );
            $consume->execute([
                $claims['nonce'],
                $claims['performance_tenant_ref'],
                $claims['portal_service_ref'],
                $claims['expires_at'],
                $now
            ]);
            if ((int)$consume->fetchColumn() !== 1) {
                return 'distributed_replay_denied';
            }
            // No operational permit returned, intentionally.
            return 'verified_synthetic_once';
        } catch (JsonException) {
            return 'malformed_verified_packet';
        } catch (Throwable) {
            // Fail closed on lost authority / privilege / DB error.
            return 'nonce_authority_unavailable';
        }
    }
}
