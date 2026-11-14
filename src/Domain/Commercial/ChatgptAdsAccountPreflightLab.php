<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Test-only preflight for the official GET /v1/ad_account JSON shape.
 * DO NOT mistake synthetic account metadata for a real API key verification.
 * Does not send HTTP, look up secrets, enroll clients, or permit campaigns.
 */
final class ChatgptAdsAccountPreflightLab
{
    /**
     * @param array<string,mixed> $scope returned by offline AdsProviderReadOnlyBoundary
     * @param array<string,mixed> $http synthetic GET /v1/ad_account HTTP response
     * @return array<string,mixed>
     */
    public static function inspect(array $scope,array $http):array
    {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_ADS_READONLY_BOUNDARY_LAB')!=='1' ||
           getenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB')!=='1') {
            throw new RuntimeException('ChatGPT Ads account metadata preflight disabled.');
        }
        // The read-only request gate is NOT a proof of identity: this step
        // prevents obvious metadata mismatch before a future trusted caller.
        if(($scope['provider']??null)!=='chatgpt_ads' ||
           ($scope['host']??null)!=='api.ads.openai.com' ||
           ($scope['method']??null)!=='GET' ||
           ($scope['path']??null)!=='/v1/ad_account/insights' ||
           ($scope['state']??null)!=='synthetic_provider_scope_inspected_without_transport' ||
           ($scope['network_enabled']??null)!==false ||
           ($scope['provider_authorization_verified']??null)!==false ||
           ($scope['provider_spend_verified']??null)!==false ||
           ($scope['billable']??null)!==false ||
           ($scope['ads_write_enabled']??null)!==false ||
           ($scope['asaas_write_enabled']??null)!==false ||
           !is_string($scope['account_id']??null) ||
           !preg_match('/\Aadacct_[A-Za-z0-9]{3,64}\z/D',$scope['account_id']) ||
           ($scope['account_currency']??null)!=='BRL' ||
           !is_string($scope['account_time_zone']??null) ||
           !in_array($scope['account_time_zone'],DateTimeZone::listIdentifiers(),true)){
            throw new InvalidArgumentException('Untrusted or non-ChatGPT account scope.');
        }
        self::exact($http,['status','content_type','redirected','body']);
        if($http['status']!==200 || $http['redirected']!==false ||
           !is_string($http['content_type']) ||
           !preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD',$http['content_type']) ||
           !is_string($http['body']) || $http['body']==='' ||
           strlen($http['body'])>65536) {
            throw new InvalidArgumentException('ChatGPT account metadata HTTP mock unavailable.');
        }
        try {
            $data=json_decode($http['body'],true,16,JSON_THROW_ON_ERROR);
            $raw=json_decode($http['body'],false,16,JSON_THROW_ON_ERROR);
        } catch(JsonException) {
            throw new InvalidArgumentException('Invalid ChatGPT Ads account JSON.');
        }
        if(!is_object($raw) || !is_array($data) || array_is_list($data) ||
           !is_array($data['review']??null) ||
           !is_object($raw->review??null)) {
            throw new InvalidArgumentException('Malformed ad account JSON object/review.');
        }
        self::allowed($data,[
            'id','name','legal_name','account_name','brand_name',
            'url','preview_url','status','timezone','currency_code','review'
        ]);
        self::allowed($data['review'],['status']);
        if(!array_key_exists('id',$data) ||
           $data['id']!==$scope['account_id'] ||
           ($data['currency_code']??null)!=='BRL' ||
           ($data['timezone']??null)!==$scope['account_time_zone'] ||
           ($data['status']??null)!=='active' ||
           ($data['review']['status']??null)!=='approved' ||
           !is_string($data['name']??null) ||
           $data['name']==='' || strlen($data['name'])>1000 ||
           !is_string($data['url']??null) ||
           strlen($data['url'])>2048 ||
           !array_key_exists('preview_url',$data) ||
           (!is_null($data['preview_url'])&&!is_string($data['preview_url']))) {
            throw new InvalidArgumentException('Ad account not active/approved or mismatched ID, currency or timezone.');
        }
        // An "active" account may still have pending review. Both values are
        // checked explicitly, but this only tests a canned JSON response.
        return [
            'tenant_id'=>$scope['tenant_id'],
            'agreement_id'=>$scope['agreement_id'],
            'period'=>$scope['period'],
            'provider'=>'chatgpt_ads',
            'account_id'=>$scope['account_id'],
            'account_currency'=>'BRL',
            'account_time_zone'=>$scope['account_time_zone'],
            'metadata_shape_matches_expected_scope'=>true,
            'synthetic_status_claimed_active'=>true,
            'synthetic_brand_review_claimed_approved'=>true,
            'state'=>'synthetic_chatgpt_account_preflight_no_api_key_verification',
            'api_key_verified_by_provider'=>false,
            'provider_account_owner_verified'=>false,
            'portal_iam_authenticated'=>false,
            'network_enabled'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    /** @param array<string,mixed> $values @param list<string> $expected */
    private static function exact(array $values,array $expected):void
    {
        $actual=array_keys($values);
        sort($actual,SORT_STRING);
        sort($expected,SORT_STRING);
        if($actual!==$expected) {
            throw new InvalidArgumentException('Unexpected HTTP mock field.');
        }
    }
    /** @param array<string,mixed> $values @param list<string> $allowed */
    private static function allowed(array $values,array $allowed):void
    {
        foreach(array_keys($values) as $key){
            if(!is_string($key)||!in_array($key,$allowed,true)){
                throw new InvalidArgumentException('Unapproved account metadata field.');
            }
        }
    }
}
