<?php
declare(strict_types=1);

namespace App\Performance\Contracts;

/**
 * F1-84: convert the existing F28 PostgreSQL READ ONLY lab projection
 * to an explicitly versioned, offline Portal candidate. Never an endpoint.
 *
 * Importantly, Portal's DemoCampaignStage/DemoRequestStage do not yet have
 * 1:1 meanings for all Performance states. This object does NOT pretend
 * that paused/archived/approved/rejected are Portal-authorized statuses.
 */
final class PortalWorkbenchOfflineProjection
{
    public function project(mixed $input): ?array
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F84_WORKBENCH_LAB')!=='1') {
            return null;
        }
        if (!is_array($input) || array_keys($input)!==[
            'schema_version','source','organization_ref','tenant_ref',
            'service_ref','read_only','contracted','dispatch_enabled',
            'commercial','counts','campaigns','requests'
        ] || $input['schema_version']!=='perf.internal.workbench.synthetic.v1'
            || $input['source']!=='synthetic_postgresql_lab_only'
            || $input['read_only']!==true || $input['contracted']!==false
            || $input['dispatch_enabled']!==false
            || $input['commercial']!=='not_homologated'
            || !$this->ref($input['organization_ref'])
            || !$this->ref($input['tenant_ref'])
            || !$this->ref($input['service_ref'])
            || !is_array($input['counts'])
            || array_keys($input['counts'])!==[
                'managed_campaigns','paused_campaigns','approvals_pending',
                'approvals_approved_internal','approvals_rejected'
            ] || !is_array($input['campaigns']) || !array_is_list($input['campaigns'])
            || !is_array($input['requests']) || !array_is_list($input['requests'])
            || count($input['campaigns'])>20 || count($input['requests'])>20) {
            return null;
        }
        foreach ($input['counts'] as $value) {
            if (!is_int($value) || $value<0 || $value>20) return null;
        }

        $campaigns=[];$known=[];$paused=0;
        foreach($input['campaigns'] as $c) {
            if (!is_array($c) || array_keys($c)!==['ref','title','channel','state']
                || !$this->campaignRef($c['ref'])
                || !is_string($c['title']) || strlen($c['title'])>120
                || !preg_match('/^[a-zA-Z0-9 ()-]{1,120}$/D',$c['title'])
                || !is_string($c['channel'])
                || !in_array($c['state'],['draft_demo','paused_demo','archived_demo'],true)
                || isset($known[$c['ref']])) return null;
            $mapped=PortalPerformanceChannelContract::toPortal($c['channel']);
            if ($mapped===null) return null;
            $known[$c['ref']]=true;
            if($c['state']==='paused_demo')++$paused;
            $campaigns[]=[
                'ref'=>$c['ref'],
                'title'=>$c['title'],
                'portal_channel'=>$mapped,
                'performance_state'=>$c['state'],
                'portal_stage'=>null,
            ];
        }
        $requests=[];$refs=[];$counts=['pending_human_demo'=>0,
            'approved_demo_no_execution'=>0,'rejected_demo'=>0];
        foreach($input['requests'] as $r) {
            if (!is_array($r)
                || array_keys($r)!==['ref','campaign_ref','operation','status','journaled']
                || !is_string($r['ref'])
                || !preg_match('/^demo-(?:req-demo|request-demo)-[a-z0-9-]{1,70}$/D',$r['ref'])
                || !is_string($r['campaign_ref']) || !isset($known[$r['campaign_ref']])
                || !in_array($r['operation'],[
                    'campaign.create','campaign.pause',
                    'campaign.replace','campaign.release'
                ],true)
                || !is_string($r['status'])
                || !array_key_exists($r['status'],$counts)
                || !is_bool($r['journaled'])
                || ($r['status']==='pending_human_demo')===$r['journaled']
                || isset($refs[$r['ref']])) return null;
            $refs[$r['ref']]=true;
            ++$counts[$r['status']];
            $requests[]=[
                'ref'=>$r['ref'],'campaign_ref'=>$r['campaign_ref'],
                'operation'=>$r['operation'],
                'performance_status'=>$r['status'],
                'portal_stage'=>null,
            ];
        }
        if ($input['counts']['managed_campaigns']!==count($campaigns)
            || $input['counts']['paused_campaigns']!==$paused
            || $input['counts']['approvals_pending']!==$counts['pending_human_demo']
            || $input['counts']['approvals_approved_internal']!==$counts['approved_demo_no_execution']
            || $input['counts']['approvals_rejected']!==$counts['rejected_demo']) {
            return null;
        }
        return [
            'schema_version'=>'perf.portal.workbench.offline.synthetic.v1',
            'organization_ref'=>$input['organization_ref'],
            'tenant_ref'=>$input['tenant_ref'],
            'service_ref'=>$input['service_ref'],
            'channel_contract'=>PortalPerformanceChannelContract::SCHEMA,
            'portal_stage_mapping_ready'=>false,
            'portal_stage_mapping_status'=>'pending_explicit_enum_alignment',
            'read_only'=>true,'synthetic_only'=>true,'contracted'=>false,
            'portal_identity_authenticated'=>false,
            'ready_for_production'=>false,'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'counts'=>$input['counts'],'campaigns'=>$campaigns,'requests'=>$requests,
        ];
    }

    private function ref(mixed $value):bool
    {
        return is_string($value) && strlen($value)<=128
            && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}$/D',$value)===1;
    }

    private function campaignRef(mixed $value):bool
    {
        return is_string($value)
            && preg_match('/^demo-cam-[a-z0-9-]{1,70}$/D',$value)===1;
    }
}
