<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * READ-ONLY request allowlist and opaque account credential binding, CI ONLY.
 *
 * No HTTP client, token lookup, credential exchange, OAuth, Ads network
 * connection or trusted provider attestation. Every accepted result is still
 * unverified and NON-BILLABLE. Production requires a separate audited service.
 */
final class AdsProviderReadOnlyBoundary
{
    private const GOOGLE_SCOPE='https://www.googleapis.com/auth/adwords';
    private const META_SCOPE='ads_read';
    private const CHATGPT_SCOPE='advertiser_api_key';

    /** @param array<string,mixed> $binding @param array<string,mixed> $request
     *  @return array<string,mixed>
     */
    public static function inspect(
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $provider,
        string $accountId,
        array $binding,
        array $request,
        int $now
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_ADS_READONLY_BOUNDARY_LAB') !== '1') {
            throw new RuntimeException('Three-provider collection boundary is not enabled.');
        }
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$expectedTenant) ||
            !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$expectedAgreement) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$expectedPeriod) ||
            $now <= 0 || !in_array($provider,['google_ads','meta_ads','chatgpt_ads'],true)) {
            throw new InvalidArgumentException('Unknown test-only provider collection scope.');
        }
        $fields=[
            'tenant_id','agreement_id','period','provider','account_id',
            'credential_reference','consent_reference','consent_state',
            'consent_issued_at','consent_expires_at','account_currency',
            'account_time_zone','permission','account_access',
            'portal_iam_authenticated','provider_authorization_verified',
            'account_ownership_verified','secret_material_embedded',
        ];
        if($provider==='google_ads') {
            $fields[]='google_cloud_project_id';
            $fields[]='google_project_access_level';
        } elseif($provider==='meta_ads') {
            $fields[]='meta_app_id';
        } else {
            $fields[]='openai_ads_account_status';
        }
        self::exact($binding,$fields);
        if(($binding['tenant_id']??null)!==$expectedTenant ||
           ($binding['agreement_id']??null)!==$expectedAgreement ||
           ($binding['period']??null)!==$expectedPeriod ||
           ($binding['provider']??null)!==$provider ||
           ($binding['account_id']??null)!==$accountId ||
           ($binding['account_currency']??null)!=='BRL' ||
           !is_string($binding['account_time_zone']) ||
           !in_array($binding['account_time_zone'],DateTimeZone::listIdentifiers(),true) ||
           ($binding['account_access']??null)!=='synthetic_reporting_only' ||
           ($binding['consent_state']??null)!=='synthetic_active' ||
           !is_int($binding['consent_issued_at']) ||
           !is_int($binding['consent_expires_at']) ||
           $binding['consent_issued_at']<=0 ||
           $binding['consent_issued_at']>$now ||
           $binding['consent_expires_at']<=$now ||
           $binding['consent_expires_at']-$binding['consent_issued_at']>31536000 ||
           $binding['portal_iam_authenticated']!==false ||
           $binding['provider_authorization_verified']!==false ||
           $binding['account_ownership_verified']!==false ||
           $binding['secret_material_embedded']!==false) {
            throw new InvalidArgumentException('Unbound, expired or authority-claiming provider access fixture.');
        }
        foreach([
            ['credential_reference','ref_',12,96],
            ['consent_reference','consent_',8,64],
        ] as [$field,$prefix,$min,$max]) {
            $value=$binding[$field];
            if(!is_string($value) ||
               !preg_match('/\A'.preg_quote($prefix,'/').'[A-Za-z0-9_-]{'.$min.','.$max.'}\z/D',$value)) {
                throw new InvalidArgumentException('Bad opaque credential/consent reference.');
            }
        }
        if($provider==='google_ads') {
            if($binding['permission']!==self::GOOGLE_SCOPE ||
               !is_string($binding['google_cloud_project_id']) ||
               !preg_match('/\A[a-z][a-z0-9-]{5,28}[a-z0-9]\z/D',$binding['google_cloud_project_id']) ||
               !in_array($binding['google_project_access_level'],['test','explorer','basic','standard'],true)) {
                throw new InvalidArgumentException('Google Cloud/OAuth fixture not scoped.');
            }
            // adwords OAuth scope can ALSO write. Restrict actual verb/path
            // separately; never equate scope with read-only privileges.
            $expected=SyntheticProviderSpendAdapter::googleRequestPlan($accountId,$expectedPeriod);
            $host='googleads.googleapis.com';
        } elseif($provider==='meta_ads') {
            if($binding['permission']!==self::META_SCOPE ||
               !is_string($binding['meta_app_id']) ||
               !preg_match('/\A[0-9]{5,24}\z/D',$binding['meta_app_id'])) {
                throw new InvalidArgumentException('Meta ads_read app fixture invalid.');
            }
            $expected=SyntheticProviderSpendAdapter::metaRequestPlan($accountId,$expectedPeriod);
            $host='graph.facebook.com';
        } else {
            if($binding['permission']!==self::CHATGPT_SCOPE ||
               $binding['openai_ads_account_status']!=='synthetic_eligible') {
                throw new InvalidArgumentException('ChatGPT Ads account key fixture invalid.');
            }
            $expected=ChatgptAdsOfflineAdapter::insightsRequestPlan(
                $accountId,$expectedPeriod,$binding['account_time_zone']
            );
            $host='api.ads.openai.com';
        }
        // Structural identity rejects injected host, URL, customer ID, path,
        // GET/POST mutation endpoints, extra headers, Ads writes, query changes.
        if($request!==$expected ||
           ($request['read_only_report']??null)!==true ||
           ($request['transport_enabled']??null)!==false ||
           !in_array($request['method'],['GET','POST'],true)) {
            throw new InvalidArgumentException('Only the exact provider report plan can be reviewed.');
        }
        return [
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'provider'=>$provider,
            'account_id'=>$accountId,
            'account_currency'=>'BRL',
            'account_time_zone'=>$binding['account_time_zone'],
            'host'=>$host,
            'method'=>$expected['method'],
            'path'=>$expected['path'],
            'query'=>$expected['query']??null,
            'body'=>$expected['body']??null,
            'credential_reference'=>$binding['credential_reference'],
            'consent_reference'=>$binding['consent_reference'],
            'permission'=>$binding['permission'],
            'state'=>'synthetic_provider_scope_inspected_without_transport',
            'network_enabled'=>false,
            'credentials_read_from_vault'=>false,
            'portal_iam_authenticated'=>false,
            'provider_authorization_verified'=>false,
            'account_ownership_verified'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    /** @param array<string,mixed> $values @param list<string> $keys */
    private static function exact(array $values,array $keys):void {
        $actual=array_keys($values);
        sort($actual,SORT_STRING);
        sort($keys,SORT_STRING);
        if($actual!==$keys) {
            throw new InvalidArgumentException('Unexpected provider account binding field.');
        }
    }
}
