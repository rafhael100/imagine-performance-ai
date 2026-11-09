<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F1-53: read-only, synthetic DTO for a future Imagine Portal preview.
 * Supplied refs are hints, not access grants. Tenant-scoped ledger decides
 * visibility; no pricing, customer data, identity, SSO, actual Ads or API.
 */
final class SyntheticScopeDashboardProjector
{
    /** @param list<string> $campaignRefs @param list<string> $groupRefs */
    public function build(
        SyntheticManagedScopeLedger $ledger,
        string $tenant,
        array $campaignRefs,
        array $groupRefs
    ): ?array {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F52_SCOPE_LAB')!=='1'
           ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'){
            return null;
        }
        if(!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant)
           ||count($campaignRefs)>20||count($groupRefs)>80){
            return null;
        }
        foreach($campaignRefs as $ref){
            if(!is_string($ref)||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
        }
        foreach($groupRefs as $ref){
            if(!is_string($ref)||!preg_match('/^grp-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
        }
        $channels=[];
        foreach(['google_ads'=>'Google Ads','meta'=>'Meta (Instagram/Facebook)'] as $channel=>$label){
            $capacity=$ledger->capacity($tenant,$channel);
            $channels[]=[
                'id'=>$channel,'label'=>$label,
                'capacity_status'=>$capacity['status'],
                'campaigns'=>$capacity['campaigns'],
                'groups'=>$capacity['groups'],
                'attention'=>$capacity['status']!=='configured_synthetic_only'
                    ?'commercial_capacity_not_approved'
                    :(($capacity['campaigns']['remaining']===0
                        ||$capacity['groups']['remaining']===0)
                        ?'review_scope_no_auto_upgrade':'none_demo'),
            ];
        }
        $campaigns=[];
        foreach(array_unique($campaignRefs) as $ref){
            $state=$ledger->campaignState($tenant,$ref);
            if(in_array($state,['not_found','outside_scope_demo','lab_disabled'],true)){
                continue;
            }
            $campaigns[]=[
                'ref'=>$ref,'state'=>$state,'managed'=>true,
                'provider_action'=>'none_synthetic',
            ];
        }
        $groups=[];
        foreach(array_unique($groupRefs) as $ref){
            $state=$ledger->groupState($tenant,$ref);
            if(in_array($state,['not_found','outside_scope_demo','lab_disabled'],true)){
                continue;
            }
            $groups[]=[
                'ref'=>$ref,'state'=>$state,'managed'=>true,
                'provider_action'=>'none_synthetic',
            ];
        }
        $audit=$ledger->auditFor($tenant);
        $rejections=0;
        foreach($audit as $entry){
            if(str_contains($entry['result'],'denied')
                ||str_contains($entry['result'],'review_required')
                ||$entry['result']==='not_found'){
                ++$rejections;
            }
        }
        return [
            'schema_version'=>'perf.portal.scope-preview.synthetic.v1',
            'source'=>'in_memory_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,
            'contracted'=>false,
            'ready_for_production'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'prices_homologated'=>false,
            'commercial'=>'not_homologated',
            'channels'=>$channels,
            'campaigns'=>$campaigns,
            'groups'=>$groups,
            'review_summary'=>[
                'audit_entries_demo'=>count($audit),
                'rejected_actions_demo'=>$rejections,
                'approval_source'=>'none_real',
            ],
        ];
    }
}
