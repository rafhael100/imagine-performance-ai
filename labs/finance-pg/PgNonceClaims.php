<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Isolated PostgreSQL lab adapter for PortalSignedQuoteGuard nonce callback.
 * A trusted server must supply tenant identity (never use a client-provided
 * tenant_id as the authority). Database role must NOT own tables or bypass RLS.
 * No HTTP endpoints, gateway writes, or production DB wiring.
 */
final class PgNonceClaims
{
    public function __construct(private readonly PDO $pdo) {}

    /** Atomic once-only insert. Returns false only for a duplicate nonce. */
    public function claim(string $tenant, string $keyId, string $nonce, int $expires): bool
    {
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !preg_match('/\Ak_[A-Za-z0-9_-]{4,64}\z/D', $keyId) ||
            !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D', $nonce) ||
            $expires < 1 || $this->pdo->inTransaction()) {
            throw new InvalidArgumentException('Invalid nonce claim input or nested transaction.');
        }
        try {
            $this->pdo->beginTransaction();
            $scope = $this->pdo->prepare("SELECT set_config('app.tenant_id', :tenant, true)");
            $scope->execute(['tenant' => $tenant]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO perf_fin_lab.signed_quote_nonces
                 (tenant_id, nonce, key_id, expires_at)
                 VALUES (:tenant, :nonce, :key, :expires)
                 ON CONFLICT (tenant_id, nonce) DO NOTHING
                 RETURNING nonce'
            );
            $stmt->execute([
                'tenant' => $tenant, 'nonce' => $nonce, 'key' => $keyId, 'expires' => $expires,
            ]);
            $inserted = $stmt->fetchColumn() !== false;
            $this->pdo->commit();
            return $inserted;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Do not disclose database/tenant internals in an API error.
            throw new RuntimeException('Laboratory nonce ledger unavailable.');
        }
    }
}
