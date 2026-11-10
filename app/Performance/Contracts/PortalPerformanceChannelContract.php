<?php
declare(strict_types=1);

namespace App\Performance\Contracts;

/**
 * F1-82: explicit, versioned codec for the two internal campaign channel
 * identifiers and the Portal's public catalog enum. Static contract only:
 * no HTTP, Laravel service binding, authorization, advertising or billing.
 */
final class PortalPerformanceChannelContract
{
    public const SCHEMA='perf.portal.channel-map.synthetic.v1';

    private const INTERNAL_TO_PORTAL=[
        'google_ads'=>'google_ads',
        'meta'=>'meta_ads',
    ];

    public static function toPortal(mixed $internal): ?string
    {
        if(!is_string($internal)) return null;
        return self::INTERNAL_TO_PORTAL[$internal]??null;
    }

    public static function fromPortal(mixed $portal): ?string
    {
        if(!is_string($portal)) return null;
        return match($portal) {
            'google_ads'=>'google_ads',
            'meta_ads'=>'meta',
            default=>null,
        };
    }

    /** No caller-controlled tenant, permissions or operational state. */
    public static function describe(mixed $internal): ?array
    {
        $portal=self::toPortal($internal);
        if($portal===null) return null;
        return [
            'schema_version'=>self::SCHEMA,
            'internal_channel'=>$internal,
            'portal_channel'=>$portal,
            'source'=>'offline_contract_only',
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
        ];
    }
}
