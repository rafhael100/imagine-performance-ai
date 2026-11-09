<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use Throwable;

/**
 * FAST-PERF F1-71: compare tenant-specific committed PostgreSQL mock rows
 * against an INDEPENDENT CI PostgreSQL checkpoint database. Read-only.
 *
 * NOT atomic across databases, not authenticated Portal identity, no WORM,
 * no signature, no trusted consensus or provider idempotency, no live writes.
 */
final class SyntheticPgIndependentRecoveryGuard
{
    public function inspect(PDO $primary, PDO $checkpoint):array
    {
        $deny=static fn(string $reason):array=>[
            'schema_version'=>'perf.recovery.guard.synthetic.v1',
            'status'=>$reason,
            'consistent_mock_only'=>false,
            'read_preview_eligible'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
        ];
        if(PHP_SAPI!=='cli'
           ||getenv('APP_ENV')!=='testing'
           ||getenv('PERF_F66_POSTGRES_RLS_LAB')!=='1'
           ||getenv('PERF_F71_INDEPENDENT_RESTORE_LAB')!=='1'){
            return $deny('lab_disabled');
        }
        if($primary->inTransaction()||$checkpoint->inTransaction()){
            return $deny('existing_transaction_denied');
        }
        try{
            if($primary->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
               ||$checkpoint->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'){
                return $deny('driver_denied');
            }
            $a=$primary->query(
                "SELECT session_user::text, current_user::text,"
                ." current_database()::text, current_setting('row_security')::text"
            )->fetch(PDO::FETCH_NUM);
            $b=$checkpoint->query(
                "SELECT session_user::text, current_user::text,"
                ." current_database()::text, current_setting('row_security')::text"
            )->fetch(PDO::FETCH_NUM);
            if(!is_array($a)||!is_array($b)||count($a)!==4||count($b)!==4){
                return $deny('identity_unavailable');
            }
            $mapped=[
                'perf_f66_tenant_a'=>'perf_f71_tenant_a',
                'perf_f66_tenant_b'=>'perf_f71_tenant_b',
            ];
            $tenant=$a[0];
            if(!isset($mapped[$tenant])||$a[1]!==$tenant
               ||$a[2]!=='perf_f66_ci'||$a[3]!=='on'
               ||$b[0]!==$mapped[$tenant]||$b[1]!==$mapped[$tenant]
               ||$b[2]!=='perf_f71_ci'||$b[3]!=='on'){
                return $deny('identity_or_authority_mismatch');
            }
            $primary->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
            try{
                $checkpoint->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }catch(Throwable $e){
                $primary->rollBack();
                return $deny('checkpoint_unavailable');
            }
            try{
                $rows=$checkpoint->query(
                    'SELECT tenant_role,kind,ref,channel,old_ref,new_ref'
                    .' FROM perf_f71_guard.checkpoints ORDER BY kind,ref'
                )->fetchAll(PDO::FETCH_ASSOC);
                if(!is_array($rows)||count($rows)<1||count($rows)>50){
                    $checkpoint->rollBack();$primary->rollBack();
                    return $deny('missing_or_oversized_independent_checkpoint');
                }
                $seen=[];
                foreach($rows as $r){
                    if($r['tenant_role']!==$tenant
                       ||!in_array($r['kind'],['reserve','swap'],true)
                       ||!is_string($r['ref'])
                       ||isset($seen[$r['kind'].'|'.$r['ref']])){
                        $checkpoint->rollBack();$primary->rollBack();
                        return $deny('malformed_independent_checkpoint');
                    }
                    $seen[$r['kind'].'|'.$r['ref']]=true;
                    if($r['kind']==='reserve'){
                        if(!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$r['ref'])
                           ||!in_array($r['channel'],['google_ads','meta'],true)
                           ||$r['old_ref']!==null||$r['new_ref']!==null){
                            $checkpoint->rollBack();$primary->rollBack();
                            return $deny('malformed_reservation_checkpoint');
                        }
                        $stmt=$primary->prepare(
                            'SELECT channel,state,managed'
                            .' FROM perf_f66_scope.campaigns'
                            .' WHERE tenant_role=? AND ref=?'
                        );
                        $stmt->execute([$tenant,$r['ref']]);
                        $current=$stmt->fetch(PDO::FETCH_ASSOC);
                        if(!is_array($current)
                           ||$current['channel']!==$r['channel']
                           ||$current['state']!=='active_demo'
                           ||!in_array($current['managed'],['1',1,true,'t'],true)){
                            $checkpoint->rollBack();$primary->rollBack();
                            return $deny('missing_or_changed_committed_reservation');
                        }
                    }else{
                        if(!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$r['ref'])
                           ||!is_string($r['old_ref'])
                           ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$r['old_ref'])
                           ||!is_string($r['new_ref'])
                           ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$r['new_ref'])
                           ||$r['channel']!==null){
                            $checkpoint->rollBack();$primary->rollBack();
                            return $deny('malformed_swap_checkpoint');
                        }
                        $q=$primary->prepare(
                            'SELECT old_ref,new_ref,result FROM perf_f66_scope.swaps'
                            .' WHERE tenant_role=? AND request_ref=?'
                        );
                        $q->execute([$tenant,$r['ref']]);
                        $current=$q->fetch(PDO::FETCH_ASSOC);
                        if(!is_array($current)
                           ||$current['old_ref']!==$r['old_ref']
                           ||$current['new_ref']!==$r['new_ref']
                           ||$current['result']!=='replaced_synthetic_no_fee'){
                            $checkpoint->rollBack();$primary->rollBack();
                            return $deny('missing_or_changed_committed_swap');
                        }
                        $q=$primary->prepare(
                            'SELECT state,managed FROM perf_f66_scope.campaigns'
                            .' WHERE tenant_role=? AND ref=?'
                        );
                        $q->execute([$tenant,$r['old_ref']]);
                        $old=$q->fetch(PDO::FETCH_ASSOC);
                        $q->execute([$tenant,$r['new_ref']]);
                        $new=$q->fetch(PDO::FETCH_ASSOC);
                        if(!is_array($old)||!is_array($new)
                           ||$old['state']!=='outside_scope_demo'
                           ||!in_array($old['managed'],['0',0,false,'f'],true)
                           ||$new['state']!=='draft_demo'
                           ||!in_array($new['managed'],['1',1,true,'t'],true)){
                            $checkpoint->rollBack();$primary->rollBack();
                            return $deny('missing_or_changed_swap_membership');
                        }
                    }
                }
                $checkpoint->commit();$primary->commit();
                return [
                    'schema_version'=>'perf.recovery.guard.synthetic.v1',
                    'status'=>'independent_fixture_matches',
                    'tenant_role'=>$tenant,
                    'checked_checkpoints'=>count($rows),
                    'consistent_mock_only'=>true,
                    'read_preview_eligible'=>true,
                    'authorization_eligible'=>false,
                    'ready_for_production'=>false,
                    'dispatch_enabled'=>false,
                    'billing_enabled'=>false,
                ];
            }catch(Throwable $e){
                if($checkpoint->inTransaction())$checkpoint->rollBack();
                if($primary->inTransaction())$primary->rollBack();
                return $deny('checkpoint_read_or_sql_denied');
            }
        }catch(Throwable $e){
            return $deny('connection_or_identity_denied');
        }
    }
}
