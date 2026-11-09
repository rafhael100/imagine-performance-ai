<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/**
 * FAST-PERF F1-52. In-memory managed scope/counting for a fictional tenant.
 * No Ads APIs, billing, operational Portal authority, DB or provider writes.
 * Quotas for groups are not commercially approved; all numbers are fixtures.
 */
final class SyntheticManagedScopeLedger
{
    private array $limits = [];
    private array $campaigns = [];
    private array $groups = [];
    private array $audit = [];

    private static function enabled(): bool
    {
        return PHP_SAPI==='cli' && getenv('APP_ENV')==='testing'
            && getenv('PERF_F52_SCOPE_LAB')==='1';
    }

    private static function ref(string $value, string $prefix): void
    {
        if (!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$value)) {
            throw new InvalidArgumentException('Invalid synthetic reference');
        }
    }

    private static function channel(string $channel): void
    {
        if (!in_array($channel,['google_ads','meta'],true)) {
            throw new InvalidArgumentException('Invalid demo channel');
        }
    }

    private function note(string $tenant,string $action,string $resource,string $result): string
    {
        $this->audit[$tenant][]=[
            'operation'=>$action,'resource'=>$resource,'result'=>$result,
            'billing_effect'=>'none_synthetic',
        ];
        return $result;
    }

    public function setDemoCapacity(string $tenant,string $channel,int $campaigns,int $groups): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');
        self::channel($channel);
        if($campaigns<0||$campaigns>1000||$groups<0||$groups>10000){
            throw new InvalidArgumentException('Invalid demo capacity');
        }
        $used=$this->capacity($tenant,$channel);
        if($used['campaigns']['used']>$campaigns||$used['groups']['used']>$groups){
            return $this->note($tenant,'capacity',$channel,'reduction_below_usage_denied');
        }
        $this->limits[$tenant][$channel]=['campaigns'=>$campaigns,'groups'=>$groups];
        return $this->note($tenant,'capacity',$channel,'capacity_configured_demo');
    }

    public function includeCampaign(string $tenant,string $channel,string $campaign): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::channel($channel);self::ref($campaign,'cam-');
        if(isset($this->campaigns[$campaign])){
            return $this->note($tenant,'include_campaign',$campaign,'already_exists_or_out_of_scope');
        }
        $limit=$this->limits[$tenant][$channel]??null;
        if($limit===null){
            return $this->note($tenant,'include_campaign',$campaign,'capacity_unapproved_denied');
        }
        if($this->capacity($tenant,$channel)['campaigns']['used'] >= $limit['campaigns']){
            return $this->note($tenant,'include_campaign',$campaign,'capacity_review_required');
        }
        $this->campaigns[$campaign]=[
            'tenant'=>$tenant,'channel'=>$channel,'state'=>'active_demo','managed'=>true,
        ];
        return $this->note($tenant,'include_campaign',$campaign,'included_synthetic_only');
    }

    public function includeGroup(string $tenant,string $campaign,string $group): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($campaign,'cam-');self::ref($group,'grp-');
        $parent=$this->campaigns[$campaign]??null;
        if($parent===null||$parent['tenant']!==$tenant||!$parent['managed']){
            return $this->note($tenant,'include_group',$group,'campaign_not_found');
        }
        if(isset($this->groups[$group])){
            return $this->note($tenant,'include_group',$group,'already_exists_or_out_of_scope');
        }
        $limit=$this->limits[$tenant][$parent['channel']]??null;
        if($limit===null){
            return $this->note($tenant,'include_group',$group,'capacity_unapproved_denied');
        }
        if($this->capacity($tenant,$parent['channel'])['groups']['used'] >= $limit['groups']){
            return $this->note($tenant,'include_group',$group,'capacity_review_required');
        }
        $this->groups[$group]=[
            'tenant'=>$tenant,'campaign'=>$campaign,'channel'=>$parent['channel'],
            'state'=>'active_demo','managed'=>true,
        ];
        return $this->note($tenant,'include_group',$group,'included_synthetic_only');
    }

    public function pauseCampaign(string $tenant,string $campaign): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($campaign,'cam-');
        $row=$this->campaigns[$campaign]??null;
        if($row===null||$row['tenant']!==$tenant||!$row['managed']){
            return $this->note($tenant,'pause_campaign',$campaign,'not_found');
        }
        if($row['state']==='paused_demo'){
            return $this->note($tenant,'pause_campaign',$campaign,'already_paused_demo');
        }
        $this->campaigns[$campaign]['state']='paused_demo';
        return $this->note($tenant,'pause_campaign',$campaign,'paused_synthetic_only');
    }

    public function pauseGroup(string $tenant,string $group): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($group,'grp-');
        $row=$this->groups[$group]??null;
        if($row===null||$row['tenant']!==$tenant||!$row['managed']){
            return $this->note($tenant,'pause_group',$group,'not_found');
        }
        $this->groups[$group]['state']='paused_demo';
        return $this->note($tenant,'pause_group',$group,'paused_synthetic_only');
    }

    /** Formal scope removal, never provider pause/deletion. */
    private function retire(string $campaign): void
    {
        $this->campaigns[$campaign]['state']='outside_scope_demo';
        $this->campaigns[$campaign]['managed']=false;
        foreach($this->groups as $id=>$row){
            if($row['campaign']===$campaign){
                $this->groups[$id]['state']='outside_scope_demo';
                $this->groups[$id]['managed']=false;
            }
        }
    }

    public function releaseCampaign(string $tenant,string $campaign,bool $humanMockApproved): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($campaign,'cam-');
        $row=$this->campaigns[$campaign]??null;
        if($row===null||$row['tenant']!==$tenant||!$row['managed']){
            return $this->note($tenant,'release_campaign',$campaign,'not_found');
        }
        if(!$humanMockApproved){
            return $this->note($tenant,'release_campaign',$campaign,'human_review_required');
        }
        $this->retire($campaign);
        return $this->note($tenant,'release_campaign',$campaign,'released_synthetic_only');
    }

    /**
     * In-memory atomic swap with no transient overlap/no fee. Human approval
     * and Imagine recommendation are BOOLEAN FIXTURES, not real identities.
     */
    public function replaceCampaign(
        string $tenant,string $old,string $new,
        bool $imagineRecommendedMock,bool $humanMockApproved
    ): string {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($old,'cam-');self::ref($new,'cam-');
        $row=$this->campaigns[$old]??null;
        if($row===null||$row['tenant']!==$tenant||!$row['managed']){
            return $this->note($tenant,'replace_campaign',$old,'not_found');
        }
        if(isset($this->campaigns[$new])){
            return $this->note($tenant,'replace_campaign',$old,'already_exists_or_out_of_scope');
        }
        if(!$imagineRecommendedMock){
            return $this->note($tenant,'replace_campaign',$old,'recommendation_required');
        }
        if(!$humanMockApproved){
            return $this->note($tenant,'replace_campaign',$old,'human_review_required');
        }
        if(!isset($this->limits[$tenant][$row['channel']])){
            return $this->note($tenant,'replace_campaign',$old,'capacity_unapproved_denied');
        }
        $this->retire($old);
        $this->campaigns[$new]=[
            'tenant'=>$tenant,'channel'=>$row['channel'],
            'state'=>'draft_demo','managed'=>true,
        ];
        return $this->note($tenant,'replace_campaign',$new,'replaced_synthetic_no_fee');
    }

    /** @return array<string,mixed> */
    public function capacity(string $tenant,string $channel): array
    {
        if(!self::enabled()){
            return [
                'campaigns'=>['used'=>0,'limit'=>null,'remaining'=>null],
                'groups'=>['used'=>0,'limit'=>null,'remaining'=>null],
                'status'=>'lab_disabled','commercial'=>'not_homologated',
                'dispatch_enabled'=>false,
            ];
        }
        self::ref($tenant,'perf-demo-');self::channel($channel);
        $usedCampaigns=count(array_filter($this->campaigns,static fn(array $r):bool=>
            $r['tenant']===$tenant && $r['channel']===$channel && $r['managed']));
        $usedGroups=count(array_filter($this->groups,static fn(array $r):bool=>
            $r['tenant']===$tenant && $r['channel']===$channel && $r['managed']));
        $limit=$this->limits[$tenant][$channel]??null;
        return [
            'campaigns'=>['used'=>$usedCampaigns,'limit'=>$limit['campaigns']??null,
                'remaining'=>$limit===null?null:max(0,$limit['campaigns']-$usedCampaigns)],
            'groups'=>['used'=>$usedGroups,'limit'=>$limit['groups']??null,
                'remaining'=>$limit===null?null:max(0,$limit['groups']-$usedGroups)],
            'status'=>$limit===null?'unconfigured_blocked_demo':'configured_synthetic_only',
            'commercial'=>'not_homologated','dispatch_enabled'=>false,
        ];
    }

    public function campaignState(string $tenant,string $campaign): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($campaign,'cam-');
        $row=$this->campaigns[$campaign]??null;
        return $row!==null&&$row['tenant']===$tenant?$row['state']:'not_found';
    }

    public function groupState(string $tenant,string $group): string
    {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($group,'grp-');
        $row=$this->groups[$group]??null;
        return $row!==null&&$row['tenant']===$tenant?$row['state']:'not_found';
    }

    /** @return list<array<string,string>> */
    public function auditFor(string $tenant): array
    {
        if(!self::enabled())return [];
        self::ref($tenant,'perf-demo-');
        return $this->audit[$tenant]??[];
    }
}
