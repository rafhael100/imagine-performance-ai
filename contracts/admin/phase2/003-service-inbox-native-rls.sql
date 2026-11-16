-- Additive candidate on a NEW isolated database, never promote laboratory tables.
CREATE TABLE performance_admin_inbox.principals (
 database_login name NOT NULL, issuer text NOT NULL, company_id text NOT NULL,
 active boolean NOT NULL DEFAULT false, expires_at timestamptz NOT NULL, revoked_at timestamptz,
 PRIMARY KEY(database_login,issuer,company_id)
);
REVOKE ALL ON performance_admin_inbox.principals FROM PUBLIC;
CREATE FUNCTION performance_admin_inbox.can_scope(service text, company text) RETURNS boolean
 LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog,performance_admin_inbox AS $$
 SELECT EXISTS(SELECT 1 FROM performance_admin_inbox.principals p WHERE p.database_login=session_user
 AND p.issuer=service AND p.company_id=company AND p.active AND p.revoked_at IS NULL AND p.expires_at>statement_timestamp())
$$;
CREATE FUNCTION performance_admin_inbox.can_nonce(service text) RETURNS boolean
 LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog,performance_admin_inbox AS $$
 SELECT EXISTS(SELECT 1 FROM performance_admin_inbox.principals p WHERE p.database_login=session_user
 AND p.issuer=service AND p.active AND p.revoked_at IS NULL AND p.expires_at>statement_timestamp())
$$;
REVOKE ALL ON FUNCTION performance_admin_inbox.can_scope(text,text),performance_admin_inbox.can_nonce(text) FROM PUBLIC;
ALTER TABLE performance_admin_inbox.receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_inbox.receipts FORCE ROW LEVEL SECURITY;
CREATE POLICY receipt_native_scope ON performance_admin_inbox.receipts
 USING(performance_admin_inbox.can_scope(issuer,company_id)) WITH CHECK(performance_admin_inbox.can_scope(issuer,company_id));
ALTER TABLE performance_admin_inbox.nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_inbox.nonces FORCE ROW LEVEL SECURITY;
CREATE POLICY nonce_native_scope ON performance_admin_inbox.nonces
 USING(performance_admin_inbox.can_nonce(issuer)) WITH CHECK(performance_admin_inbox.can_nonce(issuer));
-- Owner alone maintains principals. Runtime: explicit SELECT/INSERT on receipts/nonces,
-- USAGE schema + EXECUTE the two functions. Never grant ALL TABLES (includes authority).
CREATE TABLE performance_admin_inbox.principal_events (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, actor name NOT NULL, event text NOT NULL,
 old_state jsonb, new_state jsonb, at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON performance_admin_inbox.principal_events FROM PUBLIC;
CREATE FUNCTION performance_admin_inbox.audit_principal() RETURNS trigger
 LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog,performance_admin_inbox AS $$
BEGIN
 INSERT INTO performance_admin_inbox.principal_events(actor,event,old_state,new_state)
 VALUES(session_user,TG_OP,to_jsonb(OLD),to_jsonb(NEW));
 RETURN coalesce(NEW,OLD);
END $$;
REVOKE ALL ON FUNCTION performance_admin_inbox.audit_principal() FROM PUBLIC;
CREATE TRIGGER principal_change AFTER INSERT OR UPDATE OR DELETE ON performance_admin_inbox.principals
 FOR EACH ROW EXECUTE FUNCTION performance_admin_inbox.audit_principal();
CREATE FUNCTION performance_admin_inbox.immutable_principal_audit() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Audit is append-only'; END $$;
CREATE TRIGGER principal_events_immutable BEFORE UPDATE OR DELETE ON performance_admin_inbox.principal_events
 FOR EACH ROW EXECUTE FUNCTION performance_admin_inbox.immutable_principal_audit();
