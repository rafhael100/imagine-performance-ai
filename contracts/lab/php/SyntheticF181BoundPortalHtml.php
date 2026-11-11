<?php
declare(strict_types=1);

namespace App\Performance\Security;

use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;
use Closure;
use PDO;
use RuntimeException;
use Throwable;

/**
 * F181 CI-only integrity-bound offline Portal F63 rendering.
 *
 * The SHA256 must be the IN-MEMORY result of a successful F180 delivery;
 * the caller is not allowed to substitute a new hash after editing the file.
 * This is a laboratory TOCTOU check, not an external signer or WORM.
 */
final class SyntheticF181BoundPortalHtml
{
    public const SCHEMA='perf.portal.integrity-bound-f63-html.synthetic.v1';

    public function __construct(
        private readonly ?Closure $afterInitialHashCiHook=null
    ){}

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'html_path'=>null,'html_sha256'=>null,
            'read_only'=>true,
            'synthetic_only'=>true,
            'portal_identity_authenticated'=>false,
            'can_publish_ads'=>false,'can_bill'=>false,
            'ready_for_production'=>false,
        ];
    }

    /**
     * F193 CI-only operational boundary check. The source SQL database is
     * NOT a Portal IAM authority; the same original F100 DTO and local
     * monotonic versions must still be visible immediately before F63.
     */
    private static function unchangedServiceAndReport(
        PDO $sql,
        array $proof,
        string $sourceJson
    ):bool {
        if($sql->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
            ||$sql->inTransaction()
            ||getenv('PERF_F192_SIGNED_SERVICE_LAB')!=='1'
            ||getenv('PERF_F191_SERVICE_EPOCH_LAB')!=='1'
            ||getenv('PERF_F189_SERVICE_STATUS_LAB')!=='1'){
            return false;
        }
        $originalEpoch=$proof['service_epoch_at_delivery']??null;
        if(!is_array($originalEpoch)||array_keys($originalEpoch)!==[
            'tenant_epoch','service_epoch'
        ] ||!is_int($originalEpoch['tenant_epoch'])
          ||!is_int($originalEpoch['service_epoch'])){
            return false;
        }
        $rows=$sql->query(
            'SELECT tenant_epoch,service_epoch '
            .'FROM public.perf_f191_service_epoch_for_login()'
        )->fetchAll(PDO::FETCH_ASSOC);
        if(!is_array($rows)||count($rows)!==1
            ||!is_array($rows[0])
            ||array_keys($rows[0])!==['tenant_epoch','service_epoch']){
            return false;
        }
        foreach($originalEpoch as $key=>$expected){
            $raw=$rows[0][$key];
            if((!is_int($raw)&&!is_string($raw))
                ||preg_match('/^[1-9][0-9]{0,6}$/D',(string)$raw)!==1
                ||(int)$raw!==$expected)return false;
        }
        $expectedDto=json_decode(
            $sourceJson,true,24,JSON_THROW_ON_ERROR
        );
        if(!is_array($expectedDto)){
            return false;
        }
        $fresh=(new CredentialBoundDemoServiceStatusFence())->read($sql);
        return ($fresh['status']??null)==='credential_scoped_demo_workbench_only'
            &&($fresh['credential_binding_confirmed']??null)===true
            &&($fresh['portal_identity_authenticated']??null)===false
            &&($fresh['authorization_eligible']??null)===false
            &&($fresh['dispatch_enabled']??null)===false
            &&($fresh['billing_enabled']??null)===false
            &&$fresh===$expectedDto;
    }

    /** @param array<string,mixed> $f180Result */
    public function render(
        array $f180Result,
        string $f100JsonPath,
        string $htmlTargetPath,
        ?PDO $tenantSqlConnection=null,
        bool $inlineCiOnly=false
    ):array {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F181_BOUND_HTML_LAB')!=='1'
            ||getenv('PORTAL_F62_CREDENTIAL_WORKBENCH_LAB')!=='1'
            ||getenv('PORTAL_F63_STATIC_HTML_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||($this->afterInitialHashCiHook!==null
               &&getenv('PERF_F181_RACE_INJECTION_LAB')!=='1')){
            return self::denied('lab_disabled');
        }
        $runnerTemp=realpath((string)getenv('RUNNER_TEMP'));
        $jsonReal=realpath($f100JsonPath);
        $renderer=realpath(dirname(__DIR__,3).
            '/portal-f176-candidate/apps/portal/tools/f63-render-credential-workbench.php');
        if($runnerTemp===false ||$jsonReal===false ||$renderer===false
            ||dirname($jsonReal)!==$runnerTemp
            ||is_link($f100JsonPath)
            ||(!$inlineCiOnly && (
                dirname($htmlTargetPath)!==$runnerTemp
                ||preg_match('/^f181-[a-z0-9-]{1,64}\\.html$/D',
                     basename($htmlTargetPath))!==1
                ||is_link($htmlTargetPath)||file_exists($htmlTargetPath)
            ))
            ||($inlineCiOnly && (
                $htmlTargetPath!==':memory:'
                ||getenv('PERF_F196_INLINE_HTML_LAB')!=='1'
                ||getenv('PERF_F193_F63_SERVICE_LAB')!=='1'
                ||$tenantSqlConnection===null
            ))
            ||filesize($jsonReal)<100||filesize($jsonReal)>48000){
            return self::denied('unsafe_input_or_output_path');
        }
        $expected=$f180Result['sha256']??null;
        if(($f180Result['schema_version']??null)!==
                SyntheticPostReceiptF100Delivery::SCHEMA
            ||($f180Result['status']??null)!==
                'post_receipt_readonly_snapshot_only'
            ||($f180Result['receipt_consumed_demo']??null)!==true
            ||($f180Result['artifact_path']??null)!==$f100JsonPath
            ||!is_string($expected)
            ||preg_match('/^[a-f0-9]{64}$/D',$expected)!==1
            ||($f180Result['portal_identity_authenticated']??null)!==false
            ||($f180Result['can_publish_ads']??null)!==false
            ||($f180Result['can_bill']??null)!==false
            ||($f180Result['ready_for_production']??null)!==false){
            return self::denied('untrusted_f180_delivery_proof');
        }
        try{
            $read=(string)file_get_contents($jsonReal);
            if(!hash_equals($expected,hash('sha256',$read))){
                return self::denied('json_digest_mismatch');
            }
            $serviceRequired=getenv('PERF_F193_F63_SERVICE_LAB')==='1';
            if($serviceRequired){
                if($tenantSqlConnection===null
                    ||!self::unchangedServiceAndReport(
                        $tenantSqlConnection,$f180Result,$read
                    )){
                    return self::denied('service_or_sql_changed_before_html');
                }
            }
            if($this->afterInitialHashCiHook!==null){
                ($this->afterInitialHashCiHook)();
            }
            // The original file is checked after the deliberately hostile
            // race window; never render a replacement valid-looking F100.
            if(!hash_equals($expected,(string)hash_file('sha256',$jsonReal))){
                return self::denied('json_changed_before_render');
            }
            if($serviceRequired
                &&!self::unchangedServiceAndReport(
                    $tenantSqlConnection,$f180Result,$read
                )){
                return self::denied('service_or_sql_changed_during_html');
            }
            // Stage EXACT validated bytes into a new private file. Renderer
            // receives this file, not an attacker-controlled mutable path.
            $staged=$runnerTemp.'/.f181-json-'.bin2hex(random_bytes(12)).'.tmp';
            // F198: private file mode is established at creation time.
            $previousUmask=umask(0077);
            try{
                $handle=@fopen($staged,'x');
            }finally{
                umask($previousUmask);
            }
            if(is_resource($handle)
                &&((fileperms($staged) & 0777)!==0600)){
                fclose($handle);
                @unlink($staged);
                return self::denied('temporary_file_not_private');
            }
            if(!is_resource($handle)){
                return self::denied('stage_file_unavailable');
            }
            try{
                if(fwrite($handle,$read)!==strlen($read)
                    ||!fflush($handle)||!fsync($handle)){
                    throw new RuntimeException('stage file write/sync unavailable');
                }
            }catch(Throwable $error){
                @unlink($staged);
                throw $error;
            }finally{
                fclose($handle);
            }
            try{
                if(!hash_equals($expected,(string)hash_file('sha256',$staged))){
                    return self::denied('staged_json_mismatch');
                }
                $process=proc_open(
                    [PHP_BINARY,$renderer,$staged],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
                    $pipes,dirname(__DIR__,3)
                );
                if(!is_resource($process)){
                    return self::denied('portal_renderer_unavailable');
                }
                fclose($pipes[0]);
                $html=(string)stream_get_contents($pipes[1],200000);
                fclose($pipes[1]);
                $stderr=(string)stream_get_contents($pipes[2],4000);
                fclose($pipes[2]);
                $exit=proc_close($process);
                if($exit!==0 ||strlen($html)<2000 ||strlen($html)>150000){
                    return self::denied('portal_renderer_denied');
                }
                // CSP and absence of executable controls are verified
                // both here and in F178's full DOM/HTML regression tests.
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
                        return self::denied('unsafe_html_response');
                    }
                }
                if(preg_match(
                    '/<(?:script|iframe|form|button|a|link|img|input)\b/i',
                    $html
                )||preg_match('~https?://~i',$html)){
                    return self::denied('unsafe_html_controls');
                }
                // A change while the child F63 runs denies publication.
                if(!hash_equals($expected,(string)hash_file('sha256',$jsonReal))
                   ||!hash_equals($expected,(string)hash_file('sha256',$staged))){
                    return self::denied('json_modified_during_render');
                }
                if($serviceRequired
                    &&!self::unchangedServiceAndReport(
                        $tenantSqlConnection,$f180Result,$read
                    )){
                    return self::denied('service_or_sql_changed_after_renderer');
                }
                // F196: apply actual Portal F63 renderer and F193 SQL/epoch
                // checks but do not persist any HTML artifact on this path.
                if($inlineCiOnly){
                    return [
                        ...self::denied('in_memory_f63_ci_only'),
                        'status'=>'in_memory_f63_ci_only',
                        'html_path'=>null,
                        'html_sha256'=>hash('sha256',$html),
                        'html_bytes'=>$html,
                        'non_persistent'=>true,
                    ];
                }
                $tmpHtml=$runnerTemp.'/.f181-html-'.bin2hex(random_bytes(12)).'.tmp';
                // F198: private file mode is established at creation time.
                $previousUmask=umask(0077);
                try{
                    $out=@fopen($tmpHtml,'x');
                }finally{
                    umask($previousUmask);
                }
                if(is_resource($out)
                    &&((fileperms($tmpHtml) & 0777)!==0600)){
                    fclose($out);
                    @unlink($tmpHtml);
                    return self::denied('temporary_file_not_private');
                }
                if(!is_resource($out)){
                    return self::denied('html_output_unavailable');
                }
                try{
                    if(fwrite($out,$html)!==strlen($html)
                        ||!fflush($out)||!fsync($out)){
                        throw new RuntimeException('HTML write/sync unavailable');
                    }
                }catch(Throwable $error){
                    @unlink($tmpHtml);
                    throw $error;
                }finally{
                    fclose($out);
                }
                try{
                    if(!@link($tmpHtml,$htmlTargetPath)){
                        return self::denied('html_output_collision');
                    }
                }finally{
                    @unlink($tmpHtml);
                }
                if(file_get_contents($htmlTargetPath)!==$html){
                    return self::denied('published_html_mismatch');
                }
                return [
                    ...self::denied('offline_html_bound_to_f180_ci_only'),
                    'status'=>'offline_html_bound_to_f180_ci_only',
                    'html_path'=>$htmlTargetPath,
                    'html_sha256'=>hash('sha256',$html),
                ];
            }finally{
                @unlink($staged);
            }
        }catch(Throwable){
            return self::denied('offline_render_unavailable_fail_closed');
        }
    }
}
