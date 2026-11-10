<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use App\Performance\Contracts\PortalPerformanceChannelContract;
use PDO;
use Throwable;

/**
 * FAST-PERF F1-94. Lab-only application read model for the F93 login-bound
 * VIEW; does not take a tenant id or a caller-selected role.
 *
 * No runtime bindings, HTTP routes, Ads API clients or write operations.
 */
final class CredentialBoundDemoCampaignReader
{
    private const SCHEMA='perf.portal.credential-bound-campaigns.synthetic.v1';
    private const STATES=[
        'draft'=>'draft_demo',
        'paused'=>'paused_demo',
        'archived'=>'archived_demo',
    ];

    private static function denied(string $status): array
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
            'campaigns'=>[],
        ];
    }

    public function read(PDO $db): array
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }
        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
                return self::denied('invalid_database_driver');
            }
            // F100 legitimately composes read-only projections within
            // READ COMMITTED. Deny all caller transactions with stale
            // snapshots or any write privileges.
            if ($db->inTransaction()) {
                $isolation=$db->query('SHOW transaction_isolation')->fetchColumn();
                $readOnly=$db->query('SHOW transaction_read_only')->fetchColumn();
                if ($isolation!=='read committed' || $readOnly!=='on') {
                    return self::denied('invalid_connection_state');
                }
            }
            $identitySql='SELECT current_database() AS dbname, '
                .'session_user::text AS db_login, '
                .'public.perf_f91_tenant_for_login()::text AS bound_tenant';
            $identity=$db->query($identitySql)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($identity) || $identity['dbname']!=='perf_test_campaign_registry'
                || !in_array($identity['db_login'],['perf_f91_reader_a','perf_f91_reader_b'],true)) {
                return self::denied('untrusted_database_login');
            }
            $tenant=$identity['bound_tenant'];
            if ($tenant===null || $tenant==='') {
                return self::denied('database_login_revoked');
            }
            if (!is_string($tenant)
                || preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$tenant)!==1) {
                return self::denied('invalid_bound_tenant');
            }

            $rows=$db->query(
                'SELECT tenant_id,portal_service_ref,channel,campaign_ref,campaign_label,'
                .'lifecycle_state,account_ref,link_state '
                .'FROM public.perf_f93_demo_campaign_account_read ORDER BY campaign_ref'
            )->fetchAll(PDO::FETCH_ASSOC);
            if(!is_array($rows) || count($rows)>20) {
                return self::denied('invalid_campaign_projection');
            }
            $campaigns=[];$seen=[];
            foreach($rows as $row) {
                if(!is_array($row)
                    || array_keys($row)!==[
                        'tenant_id','portal_service_ref','channel',
                        'campaign_ref','campaign_label','lifecycle_state',
                        'account_ref','link_state'
                    ]
                    || $row['tenant_id']!==$tenant
                    || $row['portal_service_ref']!=='svc-demo-f88'
                    || !is_string($row['campaign_ref'])
                    || preg_match('/^cam-demo-[a-z0-9-]{1,70}$/D',$row['campaign_ref'])!==1
                    || !is_string($row['campaign_label'])
                    || preg_match('/^[A-Za-z0-9 ()-]{1,120}$/D',$row['campaign_label'])!==1
                    || !is_string($row['account_ref'])
                    || preg_match('/^demo_[a-z0-9][a-z0-9_-]{0,63}$/D',$row['account_ref'])!==1
                    || $row['link_state']!=='unverified_demo'
                    || !is_string($row['lifecycle_state'])
                    || !array_key_exists($row['lifecycle_state'],self::STATES)
                    || isset($seen[$row['campaign_ref']])) {
                    return self::denied('invalid_campaign_projection');
                }
                $portal=PortalPerformanceChannelContract::toPortal($row['channel']);
                if($portal===null) {
                    return self::denied('unsupported_channel');
                }
                $seen[$row['campaign_ref']]=true;
                $campaigns[]=[
                    'campaign_ref'=>$row['campaign_ref'],
                    'campaign_title'=>$row['campaign_label'],
                    'portal_channel'=>$portal,
                    'campaign_observation_state'=>self::STATES[$row['lifecycle_state']],
                    'account_ref'=>$row['account_ref'],
                    'account_state'=>'unverified_demo',
                    'portal_stage'=>null,
                ];
            }

            // Revoke or rebind committed during the database projection:
            // never emit the previously read campaign set.
            $after=$db->query($identitySql)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($after)
                || $after['dbname']!==$identity['dbname']
                || $after['db_login']!==$identity['db_login']
                || $after['bound_tenant']!==$tenant) {
                return self::denied('credential_changed_during_read');
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
                'campaigns'=>$campaigns,
            ];
        } catch(Throwable) {
            return self::denied('read_unavailable');
        }
    }
}
