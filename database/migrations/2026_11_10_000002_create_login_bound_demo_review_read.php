<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F1-97: credential-bound, read-only DEMO approval projection.
 * Does not infer an authenticated human review from a synthetic SQL journal.
 * No public endpoints, Ads dispatch, S2S credentials or billing.
 */
return new class extends Migration
{
    public function up():void
    {
        if(DB::connection()->getDriverName()!=='pgsql') {
            throw new RuntimeException('F97 review view requires PostgreSQL');
        }
        DB::unprepared(<<<'SQL'
CREATE VIEW public.perf_f97_demo_review_read
WITH (security_barrier = true)
AS
SELECT i.tenant_id,
       i.portal_service_ref,
       c.channel,
       c.campaign_ref,
       i.request_ref,
       i.operation,
       i.review_state,
       i.dispatch_state,
       j.review_state AS journal_review_state,
       (j.id IS NOT NULL) AS journaled
FROM public.performance_campaign_intents i
JOIN public.performance_managed_campaigns c
  ON c.id = i.campaign_id
 AND c.tenant_id = i.tenant_id
 AND c.portal_service_ref = i.portal_service_ref
LEFT JOIN public.performance_intent_review_journal j
  ON j.intent_id = i.id
 AND j.tenant_id = i.tenant_id
 AND j.portal_service_ref = i.portal_service_ref
 AND j.request_ref = i.request_ref
 AND j.review_state = i.review_state
 AND j.source = 'synthetic_postgresql_only'
 AND j.synthetic_only = true
WHERE i.tenant_id = public.perf_f91_tenant_for_login()
  AND c.tenant_id = public.perf_f91_tenant_for_login()
  AND i.portal_service_ref = 'svc-demo-f88'
  AND c.portal_service_ref = 'svc-demo-f88'
  AND c.channel IN ('google_ads','meta')
  AND c.dispatch_state = 'disabled'
  AND i.dispatch_state = 'disabled'
  AND i.manifest_version = 'manifest-demo-v1'
  AND (
    (i.review_state = 'awaiting_approval'
       AND i.reviewer_actor_ref IS NULL
       AND j.id IS NULL)
    OR
    (i.review_state IN ('approved_internal','rejected')
       AND i.reviewer_actor_ref IS NOT NULL
       AND i.reviewer_actor_ref <> i.proposer_actor_ref
       AND j.id IS NOT NULL)
  )
SQL);
        DB::statement('REVOKE ALL ON TABLE public.perf_f97_demo_review_read FROM PUBLIC');
    }

    public function down():void
    {
        if(DB::connection()->getDriverName()!=='pgsql') {
            throw new RuntimeException('F97 rollback requires PostgreSQL');
        }
        DB::statement('DROP VIEW IF EXISTS public.perf_f97_demo_review_read');
    }
};
