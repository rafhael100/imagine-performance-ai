<?php
declare(strict_types=1);

namespace App\Performance\Security;

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;
use Closure;
use PDO;
use RuntimeException;
use Throwable;

/**
 * F180 CI-only delivery barrier.
 *
 * Binds the preexisting ORIGINAL Portal F29/F123/F124 signed SQL result to
 * one fresh F100 read AFTER the nonce has been consumed. Only an identical
 * post-receipt SQL projection may be delivered to Portal F62/F63.
 *
 * The caller MUST NOT treat the caller-constructible synthetic authority,
 * local SQL nonce, or this artifact as operational Portal authentication.
 */
final class SyntheticPostReceiptF100Delivery
{
    public const SCHEMA='perf.portal.post-receipt-f100-delivery.synthetic.v1';

    public function __construct(
        private readonly SyntheticDurableSignedSqlPreviewBridge $signedBridge,
        private readonly CredentialBoundDemoWorkbenchReader $reader,
        private readonly ?Closure $afterReceiptCiHook=null
    ){}

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'artifact_path'=>null,
            'sha256'=>null,
            'receipt_consumed_demo'=>false,
            'read_only'=>true,
            'portal_identity_authenticated'=>false,
            'action_authorized'=>false,
            'can_publish_ads'=>false,
            'can_bill'=>false,
            'ready_for_production'=>false,
        ];
    }

    public function deliver(
        PDO $tenantSqlConnection,
        string $originalPortalSignedEnvelope,
        ?SyntheticCurrentAuthority $currentAuthority,
        int $now,
        string $targetFile
    ):array {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F180_POST_RECEIPT_LAB')!=='1'
            ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'
            ||getenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB')!=='1'
            ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
            ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
            ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
            ||getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
            ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||!extension_loaded('sodium')
            ||!extension_loaded('pdo_pgsql')
            ||$now<time()-5 ||$now>time()+5
            ||($this->afterReceiptCiHook!==null
              &&getenv('PERF_F180_RACE_INJECTION_LAB')!=='1')){
            return self::denied('lab_disabled_or_bad_clock');
        }

        // Only a NEW path in GitHub runner's private temp directory may
        // be published. Existing files/symlinks are never overwritten.
        $root=realpath((string)getenv('RUNNER_TEMP'));
        if($root===false ||dirname($targetFile)!==$root
            ||preg_match('/^f180-[a-z0-9-]{1,64}\.json$/D',
                basename($targetFile))!==1
            ||is_file($targetFile)||is_link($targetFile)){
            return self::denied('unsafe_delivery_destination');
        }

        try{
            // F29 checks Ed25519/MFA/revisions; F123 checks the actual SQL
            // login and F100; F124 consumes a nonce via INSERT-only role.
            $signed=$this->signedBridge->preview(
                $tenantSqlConnection,$originalPortalSignedEnvelope,
                $currentAuthority,$now
            );
            if(!is_array($signed)
                ||($signed['status']??null)!==
                    'signed_sql_demo_with_local_durable_nonce_only'
                ||($signed['schema_version']??null)!==
                    SyntheticDurableSignedSqlPreviewBridge::SCHEMA
                ||($signed['replay_receipt_recorded']??null)!==true
                ||($signed['cryptographic_lab_proof_verified']??null)!==true
                ||($signed['credential_binding_confirmed']??null)!==true
                ||($signed['synthetic_only']??null)!==true
                ||($signed['read_only']??null)!==true
                ||($signed['portal_identity_authenticated']??null)!==false
                ||($signed['advertiser_approval_verified']??null)!==false
                ||($signed['provider_connected']??null)!==false
                ||($signed['authorization_eligible']??null)!==false
                ||($signed['dispatch_enabled']??null)!==false
                ||($signed['billing_enabled']??null)!==false
                ||($signed['ready_for_production']??null)!==false){
                return self::denied('signed_or_nonce_gate_denied');
            }

            // This closure is *only* a disposable concurrency test hook.
            // It can revoke/reassign a synthetic DB principal or change
            // a synthetic campaign between nonce consumption and delivery.
            if($this->afterReceiptCiHook!==null){
                ($this->afterReceiptCiHook)();
            }

            // Critical F180 fence: do NOT export the F100 snapshot taken
            // before F124's SQL nonce transaction. Re-read the RLS views
            // with the authenticated login after its receipt is committed.
            $fresh=$this->reader->read($tenantSqlConnection);
            if(!is_array($fresh)
                ||array_keys($fresh)!==[
                    'schema_version','status','synthetic_only','read_only',
                    'credential_binding_confirmed',
                    'portal_identity_authenticated',
                    'real_human_approval_verified','provider_connected',
                    'authorization_eligible','ready_for_production',
                    'dispatch_enabled','billing_enabled','counts',
                    'accounts','campaigns','reviews'
                ]
                ||$fresh['schema_version']!==
                    'perf.portal.credential-bound-workbench.synthetic.v1'
                ||$fresh['status']!=='credential_scoped_demo_workbench_only'
                ||$fresh['synthetic_only']!==true
                ||$fresh['read_only']!==true
                ||$fresh['credential_binding_confirmed']!==true
                ||$fresh['portal_identity_authenticated']!==false
                ||$fresh['real_human_approval_verified']!==false
                ||$fresh['provider_connected']!==false
                ||$fresh['authorization_eligible']!==false
                ||$fresh['dispatch_enabled']!==false
                ||$fresh['billing_enabled']!==false
                ||$fresh['ready_for_production']!==false
                ||$fresh['accounts']!==($signed['accounts']??null)
                ||$fresh['campaigns']!==($signed['campaigns']??null)
                ||$fresh['reviews']!==($signed['reviews']??null)
                ||$fresh['counts']!==($signed['counts']??null)){
                return self::denied('post_receipt_sql_change_denied');
            }
            if($now>=time()+1 ||time()-$now>15){
                return self::denied('post_receipt_delivery_expired');
            }
            $json=json_encode(
                $fresh,
                JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                    |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS
                    |JSON_HEX_QUOT|JSON_THROW_ON_ERROR
            )."\n";
            if(strlen($json)>48000){
                return self::denied('post_receipt_artifact_too_large');
            }
            // Private temp file, full fsync, then atomic hard-link into a
            // NEW destination. link() refuses replacing an existing file.
            // This is a CI filesystem property, NOT WORM storage.
            $tmp=$root.'/.f180-'.bin2hex(random_bytes(12)).'.tmp';
            $handle=@fopen($tmp,'x');
            if(!is_resource($handle)){
                return self::denied('atomic_persist_unavailable');
            }
            try{
                if(fwrite($handle,$json)!==strlen($json)
                    ||!fflush($handle)||!fsync($handle)){
                    throw new RuntimeException('short write or fsync failed');
                }
            }catch(Throwable $error){
                @unlink($tmp);
                throw $error;
            }finally{
                fclose($handle);
            }
            try{
                if(!@link($tmp,$targetFile)){
                    return self::denied('destination_raced_or_unavailable');
                }
            }finally{
                @unlink($tmp);
            }
            if(!is_file($targetFile)
                ||file_get_contents($targetFile)!==$json){
                return self::denied('artifact_integrity_unavailable');
            }
            return [
                ...self::denied('post_receipt_readonly_snapshot_only'),
                'status'=>'post_receipt_readonly_snapshot_only',
                'artifact_path'=>$targetFile,
                'sha256'=>hash('sha256',$json),
                'receipt_consumed_demo'=>true,
            ];
        }catch(Throwable){
            return self::denied('delivery_unavailable_fail_closed');
        }
    }
}
