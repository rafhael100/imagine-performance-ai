<?php
declare(strict_types=1);

namespace App\Performance\ReadSide;

use PDO;
use Throwable;

/**
 * F1-100. One coherent, read-only, CI-only snapshot from the F91/F93/F97
 * credential-bound views. Explicitly replaces F28 for this new DEMO path.
 *
 * This is NOT Portal authentication, external approval or an HTTP handler.
 */
final class CredentialBoundDemoWorkbenchReader
{
    private const SCHEMA='perf.portal.credential-bound-workbench.synthetic.v1';
    private const FIELDS=[
        'portal_identity_authenticated','authorization_eligible',
        'ready_for_production','dispatch_enabled','billing_enabled'
    ];

    private static function denied(string $status):array
    {
        return [
            'schema_version'=>self::SCHEMA,
            'status'=>$status,
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

    public function read(PDO $db):array
    {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
            || getenv('PGHOST')!=='127.0.0.1'
            || getenv('PGPORT')!=='5432'
            || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
            return self::denied('lab_disabled');
        }
        try {
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                || $db->inTransaction()) {
                return self::denied('invalid_connection_state');
            }
            // READ COMMITTED deliberately rechecks revocation after all views.
            // Does not claim atomic entitlement or race-free operational auth.
            $db->beginTransaction();
            $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
            $identityQuery='SELECT current_database() AS dbname, '
                .'session_user::text AS db_login, '
                .'public.perf_f91_tenant_for_login()::text AS bound_tenant';
            $identity=$db->query($identityQuery)->fetch(PDO::FETCH_ASSOC);
            if(!is_array($identity)
                || $identity['dbname']!=='perf_test_campaign_registry'
                || !in_array($identity['db_login'],[
                    'perf_f91_reader_a','perf_f91_reader_b'
                ],true)) {
                $db->rollBack();
                return self::denied('untrusted_database_login');
            }
            $tenant=$identity['bound_tenant'];
            if($tenant===null || $tenant==='') {
                $db->rollBack();
                return self::denied('database_login_revoked');
            }
            if(!is_string($tenant)
                || preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$tenant)!==1) {
                $db->rollBack();
                return self::denied('invalid_database_binding');
            }

            $accounts=(new CredentialBoundDemoAccountReader())->read($db);
            $campaigns=(new CredentialBoundDemoCampaignReader())->read($db);
            $reviews=(new CredentialBoundDemoReviewReader())->read($db);
            foreach([$accounts,$campaigns,$reviews] as $piece) {
                if(!is_array($piece) || $piece['credential_binding_confirmed']!==true
                    || $piece['simulation_only']!==true
                    || !in_array($piece['status'],[
                        'credential_scoped_demo_preview_only',
                        'credential_scoped_demo_review_preview_only'
                    ],true)) {
                    $db->rollBack();
                    return self::denied('component_read_denied');
                }
                foreach(self::FIELDS as $flag) {
                    if(($piece[$flag]??null)!==false) {
                        $db->rollBack();
                        return self::denied('unsafe_component_flags');
                    }
                }
            }
            if(($reviews['real_human_approval_verified']??null)!==false) {
                $db->rollBack();
                return self::denied('unsafe_review_authority');
            }
            if(!is_array($accounts['accounts']) || !is_array($campaigns['campaigns'])
                || !is_array($reviews['reviews'])
                || count($accounts['accounts'])>20
                || count($campaigns['campaigns'])>20
                || count($reviews['reviews'])>20) {
                $db->rollBack();
                return self::denied('invalid_component_shape');
            }

            // Validate cross-view relationships: no campaign can refer to an
            // account missing from the credential-bound account projection,
            // and no review can refer to a campaign outside the same preview.
            $knownAccounts=[];
            foreach($accounts['accounts'] as $a) {
                if(!is_array($a) || array_keys($a)!==[
                    'portal_channel','account_ref','connection_state'
                ] || !in_array($a['portal_channel'],['google_ads','meta_ads'],true)
                || !is_string($a['account_ref'])
                || $a['connection_state']!=='unverified_demo') {
                    $db->rollBack();
                    return self::denied('invalid_account_shape');
                }
                $key=$a['portal_channel'].':'.$a['account_ref'];
                if(isset($knownAccounts[$key])) {
                    $db->rollBack();
                    return self::denied('duplicate_account');
                }
                $knownAccounts[$key]=true;
            }
            $knownCampaigns=[];
            foreach($campaigns['campaigns'] as $c) {
                if(!is_array($c)||array_keys($c)!==[
                    'campaign_ref','campaign_title','portal_channel',
                    'campaign_observation_state','account_ref','account_state','portal_stage'
                ] || !is_string($c['campaign_ref'])
                || !is_string($c['campaign_title'])
                || !in_array($c['portal_channel'],['google_ads','meta_ads'],true)
                || !in_array($c['campaign_observation_state'],[
                    'draft_demo','paused_demo','archived_demo'
                ],true)
                || $c['account_state']!=='unverified_demo'
                || $c['portal_stage']!==null
                || !isset($knownAccounts[$c['portal_channel'].':'.$c['account_ref']])
                || isset($knownCampaigns[$c['campaign_ref']])) {
                    $db->rollBack();
                    return self::denied('unmatched_campaign_account');
                }
                $knownCampaigns[$c['campaign_ref']]=$c['portal_channel'];
            }
            $seenRequests=[];
            foreach($reviews['reviews'] as $r) {
                if(!is_array($r)||array_keys($r)!==[
                    'campaign_ref','request_ref','operation','portal_channel',
                    'review_observation_state','journaled','portal_stage'
                ] || !is_string($r['request_ref'])
                || !isset($knownCampaigns[$r['campaign_ref']])
                || $knownCampaigns[$r['campaign_ref']]!==$r['portal_channel']
                || !in_array($r['operation'],[
                    'campaign.create','campaign.pause',
                    'campaign.replace','campaign.release'
                ],true)
                || !in_array($r['review_observation_state'],[
                    'pending_human_demo','approved_demo_no_execution','rejected_demo'
                ],true)
                || $r['journaled']!==($r['review_observation_state']!=='pending_human_demo')
                || $r['portal_stage']!==null
                || isset($seenRequests[$r['request_ref']])) {
                    $db->rollBack();
                    return self::denied('unmatched_review_campaign');
                }
                $seenRequests[$r['request_ref']]=true;
            }

            // F177: F100 assembles three separately snapshotted READ
            // COMMITTED views. A campaign may change or be revoked after the
            // first campaign SELECT but before review composition; the old
            // checks only compared SQL login identity. Re-run the exact
            // credential-bound component readers while still READ ONLY.
            // Any visible account/campaign/review mutation must deny the
            // whole mixed workbench instead of exposing stale associations.
            $againAccounts=(new CredentialBoundDemoAccountReader())->read($db);
            $againCampaigns=(new CredentialBoundDemoCampaignReader())->read($db);
            $againReviews=(new CredentialBoundDemoReviewReader())->read($db);
            if(($againAccounts['status']??null)!=='credential_scoped_demo_preview_only'
                ||($againCampaigns['status']??null)!=='credential_scoped_demo_preview_only'
                ||($againReviews['status']??null)!=='credential_scoped_demo_review_preview_only'
                ||($againAccounts['credential_binding_confirmed']??null)!==true
                ||($againCampaigns['credential_binding_confirmed']??null)!==true
                ||($againReviews['credential_binding_confirmed']??null)!==true
                ||($againAccounts['accounts']??null)!==$accounts['accounts']
                ||($againCampaigns['campaigns']??null)!==$campaigns['campaigns']
                ||($againReviews['reviews']??null)!==$reviews['reviews']) {
                $db->rollBack();
                return self::denied('components_changed_during_composition');
            }

            $verify=$db->query($identityQuery)->fetch(PDO::FETCH_ASSOC);
            if(!is_array($verify)
                || $verify['dbname']!==$identity['dbname']
                || $verify['db_login']!==$identity['db_login']
                || $verify['bound_tenant']!==$tenant) {
                $db->rollBack();
                return self::denied('credential_changed_during_read');
            }
            $db->commit();
            $statusCounts=[
                'review_pending'=>0,
                'review_internal_no_execution'=>0,
                'review_rejected'=>0,
            ];
            foreach($reviews['reviews'] as $r) {
                $key=match($r['review_observation_state']) {
                    'pending_human_demo'=>'review_pending',
                    'approved_demo_no_execution'=>'review_internal_no_execution',
                    'rejected_demo'=>'review_rejected',
                };
                ++$statusCounts[$key];
            }
            return [
                'schema_version'=>self::SCHEMA,
                'status'=>'credential_scoped_demo_workbench_only',
                'synthetic_only'=>true,
                'read_only'=>true,
                'credential_binding_confirmed'=>true,
                'portal_identity_authenticated'=>false,
                'real_human_approval_verified'=>false,
                'provider_connected'=>false,
                'authorization_eligible'=>false,
                'ready_for_production'=>false,
                'dispatch_enabled'=>false,
                'billing_enabled'=>false,
                'counts'=>[
                    'account_candidates'=>count($accounts['accounts']),
                    'linked_campaigns'=>count($campaigns['campaigns']),
                    'visible_reviews'=>count($reviews['reviews']),
                    ...$statusCounts,
                ],
                'accounts'=>$accounts['accounts'],
                'campaigns'=>$campaigns['campaigns'],
                'reviews'=>$reviews['reviews'],
            ];
        }catch(Throwable) {
            if($db->inTransaction())$db->rollBack();
            return self::denied('read_unavailable');
        }
    }
}
