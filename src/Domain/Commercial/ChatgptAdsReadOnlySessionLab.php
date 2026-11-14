<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * CI-only two-step ChatGPT Ads request-session contract.
 *
 * Both GET /v1/ad_account and GET /v1/ad_account/insights MUST use the
 * SAME opaque credential reference. An injected fixture transport receives
 * only a validated allowlisted descriptor, NEVER secrets/headers. There is
 * intentionally NO cURL/HTTP client, vault or network code here.
 *
 * NOT a real advertiser account connection; all evidence remains unverified.
 */
final class ChatgptAdsReadOnlySessionLab
{
    /**
     * @param array<string,mixed> $binding synthetic tenant/contract credential grant
     * @param callable(array<string,mixed>):array<string,mixed> $fixtureTransport
     * @return array<string,mixed>
     */
    public static function collect(
        string $tenant,
        string $agreement,
        string $period,
        string $account,
        string $timeZone,
        array $binding,
        int $now,
        callable $fixtureTransport
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_ADS_READONLY_BOUNDARY_LAB') !== '1' ||
            getenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB') !== '1' ||
            getenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB') !== '1' ||
            getenv('PERF_CHATGPT_READONLY_SESSION_LAB') !== '1') {
            throw new RuntimeException('Read-only advertiser session is disabled outside CI.');
        }
        $plan = ChatgptAdsOfflineAdapter::insightsRequestPlan($account,$period,$timeZone);
        $scope = AdsProviderReadOnlyBoundary::inspect(
            $tenant,$agreement,$period,'chatgpt_ads',
            $account,$timeZone,$binding,$plan,$now
        );
        foreach(['network_enabled','provider_authorization_verified',
                  'provider_spend_verified','billable','ads_write_enabled',
                  'asaas_write_enabled'] as $flag) {
            if (($scope[$flag] ?? null) !== false) {
                throw new InvalidArgumentException('Mock account scope cannot grant operational access.');
            }
        }
        $ref = $scope['credential_reference'];
        // Metadata always precedes spend: if 401, changed account ID or review
        // is pending, there MUST NOT be a second Insights request.
        $metadataRequest = self::descriptor($ref,'ad_account_metadata',
            '/v1/ad_account',[]
        );
        $accountHttp = self::invoke($fixtureTransport,$metadataRequest);
        $preflight = ChatgptAdsAccountPreflightLab::inspect($scope,$accountHttp);

        $insightsRequest = self::descriptor($ref,'ad_account_insights',
            '/v1/ad_account/insights',$plan['query']
        );
        $insightsHttp = self::invoke($fixtureTransport,$insightsRequest);

        // Classifier must recognize closed pagination before exact numeric
        // conversion; an HTTP 200 shape is NOT proof of provider identity.
        $classified = AdsReadOnlyHttpResponseReview::assess('chatgpt_ads',$insightsHttp);
        if ($classified['http_status'] !== 200 ||
            $classified['synthetic_json_shape_ok'] !== true ||
            $classified['manual_retry_review_recommended'] !== false) {
            throw new InvalidArgumentException('Cannot close failed or paginated mock Insights.');
        }
        $report = ChatgptAdsExactWireSpendLab::normalize(
            $scope,$preflight,$insightsHttp
        );
        foreach(['network_enabled','api_key_verified_by_provider',
                 'portal_iam_authenticated','provider_spend_verified',
                 'finance_approved','billable','asaas_write_enabled',
                 'ads_write_enabled'] as $flag) {
            if (($report[$flag] ?? null) !== false) {
                throw new InvalidArgumentException('Read-only lab cannot return verified evidence.');
            }
        }
        return [
            'tenant_id'=>$tenant,
            'agreement_id'=>$agreement,
            'period'=>$period,
            'account_id'=>$account,
            'credential_reference'=>$ref,
            'metadata_request_sha256'=>self::fingerprint($metadataRequest),
            'insights_request_sha256'=>self::fingerprint($insightsRequest),
            'synthetic_account_report'=>$report['synthetic_account_report'],
            'source_wire_json_sha256'=>$report['source_wire_json_sha256'],
            'reported_day_count'=>$report['reported_day_count'],
            'numeric_money_parsed_without_binary_float'=>true,
            'sequence'=>['ad_account_metadata','ad_account_insights'],
            'state'=>'synthetic_same_credential_two_gets_no_network',
            'actual_network_executed'=>false,
            'provider_account_authenticated'=>false,
            'api_key_verified_by_provider'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    private static function descriptor(
        string $ref, string $operation, string $path, array $query
    ): array {
        // Build the entire request from hardcoded primitives; no caller
        // supplied URL, method, headers, tokens, cookies or redirect policy.
        if (!preg_match('/\Aref_[A-Za-z0-9_-]{12,96}\z/D',$ref) ||
            !in_array($operation,['ad_account_metadata','ad_account_insights'],true) ||
            ($operation==='ad_account_metadata' && ($path!=='/v1/ad_account'||$query!==[])) ||
            ($operation==='ad_account_insights' && $path!=='/v1/ad_account/insights')) {
            throw new InvalidArgumentException('Unexpected credential ref or account read path.');
        }
        return [
            'operation'=>$operation,
            'provider'=>'chatgpt_ads',
            'scheme'=>'https',
            'host'=>'api.ads.openai.com',
            'method'=>'GET',
            'path'=>$path,
            'query'=>$query,
            'credential_reference'=>$ref,
            'follow_redirects'=>false,
            'automatic_retry'=>false,
            'network_enabled'=>false,
            'headers_provided_by_caller'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    /**
     * @param callable(array<string,mixed>):array<string,mixed> $transport
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private static function invoke(callable $transport,array $request):array
    {
        try {
            $http=$transport($request);
        } catch (Throwable) {
            // Transport errors can carry tokens or account details; do NOT
            // propagate the exception or include the raw response anywhere.
            throw new RuntimeException('Synthetic provider transport rejected or unavailable.');
        }
        if (!is_array($http)) {
            throw new InvalidArgumentException('Synthetic transport must return an HTTP fixture.');
        }
        $keys=array_keys($http);
        sort($keys,SORT_STRING);
        if($keys!==['body','content_type','redirected','status']) {
            throw new InvalidArgumentException('Synthetic transport supplied unexpected HTTP fields.');
        }
        if(!is_int($http['status'])||!is_string($http['body'])||
           !is_string($http['content_type'])||!is_bool($http['redirected'])||
           strlen($http['body'])>524288) {
            throw new InvalidArgumentException('Synthetic transport HTTP fixture has invalid types/size.');
        }
        return $http;
    }

    /** @param array<string,mixed> $request */
    private static function fingerprint(array $request):string
    {
        // Fingerprints cover only the ALLOWLISTED request descriptor with an
        // opaque ref, not a token. No response body/PII in the returned data.
        return hash('sha256',json_encode($request,
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
}
