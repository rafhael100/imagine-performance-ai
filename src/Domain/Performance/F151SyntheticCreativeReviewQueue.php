<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

require_once __DIR__.'/SyntheticManagedScopeLedger.php';

/**
 * FAST-PERF F1-151: a fictional, in-memory creative/campaign review queue.
 *
 * Approval here is a UI DEMONSTRATION, never legal advertising approval.
 * No database, provider SDK, IAM, payment, HTTP, operational route, or Ads.
 */
final class F151SyntheticCreativeReviewQueue
{
    private array $items=[];
    private array $events=[];

    public function __construct(private SyntheticManagedScopeLedger $scope) {}

    private static function enabled():bool
    {
        return PHP_SAPI==='cli' && getenv('APP_ENV')==='testing'
            && getenv('PERF_F52_SCOPE_LAB')==='1'
            && getenv('PERF_F151_APPROVAL_LAB')==='1';
    }

    private static function ref(string $ref,string $prefix):void
    {
        if(!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$ref)){
            throw new InvalidArgumentException('Invalid synthetic review reference');
        }
    }

    private static function title(string $title):void
    {
        if($title===''||strlen($title)>100
            ||preg_match('/[<>\x00-\x1f\x7f]/',$title)){
            throw new InvalidArgumentException('Invalid fictional creative title');
        }
    }

    private function log(string $tenant,string $reference,string $transition,int $version):void
    {
        $this->events[$tenant][]=[
            'review_ref'=>$reference,'transition'=>$transition,'version'=>$version,
            'actor_authorized'=>false,
            'provider_action'=>'none_synthetic',
            'billing_effect'=>'none_synthetic',
        ];
    }

    public function submit(
        string $tenant,string $review,string $campaign,string $channel,string $title
    ):string {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');
        self::ref($review,'rev-');
        self::ref($campaign,'cam-');
        self::title($title);
        if(!in_array($channel,['google_ads','meta'],true)){
            throw new InvalidArgumentException('Unknown synthetic advertising channel');
        }
        // The scoped campaign is resolved by the EXISTING F52 domain ledger.
        // A caller-supplied campaign ref is never enough to cross tenants.
        if($this->scope->campaignChannel($tenant,$campaign)!==$channel){
            return 'out_of_scope_or_wrong_channel_denied';
        }
        if(isset($this->items[$tenant][$review])){
            return 'duplicate_review_denied';
        }
        if(count($this->items[$tenant]??[])>=25){
            return 'review_queue_capacity_denied';
        }
        $this->items[$tenant][$review]=[
            'review_ref'=>$review,
            'campaign_ref'=>$campaign,
            'channel'=>$channel,
            'title'=>$title,
            'status'=>'pending_demo',
            'version'=>1,
            'action_authorized'=>false,
            'human_approval_verified'=>false,
            'billing_enabled'=>false,
        ];
        $this->log($tenant,$review,'submitted_demo',1);
        return 'submitted_demo';
    }

    public function decide(
        string $tenant,string $review,int $expectedVersion,string $decision,
        string $reviewer
    ):string {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');
        self::ref($review,'rev-');
        self::ref($reviewer,'reviewer-demo-');
        $item=$this->items[$tenant][$review]??null;
        if($item===null)return 'not_found';
        if($item['version']!==$expectedVersion)return 'stale_version_denied';
        if($item['status']!=='pending_demo')return 'review_not_pending_denied';
        $transitions=[
            'approve_mock'=>'approved_mock_only',
            'reject_mock'=>'rejected_mock_only',
            'request_changes'=>'changes_requested_demo',
        ];
        if(!isset($transitions[$decision]))return 'unknown_decision_denied';
        $next=$transitions[$decision];
        $this->items[$tenant][$review]['status']=$next;
        $this->items[$tenant][$review]['version']++;
        // Reviewer string is an untrusted fixture, not Portal IAM or MFA.
        $this->log($tenant,$review,$next,$expectedVersion+1);
        return $next;
    }

    public function revise(
        string $tenant,string $review,int $expectedVersion,string $title
    ):string {
        if(!self::enabled())return 'lab_disabled';
        self::ref($tenant,'perf-demo-');
        self::ref($review,'rev-');
        self::title($title);
        $item=$this->items[$tenant][$review]??null;
        if($item===null)return 'not_found';
        if($item['version']!==$expectedVersion)return 'stale_version_denied';
        if($item['status']!=='changes_requested_demo'){
            return 'revision_not_requested_denied';
        }
        if($this->scope->campaignChannel($tenant,$item['campaign_ref'])!==$item['channel']){
            return 'campaign_no_longer_in_scope_denied';
        }
        $this->items[$tenant][$review]['title']=$title;
        $this->items[$tenant][$review]['version']++;
        $this->items[$tenant][$review]['status']='pending_demo';
        $this->log($tenant,$review,'resubmitted_demo',$expectedVersion+1);
        return 'resubmitted_demo';
    }

    /** @return array<string,mixed>|null */
    public function project(string $tenant):?array
    {
        if(!self::enabled())return null;
        self::ref($tenant,'perf-demo-');
        $rows=array_values($this->items[$tenant]??[]);
        $summary=[
            'pending_demo'=>0,'changes_requested_demo'=>0,
            'approved_mock_only'=>0,'rejected_mock_only'=>0,
        ];
        foreach($rows as $row)$summary[$row['status']]++;
        return [
            'schema_version'=>'perf.portal.creative-reviews.synthetic.v1',
            'source'=>'in_memory_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,'contracted'=>false,
            'human_approval_verified'=>false,'authorization_eligible'=>false,
            'dispatch_enabled'=>false,'billing_enabled'=>false,
            'ready_for_production'=>false,
            'review_count'=>count($rows),
            'summary'=>$summary,
            'reviews'=>$rows,
            'audit_entries_demo'=>count($this->events[$tenant]??[]),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function auditFor(string $tenant):array
    {
        if(!self::enabled())return [];
        self::ref($tenant,'perf-demo-');
        return $this->events[$tenant]??[];
    }
}
