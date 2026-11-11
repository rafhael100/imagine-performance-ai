<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use PDO;
use Throwable;

/**
 * FAST-PERF F189 - fail-closed PREVIEW read preflight, NOT IAM.
 *
 * Uses only the authenticated SQL session_user and a fixed service ref.
 * A local database snapshot, even marked eligible, cannot prove paid
 * subscription, Portal identity, Meta/Google authorization or human approval.
 */
final class CredentialBoundDemoServiceStatusFence
{
    private const SCHEMA='perf.portal.credential-bound-workbench.synthetic.v1';

    private static function denied(string $reason):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$reason,
            'synthetic_only'=>true,
            'read_only'=>true,
            'credential_binding_confirmed'=>false,
            'portal_identity_authenticated'=>false,
            'real_human_approval_verified'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'counts'=>[],
            'accounts'=>[],
            'campaigns'=>[],
            'reviews'=>[],
        ];
    }

    private static function evidence(PDO $db):?array
    {
        $rows=$db->query(
            'SELECT tenant_id,tenant_status,tenant_revoked_at,link_status,'
            .'link_revision,eligibility_state,eligibility_source,'
            .'eligibility_revision,eligibility_observed_at '
            .'FROM public.perf_f189_login_service_status()'
        )->fetchAll(PDO::FETCH_ASSOC);
        if(!is_array($rows)||count($rows)!==1 ||!is_array($rows[0])
            ||array_keys($rows[0])!==[
                'tenant_id','tenant_status','tenant_revoked_at','link_status',
                'link_revision','eligibility_state','eligibility_source',
                'eligibility_revision','eligibility_observed_at'
            ])return null;
        return $rows[0];
    }

    private static function labEligible(?array $row,int $now):bool
    {
        if($row===null)return false;
        $date=$row['eligibility_observed_at'];
        if(!is_string($date))return false;
        $observed=strtotime($date);
        return is_int($observed)
            &&$observed<=$now+5
            &&$observed>=$now-300
            &&$row['tenant_status']==='active'
            &&$row['tenant_revoked_at']===null
            &&$row['link_status']==='active'
            &&is_string($row['link_revision'])
            &&preg_match('/^[a-z0-9_-]{5,64}$/D',$row['link_revision'])===1
            &&$row['eligibility_state']==='eligible'
            &&$row['eligibility_source']==='synthetic_ci_only'
            &&$row['eligibility_revision']===$row['link_revision']
            &&is_string($row['tenant_id'])
            &&preg_match('/^[a-f0-9-]{36}$/D',$row['tenant_id'])===1;
    }

    public function read(PDO $db):array
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F189_SERVICE_STATUS_LAB')!=='1'
            ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
            return self::denied('lab_disabled');
        }
        try{
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||$db->inTransaction()){
                return self::denied('invalid_connection_state');
            }
            $now=time();
            $first=self::evidence($db);
            if(!self::labEligible($first,$now)){
                return self::denied('service_not_eligible_in_local_demo');
            }

            // F100 itself opens READ ONLY and independently reconfirms all
            // three credential-bound views. No caller-selected tenant.
            $data=(new CredentialBoundDemoWorkbenchReader())->read($db);
            if(($data['schema_version']??null)!==self::SCHEMA
                ||($data['status']??null)!=='credential_scoped_demo_workbench_only'
                ||($data['credential_binding_confirmed']??null)!==true
                ||($data['portal_identity_authenticated']??null)!==false
                ||($data['authorization_eligible']??null)!==false
                ||($data['dispatch_enabled']??null)!==false
                ||($data['billing_enabled']??null)!==false){
                return self::denied('underlying_workbench_denied');
            }
            $second=self::evidence($db);
            if(!self::labEligible($second,time())||$first!==$second){
                return self::denied('service_changed_during_demo_read');
            }
            // Keep original F100 contract unchanged for pinned Portal F62.
            // The source is synthetic; flags remain false despite status.
            return $data;
        }catch(Throwable){
            return self::denied('service_status_unavailable_fail_closed');
        }
    }
}
