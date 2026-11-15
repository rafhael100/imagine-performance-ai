<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Disposable PostgreSQL18 CI registry reader. Unlike a caller-owned fixture
 * array, the decision data is read from a tenant-scoped SQL table protected by
 * authenticated session_user RLS. All rows STILL represent synthetic consent;
 * no Portal IAM, verified advertiser ownership or operational Ads permission.
 */
final class PgPortalAdsEntitlementSnapshotLab
{
    public function __construct(
        private readonly PDO $db,
        private readonly ?\Closure $ciObservationClock = null
    ) {}

    /**
     * @param array<string,string> $selector expected tenant, agreement, period, account
     * @return array<string,mixed> exact schema required by PortalAdsEntitlementSnapshotLab
     */
    public function resolve(array $selector): array
    {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PG_V6_ROLE_LAB') !== '1' ||
            getenv('PERF_PG_ADS_ENTITLEMENT_LAB') !== '1') {
            throw new RuntimeException('PG18 entitlement fixture reader disabled.');
        }
        $keys = array_keys($selector);
        sort($keys, SORT_STRING);
        if ($keys !== ['account_id','agreement_id','period','tenant_id']) {
            throw new InvalidArgumentException('Unknown entitlement selector.');
        }
        foreach ([
            'tenant_id'=>'/\Atenant_[A-Za-z0-9_-]{8,64}\z/D',
            'agreement_id'=>'/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D',
            'period'=>'/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',
            'account_id'=>'/\Aadacct_[A-Za-z0-9]{3,64}\z/D',
        ] as $name=>$pattern) {
            if (!is_string($selector[$name]) ||
                !preg_match($pattern,$selector[$name])) {
                throw new InvalidArgumentException('Invalid entitlement selector value.');
            }
        }
        try {
            $login=$this->db->query('SELECT session_user')->fetchColumn();
            if (!in_array($login,['perf_v6_tenant_alpha','perf_v6_tenant_beta'],true)) {
                throw new RuntimeException('Unexpected SQL login.');
            }
            $tenant=$this->db->query(
                'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
            )->fetchColumn();
            if (!is_string($tenant) ||
                !hash_equals($selector['tenant_id'],$tenant)) {
                throw new RuntimeException('Authenticated SQL tenant mismatch.');
            }
            $statement=$this->db->prepare(
                'SELECT tenant_id,agreement_id,period,provider,account_id,
                    credential_reference,consent_reference,agreement_status,
                    agreement_valid_from,agreement_valid_until,account_status,
                    owner_tenant_id,consent_status,consent_granted_at,
                    consent_expires_at,consent_revoked_at,permission,
                    snapshot_version
                 FROM perf_fin_role_lab.ads_read_entitlement_snapshots
                 WHERE tenant_id = :tenant AND agreement_id = :agreement
                   AND period = :period AND account_id = :account'
            );
            $statement->execute([
                'tenant'=>$selector['tenant_id'],
                'agreement'=>$selector['agreement_id'],
                'period'=>$selector['period'],
                'account'=>$selector['account_id'],
            ]);
            $data=$statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($data) || $statement->fetch(PDO::FETCH_ASSOC)!==false) {
                throw new RuntimeException('Entitlement missing or ambiguous.');
            }
            foreach ([
                'agreement_valid_from','agreement_valid_until','consent_granted_at',
                'consent_expires_at','snapshot_version',
            ] as $field) {
                $data[$field]=self::integer($data[$field]);
            }
            if ($data['snapshot_version'] < 1) {
                throw new RuntimeException('Invalid entitlement version.');
            }
            if ($data['consent_revoked_at'] !== null) {
                $data['consent_revoked_at']=self::integer($data['consent_revoked_at']);
            }
            unset($data['snapshot_version']);
            $data['source']='synthetic_registry';
            // Time of THIS DB read, not a client-supplied clock. The guard
            // evaluates freshness again with the same host's clock.
            $observed = $this->ciObservationClock === null
                ? time()
                : ($this->ciObservationClock)();
            if(!is_int($observed) || $observed<=0){
                throw new RuntimeException('Invalid simulated observation clock.');
            }
            $data['observed_at']=$observed;
            return $data;
        } catch (Throwable) {
            // Do not return SQL errors, raw account rows or connection details.
            throw new RuntimeException('Tenant-bound entitlement read denied or unavailable.');
        }
    }

    private static function integer(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) ||
            !preg_match('/\A[0-9]{1,15}\z/D',(string)$value)) {
            throw new RuntimeException('Invalid SQL entitlement timestamp.');
        }
        return (int)$value;
    }
}
