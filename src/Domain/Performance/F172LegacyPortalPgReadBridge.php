<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;
use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/F166Pg16ReadOnlyDashboard.php';

/**
 * F172 lab-only bridge using the EXISTING F29 Portal envelope, not F169's
 * incompatible alternate signer. Hardcoded single A fixture is intentionally
 * NOT an IAM/S2S mapping or an authorization mechanism for real customers.
 */
final class F172LegacyPortalPgReadBridge
{
    public const SCHEMA='perf.portal.f29-to-pg16-readonly-lab.v1';
    private const PORTAL_TENANT='perf-demo-a';
    private const SQL_TENANT='perf-demo-aurora';

    public function __construct(
        private SignedPortalCapabilityGate $existingPortalVerifier,
        private F166Pg16ReadOnlyDashboard $sqlReader,
        private PDO $nonceConnection
    ){
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
            ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
            ||getenv('PERF_F170_NONCE_LAB')!=='1'
            ||getenv('PERF_F172_LEGACY_BRIDGE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_f162_ci'){
            throw new RuntimeException('F172 canonical Portal preview only runs in disposable CI');
        }
        $identity=$this->nonceConnection->query(
            'SELECT current_database() AS database_name, session_user AS role_name'
        )->fetch(PDO::FETCH_ASSOC);
        if(!is_array($identity)
            ||$identity['database_name']!=='perf_f162_ci'
            ||$identity['role_name']!=='perf_f170_gateway'){
            throw new RuntimeException('F172 nonce writer requires restricted gateway login');
        }
    }

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'report'=>null,
            'read_only'=>true,
            'synthetic_only'=>true,
            'legacy_portal_signature_verified_in_lab'=>false,
            'portal_identity_authenticated'=>false,
            'human_approval_verified'=>false,
            'nonce_consumed_demo'=>false,
            'action_authorized'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'ready_for_production'=>false,
        ];
    }

    /** @return array<string,mixed> */
    public function read(
        string $originalPortalEnvelope,
        ?SyntheticCurrentAuthority $simulatedCurrentAuthority,
        int $now
    ):array {
        if($now<=0 || $now>2147483647){
            return self::denied('invalid_lab_clock');
        }
        $proof=$this->existingPortalVerifier->check(
            $originalPortalEnvelope,$simulatedCurrentAuthority,$now
        );
        if($proof!=='verified_synthetic_only'){
            return self::denied('original_portal_signature_or_authority_denied');
        }
        try{
            // F29 has ALREADY verified detached Ed25519, strict schema,
            // MFA and revision freshness. Extract only its trusted receipt
            // values after verification, never re-sign or translate claims.
            $packet=json_decode($originalPortalEnvelope,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($packet)
                ||($packet['version']??null)!=='portal-capability-envelope.v1'
                ||!is_string($packet['payload']??null)){
                return self::denied('verified_portal_packet_not_extractable');
            }
            $payload=$packet['payload'];
            if(!preg_match('/^[A-Za-z0-9_-]+$/D',$payload)){
                return self::denied('verified_portal_packet_not_extractable');
            }
            $raw=base64_decode(strtr($payload,'-_','+/'),true);
            if(!is_string($raw)||strlen($raw)>2048){
                return self::denied('verified_portal_packet_not_extractable');
            }
            $claim=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
            if(!is_array($claim)
                ||($claim['performance_tenant_ref']??null)!==self::PORTAL_TENANT
                ||($claim['permission']??null)!=='campaigns:read'
                ||($claim['portal_service_ref']??null)!=='svc-demo-a'
                ||($claim['service_state']??null)!=='active_mock'
                ||($claim['session_mfa_verified']??null)!==true
                ||!is_string($claim['nonce']??null)
                ||!preg_match('/^[a-f0-9]{32}$/D',$claim['nonce'])
                ||!is_int($claim['expires_at']??null)
                ||$claim['expires_at']<=$now
                ||$claim['expires_at']>$now+60){
                return self::denied('verified_portal_scope_denied');
            }
            // Only fixed A test mapping. Never infer a real tenant from
            // an envelope, bearer token, GUC or caller-supplied parameters.
            $read=$this->sqlReader->read(self::SQL_TENANT);
            if(!is_array($read)
                ||($read['tenant_ref']??null)!==self::SQL_TENANT
                ||($read['iam_verified']??null)!==false
                ||($read['s2s_verified']??null)!==false
                ||($read['authorization_eligible']??null)!==false
                ||($read['dispatch_enabled']??null)!==false
                ||($read['billing_enabled']??null)!==false
                ||($read['ready_for_production']??null)!==false){
                return self::denied('sql_tenant_or_report_denied');
            }
            $stmt=$this->nonceConnection->prepare(
                'SELECT perf_f162.f170_consume_signed_read_nonce(?,?,?)'
            );
            $stmt->bindValue(1,self::SQL_TENANT,PDO::PARAM_STR);
            $stmt->bindValue(2,$claim['nonce'],PDO::PARAM_STR);
            $stmt->bindValue(3,$claim['expires_at'],PDO::PARAM_INT);
            $stmt->execute();
            if(!in_array($stmt->fetchColumn(),[true,'t',1,'1'],true)){
                return self::denied('replay_or_nonce_denied');
            }
            return [
                ...self::denied('original_portal_f29_pg16_readonly_demo_only'),
                'status'=>'original_portal_f29_pg16_readonly_demo_only',
                'report'=>$read,
                'legacy_portal_signature_verified_in_lab'=>true,
                'nonce_consumed_demo'=>true,
            ];
        }catch(PDOException $error){
            return self::denied('nonce_storage_unavailable_fail_closed');
        }catch(\Throwable $error){
            return self::denied('original_portal_read_unavailable');
        }
    }
}
