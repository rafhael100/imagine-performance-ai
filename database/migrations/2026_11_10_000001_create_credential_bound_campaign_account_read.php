<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F1-93: a single, read-only DEMO campaign/account link view
 * bound to the PostgreSQL-authenticated login, not user-supplied GUC.
 *
 * Still a lab path. Owner executes the view with elevated table rights;
 * the explicit login-bound tenant filter is therefore mandatory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F93 tenant-bound view requires PostgreSQL');
        }
        DB::unprepared(<<<'SQL'
CREATE VIEW public.perf_f93_demo_campaign_account_read
WITH (security_barrier = true)
AS SELECT c.tenant_id,
          c.portal_service_ref,
          c.channel,
          c.campaign_ref,
          c.campaign_label,
          c.lifecycle_state,
          a.account_ref,
          l.link_state
FROM public.performance_campaign_account_links AS l
JOIN public.performance_managed_campaigns AS c
  ON c.id = l.campaign_id
 AND c.tenant_id = l.tenant_id
 AND c.portal_service_ref = l.portal_service_ref
 AND c.channel = l.channel
JOIN public.performance_ad_account_candidates AS a
  ON a.id = l.account_candidate_id
 AND a.tenant_id = l.tenant_id
 AND a.portal_service_ref = l.portal_service_ref
 AND a.channel = l.channel
WHERE l.tenant_id = public.perf_f91_tenant_for_login()
  AND c.tenant_id = public.perf_f91_tenant_for_login()
  AND a.tenant_id = public.perf_f91_tenant_for_login()
  AND c.dispatch_state = 'disabled'
  AND a.connection_state = 'unverified_demo'
  AND a.provider_connected = false
  AND a.dispatch_allowed = false
  AND a.billable = false
  AND l.link_state = 'unverified_demo'
  AND l.dispatch_allowed = false
  AND c.channel IN ('google_ads','meta')
  AND a.account_ref ~ '^demo_[a-z0-9][a-z0-9_-]{0,63}$'
SQL);
        DB::statement('REVOKE ALL ON TABLE public.perf_f93_demo_campaign_account_read FROM PUBLIC');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F93 rollback requires PostgreSQL');
        }
        DB::statement('DROP VIEW IF EXISTS public.perf_f93_demo_campaign_account_read');
    }
};
