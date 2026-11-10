<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use Throwable;

/**
 * FAST-PERF F1-67 — read-only PostgreSQL FORCE RLS snapshot (CI ONLY).
 *
 * No tenant parameter: database session_user is the sole fixture identity.
 * Requires F1-66's two unrelated login roles. This is NOT Portal login, IAM,
 * real ad account binding, production PostgreSQL tenancy or customer access.
 */
final class SyntheticPgTenantScopeProjector
{
    public function build(PDO $db): ?array
    {
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
           ||getenv('PERF_F67_PG_READ_MODEL_LAB')!=='1'
           ||$db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
           ||$db->inTransaction()){
            return null;
        }
        try{
            $identity=$db->query(
                'SELECT session_user::text AS login, current_user::text AS effective,'
                .' current_database()::text AS db_name,'
                ." current_setting('row_security')::text AS rls_enabled"
            )->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
               ||!in_array($identity['login'],[
                    'perf_f66_tenant_a','perf_f66_tenant_b'
               ],true)
               ||$identity['effective']!==$identity['login']
               ||$identity['db_name']!=='perf_f66_ci'
               ||$identity['rls_enabled']!=='on'){
                return null;
            }
            $tenant=$identity['login'];

            $db->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
            try{
                $rawLimits=$db->query(
                    'SELECT tenant_role,channel,campaign_limit,group_limit'
                    .' FROM perf_f66_scope.capacities ORDER BY channel'
                )->fetchAll(PDO::FETCH_ASSOC);
                $rawCampaigns=$db->query(
                    'SELECT ref,tenant_role,channel,state,'
                    .' CASE WHEN managed THEN 1 ELSE 0 END AS managed_flag'
                    .' FROM perf_f66_scope.campaigns ORDER BY ref'
                )->fetchAll(PDO::FETCH_ASSOC);
                $rawGroups=$db->query(
                    'SELECT g.ref,g.tenant_role,g.campaign_ref,g.state,'
                    .' CASE WHEN g.managed THEN 1 ELSE 0 END AS managed_flag,'
                    .' c.channel AS parent_channel,'
                    .' CASE WHEN c.managed THEN 1 ELSE 0 END AS parent_managed'
                    .' FROM perf_f66_scope.groups g'
                    .' LEFT JOIN perf_f66_scope.campaigns c ON g.campaign_ref=c.ref'
                    .' ORDER BY g.ref'
                )->fetchAll(PDO::FETCH_ASSOC);
                if(!is_array($rawLimits)||!is_array($rawCampaigns)
                   ||!is_array($rawGroups)||count($rawLimits)>2
                   ||count($rawCampaigns)>300||count($rawGroups)>3000){
                    $db->rollBack();
                    return null;
                }

                $allowed=['google_ads','meta'];
                $limit=[];
                foreach($rawLimits as $row){
                    $channel=$row['channel'];
                    if($row['tenant_role']!==$tenant
                       ||!in_array($channel,$allowed,true)
                       ||isset($limit[$channel])
                       ||!ctype_digit((string)$row['campaign_limit'])
                       ||!ctype_digit((string)$row['group_limit'])
                       ||(int)$row['campaign_limit']>100
                       ||(int)$row['group_limit']>1000){
                        $db->rollBack();return null;
                    }
                    $limit[$channel]=[
                        'campaigns'=>(int)$row['campaign_limit'],
                        'groups'=>(int)$row['group_limit'],
                    ];
                }

                $campaigns=[];$campaignIndex=[];
                $used=['google_ads'=>['campaigns'=>0,'groups'=>0],
                       'meta'=>['campaigns'=>0,'groups'=>0]];
                foreach($rawCampaigns as $row){
                    $ref=$row['ref'];
                    if($row['tenant_role']!==$tenant
                       ||!is_string($ref)
                       ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$ref)
                       ||isset($campaignIndex[$ref])
                       ||!in_array($row['channel'],$allowed,true)
                       ||!in_array($row['managed_flag'],['0','1',0,1],true)){
                        $db->rollBack();return null;
                    }
                    $managed=(int)$row['managed_flag']===1;
                    $campaignIndex[$ref]=[
                        'channel'=>$row['channel'],'managed'=>$managed,
                        'state'=>$row['state']
                    ];
                    if(!$managed){
                        if($row['state']!=='outside_scope_demo'){
                            $db->rollBack();return null;
                        }
                        continue;
                    }
                    if(!in_array($row['state'],[
                        'active_demo','paused_demo','draft_demo'
                    ],true)||!isset($limit[$row['channel']])){
                        $db->rollBack();return null;
                    }
                    ++$used[$row['channel']]['campaigns'];
                    $campaigns[]=[
                        'ref'=>$ref,'state'=>$row['state'],
                        'channel'=>$row['channel'],'managed'=>true,
                        'provider_action'=>'none_synthetic'
                    ];
                }

                $groups=[];$seen=[];
                foreach($rawGroups as $row){
                    $ref=$row['ref'];
                    if($row['tenant_role']!==$tenant
                       ||!is_string($ref)
                       ||!preg_match('/^grp-[a-z0-9-]{1,60}$/D',$ref)
                       ||isset($seen[$ref])
                       ||!in_array($row['managed_flag'],['0','1',0,1],true)){
                        $db->rollBack();return null;
                    }
                    $seen[$ref]=true;
                    if((int)$row['managed_flag']===0){
                        if($row['state']!=='outside_scope_demo'){
                            $db->rollBack();return null;
                        }
                        continue;
                    }
                    $parent=$campaignIndex[$row['campaign_ref']]??null;
                    if($parent===null||!$parent['managed']
                       ||$row['parent_channel']!==$parent['channel']
                       ||(int)$row['parent_managed']!==1
                       ||!in_array($row['state'],['active_demo','paused_demo'],true)){
                        $db->rollBack();return null;
                    }
                    ++$used[$parent['channel']]['groups'];
                    $groups[]=[
                        'ref'=>$ref,'state'=>$row['state'],
                        'campaign_ref'=>$row['campaign_ref'],
                        'channel'=>$parent['channel'],
                        'managed'=>true,
                        'provider_action'=>'none_synthetic'
                    ];
                }

                $channels=[];
                foreach([
                    'google_ads'=>'Google Ads',
                    'meta'=>'Meta (Instagram/Facebook)',
                ] as $channel=>$label){
                    $cfg=$limit[$channel]??null;
                    $c=$used[$channel]['campaigns'];
                    $g=$used[$channel]['groups'];
                    if($cfg===null&&($c!==0||$g!==0)){
                        $db->rollBack();return null;
                    }
                    if($cfg!==null&&($c>$cfg['campaigns']||$g>$cfg['groups'])){
                        $db->rollBack();return null;
                    }
                    $remainingC=$cfg===null?null:$cfg['campaigns']-$c;
                    $remainingG=$cfg===null?null:$cfg['groups']-$g;
                    $channels[]=[
                        'id'=>$channel,'label'=>$label,
                        'capacity_status'=>$cfg===null
                            ?'unconfigured_blocked_demo':'configured_synthetic_only',
                        'campaigns'=>[
                            'used'=>$c,'limit'=>$cfg['campaigns']??null,
                            'remaining'=>$remainingC,
                        ],
                        'groups'=>[
                            'used'=>$g,'limit'=>$cfg['groups']??null,
                            'remaining'=>$remainingG,
                        ],
                        'attention'=>$cfg===null
                            ?'commercial_capacity_not_approved'
                            :(($remainingC===0||$remainingG===0)
                                ?'review_scope_no_auto_upgrade':'none_demo'),
                    ];
                }
                $dto=[
                    'schema_version'=>'perf.portal.pg-scope-read.synthetic.v1',
                    'source'=>'postgres_force_rls_fixture_only',
                    'tenant_role'=>$tenant,
                    'read_only'=>true,
                    'contracted'=>false,
                    'human_identity_verified'=>false,
                    'provider_connected'=>false,
                    'authorization_eligible'=>false,
                    'ready_for_production'=>false,
                    'dispatch_enabled'=>false,
                    'billing_enabled'=>false,
                    'prices_homologated'=>false,
                    'channels'=>$channels,
                    'campaigns'=>$campaigns,
                    'groups'=>$groups,
                    'review_summary'=>[
                        'audit_entries_demo'=>0,
                        'approval_source'=>'none_real',
                    ],
                ];
                $db->commit();
                return $dto;
            }catch(Throwable $e){
                if($db->inTransaction())$db->rollBack();
                return null;
            }
        }catch(Throwable $e){
            return null;
        }
    }
}
