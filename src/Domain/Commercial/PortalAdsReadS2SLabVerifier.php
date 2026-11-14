<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * CI-only verifier for the REAL pinned Portal signed Ads-read grant issuer.
 * This checks crypto scope integrity, NOT real Portal IAM/consent/ownership.
 * A caller MUST pass an atomic/durable nonce claim for operational use; our
 * cross-repo tests use in-memory claims and never permit real requests.
 */
final class PortalAdsReadS2SLabVerifier
{
    /**
     * @param array<string,mixed> $grant exact Portal-issued HMAC envelope
     * @param callable(string,string):?string $resolveSecret issuer,key_id
     * @param callable(string,string,int):bool $claimNonce tenant,nonce,expires
     * @return array<string,mixed>
     */
    public static function verify(
        array $grant,
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $expectedAccount,
        string $expectedCredentialReference,
        string $expectedConsentReference,
        int $now,
        callable $resolveSecret,
        callable $claimNonce
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB')!=='1') {
            throw new RuntimeException('Ads S2S read grant verification disabled outside CI.');
        }
        $fields=[
            'version','type','issuer','audience','tenant_id','agreement_id',
            'period','provider','ad_account_id','credential_reference',
            'consent_reference','permission','can_mutate_ads','can_charge',
            'issued_at','expires_at','key_id','nonce','signature'
        ];
        $keys=array_keys($grant);
        sort($keys,SORT_STRING);sort($fields,SORT_STRING);
        if($keys!==$fields)throw new InvalidArgumentException('Unexpected signed grant fields.');
        foreach([
            'version'=>'1',
            'type'=>'imagine-portal.ads-read-delegation',
            'issuer'=>'imagine-portal-ci',
            'audience'=>'imagine-performance-ai.ads-read-lab',
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'provider'=>'chatgpt_ads',
            'ad_account_id'=>$expectedAccount,
            'credential_reference'=>$expectedCredentialReference,
            'consent_reference'=>$expectedConsentReference,
            'permission'=>'ads.insights.read'
        ] as $field=>$expected) {
            if(!is_string($grant[$field]) || !hash_equals($expected,$grant[$field])){
                throw new InvalidArgumentException('Signed grant scope mismatched '.$field);
            }
        }
        if(!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$expectedTenant) ||
           !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$expectedAgreement) ||
           !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$expectedPeriod) ||
           !preg_match('/\Aadacct_[A-Za-z0-9]{3,64}\z/D',$expectedAccount) ||
           !preg_match('/\Aref_[A-Za-z0-9_-]{12,96}\z/D',$expectedCredentialReference) ||
           !preg_match('/\Aconsent_[A-Za-z0-9_-]{8,64}\z/D',$expectedConsentReference) ||
           $grant['can_mutate_ads']!==false ||
           $grant['can_charge']!==false ||
           !is_string($grant['key_id']) ||
           !preg_match('/\Ak_[A-Za-z0-9_-]{4,64}\z/D',$grant['key_id']) ||
           !is_string($grant['nonce']) ||
           !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$grant['nonce']) ||
           !is_string($grant['signature']) ||
           !preg_match('/\A[a-f0-9]{64}\z/D',$grant['signature']) ||
           !is_int($grant['issued_at']) ||
           !is_int($grant['expires_at']) ||
           $now<=0 ||
           $grant['issued_at']<=0 ||
           $grant['issued_at']>$now+30 ||
           $grant['expires_at']<=$now ||
           $grant['expires_at']<=$grant['issued_at'] ||
           $grant['expires_at']-$grant['issued_at']>120) {
            throw new InvalidArgumentException('Invalid Ads-read permission or TTL.');
        }
        $payload=$grant;
        unset($payload['signature']);
        ksort($payload,SORT_STRING);
        $signingBytes="imagine:portal:performance:ads-read-grant:lab:v1\n".
            json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $secret=$resolveSecret($grant['issuer'],$grant['key_id']);
        if(!is_string($secret) || strlen($secret)<32 ||
           !hash_equals(hash_hmac('sha256',$signingBytes,$secret),$grant['signature'])) {
            throw new InvalidArgumentException('Portal Ads-read grant HMAC invalid.');
        }
        if($claimNonce($expectedTenant,$grant['nonce'],$grant['expires_at'])!==true){
            throw new InvalidArgumentException('Portal Ads-read grant replay denied.');
        }
        return [
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'account_id'=>$expectedAccount,
            'credential_reference'=>$expectedCredentialReference,
            'consent_reference'=>$expectedConsentReference,
            'signed_permission'=>'ads.insights.read',
            'issuer'=>'imagine-portal-ci',
            'grant_sha256'=>hash('sha256',$signingBytes),
            'short_lived_grant_hmac_verified_in_lab'=>true,
            'nonce_claimed_in_ci_memory'=>true,
            'state'=>'synthetic_portal_ads_s2s_read_scope_verified',
            'portal_iam_authenticated'=>false,
            'consent_verified_in_real_database'=>false,
            'provider_account_owner_verified'=>false,
            'real_s2s_identity_authenticated'=>false,
            'real_api_request_authorized'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }
}
