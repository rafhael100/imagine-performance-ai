<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;

use DomainException;

/** Pure, bounded laboratory engine. No HTTP, database, credentials or executor. */
final class AdministrativeLabEngine
{
    public const CONTRACT='imagine.performance.admin.v1';
    public const ROLES=[
        'superadmin'=>['view','finance','agent.pause','agent.resume','approval.approve','approval.reject','approval.revoke','approval.consume','incident.ack','incident.resolve'],
        'operations'=>['view','agent.pause','agent.resume','approval.approve','approval.reject','approval.revoke','approval.consume','incident.ack','incident.resolve'],
        'analyst'=>['view','incident.ack'],
        'ai_supervisor'=>['view','agent.pause','agent.resume','approval.approve','approval.reject','approval.revoke','approval.consume'],
        'finance'=>['view','finance'],
        'auditor'=>['view','finance'],
    ];
    public static function allows(string $role,string $action):bool
    {return in_array($action,self::ROLES[$role]??[],true);}

    /** Server context must be derived from authenticated Portal membership. */
    public static function command(array $state,array $context,array $command,int $now):array
    {
        if(($state['contract']??null)!==self::CONTRACT||($state['source']??null)!=='synthetic'
            ||($state['tenant']??null)!==($context['tenant']??null)
            ||($context['active']??false)!==true||!is_int($context['actor']??null)||$context['actor']<1
            ||!is_int($context['mfa_at']??null)||$context['mfa_at']<$now-900||$context['mfa_at']>$now+30
            ||!self::allows($context['role']??'', $command['action']??''))throw new DomainException('DENIED');
        if(($command['revision']??null)!==($state['revision']??null))throw new DomainException('STALE_REVISION');
        if(($command['confirmed']??false)!==true)throw new DomainException('CONFIRMATION_REQUIRED');
        $action=$command['action'];$id=$command['resource']??'';
        if(!is_string($id)||preg_match('/\A[a-z0-9-]{1,64}\z/D',$id)!==1)throw new DomainException('INVALID_RESOURCE');
        if(str_starts_with($action,'agent.')){
            if(!isset($state['agents'][$id]))throw new DomainException('NOT_FOUND');
            $agent=&$state['agents'][$id];
            $target=$action==='agent.pause'?'paused':'active';
            if(!in_array($agent['status'],['active','paused'],true)||$agent['status']===$target)throw new DomainException('INVALID_TRANSITION');
            $agent['status']=$target;$agent['last_change']=$now;
        }elseif(str_starts_with($action,'approval.')){
            if(!isset($state['approvals'][$id]))throw new DomainException('NOT_FOUND');
            $a=&$state['approvals'][$id];
            if($a['tenant']!==$context['tenant']||$a['expires_at']<=$now||$a['version']!==($command['proposal_version']??null)
                ||!hash_equals($a['digest'], $command['proposal_digest']??''))throw new DomainException('EXPIRED_OR_CHANGED_PROPOSAL');
            if($action==='approval.consume'){
                if($a['status']!=='approved'||$a['kind']!=='internal_note'||(!is_int($a['approved_by'])||$a['approved_by']<1||$a['approved_by']===$context['actor']))throw new DomainException('EXECUTION_BLOCKED');
                // Only an internal, synthetic review receipt. Never Ads or financial execution.
                $a['status']='consumed';$a['executed_by']=$context['actor'];
            }elseif($action==='approval.revoke'){
                if($a['status']!=='approved')throw new DomainException('INVALID_TRANSITION');
                $a['status']='revoked';
            }else{
                if($a['status']!=='pending'||$a['requested_by']===$context['actor'])throw new DomainException('INVALID_TRANSITION');
                $a['status']=$action==='approval.approve'?'approved':'rejected';
                $a['approved_by']=$context['actor'];
            }
            $a['decided_at']=$now;
        }elseif(str_starts_with($action,'incident.')){
            if(!isset($state['incidents'][$id]))throw new DomainException('NOT_FOUND');
            $i=&$state['incidents'][$id];
            if($action==='incident.ack'){
                if($i['status']!=='open')throw new DomainException('INVALID_TRANSITION');
                $i['status']='acknowledged';$i['owner']=$context['actor'];
            }else{
                if($i['status']!=='acknowledged'||$i['resolution_evidence']!=='synthetic_verified')throw new DomainException('EVIDENCE_REQUIRED');
                $i['status']='resolved';$i['resolved_by']=$context['actor'];
            }
            $i['updated_at']=$now;
        }else throw new DomainException('DENIED');
        $state['revision']++;$state['updated_at']=$now;
        return ['contract'=>self::CONTRACT,'state'=>$state,'event'=>[
            'tenant'=>$context['tenant'],'actor'=>$context['actor'],'role'=>$context['role'],
            'action'=>$action,'resource'=>$id,'at'=>$now,'revision'=>$state['revision'],
            'external_execution'=>false,'source'=>'synthetic',
        ]];
    }
    public static function finance(array $state):array
    {
        require_once __DIR__.'/../Commercial/MonthlyManagementFeeFloorV7Candidate.php';
        $channels=[];
        foreach($state['channels'] as $id=>$channel){
            if($channel['status']==='unavailable')continue;
            if(!$channel['full_month'])return ['state'=>'partial_policy_pending','billable'=>false,'channels'=>[]];
            $channels[$id]=['planned_cents'=>$channel['planned_cents'],'actual_cents'=>$channel['actual_cents']];
        }
        $preview=\Imagine\Performance\Commercial\MonthlyManagementFeeFloorV7Candidate::preview($state['period'],$channels);
        $preview['activation_reference_cents']=49000*count($channels);
        return $preview;
    }
    public static function fixture(string $tenant,int $now):array
    {
        if(!in_array($tenant,['lab-alfa','lab-beta'],true))throw new DomainException('UNKNOWN_LAB_TENANT');
        $channels=[];
        foreach(['google_ads'=>'Google Ads','meta_ads'=>'Meta Ads · Instagram e Facebook','chatgpt_ads'=>'ChatGPT Ads'] as $id=>$name){
            $channels[$id]=['id'=>$id,'name'=>$name,'status'=>$id==='chatgpt_ads'?'unavailable':'simulated',
                'account'=>'LAB-'.strtoupper($tenant).'-'.$id,'planned_cents'=>$id==='google_ads'?100000:70000,
                'actual_cents'=>$id==='google_ads'?100000:70000,'provider_verified'=>false,
                'full_month'=>true,'read_authorized'=>false,'write_authorized'=>false,'last_sync'=>null];
        }
        $campaigns=[];
        for($n=1;$n<=14;$n++){$id='campaign-'.$n;$channel=$n%2?'google_ads':'meta_ads';
            $campaigns[$id]=['id'=>$id,'name'=>'Campanha de laboratório '.str_pad((string)$n,2,'0',STR_PAD_LEFT),
                'channel'=>$channel,'objective'=>$n%2?'Leads':'Visitas','status'=>$n%3?'monitored':'alert',
                'spend_cents'=>10000+$n*100,'impressions'=>1000+$n*100,'clicks'=>50+$n,'conversions'=>5+$n,'leads'=>3+$n,
                'source'=>'synthetic','diagnosis'=>'Revisar acompanhamento e qualidade dos dados. Sem recomendação de alteração real.'];}
        $agents=[];
        foreach(['strategy'=>'Estratégia publicitária','analysis'=>'Análise de campanhas','budget'=>'Otimização de investimentos',
            'creative'=>'Criativos e anúncios','measurement'=>'Mensuração','diagnosis'=>'Diagnóstico','reports'=>'Relatórios','monitor'=>'Monitoramento de incidentes'] as $id=>$name)
            $agents[$id]=['id'=>$id,'name'=>$name,'status'=>$id==='monitor'?'failed':'active','level'=>'N1','channel'=>'google_ads',
                'last_run'=>$now-3600,'next_run'=>null,'recommendation'=>'Revisão de laboratório disponível. Nenhum modelo externo executado.'];
        $approvals=[];
        foreach(['review'=>'internal_note','budget'=>'ads_budget','payment'=>'financial'] as $id=>$kind){
            $a=['id'=>$id,'tenant'=>$tenant,'kind'=>$kind,'title'=>['review'=>'Registrar diagnóstico interno','budget'=>'Proposta de orçamento','payment'=>'Revisão financeira'][$id],
                'status'=>'pending','version'=>1,'expires_at'=>$now+86400,'requested_by'=>0,'approved_by'=>null,
                'before'=>'Sem alteração','after'=>'Revisão proposta em laboratório','reason'=>'Exercitar supervisão e rastreabilidade sem efeitos externos.',
                'risk'=>$kind==='internal_note'?'R1':'R3','campaign'=>'campaign-1'];
            $a['digest']=hash('sha256',json_encode($a,JSON_THROW_ON_ERROR));$approvals[$id]=$a;
        }
        return ['contract'=>self::CONTRACT,'tenant'=>$tenant,'name'=>$tenant==='lab-alfa'?'Empresa Alfa · Laboratório':'Empresa Beta · Laboratório',
            'source'=>'synthetic','revision'=>1,'updated_at'=>$now,'period'=>'2026-11','client_status'=>$tenant==='lab-alfa'?'active':'onboarding',
            'contract_status'=>'synthetic_only','activation_at'=>null,'channels'=>$channels,'campaigns'=>$campaigns,'agents'=>$agents,'approvals'=>$approvals,
            'incidents'=>['sync'=>['id'=>'sync','title'=>'Sincronização sem provedor homologado','severity'=>'high','status'=>'open','owner'=>null,
                'updated_at'=>$now,'resolution_evidence'=>'unavailable'],
                'task'=>['id'=>'task','title'=>'Tarefa de revisão interna','severity'=>'medium','status'=>'open','owner'=>null,
                'updated_at'=>$now,'resolution_evidence'=>'synthetic_verified']],
            'history'=>[['label'=>'Cenário sintético criado','at'=>$now]],
            'series'=>[['label'=>'Semana 1','leads'=>12],['label'=>'Semana 2','leads'=>19],['label'=>'Semana 3','leads'=>15],['label'=>'Semana 4','leads'=>24]]];
    }
}
