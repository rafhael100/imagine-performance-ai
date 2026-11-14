<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use PDO;
use Throwable;
use RuntimeException;

/**
 * PostgreSQL 18 CI-only durable replay claim for Portal Ads-read grants.
 * Uses authenticated login session_user + protected tenant mapping.
 * Unlike earlier in-memory nonce labs, the claim survives process restart
 * and pg_dump/restore. No Portal IAM, vault, Ads or billing.
 */
final class PgPortalAdsReadGrantNonceLab
{
    public function __construct(private readonly PDO $db) {}

    /** @return bool true only when an unseen nonce commits successfully */
    public function claim(string $tenant,string $nonce,int $expiresAt):bool
    {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_PORTAL_ADS_S2S_READ_GRANT_LAB')!=='1' ||
           getenv('PERF_PG_ADS_S2S_NONCE_LAB')!=='1' ||
           $this->db->inTransaction() ||
           !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',$tenant) ||
           !preg_match('/\A[A-Za-z0-9_-]{16,96}\z/D',$nonce) ||
           $expiresAt<=0) {
            return false;
        }
        try {
            $session=$this->db->query('SELECT session_user')->fetchColumn();
            if(!in_array($session,['perf_v6_tenant_alpha','perf_v6_tenant_beta'],true)){
                return false;
            }
            $this->db->beginTransaction();
            $scope=$this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if(!is_string($scope)||!hash_equals($tenant,$scope)){
                $this->db->rollBack();
                return false;
            }
            $sql=$this->db->prepare(
                'INSERT INTO perf_fin_role_lab.ads_read_grant_nonces
                   (tenant_id,nonce,expires_at)
                 VALUES (:tenant,:nonce,:expires)
                 ON CONFLICT (tenant_id,nonce) DO NOTHING
                 RETURNING nonce'
            );
            $sql->execute(['tenant'=>$tenant,'nonce'=>$nonce,'expires'=>$expiresAt]);
            $inserted=$sql->fetchColumn()===$nonce;
            if(!$inserted){
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        } catch(Throwable) {
            if($this->db->inTransaction())$this->db->rollBack();
            return false;
        }
    }

    /** @return array{role_bound_to_authenticated_pg_user:bool,network_enabled:bool,billable:bool} */
    public static function limits():array
    {
        return [
            'role_bound_to_authenticated_pg_user'=>true,
            'network_enabled'=>false,
            'billable'=>false,
        ];
    }
}
