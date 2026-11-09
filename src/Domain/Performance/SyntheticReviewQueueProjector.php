<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F1-58 read-side ONLY summary of fictional scope review workflow.
 * Not an authenticated Portal projection or client-visible approval grant.
 * Does not invoke applyMock, SQL, network, billing or Ads.
 */
final class SyntheticReviewQueueProjector
{
    /**
     * @param list<string> $requestRefs
     * @return array<string,mixed>|null
     */
    public function build(
        SyntheticScopeReviewWorkflow $workflow,
        array $scopePreview,string $tenant,
        array $requestRefs
    ):?array {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
          ||getenv('PERF_F52_SCOPE_LAB')!=='1'
          ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
          ||getenv('PERF_F57_REVIEW_LAB')!=='1'
          ||getenv('PERF_F58_REVIEW_READ_LAB')!=='1'){
            return null;
        }
        if(!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant)
          ||($scopePreview['schema_version']??null)
              !=='perf.portal.scope-preview.synthetic.v1'
          ||($scopePreview['source']??null)!=='in_memory_fixture_only'
          ||($scopePreview['tenant_ref']??null)!==$tenant
          ||($scopePreview['read_only']??null)!==true
          ||($scopePreview['contracted']??null)!==false
          ||($scopePreview['authorization_eligible']??null)!==false
          ||($scopePreview['ready_for_production']??null)!==false
          ||($scopePreview['dispatch_enabled']??null)!==false
          ||($scopePreview['billing_enabled']??null)!==false
          ||($scopePreview['prices_homologated']??null)!==false
          ||count($requestRefs)>30){
            return null;
        }
        $knownPending=[];
        foreach($workflow->pendingFor($tenant) as $row){
            $knownPending[$row['request_ref']]=$row;
        }
        $items=[];
        foreach(array_unique($requestRefs) as $ref){
            if(!is_string($ref)
              ||!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
            $status=$workflow->statusFor($tenant,$ref);
            if(!in_array($status,[
                'pending_review_synthetic',
                'approved_scope_demo_not_executed',
                'rejected_scope_demo',
                'scope_applied_synthetic_only',
                'stale_scope_review_denied',
            ],true)){
                continue;
            }
            $row=[
                'request_ref'=>$ref,
                'state'=>$status,
                'approval_is_real'=>false,
                'provider_action'=>'none_synthetic',
                'billing_effect'=>'none_synthetic',
                'operator_action_allowed'=>false,
            ];
            if($status==='pending_review_synthetic'){
                $pending=$knownPending[$ref]??null;
                if(!is_array($pending)){
                    return null; // inconsistent in-memory review evidence
                }
                $row['from_campaign_ref']=$pending['old_campaign'];
                $row['to_campaign_ref']=$pending['new_campaign'];
            }
            $items[]=$row;
        }
        $audit=$workflow->auditFor($tenant);
        $rejected=0;
        foreach($audit as $event){
            if(str_contains($event['status'],'denied')
              ||str_contains($event['status'],'conflict')){
                ++$rejected;
            }
        }
        return [
            'schema_version'=>'perf.portal.review-queue.synthetic.v1',
            'source'=>'in_memory_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,
            'human_review_real'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'items'=>$items,
            'summary'=>[
                'pending_synthetic'=>count(array_filter(
                    $items,static fn(array $r):bool=>
                       $r['state']==='pending_review_synthetic'
                )),
                'decisions_recorded_demo'=>count($audit),
                'denials_or_conflicts_demo'=>$rejected,
            ],
        ];
    }
}
