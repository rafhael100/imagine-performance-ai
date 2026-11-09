<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use JsonException;
use PDO;
use Throwable;

/**
 * F1-21 test-only mTLS reader, using independent PG16 mock source and motor.
 *
 * There are no production credentials, endpoints, workload identities,
 * high-availability guarantees, real clients or external provider effects.
 */
final class SyntheticMtlsPortalFence
{
    public function __construct(
        private readonly PDO $destination,
        private readonly string $caFile,
        private readonly string $clientCert,
        private readonly string $clientKey,
    ) {
    }

    /** @return array<string,mixed>|null */
    private function source(): ?array
    {
        if (!$this->lab()) {
            return null;
        }
        foreach ([$this->caFile, $this->clientCert, $this->clientKey] as $file) {
            if (!is_file($file)) {
                return null;
            }
        }
        $c = curl_init('https://localhost:18785/_synthetic/portal/fence/v1/svc-demo-b');
        if ($c === false) {
            return null;
        }
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_PROXY => '',
            CURLOPT_CAINFO => $this->caFile,
            CURLOPT_SSLCERT => $this->clientCert,
            CURLOPT_SSLKEY => $this->clientKey,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTPGET => true,
        ]);
        $body = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if (!is_string($body) || $code !== 200
            || strlen($body) > 2048 || strlen($body) < 30) {
            return null;
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data)
            || array_keys($data) !== [
                'version', 'service_ref', 'organization_ref', 'tenant_ref',
                'revision', 'recovery_epoch', 'active'
            ]
            || $data['version'] !== 'perf.mtls.fence.synthetic.v1'
            || $data['service_ref'] !== 'svc-demo-b'
            || $data['organization_ref'] !== 'org-demo-b'
            || $data['tenant_ref'] !== 'perf-demo-b'
            || !is_int($data['revision']) || $data['revision'] < 1
            || !is_int($data['recovery_epoch']) || $data['recovery_epoch'] < 1
            || !is_bool($data['active'])) {
            return null;
        }
        return $data;
    }

    private function target(): ?array
    {
        try {
            $facts = $this->destination->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($facts)
                || $facts['db'] !== 'perf_f20_test'
                || $facts['role'] !== 'perf_f20_writer') {
                return null;
            }
            $stmt = $this->destination->prepare(
                'SELECT service_ref,organization_ref,tenant_ref,
                        grant_revision,recovery_epoch,active
                 FROM perf_f20.mock_grants WHERE service_ref=?'
            );
            $stmt->execute(['svc-demo-b']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function bound(?array $r): bool
    {
        return is_array($r)
            && $r['service_ref'] === 'svc-demo-b'
            && $r['organization_ref'] === 'org-demo-b'
            && $r['tenant_ref'] === 'perf-demo-b';
    }

    private static function active(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't'], true);
    }

    public function admission(): string
    {
        if (!$this->lab()) {
            return 'mtls_lab_disabled';
        }
        $upstream = $this->source();
        if ($upstream === null) {
            return 'mtls_authority_unavailable';
        }
        $target = $this->target();
        if (!self::bound($target)) {
            return 'mtls_target_unavailable';
        }
        if ((int)$target['grant_revision'] !== $upstream['revision']) {
            return 'mtls_revision_mismatch';
        }
        if ((int)$target['recovery_epoch'] !== $upstream['recovery_epoch']) {
            return 'mtls_epoch_mismatch';
        }
        if (!$upstream['active']) {
            return 'mtls_source_revoked';
        }
        if (!self::active($target['active'])) {
            return 'mtls_target_revoked';
        }
        return 'admitted_synthetic_only';
    }

    public function reconcileRevoked(): string
    {
        if (!$this->lab()) {
            return 'mtls_lab_disabled';
        }
        $upstream = $this->source();
        if ($upstream === null) {
            return 'mtls_authority_unavailable';
        }
        if ($upstream['active'] || $upstream['revision'] < 2
            || $upstream['recovery_epoch'] < 2) {
            return 'mtls_authority_not_revoked';
        }
        try {
            $this->destination->beginTransaction();
            $stmt = $this->destination->prepare(
                'SELECT service_ref,organization_ref,tenant_ref,
                        grant_revision,recovery_epoch,active
                 FROM perf_f20.mock_grants
                 WHERE service_ref=? FOR UPDATE'
            );
            $stmt->execute(['svc-demo-b']);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!self::bound(is_array($target) ? $target : null)) {
                $this->destination->rollBack();
                return 'mtls_target_binding_denied';
            }
            $old = (int)$target['grant_revision'];
            $epoch = (int)$target['recovery_epoch'];
            if ($old > $upstream['revision']
                || ($old === $upstream['revision'] && self::active($target['active']))
                || $epoch > $upstream['recovery_epoch']) {
                $this->destination->rollBack();
                return 'mtls_revision_conflict';
            }
            if ($old === $upstream['revision']
                && $epoch === $upstream['recovery_epoch']) {
                $this->destination->commit();
                return 'mtls_already_reconciled_demo';
            }
            $update = $this->destination->prepare(
                'UPDATE perf_f20.mock_grants
                 SET active=false,grant_revision=?,recovery_epoch=?
                 WHERE service_ref=? AND organization_ref=? AND tenant_ref=?
                   AND grant_revision <= ? AND recovery_epoch <= ?'
            );
            $update->execute([
                $upstream['revision'], $upstream['recovery_epoch'],
                'svc-demo-b','org-demo-b','perf-demo-b',
                $upstream['revision'], $upstream['recovery_epoch']
            ]);
            if ($update->rowCount() !== 1) {
                $this->destination->rollBack();
                return 'mtls_concurrent_update_denied';
            }
            $this->destination->commit();
            return 'mtls_reconciled_demo';
        } catch (Throwable) {
            if ($this->destination->inTransaction()) {
                $this->destination->rollBack();
            }
            return 'mtls_destination_unavailable';
        }
    }

    private function lab(): bool
    {
        return PHP_SAPI === 'cli'
            && getenv('APP_ENV') === 'testing'
            && getenv('PERF_F21_MTLS_LAB') === '1'
            && getenv('PORTAL_F20_PORT') === '5432'
            && getenv('PERF_F20_PORT') === '5433'
            && getenv('PERF_F21_SERVER_HOST') === 'localhost';
    }
}
