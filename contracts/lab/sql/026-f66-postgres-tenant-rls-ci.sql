-- FAST-PERF F1-66: disposable PostgreSQL CI, NOT a production migration.
-- Real client identity, privileges, migrations and Portal integration are absent.
-- Only fixed fixture login roles can use any function, and session_user
-- rather than a caller-supplied tenant parameter is the RLS source.
\set ON_ERROR_STOP on

CREATE ROLE perf_f66_owner NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
CREATE ROLE perf_f66_tenant_a LOGIN PASSWORD 'ci-f66-role-a-only' NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
CREATE ROLE perf_f66_tenant_b LOGIN PASSWORD 'ci-f66-role-b-only' NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
CREATE SCHEMA perf_f66_scope AUTHORIZATION perf_f66_owner;
REVOKE ALL ON SCHEMA perf_f66_scope FROM PUBLIC;
GRANT CONNECT ON DATABASE perf_f66_ci TO perf_f66_tenant_a,perf_f66_tenant_b;
GRANT USAGE ON SCHEMA perf_f66_scope TO perf_f66_tenant_a,perf_f66_tenant_b;

SET ROLE perf_f66_owner;
CREATE TABLE perf_f66_scope.capacities(
 tenant_role text NOT NULL CHECK(tenant_role IN ('perf_f66_tenant_a','perf_f66_tenant_b')),
 channel text NOT NULL CHECK(channel IN ('google_ads','meta')),
 campaign_limit integer NOT NULL CHECK(campaign_limit BETWEEN 0 AND 100),
 group_limit integer NOT NULL CHECK(group_limit BETWEEN 0 AND 1000),
 PRIMARY KEY(tenant_role,channel)
);
CREATE TABLE perf_f66_scope.campaigns(
 ref text PRIMARY KEY CHECK(ref ~ '^cam-[a-z0-9-]{1,60}$'),
 tenant_role text NOT NULL CHECK(tenant_role IN ('perf_f66_tenant_a','perf_f66_tenant_b')),
 channel text NOT NULL CHECK(channel IN ('google_ads','meta')),
 state text NOT NULL CHECK(state IN ('active_demo','paused_demo','draft_demo','outside_scope_demo')),
 managed boolean NOT NULL DEFAULT true,
 UNIQUE(tenant_role,ref),
 FOREIGN KEY (tenant_role,channel)
  REFERENCES perf_f66_scope.capacities(tenant_role,channel)
);
CREATE INDEX f66_campaign_tenant_channel ON perf_f66_scope.campaigns(tenant_role,channel,managed);
CREATE TABLE perf_f66_scope.groups(
 ref text PRIMARY KEY CHECK(ref ~ '^grp-[a-z0-9-]{1,60}$'),
 tenant_role text NOT NULL CHECK(tenant_role IN ('perf_f66_tenant_a','perf_f66_tenant_b')),
 campaign_ref text NOT NULL,
 state text NOT NULL CHECK(state IN ('active_demo','paused_demo','outside_scope_demo')),
 managed boolean NOT NULL DEFAULT true,
 FOREIGN KEY (tenant_role,campaign_ref)
  REFERENCES perf_f66_scope.campaigns(tenant_role,ref)
);
CREATE INDEX f66_group_tenant ON perf_f66_scope.groups(tenant_role,managed);
CREATE TABLE perf_f66_scope.swaps(
 request_ref text PRIMARY KEY CHECK(request_ref ~ '^req-demo-[a-z0-9-]{1,60}$'),
 tenant_role text NOT NULL CHECK(tenant_role IN ('perf_f66_tenant_a','perf_f66_tenant_b')),
 old_ref text NOT NULL,
 new_ref text NOT NULL,
 result text NOT NULL CHECK(result='replaced_synthetic_no_fee'),
 FOREIGN KEY (tenant_role,old_ref)
  REFERENCES perf_f66_scope.campaigns(tenant_role,ref),
 FOREIGN KEY (tenant_role,new_ref)
  REFERENCES perf_f66_scope.campaigns(tenant_role,ref)
);

ALTER TABLE perf_f66_scope.capacities ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.capacities FORCE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.campaigns ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.campaigns FORCE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.groups ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.groups FORCE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.swaps ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f66_scope.swaps FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_cap ON perf_f66_scope.capacities
 USING(tenant_role=session_user::text)
 WITH CHECK(tenant_role=session_user::text);
CREATE POLICY tenant_campaign ON perf_f66_scope.campaigns
 USING(tenant_role=session_user::text)
 WITH CHECK(tenant_role=session_user::text);
CREATE POLICY tenant_group ON perf_f66_scope.groups
 USING(tenant_role=session_user::text)
 WITH CHECK(tenant_role=session_user::text);
CREATE POLICY tenant_swap ON perf_f66_scope.swaps
 USING(tenant_role=session_user::text)
 WITH CHECK(tenant_role=session_user::text);

-- Serialize all mock writes for a single login session_user. This is NOT
-- an RBAC check and not a distributed multi-service transaction.
CREATE FUNCTION perf_f66_scope.demo_lock() RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
BEGIN
 IF session_user NOT IN ('perf_f66_tenant_a','perf_f66_tenant_b') THEN
  RAISE EXCEPTION 'f66 fixture role only';
 END IF;
 PERFORM pg_advisory_xact_lock(hashtextextended('perf-f66|'||session_user,0));
END;
$$;

CREATE FUNCTION perf_f66_scope.configure_demo(
 p_channel text,p_campaigns integer,p_groups integer
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE used_cam integer;used_group integer;
BEGIN
 PERFORM perf_f66_scope.demo_lock();
 IF p_channel NOT IN ('google_ads','meta')
  OR p_campaigns IS NULL OR p_campaigns NOT BETWEEN 0 AND 100
  OR p_groups IS NULL OR p_groups NOT BETWEEN 0 AND 1000 THEN
  RETURN 'invalid_demo_capacity_denied';
 END IF;
 SELECT count(*) INTO used_cam FROM perf_f66_scope.campaigns
  WHERE tenant_role=session_user::text AND channel=p_channel AND managed;
 SELECT count(*) INTO used_group FROM perf_f66_scope.groups g
  JOIN perf_f66_scope.campaigns c ON g.campaign_ref=c.ref
  WHERE g.tenant_role=session_user::text AND c.channel=p_channel AND g.managed;
 IF used_cam>p_campaigns OR used_group>p_groups THEN
  RETURN 'reduction_below_usage_denied';
 END IF;
 INSERT INTO perf_f66_scope.capacities(tenant_role,channel,campaign_limit,group_limit)
 VALUES(session_user::text,p_channel,p_campaigns,p_groups)
 ON CONFLICT(tenant_role,channel) DO UPDATE
  SET campaign_limit=excluded.campaign_limit,group_limit=excluded.group_limit;
 RETURN 'capacity_configured_mock';
END;
$$;

CREATE FUNCTION perf_f66_scope.reserve_campaign(p_channel text,p_ref text)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE lim integer;used integer;
BEGIN
 PERFORM perf_f66_scope.demo_lock();
 IF p_channel NOT IN ('google_ads','meta') OR p_ref IS NULL
  OR p_ref !~ '^cam-[a-z0-9-]{1,60}$' THEN
  RETURN 'invalid_mock_campaign_denied';
 END IF;
 SELECT campaign_limit INTO lim FROM perf_f66_scope.capacities
  WHERE tenant_role=session_user::text AND channel=p_channel;
 IF NOT FOUND THEN RETURN 'capacity_unapproved_denied'; END IF;
 IF EXISTS(SELECT 1 FROM perf_f66_scope.campaigns WHERE ref=p_ref) THEN
  RETURN 'already_exists_or_out_of_scope';
 END IF;
 SELECT count(*) INTO used FROM perf_f66_scope.campaigns
  WHERE tenant_role=session_user::text AND channel=p_channel AND managed;
 IF used>=lim THEN RETURN 'capacity_review_required'; END IF;
 INSERT INTO perf_f66_scope.campaigns(ref,tenant_role,channel,state,managed)
 VALUES(p_ref,session_user::text,p_channel,'active_demo',true);
 RETURN 'included_synthetic_only';
EXCEPTION WHEN unique_violation THEN
 RETURN 'already_exists_or_out_of_scope';
END;
$$;

CREATE FUNCTION perf_f66_scope.reserve_group(p_campaign text,p_group text)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE ch text;lim integer;used integer;
BEGIN
 PERFORM perf_f66_scope.demo_lock();
 IF p_campaign IS NULL OR p_campaign !~ '^cam-[a-z0-9-]{1,60}$'
  OR p_group IS NULL OR p_group !~ '^grp-[a-z0-9-]{1,60}$' THEN
  RETURN 'invalid_mock_group_denied';
 END IF;
 SELECT channel INTO ch FROM perf_f66_scope.campaigns
  WHERE ref=p_campaign AND tenant_role=session_user::text AND managed;
 IF NOT FOUND THEN RETURN 'campaign_not_found'; END IF;
 SELECT group_limit INTO lim FROM perf_f66_scope.capacities
  WHERE tenant_role=session_user::text AND channel=ch;
 IF NOT FOUND THEN RETURN 'capacity_unapproved_denied'; END IF;
 SELECT count(*) INTO used FROM perf_f66_scope.groups g
 JOIN perf_f66_scope.campaigns c ON c.ref=g.campaign_ref
  WHERE g.tenant_role=session_user::text AND c.channel=ch AND g.managed;
 IF used>=lim THEN RETURN 'capacity_review_required'; END IF;
 IF EXISTS(SELECT 1 FROM perf_f66_scope.groups WHERE ref=p_group) THEN
  RETURN 'already_exists_or_out_of_scope';
 END IF;
 INSERT INTO perf_f66_scope.groups(ref,tenant_role,campaign_ref,state,managed)
 VALUES(p_group,session_user::text,p_campaign,'active_demo',true);
 RETURN 'included_synthetic_only';
EXCEPTION WHEN unique_violation THEN
 RETURN 'already_exists_or_out_of_scope';
END;
$$;

CREATE FUNCTION perf_f66_scope.pause_campaign(p_campaign text)
RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE cur text;
BEGIN
 PERFORM perf_f66_scope.demo_lock();
 IF p_campaign IS NULL OR p_campaign !~ '^cam-[a-z0-9-]{1,60}$' THEN
  RETURN 'invalid_mock_campaign_denied';
 END IF;
 SELECT state INTO cur FROM perf_f66_scope.campaigns
 WHERE ref=p_campaign AND tenant_role=session_user::text AND managed FOR UPDATE;
 IF NOT FOUND THEN RETURN 'not_found'; END IF;
 IF cur='paused_demo' THEN RETURN 'already_paused_demo'; END IF;
 UPDATE perf_f66_scope.campaigns SET state='paused_demo'
  WHERE ref=p_campaign AND tenant_role=session_user::text;
 RETURN 'paused_synthetic_only';
END;
$$;

CREATE FUNCTION perf_f66_scope.replace_campaign(
 p_request text,p_old text,p_new text,p_recommended boolean,p_approved boolean
) RETURNS text LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE prior record;ch text;
BEGIN
 PERFORM perf_f66_scope.demo_lock();
 IF p_request IS NULL OR p_request !~ '^req-demo-[a-z0-9-]{1,60}$'
 OR p_old IS NULL OR p_old !~ '^cam-[a-z0-9-]{1,60}$'
 OR p_new IS NULL OR p_new !~ '^cam-[a-z0-9-]{1,60}$' THEN
  RETURN 'invalid_mock_request_denied';
 END IF;
 IF p_recommended IS DISTINCT FROM true THEN
  RETURN 'recommendation_required';
 END IF;
 IF p_approved IS DISTINCT FROM true THEN
  RETURN 'human_review_required';
 END IF;
 SELECT tenant_role,old_ref,new_ref INTO prior FROM perf_f66_scope.swaps
 WHERE request_ref=p_request;
 IF FOUND THEN
  IF prior.tenant_role=session_user::text AND prior.old_ref=p_old
   AND prior.new_ref=p_new THEN RETURN 'already_applied_synthetic'; END IF;
  RETURN 'request_conflict_or_out_of_scope';
 END IF;
 SELECT channel INTO ch FROM perf_f66_scope.campaigns
  WHERE ref=p_old AND tenant_role=session_user::text AND managed FOR UPDATE;
 IF NOT FOUND THEN RETURN 'managed_campaign_not_found'; END IF;
 IF EXISTS(SELECT 1 FROM perf_f66_scope.campaigns WHERE ref=p_new) THEN
  RETURN 'already_exists_or_out_of_scope';
 END IF;
 UPDATE perf_f66_scope.campaigns
 SET managed=false,state='outside_scope_demo'
 WHERE ref=p_old AND tenant_role=session_user::text;
 UPDATE perf_f66_scope.groups
 SET managed=false,state='outside_scope_demo'
 WHERE campaign_ref=p_old AND tenant_role=session_user::text;
 INSERT INTO perf_f66_scope.campaigns(ref,tenant_role,channel,state,managed)
 VALUES(p_new,session_user::text,ch,'draft_demo',true);
 INSERT INTO perf_f66_scope.swaps(request_ref,tenant_role,old_ref,new_ref,result)
 VALUES(p_request,session_user::text,p_old,p_new,'replaced_synthetic_no_fee');
 RETURN 'replaced_synthetic_no_fee';
EXCEPTION WHEN unique_violation THEN
 RETURN 'already_exists_or_out_of_scope';
END;
$$;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f66_scope FROM PUBLIC;
RESET ROLE;

GRANT SELECT ON perf_f66_scope.capacities,perf_f66_scope.campaigns,
  perf_f66_scope.groups,perf_f66_scope.swaps
 TO perf_f66_tenant_a,perf_f66_tenant_b;
GRANT EXECUTE ON FUNCTION perf_f66_scope.configure_demo(text,integer,integer),
 perf_f66_scope.reserve_campaign(text,text),
 perf_f66_scope.reserve_group(text,text),
 perf_f66_scope.pause_campaign(text),
 perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
 TO perf_f66_tenant_a,perf_f66_tenant_b;
-- Do not grant EXECUTE on demo_lock() to tenants: called by SECURITY DEFINER
-- wrappers only. No direct writes to any table or real providers.
