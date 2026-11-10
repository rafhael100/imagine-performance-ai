<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAST-PERF F1-22: storage-only campaigns/intents, no Ads, PSP or routes.
 * The DB check dispatch_state='disabled' prevents any provider execution.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-22 campaign schema requires PostgreSQL.');
        }

        Schema::create('performance_managed_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_service_ref', 128);
            $table->string('channel', 16);
            $table->string('campaign_ref', 128);
            $table->string('campaign_label', 160);
            $table->string('lifecycle_state', 32)->default('draft');
            $table->boolean('managed')->default(true);
            $table->string('dispatch_state', 32)->default('disabled');
            $table->timestampsTz();

            $table->foreign(
                ['tenant_id', 'portal_service_ref'],
                'perf_campaign_service_link_fk'
            )->references(['tenant_id', 'portal_service_ref'])
                ->on('portal_service_links')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'id', 'portal_service_ref'],
                'perf_campaign_tenant_id_service_uq'
            );
            $table->unique(
                ['tenant_id', 'portal_service_ref', 'channel', 'campaign_ref'],
                'perf_campaign_natural_uq'
            );
            $table->index(
                ['tenant_id', 'portal_service_ref', 'managed', 'lifecycle_state'],
                'perf_campaign_managed_idx'
            );
        });

        Schema::create('performance_campaign_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('campaign_id');
            $table->string('portal_service_ref', 128);
            $table->string('operation', 32);
            $table->string('review_state', 32)->default('awaiting_approval');
            $table->string('proposer_actor_ref', 128);
            $table->string('reviewer_actor_ref', 128)->nullable();
            $table->string('manifest_version', 64);
            $table->string('request_ref', 128);
            $table->char('payload_sha256', 64);
            $table->string('dispatch_state', 32)->default('disabled');
            $table->timestampsTz();

            $table->foreign(
                ['tenant_id', 'campaign_id', 'portal_service_ref'],
                'perf_intent_campaign_scope_fk'
            )->references(['tenant_id', 'id', 'portal_service_ref'])
                ->on('performance_managed_campaigns')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'request_ref'],
                'perf_intent_idempotency_uq'
            );
            $table->index(
                ['tenant_id', 'campaign_id', 'review_state'],
                'perf_intent_pending_idx'
            );
        });

        DB::statement(
            "ALTER TABLE performance_managed_campaigns ADD CONSTRAINT "
            . "perf_campaign_channel_ck CHECK (channel IN ('google_ads','meta'))"
        );
        DB::statement(
            "ALTER TABLE performance_managed_campaigns ADD CONSTRAINT "
            . "perf_campaign_lifecycle_ck CHECK (lifecycle_state IN ('draft','paused','archived'))"
        );
        DB::statement(
            "ALTER TABLE performance_managed_campaigns ADD CONSTRAINT "
            . "perf_campaign_delivery_ck CHECK (dispatch_state='disabled')"
        );
        DB::statement(
            "ALTER TABLE performance_campaign_intents ADD CONSTRAINT "
            . "perf_intent_operation_ck CHECK (operation IN "
            . "('campaign.create','campaign.pause','campaign.replace','campaign.release'))"
        );
        DB::statement(
            "ALTER TABLE performance_campaign_intents ADD CONSTRAINT "
            . "perf_intent_review_ck CHECK (review_state IN "
            . "('awaiting_approval','approved_internal','rejected'))"
        );
        DB::statement(
            "ALTER TABLE performance_campaign_intents ADD CONSTRAINT "
            . "perf_intent_delivery_ck CHECK (dispatch_state='disabled')"
        );
        DB::statement(
            "ALTER TABLE performance_campaign_intents ADD CONSTRAINT "
            . "perf_intent_independent_approval_ck CHECK "
            . "(reviewer_actor_ref IS NULL OR reviewer_actor_ref <> proposer_actor_ref)"
        );
        DB::statement(
            "ALTER TABLE performance_campaign_intents ADD CONSTRAINT "
            . "perf_intent_approval_actor_ck CHECK "
            . "(review_state <> 'approved_internal' OR reviewer_actor_ref IS NOT NULL)"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-22 rollback requires PostgreSQL.');
        }
        Schema::dropIfExists('performance_campaign_intents');
        Schema::dropIfExists('performance_managed_campaigns');
    }
};
