<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use RuntimeException;

/**
 * CI-only composition of the independently verified Portal V7 plan HMAC
 * and current synthetic PG acceptance event + durable nonce claim.
 *
 * The HMAC verifies BEFORE the callback. The callback receives the canonical
 * plan digest but must obtain acceptance from an independent SQL authority.
 * It can still be revoked after this check; no operational permission exists.
 */
final class PortalV7AcceptedPlanReviewLab
{
    private const ACCEPTANCE_DOMAIN="IMAGINE-V7-ACCEPTANCE-STABLE-PLAN-V1\n";

    /**
     * @param array<string,mixed> $envelope Portal V7 signed plan review
     * @param array<string,mixed> $activation Portal F419 signed review
     * @param array<string,mixed> $expected independently supplied identity and PSP context
     * @param list<string> $channels
     * @param array<string,int> $planned
     * @param callable(string,string):?string $keyResolver
     * @param callable(string,string,string,string,string,string,int,int):bool $acceptAndClaim
     * @return array<string,mixed>
     */
    public static function verify(
        array $envelope,
        array $activation,
        array $expected,
        array $channels,
        array $planned,
        string $nowUtc,
        #[\SensitiveParameter] string $activationSecret,
        string $expectedTermsSha256,
        callable $keyResolver,
        callable $acceptAndClaim
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_V7_ACCEPTED_PLAN_LAB')!=='1' ||
           getenv('PERF_PORTAL_V7_SIGNED_PLAN_LAB')!=='1'){
            throw new RuntimeException('Synthetic V7 agreement acceptance gate disabled.');
        }
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$expectedTermsSha256)){
            throw new InvalidArgumentException('Missing independently approved terms digest.');
        }
        $verified=PortalV7SignedPlanAgreementLabVerifier::verify(
            $envelope,$activation,$expected,$channels,$planned,
            $nowUtc,$activationSecret,$keyResolver,
            static function(
                string $tenant,string $agreement,string $nonce,int $expires
            )use($envelope,$expectedTermsSha256,$nowUtc,$acceptAndClaim):bool {
                $payload=$envelope['payload'];
                // A customer-accepted plan is stable across signing-key
                // rotation, independent nonces and short review lifetimes.
                // Never bind a durable acceptance to an ephemeral grant nonce.
                unset($payload['nonce'],$payload['issued_at'],
                    $payload['expires_at'],$payload['key_id']);
                $digest=hash('sha256',self::ACCEPTANCE_DOMAIN.json_encode(
                    self::canonical($payload),JSON_THROW_ON_ERROR|
                    JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
                ));
                $now=\DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i:s\Z',$nowUtc,new \DateTimeZone('UTC')
                );
                if(!$now || $now->format('Y-m-d\TH:i:s\Z')!==$nowUtc){
                    return false;
                }
                return $acceptAndClaim(
                    $tenant,$agreement,$payload['billing_period'],$digest,
                    $expectedTermsSha256,$nonce,$expires,$now->getTimestamp()
                )===true;
            }
        );
        if(($verified['hmac_verified_in_lab']??null)!==true ||
           ($verified['billable']??null)!==false ||
           ($verified['customer_acceptance_proven']??null)!==false ||
           ($verified['durable_nonce_verified']??null)!==false){
            throw new RuntimeException('Lab approval may not elevate operational authority.');
        }
        return array_replace($verified,[
            'state'=>'synthetic_pg18_acceptance_then_v7_nonce_consumed_in_lab',
            'synthetic_acceptance_and_nonce_claimed_in_lab'=>true,
            'real_customer_contract_authenticated'=>false,
            'customer_acceptance_proven'=>false,
            'portal_iam_authenticated'=>false,
            'durable_nonce_verified'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'c6_write_enabled'=>false,
            'ads_write_enabled'=>false,
            'crm_write_enabled'=>false,
        ]);
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private static function canonical(array $map):array {
        if(array_is_list($map)){
            return array_map(static fn(mixed $v):mixed=>
                is_array($v)?self::canonical($v):$v,$map);
        }
        ksort($map,SORT_STRING);
        foreach($map as $key=>$value){
            if(!is_string($key))throw new InvalidArgumentException('Invalid plan key.');
            if(is_array($value))$map[$key]=self::canonical($value);
            elseif(!is_int($value)&&!is_string($value)&&!is_bool($value)){
                throw new InvalidArgumentException('Invalid plan fingerprint field.');
            }
        }
        return $map;
    }
}
