<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use App\Performance\Contracts\PortalPerformanceChannelContract;
use PDO;
use Throwable;

/**
 * FAST-PERF F1-98: read-only Laravel-autoloadable projection for F97.
 * The PDO session authenticates the tenant via PostgreSQL session_user;
 * no tenant/ref comes from HTTP or from a caller parameter.
 * NOT a human approval or authorization service.
 */
final class CredentialBoundDemoReviewReader
{
    private const SCHEMA='perf.portal.credential-bound-reviews.synthetic.v1';

    private const STATES=[
        'awaiting_approval'=>'pending_human_demo',
        'approved_internal'=>'approved_demo_no_execution',
        'rejected'=>'rejected_demo',
    ];

    private static function denied(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
            'simulation_only'=>true,
            'credential_binding_confirmed'=>false,
            'portal_identity_authenticated'=>false,
            'real_human_approval_verified'=>false,
            'authorization_eligible'=>false,
            'provider_connected'=>false,
            'ready_for_production'=>false,
            'dispatch_enabled'=>false,
            'billing_enabled'=>false,
            'reviews'=>[],
        ];
    }

    public function read(PDO $db):array
    {
        if (PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
                return self::denied('unsupported_driver');
            }
            // Workbench F100 composes a bounded READ COMMITTED / READ ONLY
            // transaction. Disallow other transactions that can preserve
            // stale RLS snapshots or include writes.
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
            if (!is_array($identity)
                || $identity['dbname']!=='perf_test_campaign_registry'
                || !in_array($identity['db_login'],
                    ['perf_f91_reader_a','perf_f91_reader_b'],true)) {
                return self::denied('untrusted_database_login');
            }
            $tenant=$identity['bound_tenant'];
            if($tenant===null || $tenant==='') {
                return self::denied('database_login_revoked');
            }
            if(!is_string($tenant)
                || preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$tenant)!==1) {
                return self::denied('invalid_database_tenant');
            }
            $rows=$db->query(
                'SELECT tenant_id,portal_service_ref,channel,campaign_ref,request_ref,'
                .'operation,review_state,dispatch_state,journal_review_state,journaled '
                .'FROM public.perf_f97_demo_review_read ORDER BY request_ref'
            )->fetchAll(PDO::FETCH_ASSOC);
            if(!is_array($rows)||count($rows)>20) {
                return self::denied('invalid_review_projection');
            }
            $reviews=[];$seen=[];
            foreach($rows as $r) {
                if(!is_array($r)
                    || array_keys($r)!==[
                        'tenant_id','portal_service_ref','channel','campaign_ref',
                        'request_ref','operation','review_state',
                        'dispatch_state','journal_review_state','journaled'
                    ]
                    || $r['tenant_id']!==$tenant
                    || $r['portal_service_ref']!=='svc-demo-f88'
                    || !is_string($r['campaign_ref'])
                    || preg_match('/^cam-demo-[a-z0-9-]{1,70}$/D',$r['campaign_ref'])!==1
                    || !is_string($r['request_ref'])
                    || preg_match('/^req-demo-[a-z0-9-]{1,70}$/D',$r['request_ref'])!==1
                    || !in_array($r['operation'],[
                        'campaign.create','campaign.pause',
                        'campaign.replace','campaign.release'
                    ],true)
                    || $r['dispatch_state']!=='disabled'
                    || !is_string($r['review_state'])
                    || !array_key_exists($r['review_state'],self::STATES)
                    || isset($seen[$r['request_ref']])) {
                    return self::denied('invalid_review_projection');
                }
                $isFinal=$r['review_state']!=='awaiting_approval';
                $journaled=in_array($r['journaled'],[true,'t','1',1],true);
                if($journaled!==$isFinal
                    || ($isFinal && $r['journal_review_state']!==$r['review_state'])
                    || (!$isFinal && $r['journal_review_state']!==null)) {
                    return self::denied('inconsistent_review_journal');
                }
                $channel=PortalPerformanceChannelContract::toPortal($r['channel']);
                if($channel===null) {
                    return self::denied('unsupported_channel');
                }
                $seen[$r['request_ref']]=true;
                $reviews[]=[
                    'campaign_ref'=>$r['campaign_ref'],
                    'request_ref'=>$r['request_ref'],
                    'operation'=>$r['operation'],
                    'portal_channel'=>$channel,
                    'review_observation_state'=>self::STATES[$r['review_state']],
                    'journaled'=>$journaled,
                    'portal_stage'=>null,
                ];
            }

            // Synthetic review states do not authorize a real campaign.
            // Revalidate principal/tenant after SELECT, including journals,
            // before producing any read-model response.
            $after=$db->query($identitySql)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($after)
                || $after['dbname']!==$identity['dbname']
                || $after['db_login']!==$identity['db_login']
                || $after['bound_tenant']!==$tenant) {
                return self::denied('credential_changed_during_read');
            }

            return [
                'schema_version'=>self::SCHEMA,
                'status'=>'credential_scoped_demo_review_preview_only',
                'simulation_only'=>true,
                'credential_binding_confirmed'=>true,
                'portal_identity_authenticated'=>false,
                'real_human_approval_verified'=>false,
                'authorization_eligible'=>false,
                'provider_connected'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
                'reviews'=>$reviews,
            ];
        }catch(Throwable) {
            return self::denied('read_unavailable');
        }
    }
}
