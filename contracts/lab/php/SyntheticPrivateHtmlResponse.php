<?php
declare(strict_types=1);

namespace App\Performance\Security;

use PDO;

/**
 * F195: CI-only prototype of a private, non-cacheable response boundary.
 *
 * NOT a registered HTTP route, Portal IAM, private object store or deployment.
 * The pre-existing F194 HTML file is still directly accessible to a caller
 * with filesystem access. Only the returned response is controlled here.
 */
final class SyntheticPrivateHtmlResponse
{
    private const HEADERS = [
        'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' =>
            "default-src 'none'; script-src 'none'; connect-src 'none'; "
            ."img-src 'none'; form-action 'none'; frame-ancestors 'none'; "
            ."base-uri 'none'; object-src 'none'",
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];

    /**
     * Encapsulates F194 without surfacing paths, tenant identifiers,
     * failure details or raw arrays in any denied response.
     */
    public function serve(
        PDO $tenantSqlConnection,
        array $receipt,
        array $htmlResult
    ): array {
        $retrieved=(new SyntheticServiceCheckedHtmlRetrieval())
            ->fetch($tenantSqlConnection,$receipt,$htmlResult);
        return self::mapVerified($retrieved);
    }

    /**
     * F196 CI-only: original Portal F63 renders directly into a response
     * buffer. No HTML path is ever emitted by this method.
     * Every request performs a fresh service/epoch/F100 check via F194.
     * Still not real Portal IAM or a public HTTP endpoint.
     */
    public function serveInline(
        PDO $tenantSqlConnection,
        array $receipt,
        string $f100JsonPath
    ):array{
        $inline=(new SyntheticF181BoundPortalHtml())->render(
            $receipt,$f100JsonPath,':memory:',$tenantSqlConnection,true
        );
        $retrieved=(new SyntheticServiceCheckedHtmlRetrieval())->fetchInline(
            $tenantSqlConnection,$receipt,$inline
        );
        return self::mapVerified($retrieved);
    }

    /**
     * F197: public raw-array mapping is a potential authorization bypass.
     * An arbitrary caller can fabricate the F194 success fields. Restrict
     * it to explicit standalone CLI tests; real serving paths must call
     * F194 SQL/service validation and the private mapper below.
     */
    public static function fromCheckedRetrieval(array $retrieved): array
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F197_TEST_ONLY_MAPPER')!=='1'){
            return self::mapVerified([]);
        }
        return self::mapVerified($retrieved);
    }

    /**
     * Never expose this map function as an independent authorization API.
     * Used only after fetch()/fetchInline() has checked F194 SQL/epoch/bytes.
     */
    private static function mapVerified(array $retrieved): array
    {
        $headers=self::HEADERS;
        $headers['Content-Type']='text/plain; charset=utf-8';
        $deny=[
            'status_code'=>403,
            'headers'=>$headers,
            'body'=>'Preview unavailable',
            'synthetic_only'=>true,
            'ready_for_production'=>false,
        ];

        if (($retrieved['schema_version']??null)
                !==SyntheticServiceCheckedHtmlRetrieval::SCHEMA
            ||($retrieved['status']??null)
                !=='offline_html_readonly_preview_only'
            ||($retrieved['synthetic_only']??null)!==true
            ||($retrieved['read_only']??null)!==true
            ||($retrieved['portal_identity_authenticated']??null)!==false
            ||($retrieved['authorization_eligible']??null)!==false
            ||($retrieved['can_publish_ads']??null)!==false
            ||($retrieved['can_bill']??null)!==false
            ||($retrieved['ready_for_production']??null)!==false
            ||($retrieved['content_type']??null)
                !=='text/html; charset=utf-8'
            ||!is_string($retrieved['html']??null)
            ||strlen($retrieved['html'])<2000
            ||strlen($retrieved['html'])>150000) {
            return $deny;
        }

        $html=$retrieved['html'];
        foreach ([
            '<html lang="pt-BR">',
            "default-src 'none'",
            "script-src 'none'",
            "connect-src 'none'",
            "form-action 'none'",
            'DEMONSTRAÇÃO NÃO HOMOLOGADA',
        ] as $required) {
            if (!str_contains($html,$required)) {
                return $deny;
            }
        }
        if (preg_match('/<(?:script|form|button|iframe|a|img)\b/i',$html)
            ||preg_match('~https?://~i',$html)) {
            return $deny;
        }

        $headers['Content-Type']='text/html; charset=utf-8';
        return [
            'status_code'=>200,
            'headers'=>$headers,
            'body'=>$html,
            'synthetic_only'=>true,
            'ready_for_production'=>false,
        ];
    }
}
