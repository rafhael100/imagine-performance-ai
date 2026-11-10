<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F1-58: a read-only projection of F1-57's synthetic review statuses.
 *
 * The caller is NOT authenticated; only CLI/test flags permit this fixture
 * projector. The supplied request refs are just filter hints, never grants.
 * A "mock approval" is NOT permission to access Google/Meta or perform work.
 */
final class SyntheticReviewQueueProjector
{
    /** @param list<string> $requestRefs */
    public function build(
        SyntheticScopeReviewWorkflow $workflow,
        string $tenant,
        array $requestRefs
    ): ?array {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1'
            ||getenv('PERF_F57_REVIEW_LAB')!=='1'
            ||getenv('PERF_F58_REVIEW_QUEUE_LAB')!=='1'
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant)
            ||count($requestRefs)>30){
            return null;
        }
        $pending=$workflow->pendingFor($tenant);
        $byRef=[];
        foreach($pending as $row){
            if(!is_array($row)||!isset(
                $row['request_ref'],$row['status'],
                $row['old_campaign'],$row['new_campaign']
            )
              ||$row['status']!=='pending_review_synthetic'
              ||!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$row['request_ref'])
              ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$row['old_campaign'])
              ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$row['new_campaign'])){
                return null;
            }
            if(isset($byRef[$row['request_ref']]))return null;
            $byRef[$row['request_ref']]=$row;
        }
        $audit=$workflow->auditFor($tenant);
        if(count($audit)>1000)return null;
        foreach($audit as $entry){
            if(!is_array($entry)
               ||($entry['billing_effect']??null)!=='none_synthetic'
               ||($entry['provider_action']??null)!=='none_synthetic'
               ||!is_string($entry['status']??null)
               ||!is_string($entry['action']??null)
               ||!is_string($entry['request_ref']??null)){
                return null;
            }
        }

        $allowed=[
            'pending_review_synthetic',
            'approved_scope_demo_not_executed',
            'rejected_scope_demo',
            'scope_applied_synthetic_only',
            'stale_scope_review_denied',
        ];
        $items=[];$unique=[];
        $tallies=[
            'pending'=>0,'approved_mock_no_action'=>0,
            'rejected_mock'=>0,'applied_mock'=>0,'stale_denied'=>0,
        ];
        foreach($requestRefs as $ref){
            if(!is_string($ref)
               ||!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
            if(isset($unique[$ref]))continue;
            $unique[$ref]=true;
            $state=$workflow->statusFor($tenant,$ref);
            if($state==='not_found')continue;
            if(!in_array($state,$allowed,true))return null;
            $category=match($state){
                'pending_review_synthetic'=>'pending',
                'approved_scope_demo_not_executed'=>'approved_mock_no_action',
                'rejected_scope_demo'=>'rejected_mock',
                'scope_applied_synthetic_only'=>'applied_mock',
                'stale_scope_review_denied'=>'stale_denied',
            };
            ++$tallies[$category];
            $p=$byRef[$ref]??null;
            if(($p!==null)!==($state==='pending_review_synthetic'))return null;
            $items[]=[
                'request_ref'=>$ref,
                'state'=>$state,
                'category'=>$category,
                'old_campaign_ref'=>$p['old_campaign']??null,
                'new_campaign_ref'=>$p['new_campaign']??null,
                'approval_real'=>false,
                'provider_action'=>'none_synthetic',
            ];
        }
        return [
            'schema_version'=>'perf.portal.review-queue.synthetic.v1',
            'source'=>'in_memory_review_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,
            'contracted'=>false,
            'human_identity_verified'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'scope_changes_permitted'=>false,
            'reviewed_by'=>'fixture_only',
            'counts'=>$tallies,
            'audit_events_demo'=>count($audit),
            'items'=>$items,
        ];
    }
}
