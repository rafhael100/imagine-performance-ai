<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * Strict CI-only verifier for Portal v6 agreement-bound HMAC.
 * Agreement ID is explicitly signed, unlike v5. Never authorizes Portal IAM,
 * real provider spend, Asaas, checkout, posting ads or a production contract.
 */
final class PortalV6AgreementSignedGuard
{
    private const CONTRACT='performance-trichannel-v6-agreement-candidate';
    private const AUDIENCE='imagine-performance-finance-v6-agreement-preview';
    private const CHANNELS=['chatgpt_ads','google_ads','meta_ads'];
    private const MAX_CENTS=100000000;

    /**
     * @param array<string,mixed> $signed
     * @param array<string,int> $ownerMinima trusted pinned agreement pricing
     * @param array<string,int> $unverifiedActual
     * @param callable(string,string):?string $resolveKey
     * @param callable(string,string,string,int):bool $claimNonce atomic when used with DB
     * @return array<string,mixed>
     */
    public static function verify(
        array $signed,
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $expectedCatalogSha256,
        array $ownerMinima,
        array $unverifiedActual,
        int $now,
        callable $resolveKey,
        callable $claimNonce
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_V6_AGREEMENT_LAB')!=='1') {
            throw new RuntimeException('Portal V6 agreement guard disabled outside CI.');
        }
        self::exact($signed,[
            'agreement_id','audience','billing_period','contract_version',
            'expires_at','issued_at','key_id','nonce','quote','signature','tenant_id'
        ]);
        $tenant=$signed['tenant_id'];
        $agreement=$signed['agreement_id'];
        $period=$signed['billing_period'];
        $keyId=$signed['key_id'];
        $nonce=$signed['nonce'];
        $signature=$signed['signature'];
        $issued=$signed['issued_at'];
        $expires=$signed['expires_at'];
        if(!is_string($tenant) ||
           !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$tenant) ||
           !hash_equals($expectedTenant,$tenant) ||
           !is_string($agreement) ||
           !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',$agreement) ||
           !hash_equals($expectedAgreement,$agreement) ||
           !is_string($period) ||
           !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$period) ||
           !hash_equals($expectedPeriod,$period) ||
           $signed['contract_version']!==self::CONTRACT ||
           $signed['audience']!==self::AUDIENCE ||
           !is_string($keyId) ||
           !preg_match('/\Ak_[A-Za-z0-9_-]{4,64}\z/D',$keyId) ||
           !is_string($nonce) ||
           !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$nonce) ||
           !is_int($issued) || !is_int($expires) ||
           $issued<=0 || $issued>$now+30 ||
           $expires<=$now || $expires<=$issued || $expires-$issued>300 ||
           !is_string($signature) ||
           !preg_match('/\A[a-f0-9]{64}\z/D',$signature) ||
           !preg_match('/\A[a-f0-9]{64}\z/D',$expectedCatalogSha256) ||
           !is_array($signed['quote'])) {
            throw new InvalidArgumentException('V6 agreement, tenant, period or HMAC metadata invalid.');
        }
        $minimaKeys=array_keys($ownerMinima);
        sort($minimaKeys,SORT_STRING);
        if($minimaKeys!==self::CHANNELS) {
            throw new InvalidArgumentException('Approved three-channel minima unavailable.');
        }
        foreach($ownerMinima as $minimum) {
            if(!is_int($minimum)||$minimum<1||$minimum>self::MAX_CENTS) {
                throw new InvalidArgumentException('Invalid approved channel minimum.');
            }
        }
        $unsigned=$signed;
        unset($unsigned['signature']);
        $json=json_encode(self::canonical($unsigned),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if(strlen($json)>8192) {
            throw new InvalidArgumentException('V6 agreement signed envelope too large.');
        }
        $signingBytes="imagine:portal:performance:quote:v6\n".$json;
        $secret=$resolveKey($tenant,$keyId);
        if(!is_string($secret)||strlen($secret)<32||
           !hash_equals(hash_hmac('sha256',$signingBytes,$secret),$signature)) {
            throw new InvalidArgumentException('V6 agreement HMAC verification failed.');
        }
        // All price/quote checks before claiming nonce.
        $quote=$signed['quote'];
        self::exact($quote,[
            'catalog_version','product','currency','gateway','real_charge_enabled',
            'media_by_channel_cents','media_paid_directly_to_platforms',
            'management_fee_basis_points','estimated_management_fee_cents',
            'reference_period','amount_now_cents','settlement_policy'
        ]);
        if(!is_string($quote['catalog_version']) ||
           !hash_equals($expectedCatalogSha256,$quote['catalog_version']) ||
           $quote['product']!=='performance' ||
           $quote['currency']!=='BRL' ||
           $quote['gateway']!=='asaas' ||
           $quote['real_charge_enabled']!==false ||
           $quote['media_paid_directly_to_platforms']!==true ||
           $quote['management_fee_basis_points']!==2000 ||
           $quote['reference_period']!=='monthly' ||
           $quote['settlement_policy']!==
              'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment' ||
           !is_array($quote['media_by_channel_cents']) ||
           $quote['media_by_channel_cents']===[] ||
           count($quote['media_by_channel_cents'])>3 ||
           !is_int($quote['estimated_management_fee_cents']) ||
           !is_int($quote['amount_now_cents'])) {
            throw new InvalidArgumentException('V6 commercial candidate not approved.');
        }
        $keys=array_keys($quote['media_by_channel_cents']);
        $actualKeys=array_keys($unverifiedActual);
        sort($keys,SORT_STRING);
        sort($actualKeys,SORT_STRING);
        if($keys!==$actualKeys||array_diff($keys,self::CHANNELS)!==[]) {
            throw new InvalidArgumentException('V6 channels differ from signed agreement.');
        }
        $plannedTotal=0;$actualTotal=0;
        foreach($quote['media_by_channel_cents'] as $channel=>$planned) {
            $actual=$unverifiedActual[$channel];
            if(!is_int($planned) || $planned<$ownerMinima[$channel] ||
               $planned>self::MAX_CENTS || !is_int($actual) ||
               $actual<0 || $actual>self::MAX_CENTS) {
                throw new InvalidArgumentException('Channel plan or synthetic spend outside approved bounds.');
            }
            $plannedTotal+=$planned;$actualTotal+=$actual;
        }
        $advance=self::fee($plannedTotal);
        $earned=self::fee($actualTotal);
        if($quote['estimated_management_fee_cents']!==$advance ||
           $quote['amount_now_cents']!==$advance) {
            throw new InvalidArgumentException('Incorrect V6 management advance.');
        }
        if($claimNonce($tenant,$keyId,$nonce,$expires)!==true) {
            throw new InvalidArgumentException('V6 agreement nonce reused or store unavailable.');
        }
        return [
            'tenant_id'=>$tenant,
            'agreement_id'=>$agreement,
            'billing_period'=>$period,
            'contract_version'=>self::CONTRACT,
            'quote_claim_hmac_verified'=>true,
            'billing_period_bound'=>true,
            'agreement_id_signed_in_envelope'=>true,
            'quote_claim_sha256'=>hash('sha256',$signingBytes),
            'catalog_sha256'=>$expectedCatalogSha256,
            'key_id'=>$keyId,'nonce'=>$nonce,
            'planned_media_cents'=>$plannedTotal,
            'unverified_actual_media_cents'=>$actualTotal,
            'estimated_advance_fee_cents'=>$advance,
            'unverified_earned_fee_cents'=>$earned,
            'review_only_adjustment_cents'=>$earned-$advance,
            'state'=>'portal_v6_signed_agreement_candidate_test_only',
            'portal_catalog_active'=>false,
            'portal_iam_authenticated'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    private static function fee(int $cents):int {
        return intdiv($cents*2000+5000,10000);
    }

    /** @param array<string,mixed> $map @param list<string> $expected */
    private static function exact(array $map,array $expected):void {
        $keys=array_keys($map);
        sort($keys,SORT_STRING);
        sort($expected,SORT_STRING);
        if($keys!==$expected) {
            throw new InvalidArgumentException('Unexpected V6 agreement signed field.');
        }
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private static function canonical(array $map):array {
        if($map===[]||array_is_list($map)) {
            throw new InvalidArgumentException('Unsigned map must use named fields.');
        }
        ksort($map,SORT_STRING);
        foreach($map as $key=>$value) {
            if(!is_string($key))throw new InvalidArgumentException('Invalid V6 canonical key.');
            if(is_array($value)) $map[$key]=self::canonical($value);
            elseif(!is_string($value)&&!is_int($value)&&!is_bool($value)) {
                throw new InvalidArgumentException('Invalid V6 canonical value.');
            }
        }
        return $map;
    }
}
