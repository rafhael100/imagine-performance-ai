<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAST-PERF F1-88: registry of unverified DEMO advertiser account references.
 * This is not OAuth enrollment. Never store tokens, real account IDs or billing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-88 advertiser candidate registry requires PostgreSQL.');
        }
        Schema::create('performance_ad_account_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_service_ref', 128);
            $table->string('channel', 16);
            $table->string('account_ref', 80);
            $table->string('connection_state', 32)->default('unverified_demo');
            $table->string('source', 48)->default('portal_demo_fixture');
            $table->boolean('provider_connected')->default(false);
            $table->boolean('dispatch_allowed')->default(false);
            $table->boolean('billable')->default(false);
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'portal_service_ref'],'perf_f88_account_service_fk')
                ->references(['tenant_id', 'portal_service_ref'])
                ->on('portal_service_links')->restrictOnDelete();
            $table->unique(
                ['tenant_id','portal_service_ref','channel','account_ref'],
                'perf_f88_tenant_service_channel_account_uq'
            );
            $table->index(['tenant_id','portal_service_ref','channel'],
                'perf_f88_account_scope_idx');
        });

        DB::statement(
            "ALTER TABLE performance_ad_account_candidates ADD CONSTRAINT "
            ."perf_f88_channel_ck CHECK (channel IN ('google_ads','meta'))"
        );
        DB::statement(
            "ALTER TABLE performance_ad_account_candidates ADD CONSTRAINT "
            ."perf_f88_reference_ck CHECK "
            ."(account_ref ~ '^demo_[a-z0-9][a-z0-9_-]{0,63}$')"
        );
        DB::statement(
            "ALTER TABLE performance_ad_account_candidates ADD CONSTRAINT "
            ."perf_f88_no_external_connection_ck CHECK "
            ."(connection_state = 'unverified_demo' "
            ."AND source = 'portal_demo_fixture' "
            ."AND provider_connected = false "
            ."AND dispatch_allowed = false AND billable = false)"
        );
        DB::statement('ALTER TABLE performance_ad_account_candidates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE performance_ad_account_candidates FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY perf_f88_tenant_boundary '
            .'ON performance_ad_account_candidates FOR ALL '
            ."USING (tenant_id = NULLIF(current_setting('app.performance_tenant_id', true),'')::uuid) "
            ."WITH CHECK (tenant_id = NULLIF(current_setting('app.performance_tenant_id', true),'')::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-88 rollback requires PostgreSQL.');
        }
        Schema::dropIfExists('performance_ad_account_candidates');
    }
};
