<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * HTTP-shaped response classifier for simulated provider reporting only.
 * Does not perform HTTP, retry, token lookup, provider auth or spend parsing.
 * A valid-looking HTTP 200 never turns a fixture into verified Ads evidence.
 */
final class AdsReadOnlyHttpResponseReview
{
    private const MAX_BODY_BYTES=524288;

    /** @param array<string,mixed> $http @return array<string,mixed> */
    public static function assess(string $provider,array $http):array
    {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_ADS_READONLY_BOUNDARY_LAB')!=='1') {
            throw new RuntimeException('Read-only HTTP report lab disabled.');
        }
        if(!in_array($provider,['google_ads','meta_ads','chatgpt_ads'],true)) {
            throw new InvalidArgumentException('Unsupported mock provider.');
        }
        self::keys($http,['status','content_type','redirected','body']);
        if(!is_int($http['status']) ||
           !is_bool($http['redirected']) ||
           !is_string($http['content_type']) ||
           !is_string($http['body']) ||
           strlen($http['body'])>self::MAX_BODY_BYTES ||
           $http['redirected']) {
            throw new InvalidArgumentException('HTTP fixture status/redirect/content invalid.');
        }
        $status=$http['status'];
        if($status!==200) {
            if(!in_array($status,[400,401,403,404,408,409,429,500,502,503,504],true)) {
                throw new InvalidArgumentException('Unexpected provider HTTP response status.');
            }
            $manualRetry=in_array($status,[408,429,500,502,503,504],true);
            $code=match($status){
                401,403=>'authentication_or_permission_failure',
                429=>'provider_quota_or_rate_limit',
                408,500,502,503,504=>'transient_upstream_failure',
                400,404,409=>'invalid_request_or_resource',
            };
            // Never serialize provider error body, which can contain tokens,
            // personal data, internal IDs, or cross-tenant information.
            return self::result($provider,$status,'failed_'.$code,0,false,$manualRetry);
        }
        if(!preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD',$http['content_type']) ||
           $http['body']==='') {
            throw new InvalidArgumentException('Provider 200 fixture must contain JSON.');
        }
        try {
            $json=json_decode($http['body'],true,32,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
        } catch(JsonException) {
            throw new InvalidArgumentException('Malformed provider HTTP JSON.');
        }
        if(!is_array($json)) {
            throw new InvalidArgumentException('Unexpected provider response collection.');
        }
        if($provider==='google_ads'){
            // Google Ads SearchStream REST is a JSON list of batch objects.
            if(!array_is_list($json)||count($json)>20) {
                throw new InvalidArgumentException('Google SearchStream must be batches array.');
            }
            $count=0;
            foreach($json as $batch){
                if(!is_array($batch)||array_is_list($batch)||
                   !array_key_exists('results',$batch) ||
                   !is_array($batch['results'])||
                   !array_is_list($batch['results'])) {
                    throw new InvalidArgumentException('Google SearchStream batch malformed.');
                }
                foreach(array_keys($batch) as $key){
                    if(!in_array($key,[
                        'results','requestId','fieldMask','queryResourceConsumption'
                    ],true)){
                        throw new InvalidArgumentException('Google SearchStream field not allowed.');
                    }
                }
                $count+=count($batch['results']);
                if($count>35)throw new InvalidArgumentException('Too many Google daily report rows.');
            }
        } elseif($provider==='meta_ads'){
            if(array_is_list($json)||!isset($json['data']) ||
               !is_array($json['data'])||!array_is_list($json['data']) ||
               count($json['data'])>35) {
                throw new InvalidArgumentException('Meta Insights data not complete array.');
            }
            foreach(array_keys($json) as $key) {
                if(!in_array($key,['data','paging'],true)) {
                    throw new InvalidArgumentException('Meta Insights unexpected field.');
                }
            }
            if(isset($json['paging'])) {
                if(!is_array($json['paging']) ||
                   isset($json['paging']['next']) &&
                   $json['paging']['next']!==null &&
                   $json['paging']['next']!=='') {
                    // Do NOT follow an arbitrary external next-page URL.
                    throw new InvalidArgumentException('Meta pagination is incomplete.');
                }
            }
            $count=count($json['data']);
        } else {
            // OPENAI Advertiser API: GET /v1/ad_account/insights lists data,
            // object, count and has_more (see official Ads API docs).
            if(array_is_list($json)||($json['object']??null)!=='list' ||
               ($json['has_more']??null)!==false ||
               !is_int($json['count']??null) ||
               !is_array($json['data']??null)||
               !array_is_list($json['data']) ||
               count($json['data'])>35 ||
               $json['count']!==count($json['data'])) {
                throw new InvalidArgumentException('ChatGPT Ads Insights incomplete/malformed page.');
            }
            foreach(array_keys($json) as $field){
                if(!in_array($field,[
                    'object','count','data','first_id','last_id','has_more'
                ],true)) {
                    throw new InvalidArgumentException('ChatGPT Ads Insights unexpected field.');
                }
            }
            $count=count($json['data']);
        }
        // This ONLY indicates structural syntax. It does not validate
        // account, metadata, permission, pagination provenance or actual spend.
        return self::result($provider,200,'synthetic_json_shape_only', $count,true,false);
    }

    private static function result(
        string $provider,int $status,string $reason,int $rows,
        bool $jsonShapeOk,bool $manualRetry
    ):array {
        return [
            'provider'=>$provider,
            'http_status'=>$status,
            'state'=>$reason,
            'synthetic_json_shape_ok'=>$jsonShapeOk,
            'synthetic_row_count'=>$rows,
            'manual_retry_review_recommended'=>$manualRetry,
            'automatic_retry_enabled'=>false,
            'network_enabled'=>false,
            'provider_identity_authenticated'=>false,
            'provider_spend_verified'=>false,
            'safe_to_bill'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    /** @param array<string,mixed> $value @param list<string> $expected */
    private static function keys(array $value,array $expected):void {
        $actual=array_keys($value);
        sort($actual,SORT_STRING);
        sort($expected,SORT_STRING);
        if($actual!==$expected) {
            throw new InvalidArgumentException('Unexpected raw HTTP response field.');
        }
    }
}
