<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use JsonException;
use PDO;
use Throwable;

/**
 * F1-33 non-operational CI illustration: an anchor in the Portal's separate
 * PostgreSQL prevents nonce-DB-only old-restore replay. If both are restored,
 * or direct F31 is called instead, there is NO guarantee. Do not register
 * this class in Laravel, APIs, workers or Ads providers.
 */
final class SyntheticPortalAnchoredReplayGate
{
    public function __construct(
        private readonly string $trustedPublicKeyHex,
        private readonly PDO $portalAnchor,
        private readonly PDO $nonceAuthority
    ) {
    }

    /** @param callable():?SyntheticCurrentAuthority $currentPortal */
    public function verifyOnce(string $packet,callable $currentPortal,int $now):string
    {
        if (PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
            || getenv('PERF_F31_ED25519_REPLAY_LAB')!=='1'
            || getenv('PERF_F33_PORTAL_ANCHOR_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='portal_perf_auth_lab') {
            return 'deployment_gate_closed';
        }
        if ($this->portalAnchor->inTransaction()) {
            return 'portal_anchor_transaction_denied';
        }
        try {
            $id=$this->portalAnchor->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($id)
                || $id['db']!=='portal_perf_auth_lab'
                || $id['role']!=='perf_f33_anchor_consumer') {
                return 'wrong_anchor_authority';
            }
        }catch(Throwable) {
            return 'portal_anchor_unavailable';
        }
        try {
            $source=$currentPortal();
        }catch(Throwable) {
            return 'portal_source_unavailable';
        }
        if (!$source instanceof SyntheticCurrentAuthority) {
            return 'portal_source_unavailable';
        }

        $verified=(new SignedPortalCapabilityGate($this->trustedPublicKeyHex))
            ->check($packet,$source,$now);
        if ($verified!=='verified_synthetic_only') return $verified;

        try {
            $envelope=json_decode($packet,true,16,JSON_THROW_ON_ERROR);
            $decoded=base64_decode(strtr((string)$envelope['payload'],'-_','+/'),true);
            if(!is_string($decoded))return 'invalid_signed_packet';
            $claims=json_decode($decoded,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($claims))return 'invalid_signed_packet';
            // Autocommit in the Portal instance. Does not share the nonce DB's
            // transaction. A restored Portal DB can still erase this anchor.
            $q=$this->portalAnchor->prepare(
                'SELECT CASE WHEN perf_f33_anchor.consume_validated_nonce('
                .'?,?,?,CAST(? AS bigint),CAST(? AS bigint)) THEN 1 ELSE 0 END'
            );
            $q->execute([
                $claims['nonce'],$claims['performance_tenant_ref'],
                $claims['portal_service_ref'],$claims['expires_at'],$now
            ]);
            if((int)$q->fetchColumn()!==1)return 'portal_anchor_replay_denied';

            // F1-34: no longer call the publicly callable legacy gate.
            // Reconsult current Portal facts after the anchor has committed.
            $fresh=$currentPortal();
            if(!$fresh instanceof SyntheticCurrentAuthority
               || (new SignedPortalCapabilityGate($this->trustedPublicKeyHex))
                  ->check($packet,$fresh,$now)!=='verified_synthetic_only') {
                return 'local_nonce_or_source_denied';
            }
            if($this->nonceAuthority->inTransaction()) {
                return 'local_nonce_or_source_denied';
            }
            $identity=$this->nonceAuthority->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
               || $identity['db']!=='perf_f31_nonce_ci'
               || $identity['role']!=='perf_f31_consumer') {
                return 'local_nonce_or_source_denied';
            }
            $consumeLocal=$this->nonceAuthority->prepare(
                'SELECT CASE WHEN perf_f31_receipts.consume_validated_nonce('
                .'?,?,?,CAST(? AS bigint),CAST(? AS bigint)) THEN 1 ELSE 0 END'
            );
            $consumeLocal->execute([
                $claims['nonce'],$claims['performance_tenant_ref'],
                $claims['portal_service_ref'],$claims['expires_at'],$now
            ]);
            if((int)$consumeLocal->fetchColumn()!==1) {
                // The Portal anchor remains spent. This is intentional
                // fail-closed behavior with availability loss, not exactly-once.
                return 'local_nonce_or_source_denied';
            }
            return 'verified_synthetic_anchored_once';
        }catch(JsonException) {
            return 'invalid_signed_packet';
        }catch(Throwable) {
            return 'portal_anchor_unavailable';
        }
    }
}
