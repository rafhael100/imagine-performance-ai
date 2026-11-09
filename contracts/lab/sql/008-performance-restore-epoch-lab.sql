-- F1-19 motor lab ONLY, after historical backup has been restored.
BEGIN;
CREATE TABLE perf_s2s_lab.mock_restore_epochs (
    service_ref text PRIMARY KEY REFERENCES perf_s2s_lab.mock_service_grants(service_ref),
    epoch integer NOT NULL CHECK (epoch > 0),
    state text NOT NULL CHECK (state IN ('ready_mock','restoring_mock'))
);
INSERT INTO perf_s2s_lab.mock_restore_epochs(service_ref,epoch,state)
VALUES ('svc-demo-b',1,'restoring_mock');
REVOKE ALL ON perf_s2s_lab.mock_restore_epochs FROM PUBLIC;
ALTER TABLE perf_s2s_lab.mock_restore_epochs ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_s2s_lab.mock_restore_epochs FORCE ROW LEVEL SECURITY;
CREATE POLICY perf_f19_motor_epoch_b ON perf_s2s_lab.mock_restore_epochs
FOR SELECT TO perf_test_wire_ingress
USING (service_ref='svc-demo-b');
GRANT SELECT ON perf_s2s_lab.mock_restore_epochs TO perf_test_wire_ingress;
COMMIT;
