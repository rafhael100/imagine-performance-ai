<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/**
 * F1-60 - fictional brand/account ownership index (PHP memory only).
 *
 * "Account" values are INTERNAL MOCK REFERENCES, never Google/Meta account
 * IDs, OAuth grants or connected advertising assets. It cannot publish,
 * pause, charge, set quotas or access a provider.
 */
final class SyntheticBrandAccountScopeIndex
{
    private array $brands=[];
    private array $accounts=[];
    private array $campaignLinks=[];
    private array $history=[];

    public function __construct(private readonly SyntheticManagedScopeLedger $scope){}

    private static function enabled():bool
    {
        return PHP_SAPI==='cli'
          && getenv('APP_ENV')==='testing'
          && getenv('PERF_F52_SCOPE_LAB')==='1'
          && getenv('PERF_F60_ACCOUNT_SCOPE_LAB')==='1';
    }

    private static function id(string $ref,string $prefix):void
    {
        if(!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$ref)){
            throw new InvalidArgumentException('Invalid synthetic account scope reference');
        }
    }

    private function note(string $tenant,string $resource,string $action,string $status):string
    {
        $this->history[$tenant][]=[
            'resource'=>$resource,
            'action'=>$action,
            'status'=>$status,
            'provider_action'=>'none_synthetic',
            'billing_effect'=>'none_synthetic',
        ];
        return $status;
    }

    public function addBrand(string $tenant,string $brand):string
    {
        if(!self::enabled())return 'brand_account_lab_disabled';
        self::id($tenant,'perf-demo-');self::id($brand,'brand-demo-');
        if(isset($this->brands[$brand])){
            return $this->note($tenant,$brand,'add_brand','duplicate_or_out_of_scope');
        }
        if(count($this->brands)>=100){
            return $this->note($tenant,$brand,'add_brand','mock_registry_capacity_denied');
        }
        $this->brands[$brand]=['tenant'=>$tenant];
        return $this->note($tenant,$brand,'add_brand','brand_registered_synthetic_only');
    }

    public function addAccount(
        string $tenant,string $brand,string $channel,string $account
    ):string {
        if(!self::enabled())return 'brand_account_lab_disabled';
        self::id($tenant,'perf-demo-');self::id($brand,'brand-demo-');
        self::id($account,'acct-demo-');
        if(!in_array($channel,['google_ads','meta'],true)){
            throw new InvalidArgumentException('Unapproved or unknown demo channel');
        }
        if(!isset($this->brands[$brand])
           ||$this->brands[$brand]['tenant']!==$tenant){
            return $this->note($tenant,$account,'add_account','brand_not_found');
        }
        if(isset($this->accounts[$account])){
            return $this->note($tenant,$account,'add_account','duplicate_or_out_of_scope');
        }
        if(count($this->accounts)>=200){
            return $this->note($tenant,$account,'add_account','mock_registry_capacity_denied');
        }
        $this->accounts[$account]=[
            'tenant'=>$tenant,
            'brand'=>$brand,
            'channel'=>$channel,
            'state'=>'unconnected_fixture',
        ];
        return $this->note($tenant,$account,'add_account','account_registered_synthetic_only');
    }

    public function bindCampaign(
        string $tenant,string $account,string $campaign
    ):string {
        if(!self::enabled())return 'brand_account_lab_disabled';
        self::id($tenant,'perf-demo-');self::id($account,'acct-demo-');
        self::id($campaign,'cam-');
        $row=$this->accounts[$account]??null;
        if($row===null||$row['tenant']!==$tenant){
            return $this->note($tenant,$campaign,'bind_campaign','account_not_found');
        }
        if(isset($this->campaignLinks[$campaign])){
            return $this->note($tenant,$campaign,'bind_campaign','already_linked_or_out_of_scope');
        }
        $channel=$this->scope->campaignChannel($tenant,$campaign);
        if($channel===null){
            return $this->note($tenant,$campaign,'bind_campaign','managed_campaign_not_found');
        }
        if($channel!==$row['channel']){
            return $this->note($tenant,$campaign,'bind_campaign','channel_mismatch_denied');
        }
        $this->campaignLinks[$campaign]=[
            'tenant'=>$tenant,
            'account'=>$account,
            'brand'=>$row['brand'],
            'channel'=>$channel,
            'state'=>'in_scope_fixture',
        ];
        return $this->note($tenant,$campaign,'bind_campaign','bound_synthetic_only');
    }

    /**
     * Unlinking a mock account does NOT formally release the F52 managed
     * campaign or its capacity. This is not deletion in any provider.
     */
    public function unlinkCampaign(
        string $tenant,string $campaign,bool $humanMockApproved
    ):string {
        if(!self::enabled())return 'brand_account_lab_disabled';
        self::id($tenant,'perf-demo-');self::id($campaign,'cam-');
        $row=$this->campaignLinks[$campaign]??null;
        if($row===null||$row['tenant']!==$tenant
            ||$row['state']!=='in_scope_fixture'){
            return $this->note($tenant,$campaign,'unlink_campaign','link_not_found');
        }
        if(!$humanMockApproved){
            return $this->note($tenant,$campaign,'unlink_campaign','human_review_required');
        }
        $this->campaignLinks[$campaign]['state']='unlinked_fixture';
        return $this->note($tenant,$campaign,'unlink_campaign','unlinked_synthetic_only');
    }

    /** A tenant-scoped offline DTO, not actual account discovery. */
    public function snapshotFor(string $tenant):?array
    {
        if(!self::enabled())return null;
        self::id($tenant,'perf-demo-');
        $brands=[];$accounts=[];$links=[];
        foreach($this->brands as $id=>$row){
            if($row['tenant']!==$tenant)continue;
            $brands[]=['ref'=>$id,'state'=>'brand_fixture_only'];
        }
        foreach($this->accounts as $id=>$row){
            if($row['tenant']!==$tenant)continue;
            $managed=0;
            foreach($this->campaignLinks as $campaign=>$link){
                if($link['tenant']!==$tenant
                   ||$link['account']!==$id
                   ||$link['state']!=='in_scope_fixture'
                   ||$this->scope->campaignChannel($tenant,$campaign)!==$row['channel']){
                    continue;
                }
                ++$managed;
                $links[]=[
                    'campaign_ref'=>$campaign,
                    'account_ref'=>$id,
                    'brand_ref'=>$row['brand'],
                    'channel'=>$row['channel'],
                    'campaign_state'=>$this->scope->campaignState($tenant,$campaign),
                    'provider_action'=>'none_synthetic',
                ];
            }
            $accounts[]=[
                'ref'=>$id,
                'brand_ref'=>$row['brand'],
                'channel'=>$row['channel'],
                'provider_connection'=>'none_synthetic',
                'managed_campaigns_demo'=>$managed,
            ];
        }
        return [
            'schema_version'=>'perf.scope.brand-account.synthetic.v1',
            'tenant_ref'=>$tenant,
            'source'=>'in_memory_only',
            'read_only'=>true,
            'contracted'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'quotas_homologated'=>false,
            'brands'=>$brands,
            'accounts'=>$accounts,
            'campaign_links'=>$links,
        ];
    }

    public function auditFor(string $tenant):array
    {
        if(!self::enabled())return [];
        self::id($tenant,'perf-demo-');
        return $this->history[$tenant]??[];
    }
}
