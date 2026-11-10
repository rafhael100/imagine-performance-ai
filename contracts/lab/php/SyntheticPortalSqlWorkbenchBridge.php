<?php
declare(strict_types=1);

namespace App\Performance\Security;

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;
use PDO;
use Throwable;

/**
 * F123 CI-ONLY intersection of:
 *  - REAL Ed25519 verification by the existing Portal F29 mock gate;
 *  - fresh, independently authenticated PostgreSQL session_user F91;
 *  - F100 composite workbench backed by the 3 credential-bound VIEWs.
 *
 * Deliberately authorizes only ONE fixed synthetic A fixture. The caller
 * cannot supply a tenant, login, SQL object, requested permission or
 * authority mapping to broaden. No HTTP route, Ads, billing or production.
 *
 * WARNING: SyntheticCurrentAuthority is caller-constructible and there is
 * no independently authenticated Portal IAM/MFA/S2S source. This is NOT a
 * production authorization or a cryptographically secure customer gateway.
 */
final class SyntheticPortalSqlWorkbenchBridge
{
    public const SCHEMA='perf.portal.sql-bound-signed-demo-workbench.v1';
    private const LOGIN_A='perf_f91_reader_a';
    private const SQL_TENANT_A='88888888-8888-4888-8888-888888888888';

    public function __construct(
        private readonly SignedPortalCapabilityGate $signedGate
    ){}

    private static function unavailable(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
            'synthetic_only'=>true,
            'read_only'=>true,
            'cryptographic_lab_proof_verified'=>false,
            'credential_binding_confirmed'=>false,
            'portal_identity_authenticated'=>false,
            'advertiser_approval_verified'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'accounts'=>[],
            'campaigns'=>[],
            'reviews'=>[],
            'counts'=>[],
        ];
    }

    private static function labEnabled():bool
    {
        return PHP_SAPI==='cli'
            && getenv('APP_ENV')==='testing'
            && getenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB')==='1'
            && getenv('PERF_F29_SIGNED_PORTAL_LAB')==='1'
            && getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')==='1'
            && getenv('PERF_F92_CREDENTIAL_READER_LAB')==='1'
            && getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')==='1'
            && getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')==='1'
            && getenv('PGHOST')==='127.0.0.1'
            && getenv('PGPORT')==='5432'
            && getenv('PGDATABASE')==='perf_test_campaign_registry'
            && extension_loaded('sodium');
    }

    private static function identity(PDO $database):?array
    {
        try {
            $record=$database->query(
                'SELECT current_database()::text AS db_name, '
                .'session_user::text AS sql_login, '
                .'public.perf_f91_tenant_for_login()::text AS sql_tenant, '
                .'pg_catalog.pg_backend_pid()::text AS backend_pid'
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($record) || array_keys($record)!==[
                'db_name','sql_login','sql_tenant','backend_pid'
            ] || $record['db_name']!=='perf_test_campaign_registry'
                ||$record['sql_login']!==self::LOGIN_A
                ||$record['sql_tenant']!==self::SQL_TENANT_A
                ||!is_string($record['backend_pid'])
                ||preg_match('/^[1-9][0-9]*$/D',$record['backend_pid'])!==1){
                return null;
            }
            return $record;
        }catch(Throwable){
            return null;
        }
    }

    public function preview(
        PDO $database,
        string $portalSignedEnvelope,
        ?SyntheticCurrentAuthority $currentAuthority,
        int $now
    ):array {
        if(!self::labEnabled()){
            return self::unavailable('lab_disabled');
        }

        try{
            if($database->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||$database->inTransaction()){
                return self::unavailable('invalid_connection_state');
            }
            // This explicitly ties the existing fixed F29 Portal test claim
            // (org-demo-a/perf-demo-a/svc-demo-a/campaigns:read) to the F91
            // SQL A fixture (UUID and svc-demo-f88). It is NOT a reusable IAM
            // mapping; never infer a customer tenant from caller input.
            $sqlBefore=self::identity($database);
            if($sqlBefore===null){
                return self::unavailable('sql_identity_untrusted_or_revoked');
            }
            // The signature, short TTL, MFA mock, revision fences, authority
            // context and nonce are checked by the existing F29 gate.
            // In-memory nonce reuse guard is explicitly NOT distributed.
            $proof=$this->signedGate->check(
                $portalSignedEnvelope,$currentAuthority,$now
            );
            if($proof!=='verified_synthetic_only'){
                return self::unavailable('signed_portal_proof_denied');
            }

            $workbench=(new CredentialBoundDemoWorkbenchReader())->read($database);
            if(!is_array($workbench)
                ||$workbench['status']!=='credential_scoped_demo_workbench_only'
                ||$workbench['credential_binding_confirmed']!==true
                ||$workbench['synthetic_only']!==true
                ||$workbench['read_only']!==true
                ||$workbench['portal_identity_authenticated']!==false
                ||$workbench['real_human_approval_verified']!==false
                ||$workbench['provider_connected']!==false
                ||$workbench['authorization_eligible']!==false
                ||$workbench['ready_for_production']!==false
                ||$workbench['dispatch_enabled']!==false
                ||$workbench['billing_enabled']!==false
                ||!is_array($workbench['counts'])
                ||!is_array($workbench['accounts'])
                ||!is_array($workbench['campaigns'])
                ||!is_array($workbench['reviews'])
                ||count($workbench['accounts'])>20
                ||count($workbench['campaigns'])>20
                ||count($workbench['reviews'])>20){
                return self::unavailable('sql_workbench_denied');
            }

            // Recheck that the database principal, binding and backend
            // did not change during workbench composition.
            $sqlAfter=self::identity($database);
            if($sqlAfter===null || $sqlAfter!==$sqlBefore){
                return self::unavailable('sql_identity_changed_during_preview');
            }
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>'signed_portal_plus_sql_demo_preview_only',
                'synthetic_only'=>true,
                'read_only'=>true,
                'cryptographic_lab_proof_verified'=>true,
                'credential_binding_confirmed'=>true,
                // Even on successful crypto, no real IAM and no Ads access.
                'portal_identity_authenticated'=>false,
                'advertiser_approval_verified'=>false,
                'provider_connected'=>false,
                'authorization_eligible'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
                'accounts'=>$workbench['accounts'],
                'campaigns'=>$workbench['campaigns'],
                'reviews'=>$workbench['reviews'],
                'counts'=>$workbench['counts'],
            ];
        }catch(Throwable){
            return self::unavailable('preview_unavailable');
        }
    }
}
