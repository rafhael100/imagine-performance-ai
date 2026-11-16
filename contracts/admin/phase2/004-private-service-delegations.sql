-- Additive candidate, owner migration on an isolated database only. No operational seeds.
CREATE TABLE performance_admin_inbox.service_delegations (
 database_login name NOT NULL, issuer text NOT NULL, key_id text NOT NULL,
 certificate_sha256 text NOT NULL CHECK(certificate_sha256 ~ '^[a-f0-9]{64}$'),
 company_id text NOT NULL, operation text NOT NULL CHECK(operation IN ('dashboard.read','clients.read','reports.read')),
 revision bigint NOT NULL CHECK(revision>0), active boolean NOT NULL DEFAULT false,
 expires_at timestamptz NOT NULL, revoked_at timestamptz,
 PRIMARY KEY(database_login,issuer,key_id,certificate_sha256,company_id,operation)
);
REVOKE ALL ON performance_admin_inbox.service_delegations FROM PUBLIC;
CREATE FUNCTION performance_admin_inbox.resolve_delegation(service text,key_ref text,cert text,company text,op text)
 RETURNS TABLE(revision bigint,expires_epoch bigint) LANGUAGE sql STABLE SECURITY DEFINER
 SET search_path=pg_catalog,performance_admin_inbox AS $$
 SELECT d.revision,extract(epoch FROM d.expires_at)::bigint FROM performance_admin_inbox.service_delegations d
 WHERE d.database_login=session_user AND d.issuer=service AND d.key_id=key_ref AND d.certificate_sha256=cert
 AND d.company_id=company AND d.operation=op AND d.active AND d.revoked_at IS NULL AND d.expires_at>statement_timestamp()
 AND performance_admin_inbox.can_scope(service,company)
$$;
REVOKE ALL ON FUNCTION performance_admin_inbox.resolve_delegation(text,text,text,text,text) FROM PUBLIC;
CREATE TRIGGER service_delegation_change AFTER INSERT OR UPDATE OR DELETE ON performance_admin_inbox.service_delegations
 FOR EACH ROW EXECUTE FUNCTION performance_admin_inbox.audit_principal();
CREATE TABLE performance_admin_inbox.request_audit (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, issuer text NOT NULL, company_id text NOT NULL,
 operation text NOT NULL, correlation_id text NOT NULL, outcome text NOT NULL CHECK(outcome IN ('read_attempt','read_result')),
 at timestamptz NOT NULL DEFAULT clock_timestamp()
);
ALTER TABLE performance_admin_inbox.request_audit ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_inbox.request_audit FORCE ROW LEVEL SECURITY;
CREATE POLICY private_audit_scope ON performance_admin_inbox.request_audit
 USING(performance_admin_inbox.can_scope(issuer,company_id)) WITH CHECK(performance_admin_inbox.can_scope(issuer,company_id));
REVOKE ALL ON performance_admin_inbox.request_audit FROM PUBLIC;
CREATE TRIGGER request_audit_immutable BEFORE UPDATE OR DELETE ON performance_admin_inbox.request_audit
 FOR EACH ROW EXECUTE FUNCTION performance_admin_inbox.immutable_principal_audit();
-- Runtime only: EXECUTE resolve_delegation; INSERT request_audit + USAGE its sequence.
-- Reuse explicit inbox grants from003. Runtime NEVER reads/writes service_delegations.
