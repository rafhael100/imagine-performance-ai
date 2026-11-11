-- FAST-PERF F1-166: CI-only PostgreSQL16 read-only Portal mock projections.
-- PG security_invoker views honor caller's RLS permissions, not view owner.
BEGIN;
CREATE VIEW perf_f162.active_review_dashboard
WITH (security_invoker=true, security_barrier=true) AS
    SELECT r.tenant,r.reference,r.campaign,r.channel,r.title,r.status,r.version
    FROM perf_f162.reviews AS r
    INNER JOIN perf_f162.managed_campaigns AS c
      ON c.tenant=r.tenant AND c.campaign=r.campaign
      AND c.channel=r.channel
    WHERE c.managed=true
      AND NOT EXISTS (
        SELECT 1 FROM perf_f162.campaign_events AS e
        WHERE e.tenant=r.tenant AND e.campaign=r.campaign
          AND e.event_type='retired_demo'
      );
CREATE VIEW perf_f162.retired_review_counts
WITH (security_invoker=true, security_barrier=true) AS
    SELECT r.tenant,r.channel,count(*)::integer AS count_retired_demo
    FROM perf_f162.reviews AS r
    INNER JOIN perf_f162.managed_campaigns AS c
      ON c.tenant=r.tenant AND c.campaign=r.campaign
      AND c.channel=r.channel
    WHERE c.managed=false
       OR EXISTS (
        SELECT 1 FROM perf_f162.campaign_events AS e
        WHERE e.tenant=r.tenant AND e.campaign=r.campaign
          AND e.event_type='retired_demo'
       )
    GROUP BY r.tenant,r.channel;
CREATE VIEW perf_f162.campaign_scope_activity
WITH (security_invoker=true, security_barrier=true) AS
    SELECT tenant,event_type,count(*)::integer AS event_count_demo
    FROM perf_f162.campaign_events
    GROUP BY tenant,event_type;

REVOKE ALL ON perf_f162.active_review_dashboard,
    perf_f162.retired_review_counts,
    perf_f162.campaign_scope_activity FROM PUBLIC;
GRANT SELECT ON perf_f162.active_review_dashboard,
    perf_f162.retired_review_counts,
    perf_f162.campaign_scope_activity TO perf_f162_a,perf_f162_b;
COMMIT;
