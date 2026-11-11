<?php
declare(strict_types=1);

namespace App\Performance\Security;

use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;
use PDO;
use Throwable;

/**
 * F194 CI-only, per-READ service check for an already rendered offline F63
 * HTML file. This is NOT an HTTP endpoint, Portal authentication, signed
 * response or storage access control: a caller with file access can still
 * bypass this code and open the HTML directly.
 */
final class SyntheticServiceCheckedHtmlRetrieval
{
    public const SCHEMA='perf.portal.service-checked-offline-html.synthetic.v1';

    private static function refused(string $why):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$why,
            'content_type'=>null,
            'html'=>null,
            'synthetic_only'=>true,
            'read_only'=>true,
            'portal_identity_authenticated'=>false,
            'authorization_eligible'=>false,
            'can_publish_ads'=>false,
            'can_bill'=>false,
            'ready_for_production'=>false,
        ];
    }

    private static function path(
        mixed $candidate,
        string $runner,
        string $extension
    ):?string{
        if(!is_string($candidate)||dirname($candidate)!==$runner
            ||preg_match('/^f(?:180|181)-[a-z0-9-]{1,64}\.'.
                preg_quote($extension,'/').'$/D',basename($candidate))!==1
            ||is_link($candidate)||!is_file($candidate)
            ||realpath($candidate)!==$candidate
            ||(fileperms($candidate) & 0777)!==0600)return null;
        return $candidate;
    }

    private static function stillValid(
        PDO $db,
        array $f192Receipt,
        array $expectedF100
    ):bool{
        if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
            ||$db->inTransaction()
            ||getenv('PERF_F192_SIGNED_SERVICE_LAB')!=='1'
            ||getenv('PERF_F191_SERVICE_EPOCH_LAB')!=='1'
            ||getenv('PERF_F189_SERVICE_STATUS_LAB')!=='1'){
            return false;
        }
        $epochs=$f192Receipt['service_epoch_at_delivery']??null;
        if(!is_array($epochs)||array_keys($epochs)!==[
            'tenant_epoch','service_epoch'
        ]||!is_int($epochs['tenant_epoch'])||!is_int($epochs['service_epoch'])){
            return false;
        }
        $rows=$db->query(
            'SELECT tenant_epoch,service_epoch '
            .'FROM public.perf_f191_service_epoch_for_login()'
        )->fetchAll(PDO::FETCH_ASSOC);
        if(!is_array($rows)||count($rows)!==1
            ||!is_array($rows[0])
            ||array_keys($rows[0])!==['tenant_epoch','service_epoch']){
            return false;
        }
        foreach($epochs as $key=>$expected){
            $value=$rows[0][$key];
            if((!is_string($value)&&!is_int($value))
                ||preg_match('/^[1-9][0-9]{0,6}$/D',(string)$value)!==1
                ||(int)$value!==$expected)return false;
        }
        $current=(new CredentialBoundDemoServiceStatusFence())->read($db);
        return ($current['status']??null)==='credential_scoped_demo_workbench_only'
            &&($current['credential_binding_confirmed']??null)===true
            &&($current['portal_identity_authenticated']??null)===false
            &&($current['authorization_eligible']??null)===false
            &&($current['dispatch_enabled']??null)===false
            &&($current['billing_enabled']??null)===false
            &&$current===$expectedF100;
    }

    /**
     * A read-time boundary for FICTIONAL runner files; not a production
     * download flow, even when cryptographic mock F29 proof is present.
     */
    public function fetch(
        PDO $tenantSqlConnection,
        array $f192Receipt,
        array $f193HtmlResult
    ):array{
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F194_OFFLINE_HTML_FETCH_LAB')!=='1'
            ||getenv('PERF_F193_F63_SERVICE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return self::refused('lab_disabled');
        }
        try{
            $runner=realpath((string)getenv('RUNNER_TEMP'));
            if($runner===false)return self::refused('runner_unavailable');
            if(($f192Receipt['schema_version']??null)!==
                   SyntheticPostReceiptF100Delivery::SCHEMA
                ||($f192Receipt['status']??null)!==
                    'post_receipt_readonly_snapshot_only'
                ||($f192Receipt['receipt_consumed_demo']??null)!==true
                ||($f192Receipt['portal_identity_authenticated']??null)!==false
                ||($f192Receipt['can_publish_ads']??null)!==false
                ||($f192Receipt['can_bill']??null)!==false
                ||($f192Receipt['ready_for_production']??null)!==false
                ||($f193HtmlResult['schema_version']??null)!==
                    SyntheticF181BoundPortalHtml::SCHEMA
                ||($f193HtmlResult['status']??null)!==
                    'offline_html_bound_to_f180_ci_only'
                ||($f193HtmlResult['portal_identity_authenticated']??null)!==false
                ||($f193HtmlResult['can_publish_ads']??null)!==false
                ||($f193HtmlResult['can_bill']??null)!==false
                ||($f193HtmlResult['ready_for_production']??null)!==false){
                return self::refused('unsigned_or_untrusted_local_handoff');
            }
            $jsonPath=self::path($f192Receipt['artifact_path']??null,$runner,'json');
            $htmlPath=self::path($f193HtmlResult['html_path']??null,$runner,'html');
            $jsonHash=$f192Receipt['sha256']??null;
            $htmlHash=$f193HtmlResult['html_sha256']??null;
            if($jsonPath===null||$htmlPath===null
                ||!is_string($jsonHash)||!is_string($htmlHash)
                ||preg_match('/^[a-f0-9]{64}$/D',$jsonHash)!==1
                ||preg_match('/^[a-f0-9]{64}$/D',$htmlHash)!==1
                ||filesize($jsonPath)>48000||filesize($jsonPath)<100
                ||filesize($htmlPath)>150000||filesize($htmlPath)<2000){
                return self::refused('missing_or_unsafe_html_artifact');
            }
            $json=(string)file_get_contents($jsonPath);
            if(!hash_equals($jsonHash,hash('sha256',$json))){
                return self::refused('json_integrity_changed');
            }
            $f100=json_decode($json,true,24,JSON_THROW_ON_ERROR);
            if(!is_array($f100)){
                return self::refused('invalid_sql_report');
            }

            // This function is deliberately invoked ON EACH READ, not just
            // when the HTML was written by F193. A later cancellation
            // denies delivery even if local file bytes remain unchanged.
            if(!self::stillValid($tenantSqlConnection,$f192Receipt,$f100)){
                return self::refused('service_revoked_or_sql_changed');
            }
            $html=(string)file_get_contents($htmlPath);
            if(!hash_equals($htmlHash,hash('sha256',$html))){
                return self::refused('html_integrity_changed');
            }
            foreach([
                '<html lang="pt-BR">',
                "default-src 'none'",
                "script-src 'none'",
                "connect-src 'none'",
                "form-action 'none'",
                'DEMONSTRAÇÃO NÃO HOMOLOGADA'
            ] as $literal){
                if(!str_contains($html,$literal)){
                    return self::refused('unsafe_offline_html');
                }
            }
            if(preg_match('/<(?:script|form|button|iframe|a|img)\b/i',$html)
               ||preg_match('~https?://~i',$html)){
                return self::refused('unsafe_offline_html');
            }
            if(!hash_equals($jsonHash,(string)hash_file('sha256',$jsonPath))
                ||!hash_equals($htmlHash,(string)hash_file('sha256',$htmlPath))
                ||!self::stillValid($tenantSqlConnection,$f192Receipt,$f100)){
                return self::refused('changed_during_read');
            }
            return [
                ...self::refused('offline_html_readonly_preview_only'),
                'status'=>'offline_html_readonly_preview_only',
                'content_type'=>'text/html; charset=utf-8',
                'html'=>$html,
            ];
        }catch(Throwable){
            return self::refused('offline_retrieval_unavailable_fail_closed');
        }
    }

    /**
     * F196 CI-only response for HTML that is rendered in memory by F181,
     * avoiding persistent HTML paths entirely. Source JSON remains a lab
     * artifact; this is NOT real IAM or an HTTP controller.
     */
    public function fetchInline(
        PDO $tenantSqlConnection,
        array $f192Receipt,
        array $inlineRender
    ):array{
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F196_INLINE_HTML_LAB')!=='1'
            ||getenv('PERF_F194_OFFLINE_HTML_FETCH_LAB')!=='1'
            ||getenv('PERF_F193_F63_SERVICE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return self::refused('lab_disabled');
        }
        try{
            if(($f192Receipt['schema_version']??null)!==
                    SyntheticPostReceiptF100Delivery::SCHEMA
                ||($f192Receipt['status']??null)!==
                    'post_receipt_readonly_snapshot_only'
                ||($f192Receipt['receipt_consumed_demo']??null)!==true
                ||($f192Receipt['portal_identity_authenticated']??null)!==false
                ||($f192Receipt['can_publish_ads']??null)!==false
                ||($f192Receipt['can_bill']??null)!==false
                ||($f192Receipt['ready_for_production']??null)!==false
                ||($inlineRender['schema_version']??null)!==
                    SyntheticF181BoundPortalHtml::SCHEMA
                ||($inlineRender['status']??null)!=='in_memory_f63_ci_only'
                ||($inlineRender['non_persistent']??null)!==true
                ||array_key_exists('html_path',$inlineRender)===false
                ||$inlineRender['html_path']!==null
                ||($inlineRender['portal_identity_authenticated']??null)!==false
                ||($inlineRender['can_publish_ads']??null)!==false
                ||($inlineRender['can_bill']??null)!==false
                ||($inlineRender['ready_for_production']??null)!==false){
                return self::refused('unsigned_or_untrusted_inline_handoff');
            }
            $runner=realpath((string)getenv('RUNNER_TEMP'));
            if($runner===false)return self::refused('runner_unavailable');
            $jsonPath=self::path($f192Receipt['artifact_path']??null,$runner,'json');
            $jsonHash=$f192Receipt['sha256']??null;
            $htmlHash=$inlineRender['html_sha256']??null;
            $html=$inlineRender['html_bytes']??null;
            if($jsonPath===null||!is_string($jsonHash)
                ||preg_match('/^[a-f0-9]{64}$/D',$jsonHash)!==1
                ||!is_string($htmlHash)
                ||preg_match('/^[a-f0-9]{64}$/D',$htmlHash)!==1
                ||!is_string($html)||strlen($html)<2000||strlen($html)>150000
                ||!hash_equals($htmlHash,hash('sha256',$html))
                ||filesize($jsonPath)<100||filesize($jsonPath)>48000){
                return self::refused('unsafe_or_modified_inline_handoff');
            }
            $json=(string)file_get_contents($jsonPath);
            if(!hash_equals($jsonHash,hash('sha256',$json))){
                return self::refused('json_integrity_changed');
            }
            $dto=json_decode($json,true,24,JSON_THROW_ON_ERROR);
            if(!is_array($dto)||!self::stillValid(
                $tenantSqlConnection,$f192Receipt,$dto
            )){
                return self::refused('service_revoked_or_sql_changed');
            }
            foreach([
                '<html lang="pt-BR">',
                "default-src 'none'",
                "script-src 'none'",
                "connect-src 'none'",
                "form-action 'none'",
                'DEMONSTRAÇÃO NÃO HOMOLOGADA',
                'Nenhuma conta publicitária real está conectada'
            ] as $required){
                if(!str_contains($html,$required)){
                    return self::refused('unsafe_inline_html');
                }
            }
            if(preg_match('/<(?:script|form|button|iframe|a|img|link|input)\\b/i',$html)
                ||preg_match('~https?://~i',$html)){
                return self::refused('unsafe_inline_html');
            }
            if(!hash_equals($jsonHash,(string)hash_file('sha256',$jsonPath))
                ||!self::stillValid($tenantSqlConnection,$f192Receipt,$dto)){
                return self::refused('changed_during_inline_read');
            }
            return [
                ...self::refused('offline_html_readonly_preview_only'),
                'status'=>'offline_html_readonly_preview_only',
                'content_type'=>'text/html; charset=utf-8',
                'html'=>$html,
            ];
        }catch(Throwable){
            return self::refused('inline_preview_unavailable_fail_closed');
        }
    }
}
