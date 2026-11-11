<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use PDOException;
use RuntimeException;

/**
 * F166: synthetic PG16 tenant-read-only Portal payload.
 * The session_user PostgreSQL fixture selects the tenant; caller parameters
 * cannot switch it. No HTTP route, no real Portal IAM, Ads or billing.
 */
final class F166Pg16ReadOnlyDashboard
{
    private string $tenant;

    public function __construct(private PDO $db)
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
            ||getenv('PERF_F162_PG_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_f162_ci'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432') {
            throw new RuntimeException('F166 restricted to local disposable PG16 CI');
        }
        $identity=$this->db->query('SELECT current_database() AS db,
            session_user AS role_name,
            perf_f162.tenant_from_session() AS tenant')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($identity)
            ||$identity['db']!=='perf_f162_ci'
            ||!in_array($identity['role_name'],['perf_f162_a','perf_f162_b'],true)
            ||!in_array($identity['tenant'],['perf-demo-aurora','perf-demo-boreal'],true)) {
            throw new RuntimeException('F166 unknown fictional SQL login');
        }
        $this->tenant=$identity['tenant'];
    }

    /**
     * CI-only credential-bound read.
     *
     * READ COMMITTED is intentional: each later SELECT observes newly
     * committed campaign retirements and role revocations. A second query
     * pass refuses internally inconsistent snapshots. This cannot prevent
     * revocation after the final check and is NOT operational IAM.
     *
     * @return array<string,mixed>|null
     */
    public function read(string $tenant):?array
    {
        if($tenant!==$this->tenant
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant)
            ||$this->db->inTransaction())return null;
        try {
            $this->db->beginTransaction();
            $this->db->exec(
                'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY'
            );
            $identitySql='SELECT current_database() AS db,
                session_user AS role_name,
                perf_f162.tenant_from_session() AS tenant';
            $identityBefore=$this->db->query($identitySql)->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identityBefore)
                ||$identityBefore['db']!=='perf_f162_ci'
                ||!in_array($identityBefore['role_name'],['perf_f162_a','perf_f162_b'],true)
                ||$identityBefore['tenant']!==$tenant)return null;

            $readRows=function()use($tenant):array {
                $stmt=$this->db->prepare(
                    'SELECT reference,campaign,channel,title,status,version
                     FROM perf_f162.active_review_dashboard
                     WHERE tenant=? ORDER BY reference COLLATE "C" LIMIT 26'
                );
                $stmt->execute([$tenant]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            };
            $readRetired=function()use($tenant):array {
                $stmt=$this->db->prepare(
                    'SELECT channel,count_retired_demo
                     FROM perf_f162.retired_review_counts WHERE tenant=?
                     ORDER BY channel COLLATE "C"'
                );
                $stmt->execute([$tenant]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            };
            $readActivity=function()use($tenant):array {
                $stmt=$this->db->prepare(
                    'SELECT event_type,event_count_demo
                     FROM perf_f162.campaign_scope_activity WHERE tenant=?
                     ORDER BY event_type COLLATE "C"'
                );
                $stmt->execute([$tenant]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            };

            $reviews=$readRows();
            if(count($reviews)>25)return null;
            $stats=[
                'pending_demo'=>0,'changes_requested_demo'=>0,
                'approved_mock_only'=>0,'rejected_mock_only'=>0,
            ];
            $seen=[];$items=[];
            foreach($reviews as $row){
                if(!is_string($row['reference'])
                    ||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$row['reference'])
                    ||isset($seen[$row['reference']])
                    ||!is_string($row['campaign'])
                    ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$row['campaign'])
                    ||!in_array($row['channel'],['google_ads','meta'],true)
                    ||!isset($stats[$row['status']])
                    ||!is_string($row['title'])||$row['title']===''
                    ||strlen($row['title'])>100
                    ||preg_match('/[<>\x00-\x1f\x7f]/',$row['title'])
                    ||(int)$row['version']<1||(int)$row['version']>1000)return null;
                $seen[$row['reference']]=true;
                ++$stats[$row['status']];
                $items[]=[
                    'review_ref'=>$row['reference'],
                    'campaign_ref'=>$row['campaign'],
                    'channel'=>$row['channel'],
                    'title'=>$row['title'],
                    'status'=>$row['status'],
                    'version'=>(int)$row['version'],
                    'real_approval'=>false,'action_authorized'=>false,
                ];
            }

            $retiredRows=$readRetired();
            $retired=['google_ads'=>0,'meta'=>0];
            foreach($retiredRows as $row){
                if(!isset($retired[$row['channel']]))return null;
                $retired[$row['channel']]=(int)$row['count_retired_demo'];
                if($retired[$row['channel']]<0
                    ||$retired[$row['channel']]>25)return null;
            }
            $activityRows=$readActivity();
            $activity=[];
            foreach($activityRows as $row){
                if(!in_array($row['event_type'],[
                    'baseline_demo','registered_demo','retired_demo',
                    'reactivated_demo',
                ],true)||isset($activity[$row['event_type']]))return null;
                $activity[$row['event_type']]=(int)$row['event_count_demo'];
                if($activity[$row['event_type']]<0
                    ||$activity[$row['event_type']]>2000)return null;
            }

            // F175: fail closed if a committed SQL scope change or review
            // edit became visible during composition, instead of leaking
            // titles/versions that were already retired mid-read.
            if($reviews!==$readRows()
                ||$retiredRows!==$readRetired()
                ||$activityRows!==$readActivity())return null;
            $identityAfter=$this->db->query($identitySql)->fetch(PDO::FETCH_ASSOC);
            if($identityAfter!==$identityBefore)return null;

            $this->db->commit();
            return [
                'schema_version'=>'perf.portal.pg16-tenant-review-report.synthetic.v1',
                'source'=>'pg16_security_invoker_ci_only',
                'tenant_ref'=>$tenant,'read_only'=>true,
                'iam_verified'=>false,'s2s_verified'=>false,
                'human_approval_verified'=>false,
                'authorization_eligible'=>false,
                'contracted'=>false,'dispatch_enabled'=>false,
                'billing_enabled'=>false,'ready_for_production'=>false,
                'real_metrics_available'=>false,
                'active_review_count'=>count($items),
                'reviews'=>$items,
                'review_status_demo'=>$stats,
                'quarantined_review_counts_demo'=>$retired,
                'campaign_scope_events_demo'=>$activity,
            ];
        }catch(\Throwable $e){
            // Diagnostics are intentionally limited to SQLSTATE so that
            // even test logs do not disclose tenant or review content.
            if($e instanceof PDOException
                &&getenv('PERF_F166_READ_DASHBOARD_LAB')==='1'){
                fwrite(STDERR,'F166 query denial SQLSTATE='.$e->getCode()."\n");
            }
            return null;
        }finally{
            if($this->db->inTransaction())$this->db->rollBack();
        }
    }
}
