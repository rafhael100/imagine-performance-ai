<?php

declare(strict_types=1);

namespace Imagine\Protocol\Lab;

use JsonException;
use PDO;
use Throwable;

/**
 * F1-24: Read-only SQL-to-Portal OFFLINE DEMO projection.
 *
 * Output is synthetic and cannot be used as authenticated customer state,
 * commercial entitlement, actual campaign delivery, metrics or live Portal
 * data. The SQL role is SELECT-only, and an explicit read-only transaction is
 * required. No network, no Laravel routes/jobs and no provider credentials.
 */
final class SyntheticCampaignPortalProjection
{
    private const TENANT_ID = '11111111-1111-4111-8111-111111111111';
    private const ORGANIZATION_REF = 'org-demo-a';
    private const SERVICE_REF = 'svc-demo-a';
    private const TENANT_REF = 'perf-demo-a';

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function build(string $organization, string $tenantRef): ?array
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F24_READ_MODEL_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry'
            || $organization !== self::ORGANIZATION_REF
            || $tenantRef !== self::TENANT_REF) {
            return null;
        }
        try {
            $identity = $this->db->query(
                'SELECT current_database() AS db,current_user AS role'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity)
                || $identity['db'] !== 'perf_test_campaign_registry'
                || $identity['role'] !== 'perf_f24_reader') {
                return null;
            }
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION READ ONLY');
            $scope=$this->db->prepare(
                "SELECT set_config('app.performance_tenant_id', ?, true)"
            );
            $scope->execute([self::TENANT_ID]);

            $sql=$this->db->prepare(
                'SELECT id,campaign_ref,campaign_label,channel,lifecycle_state,
                        managed,dispatch_state
                 FROM performance_managed_campaigns
                 WHERE tenant_id=? AND portal_service_ref=?
                 ORDER BY campaign_ref ASC'
            );
            $sql->execute([self::TENANT_ID,self::SERVICE_REF]);
            $rawCampaigns=$sql->fetchAll(PDO::FETCH_ASSOC);
            if (count($rawCampaigns)>20) {
                $this->db->rollBack();
                return null;
            }
            $known=[];
            $campaigns=[];
            $paused=0;
            foreach ($rawCampaigns as $row) {
                $rawRef=$row['campaign_ref'];
                $label=$row['campaign_label'];
                if (!is_string($rawRef)
                    || !preg_match('/^cam-[a-z0-9-]{1,70}$/D',$rawRef)
                    || !is_string($label)
                    || !preg_match('/^[a-zA-Z0-9 -]{1,100}$/D',$label)
                    || !in_array($row['channel'],['google_ads','meta'],true)
                    || !in_array($row['lifecycle_state'],['draft','paused','archived'],true)
                    || $row['dispatch_state'] !== 'disabled'
                    || !in_array($row['managed'],[true,'t','1',1],true)
                    || isset($known[$row['id']])) {
                    $this->db->rollBack();
                    return null;
                }
                $known[$row['id']]='demo-'.$rawRef;
                if ($row['lifecycle_state']==='paused') {
                    ++$paused;
                }
                $campaigns[]=[
                    'ref'=>'demo-'.$rawRef,
                    'channel'=>$row['channel'],
                    'title'=>$label.' (exemplo)',
                    'state'=>$row['lifecycle_state'].'_demo',
                    'managed'=>true,
                ];
            }

            $intents=$this->db->prepare(
                'SELECT i.campaign_id,i.operation,i.review_state,
                        i.request_ref,i.manifest_version,i.dispatch_state
                 FROM performance_campaign_intents i
                 INNER JOIN performance_managed_campaigns c
                   ON c.tenant_id=i.tenant_id
                  AND c.id=i.campaign_id
                  AND c.portal_service_ref=i.portal_service_ref
                 WHERE i.tenant_id=? AND i.portal_service_ref=?
                 ORDER BY i.request_ref ASC'
            );
            $intents->execute([self::TENANT_ID,self::SERVICE_REF]);
            $rawIntents=$intents->fetchAll(PDO::FETCH_ASSOC);
            if(count($rawIntents)>20){
                $this->db->rollBack();
                return null;
            }
            $approvals=[];
            $pending=0;$approved=0;$rejected=0;
            foreach($rawIntents as $row){
                if (!isset($known[$row['campaign_id']])
                    || !is_string($row['request_ref'])
                    || !preg_match('/^(?:req-demo|request-demo)-[a-z0-9-]{1,70}$/D',$row['request_ref'])
                    || $row['manifest_version'] !== 'manifest-demo-v1'
                    || $row['dispatch_state'] !== 'disabled'
                    || !in_array($row['operation'],[
                        'campaign.create','campaign.pause',
                        'campaign.replace','campaign.release'
                    ],true)) {
                    $this->db->rollBack();
                    return null;
                }
                $status=match($row['review_state']){
                    'awaiting_approval'=>'pending_human_demo',
                    'approved_internal'=>'approved_demo_no_execution',
                    'rejected'=>'rejected_demo',
                    default=>null,
                };
                if ($status===null){
                    $this->db->rollBack();
                    return null;
                }
                match ($status) {
                    'pending_human_demo' => ++$pending,
                    'approved_demo_no_execution' => ++$approved,
                    'rejected_demo' => ++$rejected,
                };
                $approvals[]=[
                    'ref'=>'demo-'.$row['request_ref'],
                    'campaign_ref'=>$known[$row['campaign_id']],
                    'operation'=>$row['operation'],
                    'status'=>$status,
                    'manifest_version'=>'demo-v1',
                ];
            }
            $this->db->commit();
            return [
                'schema_version'=>'perf.portal.storage.synthetic.v1',
                'producer'=>'imagine-performance-ai-lab',
                'source'=>'synthetic_postgresql_only',
                'portal_organization_ref'=>self::ORGANIZATION_REF,
                'performance_tenant_ref'=>self::TENANT_REF,
                'portal_service_ref'=>self::SERVICE_REF,
                'read_only'=>true,
                'contracted'=>false,
                'dispatch_enabled'=>false,
                'commercial'=>[
                    'capacity_state'=>'unknown_not_for_sale_demo',
                    'price_state'=>'not_homologated',
                ],
                'metrics'=>[
                    'managed_campaigns'=>count($campaigns),
                    'paused_campaigns'=>$paused,
                    'approvals_pending'=>$pending,
                    'approvals_approved_internal'=>$approved,
                    'approvals_rejected'=>$rejected,
                    'clicks'=>null,
                    'media_spend'=>null,
                ],
                'campaigns'=>$campaigns,
                'approvals'=>$approvals,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return null;
        }
    }

    public function exportJson(string $org,string $tenant): ?string
    {
        $projection=$this->build($org,$tenant);
        if($projection===null){
            return null;
        }
        try {
            return json_encode(
                $projection,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )."\n";
        } catch(JsonException) {
            return null;
        }
    }
}
