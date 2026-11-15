<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Independent CI-only verification of the Portal F419 signed Performance
 * activation *review*. It is NOT an executed customer contract, payment
 * mandate, authorization to spend, actual advertiser identity or invoice.
 *
 * The Portal-side issuer is pinned to an exact reviewed commit in CI. The
 * independent org/order/revision, channel eligibility and CRM review MAC must
 * come from the test's expected context, never copied from the wire payload.
 */
final class PortalF419SignedActivationReviewV7LabVerifier
{
    private const POLICY='performance-postpaid-170-per-channel-2026-11-14';
    private const SCHEMA='imagine.portal.performance.activation-channels.review.v1';
    private const CONTEXT="IMAGINE-PERF-CHANNEL-REVIEW-OFFLINE-V1\n";
    private const CHANNELS=['chatgpt_ads','google_ads','meta_ads'];
    private const ACTIVATION_PER_CHANNEL_CENTS=49000;
    private const MANAGE_MINIMUM_PER_CHANNEL_CENTS=17000;
    private const MANAGEMENT_BPS=2000;
    private const TTL_SECONDS=900;

    /**
     * @param array<string,mixed> $envelope issued by pinned Portal PHP class
     * @param list<string> $expectedChannels independently selected and eligible
     * @return array<string,mixed> never grant of operational authority
     */
    public static function verify(
        array $envelope,
        array $expectedChannels,
        string $expectedOrganization,
        string $expectedOrder,
        int $expectedRevision,
        string $expectedGateway,
        string $expectedMethod,
        string $expectedCrmMac,
        string $nowUtc,
        #[\SensitiveParameter] string $key
    ): array {
        if (getenv('APP_ENV')!=='testing' ||
            getenv('PERF_PORTAL_F419_V7_REVIEW_LAB')!=='1') {
            throw new RuntimeException('Pinned Portal F419 review verifier disabled.');
        }
        if (strlen($key)<32 || strlen($key)>128 ||
            !preg_match('/\A[A-Za-z0-9._:-]{3,128}\z/D',$expectedOrganization) ||
            !preg_match('/\A[A-Za-z0-9._:-]{3,128}\z/D',$expectedOrder) ||
            $expectedRevision<1 || $expectedRevision>1000000 ||
            !in_array($expectedGateway,['asaas','c6'],true) ||
            !preg_match('/\A[a-z_]{2,32}\z/D',$expectedMethod) ||
            !preg_match('/\A[a-f0-9]{64}\z/D',$expectedCrmMac)) {
            throw new InvalidArgumentException('Invalid independent Portal review context.');
        }
        if (!array_is_list($expectedChannels) ||
            count($expectedChannels)<1 || count($expectedChannels)>3) {
            throw new InvalidArgumentException('Expected nonempty server-selected channels.');
        }
        foreach ($expectedChannels as $channel) {
            if (!is_string($channel) || !in_array($channel,self::CHANNELS,true)) {
                throw new InvalidArgumentException('Unsupported channel in expected commercial scope.');
            }
        }
        $canonicalChannels=$expectedChannels;
        sort($canonicalChannels,SORT_STRING);
        if (count(array_unique($canonicalChannels))!==count($canonicalChannels)) {
            throw new InvalidArgumentException('Repeated channel in independently selected scope.');
        }
        self::exact($envelope,['payload','mac']);
        $payload=$envelope['payload'];
        $mac=$envelope['mac'];
        if (!is_array($payload) || !is_string($mac) ||
            !preg_match('/\A[a-f0-9]{64}\z/D',$mac)) {
            throw new InvalidArgumentException('Invalid signed review envelope.');
        }
        self::exact($payload,[
            'schema_version','organization_ref','order_ref','policy_revision',
            'channels','activation_cents','gateway','method','crm_review_mac',
            'issued_at','expires_at','management_fee_postpaid_basis_points',
            'minimum_management_fee_cents','postpaid_policy_version',
            'media_paid_directly_to_platforms','can_charge','can_activate',
            'can_collect_personal_data','environment',
        ]);
        if ($payload['schema_version']!==self::SCHEMA ||
            $payload['organization_ref']!==$expectedOrganization ||
            $payload['order_ref']!==$expectedOrder ||
            $payload['policy_revision']!==$expectedRevision ||
            $payload['channels']!==$canonicalChannels ||
            $payload['activation_cents']!==self::ACTIVATION_PER_CHANNEL_CENTS*count($canonicalChannels) ||
            $payload['gateway']!==$expectedGateway ||
            $payload['method']!==$expectedMethod ||
            $payload['crm_review_mac']!==$expectedCrmMac ||
            $payload['management_fee_postpaid_basis_points']!==self::MANAGEMENT_BPS ||
            $payload['minimum_management_fee_cents']!==self::MANAGE_MINIMUM_PER_CHANNEL_CENTS ||
            $payload['postpaid_policy_version']!==self::POLICY ||
            $payload['media_paid_directly_to_platforms']!==true ||
            $payload['can_charge']!==false ||
            $payload['can_activate']!==false ||
            $payload['can_collect_personal_data']!==false ||
            $payload['environment']!=='offline_review' ||
            !is_string($payload['issued_at']) ||
            !is_string($payload['expires_at'])) {
            throw new InvalidArgumentException('Signed activation review scope/price/mode mismatch.');
        }
        $issued=self::utc($payload['issued_at']);
        $expires=self::utc($payload['expires_at']);
        $now=self::utc($nowUtc);
        if ($expires->getTimestamp()-$issued->getTimestamp()!==self::TTL_SECONDS ||
            $now<$issued || $now>$expires) {
            throw new InvalidArgumentException('Invalid signed review validity interval.');
        }
        $canonical=self::canonical($payload);
        $bytes=self::CONTEXT.json_encode($canonical,
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        if (!hash_equals(hash_hmac('sha256',$bytes,$key),$mac)) {
            throw new InvalidArgumentException('Signed review HMAC failed.');
        }
        return [
            'state'=>'pinned_portal_f419_signed_activation_review_test_only',
            'organization_ref'=>$expectedOrganization,
            'order_ref'=>$expectedOrder,
            'policy_revision'=>$expectedRevision,
            'channels'=>$canonicalChannels,
            'activation_review_cents'=>$payload['activation_cents'],
            'management_postpaid_policy'=>self::POLICY,
            'minimum_management_fee_per_channel_cents'=>self::MANAGE_MINIMUM_PER_CHANNEL_CENTS,
            'management_basis_points'=>self::MANAGEMENT_BPS,
            'review_hmac_sha256'=>hash('sha256',$bytes),
            'crm_review_mac_bound_to_expected_context_in_lab'=>true,
            'hmac_verified_in_lab'=>true,
            'portal_iam_authenticated'=>false,
            'real_customer_contract_authenticated'=>false,
            'real_psp_approved'=>false,
            'media_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'activation_enabled'=>false,
            'asaas_write_enabled'=>false,
            'c6_write_enabled'=>false,
            'ads_write_enabled'=>false,
            'crm_write_enabled'=>false,
        ];
    }

    /** @param array<string,mixed> $map @param list<string> $fields */
    private static function exact(array $map,array $fields):void
    {
        $keys=array_keys($map);
        sort($keys,SORT_STRING);
        sort($fields,SORT_STRING);
        if($keys!==$fields)throw new InvalidArgumentException('Unexpected signed review fields.');
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private static function canonical(array $map):array
    {
        if(array_is_list($map)){
            return array_map(static fn(mixed $v):mixed=>
                is_array($v)?self::canonical($v):$v,$map);
        }
        ksort($map,SORT_STRING);
        foreach($map as $key=>$value){
            if(!is_string($key))throw new InvalidArgumentException('Invalid canonical key.');
            if(is_array($value))$map[$key]=self::canonical($value);
            elseif(!is_string($value)&&!is_int($value)&&!is_bool($value)) {
                throw new InvalidArgumentException('Noncanonical review field.');
            }
        }
        return $map;
    }

    private static function utc(string $date):DateTimeImmutable
    {
        if(!preg_match('/\A20[0-9]{2}-(0[1-9]|1[0-2])-[0-3][0-9]T[0-2][0-9]:[0-5][0-9]:[0-5][0-9]Z\z/D',$date)) {
            throw new InvalidArgumentException('Expected strict UTC timestamp.');
        }
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',
            $date,new DateTimeZone('UTC'));
        if(!$d || $d->format('Y-m-d\TH:i:s\Z')!==$date){
            throw new InvalidArgumentException('Invalid calendar UTC timestamp.');
        }
        return $d;
    }
}
