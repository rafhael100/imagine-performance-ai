<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-26: defense-in-depth for campaign intent SQL rows.
 *
 * No identity, membership, commercial entitlement or provider dispatch is
 * authenticated by this trigger. Operational release remains CLOSED.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-26 integrity guard requires PostgreSQL.');
        }

        DB::unprepared(<<<'SQL'
CREATE FUNCTION perf_f26_guard_intent_row()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'perf_intent_delete_blocked'
            USING ERRCODE = 'P0001';
    END IF;

    IF TG_OP = 'INSERT' THEN
        IF NEW.review_state <> 'awaiting_approval'
            OR NEW.reviewer_actor_ref IS NOT NULL
            OR NEW.dispatch_state <> 'disabled' THEN
            RAISE EXCEPTION 'perf_intent_must_start_pending'
                USING ERRCODE = 'P0001';
        END IF;
        RETURN NEW;
    END IF;

    -- Identity, tenant, service, request content, canonical manifest and
    -- original timestamp cannot be revised after an intent is created.
    IF ROW(
        NEW.id, NEW.tenant_id, NEW.campaign_id, NEW.portal_service_ref,
        NEW.operation, NEW.proposer_actor_ref, NEW.manifest_version,
        NEW.request_ref, NEW.payload_sha256, NEW.dispatch_state,
        NEW.created_at
    ) IS DISTINCT FROM ROW(
        OLD.id, OLD.tenant_id, OLD.campaign_id, OLD.portal_service_ref,
        OLD.operation, OLD.proposer_actor_ref, OLD.manifest_version,
        OLD.request_ref, OLD.payload_sha256, OLD.dispatch_state,
        OLD.created_at
    ) THEN
        RAISE EXCEPTION 'perf_intent_immutable_fields'
            USING ERRCODE = 'P0001';
    END IF;

    -- A decision is terminal. No rewrites, resubmission or re-opening.
    IF OLD.review_state <> 'awaiting_approval' THEN
        RAISE EXCEPTION 'perf_intent_terminal_state_immutable'
            USING ERRCODE = 'P0001';
    END IF;

    IF NEW.review_state NOT IN ('approved_internal', 'rejected') THEN
        RAISE EXCEPTION 'perf_intent_illegal_transition'
            USING ERRCODE = 'P0001';
    END IF;

    IF NEW.reviewer_actor_ref IS NULL
        OR NEW.reviewer_actor_ref = NEW.proposer_actor_ref THEN
        RAISE EXCEPTION 'perf_intent_independent_reviewer_required'
            USING ERRCODE = 'P0001';
    END IF;

    RETURN NEW;
END;
$function$;
SQL);

        DB::statement(
            'CREATE TRIGGER perf_f26_intent_row_guard '
            . 'BEFORE INSERT OR UPDATE OR DELETE '
            . 'ON performance_campaign_intents '
            . 'FOR EACH ROW EXECUTE FUNCTION perf_f26_guard_intent_row()'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-26 rollback requires PostgreSQL.');
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS perf_f26_intent_row_guard '
            . 'ON performance_campaign_intents'
        );
        DB::statement('DROP FUNCTION IF EXISTS perf_f26_guard_intent_row()');
    }
};
