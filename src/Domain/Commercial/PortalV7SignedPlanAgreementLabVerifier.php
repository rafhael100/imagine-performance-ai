<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Independent verifier for actual pinned Portal V7 PLAN review issuer.
 * Domain-separated from Portal F419 activation review and the older V6
 * finance HMAC. No authenticated customer acceptance, real IAM, invoice,
 * provider statement, ads mutation or operational S2S connection.
 *
 * The caller provides independently expected tenant/agreement/period,
 * organization/order, PSP/CRM review MAC and channel media plan.
 * The callback is NOT durable: only real authenticated SQL can enforce
 * one-time processing between different worker processes.
 */
final class PortalV7SignedPlanAgreementLabVerifier
{
    private const TYPE='imagine.portal.performance.v7-plan-agreement-review';
    private const DOMAIN="IMAGINE-PORTAL-PERFORMANCE-V7-PLAN-AGREEMENT-LAB\n";
    private const POLICY='performance-postpaid-170-per-channel-2026-11-14';
    private const MIN_MEDIA=[
        'chatgpt_ads'=>120000,
        'google_ads'=>100000,
        'meta_ads'=>70000,
    ];
    private const MAX_CENTS=100000000;
    private const FEE_MIN_CENTS=17000;
    private const BPS=2000;

    /**
     * @param array<string,mixed> $envelope Actual Portal V7 candidate HMAC
     * @param array<string,mixed> $activation Actual Portal F419 signed envelope
     * @param array<string,mixed> $expected Independently supplied context:
     * tenant_id,agreement_id,period,organization_ref,order_ref,
     * policy_revision,gateway,method,crm_review_mac,key_id.
     * @param list<string> $expectedChannels
     * @param array<string,int> $expectedPlannedMedia
     * @param callable(string,string):?string $resolveKey
     * @param callable(string,string,string,int):bool $claimNonce
     * @return array<string,mixed>
     */
    public static function verify(
        array $envelope,
        array $activation,
        array $expected,
        array $expectedChannels,
        array $expectedPlannedMedia,
        string $nowUtc,
        #[\SensitiveParameter] string $activationReviewSecret,
        callable $resolveKey,
        callable $claimNonce
    ): array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_V7_SIGNED_PLAN_LAB')!=='1' ||
           getenv('PERF_PORTAL_F419_V7_REVIEW_LAB')!=='1') {
            throw new RuntimeException('V7 plan agreement review lab is disabled.');
        }
        self::exact($expected,[
            'tenant_id','agreement_id','period','organization_ref',
            'order_ref','policy_revision','gateway','method','crm_review_mac','key_id',
        ]);
        foreach([
            'tenant_id'=>'/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',
            'agreement_id'=>'/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',
            'period'=>'/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',
            'key_id'=>'/\Ak_[A-Za-z0-9_-]{4,64}\z/D',
        ] as $field=>$regex){
            if(!is_string($expected[$field]) ||
                !preg_match($regex,$expected[$field])) {
                throw new InvalidArgumentException('Invalid independently expected V7 plan scope.');
            }
        }
        $review=PortalF419SignedActivationReviewV7LabVerifier::verify(
            $activation,$expectedChannels,$expected['organization_ref'],
            $expected['order_ref'],$expected['policy_revision'],
            $expected['gateway'],$expected['method'],$expected['crm_review_mac'],
            $nowUtc,$activationReviewSecret
        );
        $channels=$review['channels'];
        if(array_keys($expectedPlannedMedia)!==$channels) {
            throw new InvalidArgumentException('Expected media plan must include exact signed channel selection.');
        }
        $sum=0;$estimated=0;
        foreach($expectedPlannedMedia as $channel=>$planned){
            if(!is_int($planned) || !isset(self::MIN_MEDIA[$channel]) ||
                $planned<self::MIN_MEDIA[$channel] || $planned>self::MAX_CENTS) {
                throw new InvalidArgumentException('Invalid independently expected channel media budget.');
            }
            $sum+=$planned;
            $estimated+=max(self::FEE_MIN_CENTS,self::fee($planned));
        }
        self::exact($envelope,['payload','mac']);
        $payload=$envelope['payload'];
        $mac=$envelope['mac'];
        if(!is_array($payload) || !is_string($mac) ||
           !preg_match('/\A[a-f0-9]{64}\z/D',$mac)) {
            throw new InvalidArgumentException('Invalid V7 plan HMAC wrapper.');
        }
        self::exact($payload,[
            'version','type','issuer','audience',
            'tenant_id','agreement_id','billing_period','organization_ref',
            'order_ref','policy_revision','channels',
            'planned_media_by_channel_cents','planned_media_total_cents',
            'activation_cents','activation_review_mac',
            'postpaid_policy_version','management_basis_points',
            'minimum_management_fee_per_channel_cents',
            'estimated_management_fee_cents','minimum_scope',
            'partial_first_month_rule','media_paid_directly_to_platforms',
            'customer_acceptance_proven','portal_iam_authenticated',
            'provider_spend_verified','billable','can_charge',
            'can_activate','ads_write_enabled','key_id','nonce',
            'issued_at','expires_at',
        ]);
        if($payload['version']!=='1' ||
           $payload['type']!==self::TYPE ||
           $payload['issuer']!=='imagine-portal-ci' ||
           $payload['audience']!=='imagine-performance-ai.v7-plan-lab' ||
           $payload['tenant_id']!==$expected['tenant_id'] ||
           $payload['agreement_id']!==$expected['agreement_id'] ||
           $payload['billing_period']!==$expected['period'] ||
           $payload['organization_ref']!==$expected['organization_ref'] ||
           $payload['order_ref']!==$expected['order_ref'] ||
           $payload['policy_revision']!==$expected['policy_revision'] ||
           $payload['channels']!==$channels ||
           $payload['planned_media_by_channel_cents']!==$expectedPlannedMedia ||
           $payload['planned_media_total_cents']!==$sum ||
           $payload['activation_cents']!==$review['activation_review_cents'] ||
           $payload['activation_review_mac']!==$activation['mac'] ||
           $payload['postpaid_policy_version']!==self::POLICY ||
           $payload['management_basis_points']!==self::BPS ||
           $payload['minimum_management_fee_per_channel_cents']!==self::FEE_MIN_CENTS ||
           $payload['estimated_management_fee_cents']!==$estimated ||
           $payload['minimum_scope']!=='per_active_channel_full_month' ||
           $payload['partial_first_month_rule']!=='finance_and_contract_review_required' ||
           $payload['media_paid_directly_to_platforms']!==true ||
           $payload['customer_acceptance_proven']!==false ||
           $payload['portal_iam_authenticated']!==false ||
           $payload['provider_spend_verified']!==false ||
           $payload['billable']!==false ||
           $payload['can_charge']!==false ||
           $payload['can_activate']!==false ||
           $payload['ads_write_enabled']!==false ||
           $payload['key_id']!==$expected['key_id'] ||
           !is_string($payload['nonce']) ||
           !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$payload['nonce']) ||
           !is_int($payload['issued_at']) || !is_int($payload['expires_at'])) {
            throw new InvalidArgumentException('Signed V7 agreement review scope/fee/authority mismatch.');
        }
        $now=self::utc($nowUtc)->getTimestamp();
        if($payload['issued_at']<=0 ||
           $payload['issued_at']>$now+30 ||
           $payload['issued_at']<$now-30 ||
           $payload['expires_at']<=$now ||
           $payload['expires_at']<=$payload['issued_at'] ||
           $payload['expires_at']-$payload['issued_at']>300) {
            throw new InvalidArgumentException('V7 signing timestamp or expiration not valid.');
        }
        $unsigned=self::canonical($payload);
        $bytes=self::DOMAIN.json_encode($unsigned,JSON_THROW_ON_ERROR|
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if(strlen($bytes)>8192)throw new InvalidArgumentException('V7 plan signed data too large.');
        $key=$resolveKey('imagine-portal-ci',$expected['key_id']);
        if(!is_string($key) || strlen($key)<32 || strlen($key)>128 ||
           hash_equals($key,$activationReviewSecret) ||
           !hash_equals(hash_hmac('sha256',$bytes,$key),$mac)) {
            throw new InvalidArgumentException('V7 agreement HMAC verification failed.');
        }
        // Late nonce claim only AFTER all independent scope, price, HMAC and
        // time validations. Callback is in-memory CI, not durable production.
        if($claimNonce($expected['tenant_id'],$expected['agreement_id'],
            $payload['nonce'],$payload['expires_at'])!==true) {
            throw new InvalidArgumentException('V7 signed plan nonce rejected or replayed.');
        }
        return [
            'state'=>'verified_synthetic_portal_v7_plan_agreement_review_only',
            'tenant_id'=>$expected['tenant_id'],
            'agreement_id'=>$expected['agreement_id'],
            'billing_period'=>$expected['period'],
            'channels'=>$channels,
            'planned_media_total_cents'=>$sum,
            'estimated_management_fee_cents'=>$estimated,
            'activation_review_cents'=>$review['activation_review_cents'],
            'minimum_management_fee_per_channel_cents'=>self::FEE_MIN_CENTS,
            'management_basis_points'=>self::BPS,
            'postpaid_policy_version'=>self::POLICY,
            'signed_plan_sha256'=>hash('sha256',$bytes),
            'hmac_verified_in_lab'=>true,
            'nonce_claimed_via_callback_in_lab'=>true,
            'durable_nonce_verified'=>false,
            'real_customer_contract_authenticated'=>false,
            'customer_acceptance_proven'=>false,
            'portal_iam_authenticated'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'c6_write_enabled'=>false,
            'ads_write_enabled'=>false,
            'crm_write_enabled'=>false,
        ];
    }

    private static function fee(int $cents):int {
        return intdiv($cents*self::BPS+5000,10000);
    }

    /** @param array<string,mixed> $map @param list<string> $fields */
    private static function exact(array $map,array $fields):void {
        $keys=array_keys($map);
        sort($keys,SORT_STRING); sort($fields,SORT_STRING);
        if($keys!==$fields)throw new InvalidArgumentException('Unexpected V7 plan fields.');
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private static function canonical(array $map):array {
        if(array_is_list($map)) {
            return array_map(static fn(mixed $v):mixed=>
                is_array($v)?self::canonical($v):$v,$map);
        }
        ksort($map,SORT_STRING);
        foreach($map as $key=>$v) {
            if(!is_string($key))throw new InvalidArgumentException('Invalid V7 key.');
            if(is_array($v))$map[$key]=self::canonical($v);
            elseif(!is_string($v)&&!is_int($v)&&!is_bool($v)) {
                throw new InvalidArgumentException('Invalid V7 signed type.');
            }
        }
        return $map;
    }

    private static function utc(string $value):DateTimeImmutable {
        if(!preg_match('/\A20[0-9]{2}-(0[1-9]|1[0-2])-[0-3][0-9]T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]Z\z/D',$value)) {
            throw new InvalidArgumentException('Invalid V7 UTC clock.');
        }
        $date=DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',$value,new DateTimeZone('UTC')
        );
        if(!$date || $date->format('Y-m-d\TH:i:s\Z')!==$value) {
            throw new InvalidArgumentException('Invalid V7 calendar timestamp.');
        }
        return $date;
    }
}
