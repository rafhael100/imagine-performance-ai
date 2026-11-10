<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-106: tenant-scoped, non-operational DEMO budget planning storage.
 * All records are synthetic, in centavos and basis points; no provider spend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F106 budget schema requires PostgreSQL');
        }

        Schema::create('performance_demo_budget_scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_service_ref', 128);
            $table->string('scenario_ref', 64);
            $table->string('currency', 3)->default('BRL');
            $table->date('start_date');
            $table->date('end_date');
            $table->bigInteger('total_centavos');
            $table->integer('google_ads_bp');
            $table->integer('meta_ads_bp');
            $table->string('source', 40)->default('offline_demo_fixture');
            $table->boolean('provider_connected')->default(false);
            $table->boolean('media_execution_allowed')->default(false);
            $table->boolean('billing_allowed')->default(false);
            $table->boolean('advertiser_approval_verified')->default(false);
            $table->timestampsTz();

            $table->foreign(
                ['tenant_id', 'portal_service_ref'],
                'perf_f106_budget_service_fk'
            )->references(['tenant_id', 'portal_service_ref'])
                ->on('portal_service_links')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'scenario_ref'],
                'perf_f106_budget_tenant_ref_uq'
            );
            $table->index(
                ['tenant_id', 'portal_service_ref', 'start_date'],
                'perf_f106_budget_tenant_start_idx'
            );
        });

        DB::statement(
            "ALTER TABLE public.performance_demo_budget_scenarios "
            ."ADD CONSTRAINT perf_f106_budget_preview_only_ck CHECK ("
            ."scenario_ref ~ '^scenario-demo-[a-z0-9-]{1,50}$'"
            ." AND currency = 'BRL'"
            ." AND total_centavos BETWEEN 1 AND 2000000000"
            ." AND google_ads_bp BETWEEN 0 AND 10000"
            ." AND meta_ads_bp BETWEEN 0 AND 10000"
            ." AND google_ads_bp + meta_ads_bp = 10000"
            ." AND end_date >= start_date"
            ." AND end_date - start_date BETWEEN 0 AND 365"
            ." AND source = 'offline_demo_fixture'"
            ." AND provider_connected = false"
            ." AND media_execution_allowed = false"
            ." AND billing_allowed = false"
            ." AND advertiser_approval_verified = false)"
        );
        // No policies and no grants on the base table: even accidentally
        // granted SELECT cannot return a row through FORCE RLS.
        DB::statement(
            'ALTER TABLE public.performance_demo_budget_scenarios ENABLE ROW LEVEL SECURITY'
        );
        DB::statement(
            'ALTER TABLE public.performance_demo_budget_scenarios FORCE ROW LEVEL SECURITY'
        );
        DB::statement(
            'REVOKE ALL ON TABLE public.performance_demo_budget_scenarios FROM PUBLIC'
        );

        // View ownership needs independent DBA review for production.
        // The explicit trusted PostgreSQL LOGIN predicate is critical.
        DB::unprepared(<<<'SQL'
CREATE VIEW public.perf_f106_credential_budget_demo_read
WITH (security_barrier = true)
AS
SELECT s.tenant_id,
       s.portal_service_ref,
       s.scenario_ref,
       s.currency,
       s.start_date,
       s.end_date,
       s.total_centavos,
       s.google_ads_bp,
       s.meta_ads_bp,
       s.source,
       s.provider_connected,
       s.media_execution_allowed,
       s.billing_allowed,
       s.advertiser_approval_verified
FROM public.performance_demo_budget_scenarios AS s
WHERE s.tenant_id = public.perf_f91_tenant_for_login()
  AND s.currency = 'BRL'
  AND s.source = 'offline_demo_fixture'
  AND s.provider_connected = false
  AND s.media_execution_allowed = false
  AND s.billing_allowed = false
  AND s.advertiser_approval_verified = false
SQL);
        DB::statement(
            'REVOKE ALL ON TABLE public.perf_f106_credential_budget_demo_read FROM PUBLIC'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F106 rollback requires PostgreSQL');
        }
        DB::statement('DROP VIEW IF EXISTS public.perf_f106_credential_budget_demo_read');
        Schema::dropIfExists('performance_demo_budget_scenarios');
    }
};
