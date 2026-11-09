<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-61: reconcile two SYNTHETIC tenant-scoped read models.
 *
 * The hierarchy is a mock account-ref index, NOT proof of actual advertiser
 * ownership or a Portal entitlement. Missing/mismatched account data never
 * becomes a real grant. No persistence, OAuth, Ads API or commercial action.
 */
final class SyntheticAccountScopedDashboardProjector
{
    /** @param list<string> $campaignRefs @param list<string> $groupRefs */
    public function build(
        SyntheticManagedScopeLedger $ledger,
        SyntheticBrandAccountScopeIndex $accounts,
        string $tenant,
        array $campaignRefs,
        array $groupRefs
    ): ?array {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
          ||getenv('PERF_F52_SCOPE_LAB')!=='1'
          ||getenv('PERF_F53_PORTAL_PREVIEW_LAB')!=='1'
          ||getenv('PERF_F60_ACCOUNT_SCOPE_LAB')!=='1'
          ||getenv('PERF_F61_COMBINED_READ_LAB')!=='1'){
            return null;
        }
        $scope=(new SyntheticScopeDashboardProjector())->build(
            $ledger,$tenant,$campaignRefs,$groupRefs
        );
        $mapping=$accounts->snapshotFor($tenant);
        if(!is_array($scope)||!is_array($mapping)
          ||$scope['schema_version']!=='perf.portal.scope-preview.synthetic.v1'
          ||$mapping['schema_version']!=='perf.scope.brand-account.synthetic.v1'
          ||$scope['tenant_ref']!==$tenant||$mapping['tenant_ref']!==$tenant
          ||$scope['read_only']!==true||$mapping['read_only']!==true
          ||$scope['contracted']!==false||$mapping['contracted']!==false
          ||$scope['dispatch_enabled']!==false||$mapping['dispatch_enabled']!==false
          ||$scope['billing_enabled']!==false||$mapping['billing_enabled']!==false
          ||$scope['authorization_eligible']!==false
          ||$mapping['authorization_eligible']!==false
          ||$mapping['provider_connected']!==false
          ||$scope['ready_for_production']!==false
          ||$mapping['quotas_homologated']!==false
          ||!is_array($scope['campaigns'])||!is_array($scope['channels'])
          ||!is_array($scope['groups'])||!is_array($mapping['brands'])
          ||!is_array($mapping['accounts'])||!is_array($mapping['campaign_links'])){
            return null;
        }

        $brands=[];
        foreach($mapping['brands'] as $b){
            if(!is_array($b)||!isset($b['ref'],$b['state'])
              ||$b['state']!=='brand_fixture_only'
              ||!is_string($b['ref'])
              ||!preg_match('/^brand-demo-[a-z0-9-]{1,60}$/D',$b['ref'])
              ||isset($brands[$b['ref']]))return null;
            $brands[$b['ref']]=true;
        }
        $accountsByRef=[];
        foreach($mapping['accounts'] as $a){
            if(!is_array($a)||!isset(
                $a['ref'],$a['brand_ref'],$a['channel'],
                $a['provider_connection'],$a['managed_campaigns_demo']
            )
              ||!is_string($a['ref'])
              ||!preg_match('/^acct-demo-[a-z0-9-]{1,60}$/D',$a['ref'])
              ||isset($accountsByRef[$a['ref']])
              ||!isset($brands[$a['brand_ref']])
              ||!in_array($a['channel'],['google_ads','meta'],true)
              ||$a['provider_connection']!=='none_synthetic'
              ||!is_int($a['managed_campaigns_demo'])
              ||$a['managed_campaigns_demo']<0){
                return null;
            }
            $accountsByRef[$a['ref']]=$a;
        }
        $links=[];
        $byAccount=[];
        foreach($mapping['campaign_links'] as $link){
            if(!is_array($link)||!isset(
                $link['campaign_ref'],$link['account_ref'],$link['brand_ref'],
                $link['channel'],$link['campaign_state'],$link['provider_action']
            )
              ||!is_string($link['campaign_ref'])
              ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$link['campaign_ref'])
              ||isset($links[$link['campaign_ref']])
              ||!isset($accountsByRef[$link['account_ref']])
              ||$link['brand_ref']!==$accountsByRef[$link['account_ref']]['brand_ref']
              ||$link['channel']!==$accountsByRef[$link['account_ref']]['channel']
              ||$link['channel']!==$ledger->campaignChannel($tenant,$link['campaign_ref'])
              ||$link['campaign_state']!==$ledger->campaignState($tenant,$link['campaign_ref'])
              ||$link['provider_action']!=='none_synthetic'){
                return null;
            }
            $links[$link['campaign_ref']]=$link;
            $byAccount[$link['account_ref']]=($byAccount[$link['account_ref']]??0)+1;
        }
        foreach($accountsByRef as $account){
            if(($byAccount[$account['ref']]??0)!==$account['managed_campaigns_demo']){
                return null;
            }
        }
        $cards=[];$mapped=0;$unmapped=0;$paused=0;
        foreach($scope['campaigns'] as $campaign){
            if(!is_array($campaign)||!isset(
                $campaign['ref'],$campaign['state'],
                $campaign['managed'],$campaign['provider_action']
            )
              ||$campaign['managed']!==true
              ||$campaign['provider_action']!=='none_synthetic'
              ||!is_string($campaign['ref'])
              ||$ledger->campaignChannel($tenant,$campaign['ref'])===null){
                return null;
            }
            $link=$links[$campaign['ref']]??null;
            if($link!==null){
                if($link['campaign_state']!==$campaign['state'])return null;
                ++$mapped;
            } else{
                ++$unmapped;
            }
            if($campaign['state']==='paused_demo')++$paused;
            $cards[]=[
                'campaign_ref'=>$campaign['ref'],
                'campaign_state'=>$campaign['state'],
                'channel'=>$ledger->campaignChannel($tenant,$campaign['ref']),
                'brand_ref'=>$link['brand_ref']??null,
                'account_ref'=>$link['account_ref']??null,
                'account_scope_status'=>$link===null
                    ?'unassigned_synthetic':'linked_synthetic',
                'provider_action'=>'none_synthetic',
            ];
        }
        return [
            'schema_version'=>'perf.portal.account-scope-preview.synthetic.v1',
            'source'=>'reconciled_in_memory_fixtures_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,
            'contracted'=>false,
            'provider_connected'=>false,
            'human_identity_verified'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'ready_for_production'=>false,
            'quotas_homologated'=>false,
            'accounts'=>array_values($accountsByRef),
            'channels'=>$scope['channels'],
            'campaigns'=>$cards,
            'groups'=>$scope['groups'],
            'counts'=>[
                'mapped_campaigns_demo'=>$mapped,
                'unassigned_campaigns_demo'=>$unmapped,
                'paused_campaigns_demo'=>$paused,
                'groups_without_account_mapping_demo'=>count($scope['groups']),
            ],
            'warning'=>'MOCK: account refs are not provider accounts; no execution or billing',
        ];
    }
}
