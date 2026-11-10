<?php
declare(strict_types=1);

namespace App\Performance\Contracts;

use JsonException;

/**
 * F1-83: parse the CURRENT SYNTHETIC Portal event shape and compare its
 * canonical bytes with the Portal's fingerprint algorithm.
 *
 * This is NOT an inbound authenticated API, a provisioning operation, or
 * a real Portal authority. There is deliberately no "allowed" outcome.
 */
final class PortalOnboardingEventContract
{
    private const FIELDS = [
        'schema_version','event_type','producer','event_id',
        'correlation_id','portal_organization_id',
        'demo_onboarding_id','proposal_fingerprint',
    ];

    private static function denied(string $reason): array
    {
        return [
            'schema_version'=>'perf.portal.onboarding.parity.synthetic.v1',
            'status'=>$reason,
            'fingerprint_sha256'=>null,
            'synthetic_only'=>true,
            'portal_authenticated'=>false,
            'provisioning_allowed'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'ready_for_production'=>false,
        ];
    }

    public function validate(
        string $rawJson,
        string $expectedOrganizationId,
        string $expectedOnboardingId,
        ?string $expectedSha256 = null
    ): array {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F83_ONBOARDING_PARITY_LAB')!=='1') {
            return self::denied('lab_disabled');
        }
        if (strlen($rawJson)<30 || strlen($rawJson)>4096
            || !self::organization($expectedOrganizationId)
            || !self::onboarding($expectedOnboardingId)
            || ($expectedSha256!==null && !self::hex($expectedSha256,64))) {
            return self::denied('invalid_expected_context');
        }
        try {
            $body=json_decode($rawJson,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($body) || array_keys($body)!==self::FIELDS) {
                return self::denied('invalid_shape_or_order');
            }
            // Reject whitespace drift, key duplication, scalar type substitution,
            // repeated keys, reordered keys and noncanonical JSON encodings.
            $canonical=json_encode($body,JSON_THROW_ON_ERROR);
            if ($canonical!==$rawJson) {
                return self::denied('noncanonical_json');
            }
        }catch(JsonException) {
            return self::denied('invalid_json');
        }
        if ($body['schema_version']!=='0.1-draft'
            || $body['event_type']!=='performance.onboarding.mock_requested'
            || $body['producer']!=='portal-imagine-mock'
            || !is_string($body['event_id'])
            || !preg_match('/^evt-demo-[a-zA-Z0-9_-]{1,72}$/D',$body['event_id'])
            || !is_string($body['correlation_id'])
            || !preg_match('/^corr-demo-[a-zA-Z0-9_-]{1,72}$/D',$body['correlation_id'])
            || !self::organization($body['portal_organization_id'])
            || !self::onboarding($body['demo_onboarding_id'])
            || !self::hex($body['proposal_fingerprint'],64)) {
            return self::denied('unsupported_portal_demo_contract');
        }
        if ($body['portal_organization_id']!==$expectedOrganizationId
            || $body['demo_onboarding_id']!==$expectedOnboardingId) {
            return self::denied('context_mismatch');
        }
        $sha=hash('sha256',$rawJson);
        if ($expectedSha256!==null && !hash_equals($expectedSha256,$sha)) {
            return self::denied('fingerprint_mismatch');
        }
        return [
            'schema_version'=>'perf.portal.onboarding.parity.synthetic.v1',
            'status'=>'matched_untrusted_demo_event',
            'fingerprint_sha256'=>$sha,
            'synthetic_only'=>true,
            'portal_authenticated'=>false,
            'provisioning_allowed'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'ready_for_production'=>false,
        ];
    }

    private static function hex(mixed $value,int $size):bool
    {
        return is_string($value) && strlen($value)===$size
            && preg_match('/^[a-f0-9]+$/D',$value)===1;
    }

    private static function organization(mixed $id):bool
    {
        return is_string($id) && strlen($id)<=128
            && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}$/D',$id)===1;
    }

    private static function onboarding(mixed $id):bool
    {
        return is_string($id) && strlen($id)<=101
            && preg_match('/^demo_[a-zA-Z0-9][a-zA-Z0-9._:-]{0,95}$/D',$id)===1;
    }
}
