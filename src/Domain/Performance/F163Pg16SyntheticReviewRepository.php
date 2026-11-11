<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/SyntheticManagedScopeLedger.php';

/**
 * F163: CI-only PHP adapter for F162 PostgreSQL16 RLS reviews.
 * SQL session_user is authoritative for one SYNTHETIC tenant, not caller
 * strings or GUC. Real Portal authentication and authorization are absent.
 */
final class F163Pg16SyntheticReviewRepository
{
    private string $sessionTenant;

    public function __construct(
        private PDO $db,
        private SyntheticManagedScopeLedger $scope
    ) {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1'
            ||getenv('PERF_F162_PG_LAB')!=='1'
            ||getenv('PERF_F163_PHP_BRIDGE_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_f162_ci'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432') {
            throw new RuntimeException('F163 PG bridge may only run in disposable CI');
        }
        $sql=$this->db->query('SELECT current_database() AS database_name,
            session_user AS role_name,
            perf_f162.tenant_from_session() AS tenant')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($sql)
            ||$sql['database_name']!=='perf_f162_ci'
            ||!in_array($sql['role_name'],['perf_f162_a','perf_f162_b'],true)
            ||!in_array($sql['tenant'],['perf-demo-aurora','perf-demo-boreal'],true)) {
            throw new RuntimeException('F163 requires a known synthetic RLS login');
        }
        $this->sessionTenant=$sql['tenant'];
    }

    private static function valid(string $value,string $prefix):bool
    {
        return preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$value)===1;
    }

    private function owns(string $tenant):bool
    {
        return self::valid($tenant,'perf-demo-') && $tenant===$this->sessionTenant;
    }

    public function submit(
        string $tenant,string $ref,string $campaign,string $channel,string $title
    ):string {
        if(!$this->owns($tenant))return 'tenant_identity_denied';
        if(!self::valid($ref,'rev-')||!self::valid($campaign,'cam-')
            ||!in_array($channel,['google_ads','meta'],true)
            ||$title===''||strlen($title)>100
            ||preg_match('/[<>\x00-\x1f\x7f]/',$title))return 'invalid_fixture_denied';
        if($this->scope->campaignChannel($tenant,$campaign)!==$channel){
            return 'campaign_out_of_scope_denied';
        }
        try {
            $stmt=$this->db->prepare('INSERT INTO perf_f162.reviews
              (tenant,reference,campaign,channel,title) VALUES(?,?,?,?,?)');
            $stmt->execute([$tenant,$ref,$campaign,$channel,$title]);
            return 'submitted_demo';
        } catch(PDOException $e) {
            if($e->getCode()==='23505')return 'duplicate_review_denied';
            if($e->getCode()==='23514')return 'review_capacity_or_constraint_denied';
            return 'database_unknown_fail_closed';
        }
    }

    public function decide(
        string $tenant,string $ref,int $version,string $decision
    ):string {
        if(!$this->owns($tenant))return 'tenant_identity_denied';
        if(!self::valid($ref,'rev-')||$version<1||$version>999){
            return 'invalid_fixture_denied';
        }
        $states=[
            'approve_mock'=>'approved_mock_only',
            'reject_mock'=>'rejected_mock_only',
            'request_changes'=>'changes_requested_demo',
        ];
        if(!isset($states[$decision]))return 'unknown_decision_denied';
        try {
            $stmt=$this->db->prepare('UPDATE perf_f162.reviews
                SET status=?, version=version+1
                WHERE tenant=? AND reference=? AND version=? AND status=?');
            $stmt->execute([$states[$decision],$tenant,$ref,$version,'pending_demo']);
            return $stmt->rowCount()===1?$states[$decision]:'stale_or_not_pending_denied';
        } catch(PDOException $e) {
            return 'database_unknown_fail_closed';
        }
    }

    public function revise(
        string $tenant,string $ref,int $version,string $title
    ):string {
        if(!$this->owns($tenant))return 'tenant_identity_denied';
        if(!self::valid($ref,'rev-')||$version<1||$version>999
            ||$title===''||strlen($title)>100
            ||preg_match('/[<>\x00-\x1f\x7f]/',$title))return 'invalid_fixture_denied';
        try {
            $check=$this->db->prepare('SELECT campaign,channel
                FROM perf_f162.reviews WHERE tenant=? AND reference=?');
            $check->execute([$tenant,$ref]);
            $scope=$check->fetch(PDO::FETCH_ASSOC);
            if(!is_array($scope))return 'stale_or_not_pending_denied';
            if($this->scope->campaignChannel($tenant,$scope['campaign'])!==$scope['channel'])
                return 'campaign_out_of_scope_denied';
            $stmt=$this->db->prepare('UPDATE perf_f162.reviews
                SET title=?,status=?,version=version+1
                WHERE tenant=? AND reference=? AND version=? AND status=?');
            $stmt->execute([$title,'pending_demo',$tenant,$ref,$version,'changes_requested_demo']);
            return $stmt->rowCount()===1?'resubmitted_demo':'stale_or_not_pending_denied';
        } catch(PDOException $e) {
            return 'database_unknown_fail_closed';
        }
    }

    /** @return array<string,mixed>|null */
    public function project(string $tenant):?array
    {
        if(!$this->owns($tenant))return null;
        try{
            $stmt=$this->db->prepare('SELECT reference,campaign,channel,title,status,version
                FROM perf_f162.reviews WHERE tenant=? ORDER BY reference LIMIT 26');
            $stmt->execute([$tenant]);
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>25)return null;
            $summary=array_fill_keys([
                'pending_demo','changes_requested_demo',
                'approved_mock_only','rejected_mock_only',
            ],0);
            $items=[];
            foreach($rows as $r){
                if($this->scope->campaignChannel($tenant,$r['campaign'])!==$r['channel']){
                    return null;
                }
                $summary[$r['status']]++;
                $items[]=[
                    'review_ref'=>$r['reference'],
                    'campaign_ref'=>$r['campaign'],
                    'channel'=>$r['channel'],
                    'title'=>$r['title'],
                    'status'=>$r['status'],
                    'version'=>(int)$r['version'],
                    'human_approval_verified'=>false,
                    'action_authorized'=>false,'billing_enabled'=>false,
                ];
            }
            $audit=$this->db->prepare('SELECT count(*) FROM perf_f162.events WHERE tenant=?');
            $audit->execute([$tenant]);
            return [
                'schema_version'=>'perf.portal.creative-reviews.pg16-lab.v1',
                'source'=>'disposable_pg16_fixture_only','tenant_ref'=>$tenant,
                'read_only'=>true,'contracted'=>false,
                'iam_verified'=>false,'s2s_verified'=>false,
                'human_approval_verified'=>false,
                'authorization_eligible'=>false,
                'dispatch_enabled'=>false,'billing_enabled'=>false,
                'ready_for_production'=>false,'review_count'=>count($items),
                'summary'=>$summary,'reviews'=>$items,
                'audit_entries_demo'=>(int)$audit->fetchColumn(),
            ];
        } catch(PDOException $e) {
            return null;
        }
    }
}
