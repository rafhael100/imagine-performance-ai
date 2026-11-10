<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-65. Read-only fake Portal projection from durable SQLite.
 * Uses one database snapshot; no user identity, real quotas, HTTP, Ads,
 * contract or customer. Identifiers supplied here NEVER grant access.
 */
final class SyntheticSqliteScopeDashboardProjector
{
    /** @param list<string> $campaignRefs @param list<string> $groupRefs */
    public function build(
        SyntheticSqliteManagedScopeStore $store,
        string $tenant,
        array $campaignRefs,
        array $groupRefs
    ): ?array {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F64_SQLITE_SCOPE_LAB')!=='1'
           ||getenv('PERF_F65_DURABLE_PREVIEW_LAB')!=='1'
           ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant)
           ||count($campaignRefs)>20||count($groupRefs)>80){
            return null;
        }
        foreach($campaignRefs as $ref){
            if(!is_string($ref)
               ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
        }
        foreach($groupRefs as $ref){
            if(!is_string($ref)
               ||!preg_match('/^grp-[a-z0-9-]{1,60}$/D',$ref)){
                return null;
            }
        }
        $result=$store->consistentRead(
            static function(SyntheticSqliteManagedScopeStore $reader)use(
                $tenant,$campaignRefs,$groupRefs
            ):?array{
                $channels=[];
                foreach([
                    'google_ads'=>'Google Ads',
                    'meta'=>'Meta (Instagram/Facebook)'
                ] as $channel=>$label){
                    $cap=$reader->capacity($tenant,$channel);
                    if(!is_array($cap)
                       ||($cap['contracted']??null)!==false
                       ||($cap['billing_enabled']??null)!==false
                       ||($cap['dispatch_enabled']??null)!==false
                       ||($cap['ready_for_production']??null)!==false){
                        return null;
                    }
                    $attention=$cap['status']!=='configured_synthetic_only'
                        ?'commercial_capacity_not_approved'
                        :(($cap['campaigns']['remaining']===0
                            ||$cap['groups']['remaining']===0)
                            ?'review_scope_no_auto_upgrade':'none_demo');
                    $channels[]=[
                        'id'=>$channel,'label'=>$label,
                        'capacity_status'=>$cap['status'],
                        'campaigns'=>$cap['campaigns'],
                        'groups'=>$cap['groups'],
                        'attention'=>$attention,
                    ];
                }
                $campaigns=[];
                foreach(array_unique($campaignRefs) as $ref){
                    $state=$reader->campaignState($tenant,$ref);
                    if(in_array($state,[
                        'not_found','outside_scope_demo','lab_disabled'
                    ],true))continue;
                    if(!in_array($state,[
                        'active_demo','paused_demo','draft_demo'
                    ],true))return null;
                    $campaigns[]=[
                        'ref'=>$ref,'state'=>$state,'managed'=>true,
                        'provider_action'=>'none_synthetic',
                    ];
                }
                $groups=[];
                foreach(array_unique($groupRefs) as $ref){
                    $state=$reader->groupState($tenant,$ref);
                    if(in_array($state,['not_found','lab_disabled'],true))continue;
                    if(!in_array($state,['active_demo','paused_demo'],true)){
                        return null;
                    }
                    $groups[]=[
                        'ref'=>$ref,'state'=>$state,'managed'=>true,
                        'provider_action'=>'none_synthetic',
                    ];
                }
                return [
                    'schema_version'=>'perf.portal.scope-preview.synthetic.v1',
                    'source'=>'sqlite_ci_fixture_only',
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
                        'audit_entries_demo'=>0,
                        'rejected_actions_demo'=>0,
                        'approval_source'=>'none_real',
                    ],
                ];
            }
        );
        return is_array($result)?$result:null;
    }
}
