<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-27: database-owned decision journal for laboratory approvals.
 * The trigger captures a SQL transition; it does not prove who a human
 * reviewer really is. No operational authorization has been granted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-27 decision journal requires PostgreSQL.');
        }

        Schema::create('performance_intent_review_journal', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('intent_id');
            $table->uuid('tenant_id');
            $table->string('portal_service_ref', 128);
            $table->string('request_ref', 128);
            $table->string('review_state', 32);
            $table->string('reviewer_actor_ref', 128);
            $table->string('manifest_version', 64);
            $table->char('payload_sha256', 64);
            $table->string('source', 48)->default('synthetic_postgresql_only');
            $table->boolean('synthetic_only')->default(true);
            $table->timestampTz('recorded_at')->useCurrent();

            $table->foreign('intent_id', 'perf_f27_journal_intent_fk')
                ->references('id')->on('performance_campaign_intents')
                ->restrictOnDelete();
            $table->unique('intent_id', 'perf_f27_one_terminal_review_per_intent');
            $table->index(['tenant_id', 'recorded_at'], 'perf_f27_journal_tenant_time_idx');
        });
        DB::statement(
            "ALTER TABLE performance_intent_review_journal ADD CONSTRAINT "
            ."perf_f27_journal_state_ck CHECK "
            ."(review_state IN ('approved_internal', 'rejected'))"
        );
        DB::statement(
            "ALTER TABLE performance_intent_review_journal ADD CONSTRAINT "
            ."perf_f27_journal_source_ck CHECK "
            ."(source='synthetic_postgresql_only' AND synthetic_only=true)"
        );

        DB::statement(
            'ALTER TABLE performance_intent_review_journal ENABLE ROW LEVEL SECURITY'
        );
        DB::statement(
            'ALTER TABLE performance_intent_review_journal FORCE ROW LEVEL SECURITY'
        );
        DB::statement(
            'CREATE POLICY perf_f27_journal_tenant_read '
            .'ON performance_intent_review_journal FOR SELECT '
            ."USING (tenant_id = NULLIF("
            ."current_setting('app.performance_tenant_id', true), '')::uuid)"
        );

        // No insert grant to application roles. This small, audited trigger
        // helper has a fixed query and qualified table; it does NOT decide
        // authorization. SECURITY DEFINER needs AppSec/DBA review before
        // production and must not be an external API.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION perf_f27_capture_review_transition()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $function$
BEGIN
    IF OLD.review_state <> 'awaiting_approval'
        OR NEW.review_state NOT IN ('approved_internal', 'rejected')
        OR NEW.reviewer_actor_ref IS NULL
        OR NEW.reviewer_actor_ref = NEW.proposer_actor_ref THEN
        RAISE EXCEPTION 'perf_f27_invalid_review_capture'
            USING ERRCODE='P0001';
    END IF;

    INSERT INTO public.performance_intent_review_journal (
        id, intent_id, tenant_id, portal_service_ref, request_ref,
        review_state, reviewer_actor_ref, manifest_version, payload_sha256,
        source, synthetic_only, recorded_at
    )
    VALUES (
        gen_random_uuid(), NEW.id, NEW.tenant_id,
        NEW.portal_service_ref, NEW.request_ref, NEW.review_state,
        NEW.reviewer_actor_ref, NEW.manifest_version, NEW.payload_sha256,
        'synthetic_postgresql_only', true, clock_timestamp()
    );

    RETURN NEW;
END;
$function$;
SQL);

        DB::statement(
            'REVOKE EXECUTE ON FUNCTION perf_f27_capture_review_transition() FROM PUBLIC'
        );
        DB::statement(
            'CREATE TRIGGER perf_f27_after_review_transition '
            . 'AFTER UPDATE OF review_state ON performance_campaign_intents '
            . 'FOR EACH ROW '
            . 'WHEN (OLD.review_state IS DISTINCT FROM NEW.review_state) '
            . 'EXECUTE FUNCTION perf_f27_capture_review_transition()'
        );

        DB::unprepared(<<<'SQL'
CREATE FUNCTION perf_f27_forbid_journal_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION 'perf_f27_journal_is_append_only'
        USING ERRCODE='P0001';
END;
$function$;
SQL);
        DB::statement(
            'CREATE TRIGGER perf_f27_journal_append_only '
            . 'BEFORE UPDATE OR DELETE ON performance_intent_review_journal '
            . 'FOR EACH ROW EXECUTE FUNCTION perf_f27_forbid_journal_mutation()'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-27 journal rollback requires PostgreSQL.');
        }
        DB::statement(
            'DROP TRIGGER IF EXISTS perf_f27_after_review_transition '
            .'ON performance_campaign_intents'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS perf_f27_capture_review_transition()'
        );
        DB::statement(
            'DROP TRIGGER IF EXISTS perf_f27_journal_append_only '
            .'ON performance_intent_review_journal'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS perf_f27_forbid_journal_mutation()'
        );
        Schema::dropIfExists('performance_intent_review_journal');
    }
};
