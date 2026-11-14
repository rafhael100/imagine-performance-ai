<?php
declare(strict_types=1);

require_once __DIR__ . '/../portal-v6-reference/apps/portal/app/Homologation/Commerce/PerformanceTriChannelV5CandidateCatalog.php';
require_once __DIR__ . '/../portal-v6-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceV6AgreementLabIssuer.php';

use App\Homologation\Commerce\PerformanceTriChannelV5CandidateCatalog as V5Catalog;
use App\Homologation\Commerce\PortalPerformanceV6AgreementLabIssuer as V6Issuer;

final class V6SignedAgreementCases
{
    public const NOW = 1794660000;
    public const KEY_ID = 'k_v6_multi_lab_01';
    private const SECRET = 'SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS';
    public const ALPHA = 'tenant_v6multi_alpha_123456';
    public const BETA = 'tenant_v6multi_beta_567890';

    /** @return array<string,mixed> */
    public static function prepare(): array
    {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PORTAL_PERF_TRICHANNEL_V5_LAB') !== '1' ||
            getenv('PORTAL_PERF_V6_AGREEMENT_LAB') !== '1') {
            throw new RuntimeException('Multiagreement fixture issuer is testing-only.');
        }
        $catalog = new V5Catalog();
        $rows = [
            [
                'tenant'=>self::ALPHA,'agreement'=>'agreement_alpha_brand_01',
                'period'=>'2026-11','nonce'=>'nonce_multi_v6_alpha_01_123456',
                'plan'=>['google_ads'=>100000,'meta_ads'=>70000],
                'records'=>[
                    ['channel'=>'google_ads','period'=>'2026-11','report_id'=>'g_alpha_nov_01','source'=>'synthetic_fixture','spend_cents'=>80000],
                    ['channel'=>'meta_ads','period'=>'2026-11','report_id'=>'m_alpha_nov_01','source'=>'synthetic_fixture','spend_cents'=>50000],
                ],
            ],
            [
                'tenant'=>self::ALPHA,'agreement'=>'agreement_alpha_brand_02',
                'period'=>'2026-11','nonce'=>'nonce_multi_v6_alpha_02_123456',
                'plan'=>['chatgpt_ads'=>120000],
                'records'=>[
                    ['channel'=>'chatgpt_ads','period'=>'2026-11','report_id'=>'c_alpha_nov_02','source'=>'synthetic_fixture','spend_cents'=>0],
                ],
            ],
            [
                'tenant'=>self::BETA,'agreement'=>'agreement_alpha_brand_01',
                'period'=>'2026-11','nonce'=>'nonce_multi_v6_beta_01_1234567',
                'plan'=>['chatgpt_ads'=>120000,'meta_ads'=>70000],
                'records'=>[
                    ['channel'=>'chatgpt_ads','period'=>'2026-11','report_id'=>'c_beta_nov_01','source'=>'synthetic_fixture','spend_cents'=>80000],
                    ['channel'=>'meta_ads','period'=>'2026-11','report_id'=>'m_beta_nov_01','source'=>'synthetic_fixture','spend_cents'=>80000],
                ],
            ],
            [
                'tenant'=>self::ALPHA,'agreement'=>'agreement_alpha_brand_01',
                'period'=>'2026-12','nonce'=>'nonce_multi_v6_alpha_dec_123456',
                'plan'=>['google_ads'=>100000],
                'records'=>[
                    ['channel'=>'google_ads','period'=>'2026-12','report_id'=>'g_alpha_dec_01','source'=>'synthetic_fixture','spend_cents'=>0],
                ],
            ],
        ];
        foreach ($rows as &$row) {
            $row['signed'] = V6Issuer::issue($catalog,$row['plan'],$row['tenant'],$row['agreement'],
                $row['period'],self::KEY_ID,$row['nonce'],
                self::NOW-3,self::NOW+120,self::SECRET
            );
            $row['quote_hash'] = self::quoteClaim($row['signed']);
        }
        unset($row);
        return [
            'catalog_sha256'=>$catalog->version(),
            'minima'=>$catalog->minima(),
            'cases'=>$rows,
        ];
    }

    public static function quoteClaim(array $signed): string
    {
        $signature = $signed['signature'] ?? null;
        if (!is_string($signature)) throw new RuntimeException('Fixture must be signed.');
        unset($signed['signature']);
        $bytes = "imagine:portal:performance:quote:v6\n" .
            json_encode(self::sorted($signed),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!hash_equals(hash_hmac('sha256',$bytes,self::SECRET),$signature)) {
            throw new RuntimeException('Pinned Portal issuer fixture signature invalid.');
        }
        return hash('sha256',$bytes);
    }

    public static function resolveKey(string $tenant,string $keyId): ?string
    {
        return in_array($tenant,[self::ALPHA,self::BETA],true) &&
            $keyId===self::KEY_ID ? self::SECRET : null;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private static function sorted(array $input): array
    {
        ksort($input,SORT_STRING);
        foreach ($input as $k=>$value) {
            if (is_array($value)) $input[$k]=self::sorted($value);
        }
        return $input;
    }
}
