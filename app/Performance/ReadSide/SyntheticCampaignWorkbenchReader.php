<?php

declare(strict_types=1);

namespace App\Performance\ReadSide;

use PDO;
use Throwable;

/**
 * F1-28 internal Laravel namespace, READ ONLY; no routes or ServiceProvider.
 * It consumes only fictional SQL fixtures in CI and returns a safe,
 * strictly-presentational model. There is NO authenticated user or Portal
 * contract here. Do not turn this into an HTTP endpoint.
 */
final readonly class SyntheticWorkbenchScope
{
    private function __construct(
        public string $tenantId,
        public string $tenantRef,
        public string $organizationRef,
        public string $serviceRef,
    ) {
    }

    public static function forSyntheticLabOnly(): ?self
    {
        if (PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('RUN_PERF_F22_PG_LAB')!=='1'
            || getenv('PERF_F28_APP_READ_LAB')!=='1') {
            return null;
        }
        return new self(
            '11111111-1111-4111-8111-111111111111',
            'perf-demo-a',
            'org-demo-a',
            'svc-demo-a',
        );
    }
}

final class SyntheticCampaignWorkbenchReader
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function read(?SyntheticWorkbenchScope $scope): ?array
    {
        if ($scope===null
            || PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('RUN_PERF_F22_PG_LAB')!=='1'
            || getenv('PERF_F28_APP_READ_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return null;
        }
        try {
            $role=$this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($role)
                || $role['db']!=='perf_test_campaign_registry'
                || $role['role']!=='perf_f28_app_reader') {
                return null;
            }
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION READ ONLY');
            $set=$this->db->prepare(
                "SELECT set_config('app.performance_tenant_id', ?, true)"
            );
            $set->execute([$scope->tenantId]);

            $link=$this->db->prepare(
                'SELECT tenant_id,portal_organization_ref,portal_service_ref,
                        link_status,source_revision
                 FROM portal_service_links
                 WHERE tenant_id=? AND portal_organization_ref=?
                   AND portal_service_ref=?'
            );
            $link->execute([
                $scope->tenantId,$scope->organizationRef,$scope->serviceRef,
            ]);
            $service=$link->fetch(PDO::FETCH_ASSOC);
            if (!is_array($service)
                || $service['tenant_id']!==$scope->tenantId
                || $service['portal_organization_ref']!==$scope->organizationRef
                || $service['portal_service_ref']!==$scope->serviceRef
                || $service['link_status']!=='active_demo'
                || $service['source_revision']!=='demo-revision-1') {
                $this->db->rollBack();
                return null;
            }

            $campaignStatement=$this->db->prepare(
                'SELECT id,campaign_ref,campaign_label,channel,lifecycle_state,
                        managed,dispatch_state
                 FROM performance_managed_campaigns
                 WHERE tenant_id=? AND portal_service_ref=?
                 ORDER BY campaign_ref'
            );
            $campaignStatement->execute([$scope->tenantId,$scope->serviceRef]);
            $raw=$campaignStatement->fetchAll(PDO::FETCH_ASSOC);
            if (count($raw)>20) {
                $this->db->rollBack();
                return null;
            }
            $known=[];$campaigns=[];$paused=0;
            foreach($raw as $row){
                if (!is_string($row['campaign_ref'])
                    || !preg_match('/^cam-[a-z0-9-]{1,70}$/D',$row['campaign_ref'])
                    || !is_string($row['campaign_label'])
                    || !preg_match('/^[a-zA-Z0-9 -]{1,100}$/D',$row['campaign_label'])
                    || !in_array($row['channel'],['google_ads','meta'],true)
                    || !in_array($row['lifecycle_state'],['draft','paused','archived'],true)
                    || $row['dispatch_state']!=='disabled'
                    || !in_array($row['managed'],[true,'t','1',1],true)
                    || isset($known[$row['id']])) {
                    $this->db->rollBack();
                    return null;
                }
                $known[$row['id']]='demo-'.$row['campaign_ref'];
                $campaigns[]=[
                    'ref'=>'demo-'.$row['campaign_ref'],
                    'title'=>$row['campaign_label'].' (exemplo)',
                    'channel'=>$row['channel'],
                    'state'=>$row['lifecycle_state'].'_demo',
                ];
                if($row['lifecycle_state']==='paused') ++$paused;
            }

            $intentQuery=$this->db->prepare(
                'SELECT i.campaign_id,i.request_ref,i.operation,i.review_state,
                        i.dispatch_state,i.manifest_version,
                        j.id AS journal_id,j.review_state AS journal_review_state
                 FROM performance_campaign_intents i
                 LEFT JOIN performance_intent_review_journal j
                   ON j.intent_id=i.id AND j.tenant_id=i.tenant_id
                 WHERE i.tenant_id=? AND i.portal_service_ref=?
                 ORDER BY i.request_ref'
            );
            $intentQuery->execute([$scope->tenantId,$scope->serviceRef]);
            $rawIntents=$intentQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($rawIntents)>20){
                $this->db->rollBack();
                return null;
            }
            $requests=[];$pending=0;$approved=0;$rejected=0;
            foreach($rawIntents as $row){
                if (!isset($known[$row['campaign_id']])
                    || !is_string($row['request_ref'])
                    || !preg_match(
                        '/^(?:req-demo|request-demo)-[a-z0-9-]{1,70}$/D',
                        $row['request_ref']
                    )
                    || !in_array($row['operation'],[
                        'campaign.create','campaign.pause',
                        'campaign.replace','campaign.release'
                    ],true)
                    || $row['dispatch_state']!=='disabled'
                    || $row['manifest_version']!=='manifest-demo-v1'
                    || !in_array($row['review_state'],[
                        'awaiting_approval','approved_internal','rejected'
                    ],true)) {
                    $this->db->rollBack();
                    return null;
                }
                $isFinal=$row['review_state']!=='awaiting_approval';
                if ($isFinal
                    && (!is_string($row['journal_id'])
                        || $row['journal_review_state']!==$row['review_state'])) {
                    $this->db->rollBack();
                    return null; // never show an unjournaled final decision
                }
                if (!$isFinal && $row['journal_id']!==null) {
                    $this->db->rollBack();
                    return null;
                }
                $status=match($row['review_state']){
                    'awaiting_approval'=>'pending_human_demo',
                    'approved_internal'=>'approved_demo_no_execution',
                    'rejected'=>'rejected_demo'
                };
                if($status==='pending_human_demo') ++$pending;
                elseif($status==='approved_demo_no_execution') ++$approved;
                else ++$rejected;
                $requests[]=[
                    'ref'=>'demo-'.$row['request_ref'],
                    'campaign_ref'=>$known[$row['campaign_id']],
                    'operation'=>$row['operation'],
                    'status'=>$status,
                    'journaled'=>$isFinal,
                ];
            }

            $this->db->commit();
            return [
                'schema_version'=>'perf.internal.workbench.synthetic.v1',
                'source'=>'synthetic_postgresql_lab_only',
                'organization_ref'=>$scope->organizationRef,
                'tenant_ref'=>$scope->tenantRef,
                'service_ref'=>$scope->serviceRef,
                'read_only'=>true,
                'contracted'=>false,
                'dispatch_enabled'=>false,
                'commercial'=>'not_homologated',
                'counts'=>[
                    'managed_campaigns'=>count($campaigns),
                    'paused_campaigns'=>$paused,
                    'approvals_pending'=>$pending,
                    'approvals_approved_internal'=>$approved,
                    'approvals_rejected'=>$rejected,
                ],
                'campaigns'=>$campaigns,
                'requests'=>$requests,
            ];
        } catch(Throwable) {
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }
            return null;
        }
    }
}
