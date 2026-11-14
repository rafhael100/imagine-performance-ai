<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * CI-only atomic application boundary: a signed Portal Ads-read grant
 * must be verified against an INDEPENDENT expected context and the SAME
 * credential and consent references in the bound account session.
 *
 * Never invoke ChatgptAdsReadOnlySessionLab directly for a purported Portal
 * delegation: it knows the account binding but has no signed grant.
 */
final class PortalAdsGrantedReadOnlySessionLab
{
    /**
     * @param array<string,mixed> $signedGrant Portal issuer's signed envelope
     * @param array<string,mixed> $binding Ads account credential/consent fixture
     * @param callable(string,string):?string $keyResolver
     * @param callable(string,string,int):bool $nonceClaim
     * @param callable(array<string,mixed>):array<string,mixed> $fixtureTransport
     * @return array<string,mixed>
     */
    public static function collect(
        array $signedGrant,
        array $binding,
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $expectedAccount,
        string $expectedCredentialReference,
        string $expectedConsentReference,
        string $expectedTimeZone,
        int $now,
        callable $keyResolver,
        callable $nonceClaim,
        callable $fixtureTransport
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB')!=='1' ||
           getenv('PERF_CHATGPT_READONLY_SESSION_LAB')!=='1') {
            throw new RuntimeException('Portal-authorized Ads account fixture session disabled.');
        }
        // A signed grant is NOT a substitute for binding the account's
        // separately resolved credential & consent in the account registry.
        // Check this BEFORE claiming the nonce or invoking any transport.
        foreach([
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'provider'=>'chatgpt_ads',
            'account_id'=>$expectedAccount,
            'credential_reference'=>$expectedCredentialReference,
            'consent_reference'=>$expectedConsentReference,
        ] as $field=>$expected) {
            if(!is_string($binding[$field]??null) ||
               !is_string($expected) ||
               !hash_equals($expected,$binding[$field])) {
                throw new InvalidArgumentException('Ads account binding does not match signed grant scope: '.$field);
            }
        }
        $grant=PortalAdsReadS2SLabVerifier::verify(
            $signedGrant,$expectedTenant,$expectedAgreement,$expectedPeriod,
            $expectedAccount,$expectedCredentialReference,
            $expectedConsentReference,$now,$keyResolver,$nonceClaim
        );
        if(($grant['short_lived_grant_hmac_verified_in_lab']??null)!==true ||
           ($grant['signed_permission']??null)!=='ads.insights.read' ||
           ($grant['credential_reference']??null)!==$binding['credential_reference'] ||
           ($grant['consent_reference']??null)!==$binding['consent_reference']) {
            throw new InvalidArgumentException('Signed scope unavailable to begin mock provider reads.');
        }
        // No network is implemented by this class. This is a CI-only
        // orchestrator using injected fixture callbacks.
        $session=ChatgptAdsReadOnlySessionLab::collect(
            $expectedTenant,$expectedAgreement,$expectedPeriod,$expectedAccount,
            $expectedTimeZone,$binding,$now,$fixtureTransport
        );
        foreach([
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'account_id'=>$expectedAccount,
            'credential_reference'=>$expectedCredentialReference,
        ] as $field=>$expected) {
            if(($session[$field]??null)!==$expected) {
                throw new InvalidArgumentException('Session output disagrees with signed Portal grant.');
            }
        }
        foreach(['actual_network_executed','api_key_verified_by_provider',
                 'provider_spend_verified','finance_approved','billable',
                 'asaas_write_enabled','ads_write_enabled'] as $flag) {
            if(($session[$flag]??null)!==false) {
                throw new RuntimeException('Synthetic Portal grant cannot grant operational authority.');
            }
        }
        return [
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'account_id'=>$expectedAccount,
            'credential_reference'=>$expectedCredentialReference,
            'signed_grant_sha256'=>$grant['grant_sha256'],
            'metadata_request_sha256'=>$session['metadata_request_sha256'],
            'insights_request_sha256'=>$session['insights_request_sha256'],
            'synthetic_account_report'=>$session['synthetic_account_report'],
            'reported_day_count'=>$session['reported_day_count'],
            'state'=>'synthetic_signed_portal_grant_and_account_same_credential_two_gets',
            'portal_iam_authenticated'=>false,
            'real_s2s_identity_authenticated'=>false,
            'real_api_request_authorized'=>false,
            'provider_account_authenticated'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }
}
