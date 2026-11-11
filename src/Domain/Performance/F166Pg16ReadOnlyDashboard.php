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

    /** @return array<string,mixed>|null */
    public function read(string $tenant):?array
    {
        if($tenant!==$this->tenant
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant))return null;
        try{
            $statement=$this->db->prepare('SELECT reference,campaign,channel,title,status,version
                FROM perf_f162.active_review_dashboard
                WHERE tenant=? ORDER BY reference COLLATE "C" LIMIT 26');
            $statement->execute([$tenant]);
            $reviews=$statement->fetchAll(PDO::FETCH_ASSOC);
            if(count($reviews)>25)return null;
            $stats=[
                'pending_demo'=>0,'changes_requested_demo'=>0,
                'approved_mock_only'=>0,'rejected_mock_only'=>0,
            ];
            $seen=[];$items=[];
            foreach($reviews as $row) {
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
                    ||(int)$row['version']<1 ||(int)$row['version']>1000)return null;
                $seen[$row['reference']]=true;
                $stats[$row['status']]++;
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
            $query=$this->db->prepare('SELECT channel,count_retired_demo
                FROM perf_f162.retired_review_counts WHERE tenant=?');
            $query->execute([$tenant]);
            $retired=['google_ads'=>0,'meta'=>0];
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
                if(!isset($retired[$row['channel']]))return null;
                $retired[$row['channel']]=(int)$row['count_retired_demo'];
                if($retired[$row['channel']]<0 || $retired[$row['channel']]>25)return null;
            }
            $query=$this->db->prepare('SELECT event_type,event_count_demo
                FROM perf_f162.campaign_scope_activity WHERE tenant=?');
            $query->execute([$tenant]);
            $activity=[];
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
                if(!in_array($row['event_type'],[
                    'baseline_demo','registered_demo','retired_demo',
                    'reactivated_demo',
                ],true) || isset($activity[$row['event_type']]))return null;
                $activity[$row['event_type']]=(int)$row['event_count_demo'];
                if($activity[$row['event_type']]<0
                    ||$activity[$row['event_type']]>2000)return null;
            }
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
                'retired_review_counts_demo'=>$retired,
                'campaign_scope_events_demo'=>$activity,
            ];
        }catch(PDOException $e){
            return null;
        }
    }
}
