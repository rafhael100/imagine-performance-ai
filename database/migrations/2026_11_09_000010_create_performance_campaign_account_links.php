<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAST-PERF F1-89 — structural relation of synthetic campaigns to
 * synthetic advertiser account candidates, scoped to same tenant/service/channel.
 * All upstream candidates and managed campaigns are hard-disabled for dispatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if(DB::connection()->getDriverName()!=='pgsql') {
            throw new RuntimeException('F89 campaign-account FK requires PostgreSQL.');
        }

        // These composite keys make cross-service, cross-channel and
        // cross-tenant references impossible at the database constraint layer.
        DB::statement('ALTER TABLE performance_managed_campaigns '
            .'ADD CONSTRAINT perf_f89_campaign_scope_channel_uq '
            .'UNIQUE (tenant_id,id,portal_service_ref,channel)');
        DB::statement('ALTER TABLE performance_ad_account_candidates '
            .'ADD CONSTRAINT perf_f89_account_scope_channel_uq '
            .'UNIQUE (tenant_id,id,portal_service_ref,channel)');

        Schema::create('performance_campaign_account_links', function(Blueprint $table):void{
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_service_ref',128);
            $table->string('channel',16);
            $table->uuid('campaign_id');
            $table->uuid('account_candidate_id');
            $table->string('link_state',32)->default('unverified_demo');
            $table->string('source',48)->default('synthetic_sql_fixture');
            $table->boolean('dispatch_allowed')->default(false);
            $table->timestampsTz();
            $table->foreign(
                ['tenant_id','campaign_id','portal_service_ref','channel'],
                'perf_f89_link_campaign_scope_fk'
            )->references(
                ['tenant_id','id','portal_service_ref','channel']
            )->on('performance_managed_campaigns')->restrictOnDelete();
            $table->foreign(
                ['tenant_id','account_candidate_id','portal_service_ref','channel'],
                'perf_f89_link_account_scope_fk'
            )->references(
                ['tenant_id','id','portal_service_ref','channel']
            )->on('performance_ad_account_candidates')->restrictOnDelete();
            $table->unique(
                ['tenant_id','campaign_id'],
                'perf_f89_unique_campaign_account_link'
            );
            $table->index(
                ['tenant_id','portal_service_ref','account_candidate_id'],
                'perf_f89_candidate_scope_idx'
            );
        });
        DB::statement("ALTER TABLE performance_campaign_account_links "
            ."ADD CONSTRAINT perf_f89_demo_only_ck CHECK "
            ."(link_state='unverified_demo' AND source='synthetic_sql_fixture' "
            ."AND dispatch_allowed=false)");
        DB::statement('ALTER TABLE performance_campaign_account_links ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE performance_campaign_account_links FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY perf_f89_tenant_boundary '
            .'ON performance_campaign_account_links FOR ALL '
            ."USING (tenant_id=NULLIF(current_setting('app.performance_tenant_id',true),'')::uuid) "
            ."WITH CHECK (tenant_id=NULLIF(current_setting('app.performance_tenant_id',true),'')::uuid)"
        );
    }

    public function down(): void
    {
        if(DB::connection()->getDriverName()!=='pgsql') {
            throw new RuntimeException('F89 rollback requires PostgreSQL.');
        }
        Schema::dropIfExists('performance_campaign_account_links');
        DB::statement('ALTER TABLE performance_ad_account_candidates '
            .'DROP CONSTRAINT IF EXISTS perf_f89_account_scope_channel_uq');
        DB::statement('ALTER TABLE performance_managed_campaigns '
            .'DROP CONSTRAINT IF EXISTS perf_f89_campaign_scope_channel_uq');
    }
};
