<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use App\Performance\Contracts\PortalPerformanceChannelContract;
use PDO;
use Throwable;

/**
 * FAST-PERF F1-92 — Laravel-compatible CLI lab reader of the F91
 * credential-bound, read-only PostgreSQL VIEW.
 *
 * Never accepts a tenant ID from its caller; SESSION_USER is established by
 * PostgreSQL authentication. No API route, mutation or provider connector.
 */
final class CredentialBoundDemoAccountReader
{
    private const SCHEMA='perf.portal.credential-bound-accounts.synthetic.v1';

    private static function denied(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
            'simulation_only'=>true,
            'credential_binding_confirmed'=>false,
            'portal_identity_authenticated'=>false,
            'provider_connected'=>false,
            'authorization_eligible'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'accounts'=>[],
        ];
    }

    public function read(PDO $db):array
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry'
            || getenv('PGPORT')!=='5432') {
            return self::denied('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
                return self::denied('invalid_database_driver');
            }
            $id=$db->query(
                'SELECT current_database() AS dbname, '
                .'session_user::text AS db_login, '
                .'public.perf_f91_tenant_for_login()::text AS bound_tenant'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($id)
                || $id['dbname']!=='perf_test_campaign_registry'
                || !in_array($id['db_login'],[
                    'perf_f91_reader_a','perf_f91_reader_b'
                ],true)) {
                return self::denied('untrusted_database_login');
            }
            if (!is_string($id['bound_tenant']) || $id['bound_tenant']==='') {
                return self::denied('database_login_revoked');
            }
            if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$id['bound_tenant'])) {
                return self::denied('invalid_bound_tenant');
            }
            $rows=$db->query(
                'SELECT tenant_id,portal_service_ref,channel,account_ref,connection_state '
                .'FROM public.perf_f91_demo_account_read ORDER BY channel,account_ref'
            )->fetchAll(PDO::FETCH_ASSOC);
            if(!is_array($rows) || count($rows)>20) {
                return self::denied('invalid_account_projection');
            }
            $accounts=[];$refs=[];
            foreach($rows as $r) {
                if(!is_array($r) || array_keys($r)!==[
                    'tenant_id','portal_service_ref','channel','account_ref','connection_state'
                ] || $r['tenant_id']!==$id['bound_tenant']
                || $r['portal_service_ref']!=='svc-demo-f88'
                || $r['connection_state']!=='unverified_demo'
                || !is_string($r['account_ref'])
                || preg_match('/^demo_[a-z0-9][a-z0-9_-]{0,63}$/D',$r['account_ref'])!==1) {
                    return self::denied('invalid_account_projection');
                }
                $portal=PortalPerformanceChannelContract::toPortal($r['channel']);
                if($portal===null) {
                    return self::denied('unsupported_channel');
                }
                $key=$portal.':'.$r['account_ref'];
                if(isset($refs[$key])) {
                    return self::denied('duplicate_account_candidate');
                }
                $refs[$key]=true;
                $accounts[]=[
                    'portal_channel'=>$portal,
                    'account_ref'=>$r['account_ref'],
                    'connection_state'=>'unverified_demo',
                ];
            }
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>'credential_scoped_demo_preview_only',
                'simulation_only'=>true,
                'credential_binding_confirmed'=>true,
                'portal_identity_authenticated'=>false,
                'provider_connected'=>false,
                'authorization_eligible'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
                'accounts'=>$accounts,
            ];
        } catch(Throwable) {
            return self::denied('read_unavailable');
        }
    }
}
