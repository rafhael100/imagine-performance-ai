<?php

declare(strict_types=1);

namespace App\Performance\Security;

use JsonException;
use SodiumException;

/**
 * F1-29 — cryptographically checks an EPHEMERAL LAB Portal assertion.
 *
 * Critical: this class cannot authorize real operations, clients or Ads.
 * The Portal still has no operational authenticated session, membership or
 * contract authority, and SyntheticCurrentAuthority is caller-constructible.
 * A test-only allow string MUST NOT be converted into an actual grant.
 */
final class SignedPortalCapabilityGate
{
    private const DOMAIN_SEPARATOR = "IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1\n";
    private const MAX_AGE_SECONDS = 30;
    private const MAX_SOURCE_AGE_SECONDS = 5;

    /** @var array<string,int> */
    private array $seenNonces = [];

    /** The key comes from controlled out-of-band configuration, not the envelope. */
    public function __construct(private readonly string $trustedPublicKeyHex)
    {
    }

    public function check(
        string $envelope,
        ?SyntheticCurrentAuthority $authority,
        int $now,
    ): string {
        if (!self::labEnabled()) {
            return 'deployment_gate_closed';
        }
        if ($now<=0 || $now>2147483647
            || $authority===null
            || strlen($envelope)<200 || strlen($envelope)>8192) {
            return 'missing_or_invalid_authority';
        }

        try {
            $packet=json_decode($envelope,true,16,JSON_THROW_ON_ERROR);
        }catch(JsonException){
            return 'malformed_envelope';
        }
        if (!is_array($packet)
            || array_keys($packet)!==['version','key_id','payload','signature']
            || $packet['version']!=='portal-capability-envelope.v1'
            || $packet['key_id']!=='ephemeral-lab-key-1'
            || !is_string($packet['payload'])
            || !is_string($packet['signature'])
            || !preg_match('/^[a-f0-9]{64}$/D',$this->trustedPublicKeyHex)) {
            return 'malformed_envelope';
        }
        $payload=self::decodeBase64url($packet['payload'],2048);
        $signature=self::decodeBase64url($packet['signature'],128);
        if ($payload===null || $signature===null
            || strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES) {
            return 'malformed_envelope';
        }
        try{
            if(!sodium_crypto_sign_verify_detached(
                $signature,self::DOMAIN_SEPARATOR.$payload,hex2bin($this->trustedPublicKeyHex)
            )){
                return 'invalid_signature';
            }
        }catch(SodiumException){
            return 'invalid_signature';
        }

        try{
            $claim=json_decode($payload,true,24,JSON_THROW_ON_ERROR);
            $encoded=json_encode(
                $claim,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }catch(JsonException){
            return 'invalid_claim_schema';
        }
        if (!is_array($claim)
            || !is_string($encoded) || $encoded!==$payload
            || array_keys($claim)!==[
                'version','issuer','audience','profile','organization_ref',
                'performance_tenant_ref','portal_service_ref','resource_ref',
                'actor_ref','session_ref','permission','session_mfa_verified',
                'membership_revision','service_revision','session_revision',
                'recovery_epoch','service_state','issued_at','expires_at','nonce'
            ]
            || $claim['version']!==1
            || $claim['issuer']!=='imagine.portal'
            || $claim['audience']!=='imagine.performance-ai'
            || $claim['profile']!=='portal.capability.synthetic.v1'
            || $claim['organization_ref']!=='org-demo-a'
            || $claim['performance_tenant_ref']!=='perf-demo-a'
            || $claim['portal_service_ref']!=='svc-demo-a'
            || $claim['resource_ref']!=='demo-cam-a1'
            || $claim['actor_ref']!=='actor-demo-a'
            || $claim['session_ref']!=='session-demo-a'
            || $claim['permission']!=='campaigns:read'
            || $claim['session_mfa_verified']!==true
            || $claim['service_state']!=='active_mock'
            || !is_string($claim['nonce'])
            || !preg_match('/^[a-f0-9]{32}$/D',$claim['nonce'])) {
            return 'invalid_claim_scope';
        }
        foreach([
            'membership_revision','service_revision',
            'session_revision','recovery_epoch','issued_at','expires_at'
        ] as $field){
            if (!is_int($claim[$field]) || $claim[$field]<=0) {
                return 'invalid_claim_schema';
            }
        }
        if ($claim['expires_at']<=$claim['issued_at']
            || $claim['expires_at']-$claim['issued_at']>self::MAX_AGE_SECONDS
            || $now<$claim['issued_at']
            || $now>=$claim['expires_at']) {
            return 'expired_or_future';
        }

        // This proof is NOT a trusted resolver. It is populated manually
        // in offline CI. Real production must obtain an up-to-date independent
        // Portal authority lookup and a durable distributed revocation fence.
        if ($authority->observedAt<=0
            || $authority->observedAt<$now-self::MAX_SOURCE_AGE_SECONDS
            || $authority->observedAt>$now
            || !$authority->sourceAvailable) {
            return 'source_unavailable_or_stale';
        }
        if ($authority->organizationRef!==$claim['organization_ref']
            || $authority->tenantRef!==$claim['performance_tenant_ref']
            || $authority->serviceRef!==$claim['portal_service_ref']
            || $authority->resourceRef!==$claim['resource_ref']
            || $authority->actorRef!==$claim['actor_ref']
            || $authority->sessionRef!==$claim['session_ref']
            || $authority->permission!==$claim['permission']) {
            return 'authority_context_mismatch';
        }
        if (!$authority->sessionActive || !$authority->mfaVerified
            || !$authority->membershipActive || !$authority->serviceActive) {
            return 'authority_revoked';
        }
        if ($authority->membershipRevision!==$claim['membership_revision']
            || $authority->serviceRevision!==$claim['service_revision']
            || $authority->sessionRevision!==$claim['session_revision']
            || $authority->recoveryEpoch!==$claim['recovery_epoch']) {
            return 'authority_revision_mismatch';
        }

        $nonce=$claim['nonce'];
        if(isset($this->seenNonces[$nonce])){
            return 'replay_denied';
        }
        // In-memory replay state is sufficient ONLY for test vectors, NOT
        // multiple workers, process restart, DB restore or distributed CI.
        $this->seenNonces[$nonce]=$claim['expires_at'];
        return 'verified_synthetic_only';
    }

    private static function labEnabled(): bool
    {
        return PHP_SAPI==='cli'
            && getenv('APP_ENV')==='testing'
            && getenv('PERF_F29_SIGNED_PORTAL_LAB')==='1'
            && extension_loaded('sodium');
    }

    private static function decodeBase64url(string $encoded,int $maxBytes): ?string
    {
        if ($encoded==='' || strlen($encoded)>($maxBytes*2)
            || !preg_match('/^[a-zA-Z0-9_-]+$/D',$encoded)) {
            return null;
        }
        $decoded=base64_decode(strtr($encoded,'-_','+/'),true);
        if (!is_string($decoded)
            || strlen($decoded)>$maxBytes
            || rtrim(strtr(base64_encode($decoded),'+/','-_'),'=')!==$encoded) {
            return null;
        }
        return $decoded;
    }
}
