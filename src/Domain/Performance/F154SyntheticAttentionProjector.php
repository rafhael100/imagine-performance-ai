<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F154: attention cards from existing synthetic F52/F53 scope and F151
 * reviews; NO actual provider metrics, SLA promises, spending or execution.
 */
final class F154SyntheticAttentionProjector
{
    /** @return array<string,mixed>|null */
    public function build(?array $scope,?array $reviews):?array
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1'
            ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
            ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
            ||getenv('PERF_F154_ATTENTION_LAB')!=='1')return null;
        if(!is_array($scope)||!is_array($reviews)
            ||($scope['schema_version']??null)!=='perf.portal.scope-preview.synthetic.v1'
            ||($reviews['schema_version']??null)!=='perf.portal.creative-reviews.synthetic.v1'
            ||($scope['tenant_ref']??null)!==($reviews['tenant_ref']??null)
            ||!is_string($scope['tenant_ref']??null)
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$scope['tenant_ref'])
            ||($scope['source']??null)!=='in_memory_fixture_only'
            ||($reviews['source']??null)!=='in_memory_fixture_only'
            ||!self::denied($scope)||!self::denied($reviews)
            ||!is_array($scope['channels']??null)
            ||count($scope['channels'])!==2
            ||!is_array($reviews['reviews']??null)
            ||count($reviews['reviews'])>25
            ||!is_int($reviews['review_count']??null)
            ||$reviews['review_count']!==count($reviews['reviews'])){
            return null;
        }
        $channelRows=[];
        foreach($scope['channels'] as $channel){
            if(!is_array($channel)
                ||!in_array($channel['id']??null,['google_ads','meta'],true)
                ||isset($channelRows[$channel['id']])
                ||!is_array($channel['campaigns']??null)
                ||!is_array($channel['groups']??null))return null;
            foreach(['campaigns','groups'] as $type){
                $capacity=$channel[$type];
                if(!is_int($capacity['used']??null)
                    ||$capacity['used']<0||$capacity['used']>10000
                    ||!array_key_exists('limit',$capacity)
                    ||($capacity['limit']!==null &&
                        (!is_int($capacity['limit'])||$capacity['limit']<0
                         ||$capacity['limit']>10000
                         ||$capacity['used']>$capacity['limit']))
                    ||!array_key_exists('remaining',$capacity)
                    ||($capacity['remaining']!==null
                        &&(!is_int($capacity['remaining'])
                            ||$capacity['remaining']<0)))return null;
                if($capacity['limit']!==null
                    &&$capacity['remaining']!==$capacity['limit']-$capacity['used']) {
                    return null;
                }
            }
            $channelRows[$channel['id']]=$channel;
        }
        if(!isset($channelRows['google_ads'],$channelRows['meta']))return null;
        $counts=[
            'google_ads'=>['pending'=>0,'changes'=>0],
            'meta'=>['pending'=>0,'changes'=>0],
        ];
        foreach($reviews['reviews'] as $review){
            if(!is_array($review)
                ||!isset($counts[$review['channel']??''])
                ||!in_array($review['status']??null,[
                    'pending_demo','changes_requested_demo',
                    'approved_mock_only','rejected_mock_only',
                ],true)
                ||($review['action_authorized']??null)!==false
                ||($review['human_approval_verified']??null)!==false
                ||($review['billing_enabled']??null)!==false)return null;
            $c=$review['channel'];
            if($review['status']==='pending_demo')$counts[$c]['pending']++;
            if($review['status']==='changes_requested_demo')$counts[$c]['changes']++;
        }
        $total=0;$backlog=0;$capacityAlerts=0;$channels=[];
        foreach(['google_ads','meta'] as $id){
            $scopeRow=$channelRows[$id];
            $alerts=[];
            if($counts[$id]['changes']>0)$alerts[]=[
                'code'=>'mock_changes_requested',
                'title'=>'Ajustes fictícios solicitados',
                'count'=>$counts[$id]['changes'],'action_enabled'=>false,
            ];
            if($counts[$id]['pending']>0)$alerts[]=[
                'code'=>'mock_review_pending',
                'title'=>'Revisões fictícias pendentes',
                'count'=>$counts[$id]['pending'],'action_enabled'=>false,
            ];
            if($scopeRow['campaigns']['limit']===null
                ||$scopeRow['groups']['limit']===null){
                $alerts[]=['code'=>'capacity_not_homologated',
                    'title'=>'Capacidade comercial não homologada',
                    'count'=>1,'action_enabled'=>false];
                ++$capacityAlerts;
            }elseif($scopeRow['campaigns']['remaining']===0
                ||$scopeRow['groups']['remaining']===0){
                $alerts[]=['code'=>'fictional_capacity_at_limit',
                    'title'=>'Capacidade ilustrativa no limite',
                    'count'=>1,'action_enabled'=>false];
                ++$capacityAlerts;
            }
            $total+=count($alerts);
            $backlog+=$counts[$id]['pending']+$counts[$id]['changes'];
            $channels[]=[
                'id'=>$id,
                'label'=>$id==='google_ads'?'Google Ads':'Meta (Instagram/Facebook)',
                'pending_demo'=>$counts[$id]['pending'],
                'changes_requested_demo'=>$counts[$id]['changes'],
                'alerts'=>$alerts,
                'provider_action'=>'none_synthetic',
            ];
        }
        return [
            'schema_version'=>'perf.portal.attention.synthetic.v1',
            'source'=>'in_memory_fixture_only',
            'tenant_ref'=>$scope['tenant_ref'],
            'read_only'=>true,'contracted'=>false,
            'authorization_eligible'=>false,'human_approval_verified'=>false,
            'dispatch_enabled'=>false,'billing_enabled'=>false,
            'ready_for_production'=>false,'real_metrics_available'=>false,
            'summary'=>[
                'alert_cards_demo'=>$total,
                'review_backlog_demo'=>$backlog,
                'capacity_attention_demo'=>$capacityAlerts,
                'sla_verified'=>false,
            ],
            'channels'=>$channels,
        ];
    }

    private static function denied(array $dto):bool
    {
        foreach(['read_only'=>true,'contracted'=>false,
                 'authorization_eligible'=>false,'dispatch_enabled'=>false,
                 'billing_enabled'=>false,'ready_for_production'=>false]
                as $field=>$required) {
            if(($dto[$field]??null)!==$required)return false;
        }
        return true;
    }
}
