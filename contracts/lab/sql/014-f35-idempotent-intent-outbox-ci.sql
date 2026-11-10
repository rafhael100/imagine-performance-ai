-- FAST-PERF F1-35: purely synthetic idempotent intent reservation, CI ONLY.
-- Database: throwaway portal_perf_auth_lab on 127.0.0.1:5432.
-- No payment, API, Ads dispatch, schedulers or genuine customer identifiers.
\set ON_ERROR_STOP on
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f35_intents;
REVOKE ALL ON SCHEMA perf_f35_intents FROM PUBLIC;

CREATE TABLE perf_f35_intents.requests (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_ref text NOT NULL,
    service_ref text NOT NULL,
    actor_ref text NOT NULL,
    request_ref text NOT NULL,
    permission text NOT NULL CHECK(permission='campaigns:read'),
    manifest_sha256 text NOT NULL CHECK(manifest_sha256 ~ '^[a-f0-9]{64}$'),
    state text NOT NULL DEFAULT 'pending_synthetic'
        CHECK(state='pending_synthetic'),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(tenant_ref,service_ref,request_ref)
);
REVOKE ALL ON perf_f35_intents.requests FROM PUBLIC;

CREATE TABLE perf_f35_intents.outbox (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    intent_id uuid NOT NULL UNIQUE
        REFERENCES perf_f35_intents.requests(id) ON DELETE RESTRICT,
    event_type text NOT NULL DEFAULT 'demo.intent.reserved'
        CHECK(event_type='demo.intent.reserved'),
    dispatch_enabled boolean NOT NULL DEFAULT false CHECK(dispatch_enabled=false),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f35_intents.outbox FROM PUBLIC;

-- One SQL statement per request; unique index serializes key conflicts.
-- No consumer/worker exists. Returning a string is NOT command authorization.
CREATE FUNCTION perf_f35_intents.reserve(
    p_tenant text,p_service text,p_actor text,
    p_request text,p_permission text,p_manifest text
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE created uuid;
        previous record;
BEGIN
    IF p_tenant IS DISTINCT FROM 'perf-demo-a'
       OR p_service IS DISTINCT FROM 'svc-demo-a'
       OR p_actor IS DISTINCT FROM 'actor-demo-a'
       OR p_permission IS DISTINCT FROM 'campaigns:read'
       OR p_request IS NULL OR p_request !~ '^[a-z0-9-]{8,48}$'
       OR p_manifest IS NULL OR p_manifest !~ '^[a-f0-9]{64}$'
    THEN
       RETURN 'invalid_scope_denied';
    END IF;

    INSERT INTO perf_f35_intents.requests(
        tenant_ref,service_ref,actor_ref,request_ref,permission,manifest_sha256)
    VALUES(p_tenant,p_service,p_actor,p_request,p_permission,p_manifest)
    ON CONFLICT (tenant_ref,service_ref,request_ref) DO NOTHING
    RETURNING id INTO created;

    IF created IS NULL THEN
        SELECT actor_ref,permission,manifest_sha256
        INTO previous FROM perf_f35_intents.requests
        WHERE tenant_ref=p_tenant AND service_ref=p_service AND request_ref=p_request;
        IF NOT FOUND THEN
            RETURN 'idempotency_conflict';
        END IF;
        IF previous.actor_ref=p_actor
           AND previous.permission=p_permission
           AND previous.manifest_sha256=p_manifest THEN
            RETURN 'already_reserved_synthetic';
        END IF;
        RETURN 'idempotency_conflict';
    END IF;

    INSERT INTO perf_f35_intents.outbox(intent_id)
    VALUES(created);
    RETURN 'reserved_synthetic';
END;
$$;
REVOKE ALL ON FUNCTION perf_f35_intents.reserve(
    text,text,text,text,text,text) FROM PUBLIC;
COMMIT;
