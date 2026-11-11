<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

require_once __DIR__.'/SyntheticManagedScopeLedger.php';

/**
 * F156: versioned offline contract intended for future Imagine Portal reader.
 * There is NO HTTP endpoint and caller-supplied tenant is NOT authentication.
 */
final class F156SyntheticPortalReadBundle
{
    /** @return array<string,mixed>|null */
    public function build(
        SyntheticManagedScopeLedger $ledger,
        ?array $scope,?array $reviews,?array $attention,
        string $expectedTenant
    ):?array {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1'
            ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
            ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
            ||getenv('PERF_F154_ATTENTION_LAB')!=='1'
            ||getenv('PERF_F156_PORTAL_BUNDLE_LAB')!=='1'
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$expectedTenant)) {
            return null;
        }
        if(!is_array($scope)||!is_array($reviews)||!is_array($attention)
            ||($scope['schema_version']??null)!=='perf.portal.scope-preview.synthetic.v1'
            ||($reviews['schema_version']??null)!=='perf.portal.creative-reviews.synthetic.v1'
            ||($attention['schema_version']??null)!=='perf.portal.attention.synthetic.v1'
            ||($scope['tenant_ref']??null)!==$expectedTenant
            ||($reviews['tenant_ref']??null)!==$expectedTenant
            ||($attention['tenant_ref']??null)!==$expectedTenant) {
            return null;
        }
        foreach([$scope,$reviews,$attention] as $dto){
            if(($dto['source']??null)!=='in_memory_fixture_only'
                ||($dto['read_only']??null)!==true
                ||($dto['contracted']??null)!==false
                ||($dto['authorization_eligible']??null)!==false
                ||($dto['dispatch_enabled']??null)!==false
                ||($dto['billing_enabled']??null)!==false
                ||($dto['ready_for_production']??null)!==false)return null;
        }
        if(($reviews['human_approval_verified']??null)!==false
            ||($attention['human_approval_verified']??null)!==false
            ||($attention['real_metrics_available']??null)!==false
            ||!is_array($scope['channels']??null)
            ||count($scope['channels'])!==2
            ||!is_array($reviews['reviews']??null)
            ||!is_array($attention['channels']??null)
            ||count($attention['channels'])!==2
            ||!is_int($reviews['review_count']??null)
            ||$reviews['review_count']!==count($reviews['reviews'])
            ||$reviews['review_count']>25)return null;
        $sourceChannels=[];
        foreach($scope['channels'] as $row){
            if(!is_array($row)
                ||!in_array($row['id']??null,['google_ads','meta'],true)
                ||isset($sourceChannels[$row['id']])
                ||!is_array($row['campaigns']??null)
                ||!is_array($row['groups']??null))return null;
            foreach(['campaigns','groups'] as $field){
                $count=$row[$field];
                if(!is_int($count['used']??null)
                    ||$count['used']<0||$count['used']>10000
                    ||!array_key_exists('limit',$count)
                    ||($count['limit']!==null
                        &&(!is_int($count['limit'])||$count['limit']<0
                           ||$count['used']>$count['limit']))
                    ||!array_key_exists('remaining',$count)
                    ||($count['limit']===null && $count['remaining']!==null)
                    ||($count['limit']!==null
                        &&$count['remaining']!==$count['limit']-$count['used']))
                    return null;
            }
            $sourceChannels[$row['id']]=$row;
        }
        if(!isset($sourceChannels['google_ads'],$sourceChannels['meta']))return null;
        $reviewRows=[];$byChannel=[
            'google_ads'=>['pending'=>0,'changes'=>0],
            'meta'=>['pending'=>0,'changes'=>0],
        ];$seen=[];
        foreach($reviews['reviews'] as $row){
            if(!is_array($row)
                ||!is_string($row['review_ref']??null)
                ||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$row['review_ref'])
                ||isset($seen[$row['review_ref']])
                ||!is_string($row['campaign_ref']??null)
                ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$row['campaign_ref'])
                ||!in_array($row['channel']??null,['google_ads','meta'],true)
                ||$ledger->campaignChannel($expectedTenant,$row['campaign_ref'])!==$row['channel']
                ||!in_array($row['status']??null,[
                    'pending_demo','changes_requested_demo',
                    'approved_mock_only','rejected_mock_only'
                ],true)
                ||!is_int($row['version']??null)||$row['version']<1
                ||($row['action_authorized']??null)!==false
                ||($row['human_approval_verified']??null)!==false
                ||($row['billing_enabled']??null)!==false
                ||!is_string($row['title']??null)||$row['title']===''
                ||strlen($row['title'])>100
                ||preg_match('/[<>\x00-\x1f\x7f]/',$row['title']))return null;
            $seen[$row['review_ref']]=true;
            $channel=$row['channel'];
            if($row['status']==='pending_demo')$byChannel[$channel]['pending']++;
            if($row['status']==='changes_requested_demo')$byChannel[$channel]['changes']++;
            $reviewRows[]=[
                'review_ref'=>$row['review_ref'],
                'campaign_ref'=>$row['campaign_ref'],
                'channel'=>$channel,'title'=>$row['title'],
                'status'=>$row['status'],'version'=>$row['version'],
                'human_approval_verified'=>false,
                'action_authorized'=>false,
            ];
        }
        $attentionRows=[];
        foreach($attention['channels'] as $row){
            if(!is_array($row)
                ||!isset($byChannel[$row['id']??''])
                ||isset($attentionRows[$row['id']])
                ||($row['pending_demo']??null)!==$byChannel[$row['id']]['pending']
                ||($row['changes_requested_demo']??null)!==$byChannel[$row['id']]['changes']
                ||($row['provider_action']??null)!=='none_synthetic'
                ||!is_array($row['alerts']??null))return null;
            foreach($row['alerts'] as $alert) {
                if(!is_array($alert)||($alert['action_enabled']??null)!==false
                    ||!is_string($alert['code']??null)
                    ||!is_string($alert['title']??null)
                    ||!is_int($alert['count']??null)
                    ||$alert['count']<0)return null;
            }
            $attentionRows[$row['id']]=[
                'channel'=>$row['id'],
                'pending_demo'=>$row['pending_demo'],
                'changes_requested_demo'=>$row['changes_requested_demo'],
                'alerts'=>$row['alerts'],
            ];
        }
        if(count($attentionRows)!==2)return null;
        $capacity=[];
        foreach(['google_ads','meta'] as $channel){
            $src=$sourceChannels[$channel];
            $capacity[]=[
                'channel'=>$channel,'campaigns'=>$src['campaigns'],
                'groups'=>$src['groups'],
                'commercial_approved'=>false,
            ];
        }
        return [
            'schema_version'=>'perf.portal.performance-dashboard.synthetic.v1',
            'source'=>'offline_fixture_only','tenant_ref'=>$expectedTenant,
            'read_only'=>true,'contracted'=>false,
            'iam_verified'=>false,'s2s_verified'=>false,
            'authorization_eligible'=>false,'human_approval_verified'=>false,
            'dispatch_enabled'=>false,'billing_enabled'=>false,
            'ready_for_production'=>false,'real_metrics_available'=>false,
            'campaign_capacity'=>$capacity,
            'creative_reviews'=>$reviewRows,
            'attention'=>array_values($attentionRows),
            'review_count_demo'=>count($reviewRows),
        ];
    }
}
