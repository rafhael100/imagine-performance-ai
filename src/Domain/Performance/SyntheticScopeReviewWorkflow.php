<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/**
 * F1-57. No operational human identity, no external action, no billing.
 * Two-step fictional review records a frozen replacement intent, then a
 * different reviewer and operator may APPLY an IN-MEMORY managed-scope swap.
 * Assumed identities and the clock are caller fixtures: NEVER a real grant.
 */
final class SyntheticScopeReviewWorkflow
{
    /** @var array<string,array<string,mixed>> */
    private array $requests=[];

    /** @var array<string,list<array<string,string>>> */
    private array $audit=[];

    public function __construct(private readonly SyntheticManagedScopeLedger $ledger){}

    private static function enabled():bool
    {
        return PHP_SAPI==='cli'
            && getenv('APP_ENV')==='testing'
            && getenv('PERF_F52_SCOPE_LAB')==='1'
            && getenv('PERF_F57_REVIEW_LAB')==='1';
    }

    private static function ref(string $ref,string $prefix):void
    {
        if(!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$ref)){
            throw new InvalidArgumentException('Invalid synthetic review reference');
        }
    }

    private function record(string $tenant,string $request,string $action,string $result):string
    {
        $this->audit[$tenant][]=[
            'request_ref'=>$request,
            'action'=>$action,
            'status'=>$result,
            'billing_effect'=>'none_synthetic',
            'provider_action'=>'none_synthetic',
        ];
        return $result;
    }

    /**
     * @param string $manifest SHA-256 lower-case hex for a fictional proposal.
     */
    public function propose(
        string $tenant,string $request,string $proposer,
        string $oldCampaign,string $newCampaign,
        string $manifest,int $now,int $expiresAt
    ):string {
        if(!self::enabled())return 'review_lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($request,'req-demo-');
        self::ref($proposer,'actor-demo-');
        self::ref($oldCampaign,'cam-');self::ref($newCampaign,'cam-');
        if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)){
            throw new InvalidArgumentException('Invalid fictional manifest digest');
        }
        if($now<=0||$expiresAt<=$now||$expiresAt-$now>3600){
            return $this->record($tenant,$request,'propose','invalid_review_window_denied');
        }
        if(isset($this->requests[$request])){
            return $this->record($tenant,$request,'propose','duplicate_or_out_of_scope');
        }
        $oldState=$this->ledger->campaignState($tenant,$oldCampaign);
        $newState=$this->ledger->campaignState($tenant,$newCampaign);
        if(!in_array($oldState,['active_demo','paused_demo','draft_demo'],true)
            ||$newState!=='not_found'){
            return $this->record($tenant,$request,'propose','managed_scope_mismatch_denied');
        }
        $this->requests[$request]=[
            'tenant'=>$tenant,'request'=>$request,
            'proposer'=>$proposer,'old'=>$oldCampaign,'new'=>$newCampaign,
            'old_state'=>$oldState,'manifest'=>$manifest,
            'created'=>$now,'expires'=>$expiresAt,'reviewer'=>null,
            'state'=>'pending_review_synthetic','applied'=>false,
        ];
        return $this->record($tenant,$request,'propose','proposed_synthetic_only');
    }

    public function decide(
        string $tenant,string $request,string $reviewer,
        string $manifest,bool $authorizedReviewerFixture,
        bool $approve,int $now
    ):string {
        if(!self::enabled())return 'review_lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($request,'req-demo-');
        self::ref($reviewer,'actor-demo-');
        if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)){
            throw new InvalidArgumentException('Invalid fictional manifest digest');
        }
        $r=$this->requests[$request]??null;
        if(!is_array($r)||$r['tenant']!==$tenant){
            return $this->record($tenant,$request,'decide','not_found');
        }
        if($r['state']!=='pending_review_synthetic'){
            return $this->record($tenant,$request,'decide','already_reviewed_denied');
        }
        if($now<$r['created']||$now>=$r['expires']){
            return $this->record($tenant,$request,'decide','review_expired_denied');
        }
        if(!$authorizedReviewerFixture||$reviewer===$r['proposer']){
            return $this->record($tenant,$request,'decide','independent_reviewer_required');
        }
        if($manifest!==$r['manifest']){
            return $this->record($tenant,$request,'decide','review_manifest_conflict');
        }
        $this->requests[$request]['reviewer']=$reviewer;
        $this->requests[$request]['state']=$approve
            ?'approved_scope_demo_not_executed':'rejected_scope_demo';
        return $this->record(
            $tenant,$request,'decide',$this->requests[$request]['state']
        );
    }

    /**
     * Applies solely a mock ledger replacement after independent fake review.
     * It NEVER calls Google/Meta, publishes campaigns or persists a grant.
     */
    public function applyMock(
        string $tenant,string $request,string $operator,
        string $manifest,bool $authorizedOperatorFixture,int $now
    ):string {
        if(!self::enabled())return 'review_lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($request,'req-demo-');
        self::ref($operator,'actor-demo-');
        if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)){
            throw new InvalidArgumentException('Invalid fictional manifest digest');
        }
        $r=$this->requests[$request]??null;
        if(!is_array($r)||$r['tenant']!==$tenant){
            return $this->record($tenant,$request,'apply','not_found');
        }
        if($r['state']!=='approved_scope_demo_not_executed'){
            return $this->record($tenant,$request,'apply','review_not_approved_denied');
        }
        if($now<$r['created']||$now>=$r['expires']){
            return $this->record($tenant,$request,'apply','review_expired_denied');
        }
        if(!$authorizedOperatorFixture
            ||$operator===$r['proposer']||$operator===$r['reviewer']){
            return $this->record($tenant,$request,'apply','independent_operator_required');
        }
        if($manifest!==$r['manifest']){
            return $this->record($tenant,$request,'apply','review_manifest_conflict');
        }
        if($this->ledger->campaignState($tenant,$r['old'])!==$r['old_state']
            ||$this->ledger->campaignState($tenant,$r['new'])!=='not_found'){
            $this->requests[$request]['state']='stale_scope_review_denied';
            return $this->record($tenant,$request,'apply','stale_scope_review_denied');
        }
        $result=$this->ledger->replaceCampaign(
            $tenant,$r['old'],$r['new'],true,true
        );
        if($result!=='replaced_synthetic_no_fee'){
            // A prior capacity or scope change prevents use of old approval.
            $this->requests[$request]['state']='stale_scope_review_denied';
            return $this->record($tenant,$request,'apply','stale_scope_review_denied');
        }
        $this->requests[$request]['state']='scope_applied_synthetic_only';
        $this->requests[$request]['applied']=true;
        return $this->record($tenant,$request,'apply','scope_applied_synthetic_only');
    }

    public function statusFor(string $tenant,string $request):string
    {
        if(!self::enabled())return 'review_lab_disabled';
        self::ref($tenant,'perf-demo-');self::ref($request,'req-demo-');
        $r=$this->requests[$request]??null;
        return is_array($r)&&$r['tenant']===$tenant?$r['state']:'not_found';
    }

    /** @return list<array<string,string>> */
    public function auditFor(string $tenant):array
    {
        if(!self::enabled())return [];
        self::ref($tenant,'perf-demo-');
        return $this->audit[$tenant]??[];
    }

    /** @return list<array{request_ref:string,status:string,old_campaign:string,new_campaign:string}> */
    public function pendingFor(string $tenant):array
    {
        if(!self::enabled())return [];
        self::ref($tenant,'perf-demo-');
        $entries=[];
        foreach($this->requests as $row){
            if($row['tenant']!==$tenant
                ||$row['state']!=='pending_review_synthetic')continue;
            $entries[]=[
                'request_ref'=>$row['request'],
                'status'=>$row['state'],
                'old_campaign'=>$row['old'],
                'new_campaign'=>$row['new'],
            ];
        }
        return $entries;
    }
}
