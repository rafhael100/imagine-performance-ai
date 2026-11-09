-- F1-19 Portal lab ONLY. Must be executed in disposable portal_lab.
BEGIN;
CREATE TABLE lab_portal_perf_restore_epochs (
    service_ref text PRIMARY KEY REFERENCES lab_portal_perf_authority(service_ref),
    epoch integer NOT NULL CHECK (epoch > 0),
    state text NOT NULL CHECK (state IN ('ready_mock','restoring_mock'))
);
INSERT INTO lab_portal_perf_restore_epochs(service_ref,epoch,state)
VALUES ('svc-demo-b',2,'ready_mock');
REVOKE ALL ON lab_portal_perf_restore_epochs FROM PUBLIC;
ALTER TABLE lab_portal_perf_restore_epochs ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_portal_perf_restore_epochs FORCE ROW LEVEL SECURITY;
CREATE POLICY perf_f19_portal_epoch_b ON lab_portal_perf_restore_epochs
FOR SELECT TO portal_perf_source_reader
USING (service_ref='svc-demo-b');
GRANT SELECT ON lab_portal_perf_restore_epochs TO portal_perf_source_reader;
COMMIT;
