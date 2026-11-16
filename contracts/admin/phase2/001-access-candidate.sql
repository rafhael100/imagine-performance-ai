-- Review candidate, never a migration of lab-alfa/lab-beta into production.
-- Owner executes explicitly in an EMPTY disposable schema only.
CREATE SCHEMA performance_admin_candidate;
REVOKE ALL ON SCHEMA performance_admin_candidate FROM PUBLIC;
CREATE TABLE performance_admin_candidate.companies (
 id uuid PRIMARY KEY, portal_organization_ref varchar(100) NOT NULL UNIQUE CHECK(portal_organization_ref ~ '^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,99}$'),
 contract_status text NOT NULL CHECK(contract_status IN ('active','suspended','cancelled')),
 operational_status text NOT NULL CHECK(operational_status IN ('enabled','blocked'))
);
CREATE TABLE performance_admin_candidate.internal_users (
 id uuid PRIMARY KEY, portal_admin_id bigint NOT NULL UNIQUE,
 database_login name NOT NULL UNIQUE, active boolean NOT NULL DEFAULT false,
 session_epoch bigint NOT NULL DEFAULT 1 CHECK(session_epoch>0)
);
CREATE TABLE performance_admin_candidate.services (
 company_id uuid REFERENCES performance_admin_candidate.companies,
 channel text CHECK(channel IN ('google_ads','meta_ads','chatgpt_ads')),
 active boolean NOT NULL DEFAULT false, version bigint NOT NULL CHECK(version>0),
 PRIMARY KEY(company_id,channel)
);
CREATE TABLE performance_admin_candidate.grants (
 user_id uuid REFERENCES performance_admin_candidate.internal_users,
 company_id uuid REFERENCES performance_admin_candidate.companies,
 role text NOT NULL CHECK(role IN ('superadmin','operations','analyst','ai_supervisor','finance','auditor')),
 expires_at timestamptz NOT NULL, revoked_at timestamptz,
 version bigint NOT NULL CHECK(version>0), PRIMARY KEY(user_id,company_id)
);
CREATE TABLE performance_admin_candidate.ad_accounts (
 company_id uuid NOT NULL, channel text NOT NULL, account_reference text NOT NULL,
 consent_reference text NOT NULL, expires_at timestamptz NOT NULL, revoked_at timestamptz,
 PRIMARY KEY(company_id,channel,account_reference),
 FOREIGN KEY(company_id,channel) REFERENCES performance_admin_candidate.services
);
CREATE TABLE performance_admin_candidate.grant_audit (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 actor name NOT NULL, event text NOT NULL, user_id uuid NOT NULL, company_id uuid NOT NULL,
 old_version bigint, new_version bigint, at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE FUNCTION performance_admin_candidate.audit_grant() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog,performance_admin_candidate AS $$
BEGIN
 INSERT INTO performance_admin_candidate.grant_audit(actor,event,user_id,company_id,old_version,new_version)
 VALUES(session_user,TG_OP,coalesce(NEW.user_id,OLD.user_id),coalesce(NEW.company_id,OLD.company_id),OLD.version,NEW.version);
 RETURN coalesce(NEW,OLD);
END $$;
REVOKE ALL ON FUNCTION performance_admin_candidate.audit_grant() FROM PUBLIC;
CREATE TRIGGER grant_change AFTER INSERT OR UPDATE OR DELETE ON performance_admin_candidate.grants
 FOR EACH ROW EXECUTE FUNCTION performance_admin_candidate.audit_grant();
CREATE FUNCTION performance_admin_candidate.immutable_audit() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Audit is append-only'; END $$;
CREATE TRIGGER immutable_audit BEFORE UPDATE OR DELETE ON performance_admin_candidate.grant_audit
 FOR EACH ROW EXECUTE FUNCTION performance_admin_candidate.immutable_audit();
CREATE FUNCTION performance_admin_candidate.can_access(target uuid) RETURNS boolean
LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog,performance_admin_candidate AS $$
 SELECT EXISTS(SELECT 1 FROM performance_admin_candidate.internal_users u
 JOIN performance_admin_candidate.grants g ON g.user_id=u.id

 WHERE u.database_login=session_user AND u.active AND g.company_id=target
 AND g.revoked_at IS NULL AND g.expires_at>statement_timestamp())
$$;
REVOKE ALL ON FUNCTION performance_admin_candidate.can_access(uuid) FROM PUBLIC;
ALTER TABLE performance_admin_candidate.companies ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_candidate.companies FORCE ROW LEVEL SECURITY;
CREATE POLICY company_access ON performance_admin_candidate.companies USING(performance_admin_candidate.can_access(id) AND contract_status='active' AND operational_status='enabled');
ALTER TABLE performance_admin_candidate.services ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_candidate.services FORCE ROW LEVEL SECURITY;
CREATE POLICY service_access ON performance_admin_candidate.services USING(performance_admin_candidate.can_access(company_id) AND EXISTS(SELECT 1 FROM performance_admin_candidate.companies c WHERE c.id=company_id));
ALTER TABLE performance_admin_candidate.ad_accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE performance_admin_candidate.ad_accounts FORCE ROW LEVEL SECURITY;
CREATE POLICY account_access ON performance_admin_candidate.ad_accounts USING(performance_admin_candidate.can_access(company_id) AND revoked_at IS NULL AND expires_at>statement_timestamp() AND EXISTS(SELECT 1 FROM performance_admin_candidate.services s WHERE s.company_id=ad_accounts.company_id AND s.channel=ad_accounts.channel AND s.active));
-- internal_users/grants/audit are authority tables: runtime receives NO table grants.
-- Role mapping is native session_user, never a tenant/user GUC from the caller.
-- Production HTTP pooling requires a separately reviewed identity bridge; not enabled here.
