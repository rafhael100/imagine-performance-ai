-- Synthetic tenant and ledger proof; psql -v ON_ERROR_STOP=1.
SET ROLE perf_fin_lab_app;
SET perf_lab.tenant_id = '11111111-1111-4111-8111-111111111111';
INSERT INTO perf_fin_lab.spend_evidence
(tenant_id, reference_month, channel, report_id, spend_cents, record_sha256)
VALUES
('11111111-1111-4111-8111-111111111111', '2026-11-01', 'google_ads', 'g-1', 70000, repeat('a',64)),
('11111111-1111-4111-8111-111111111111', '2026-11-01', 'meta_ads', 'm-1', 60000, repeat('b',64));
INSERT INTO perf_fin_lab.monthly_review_candidate
(tenant_id, reference_month, quote_sha256, evidence_sha256, advance_fee_cents, earned_fee_cents, adjustment_cents)
VALUES
('11111111-1111-4111-8111-111111111111', '2026-11-01', repeat('c',64), repeat('d',64), 28000, 26000, -2000);
DO $test$
BEGIN
 IF (SELECT count(*) FROM perf_fin_lab.spend_evidence) != 2 THEN
   RAISE EXCEPTION 'Tenant A must see exactly two spend rows';
 END IF;
 IF (SELECT count(*) FROM perf_fin_lab.monthly_review_candidate) != 1 THEN
   RAISE EXCEPTION 'Tenant A review candidate missing';
 END IF;
 BEGIN
   INSERT INTO perf_fin_lab.spend_evidence (tenant_id,reference_month,channel,report_id,spend_cents,record_sha256)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01','google_ads','g-1',70000,repeat('a',64));
   RAISE EXCEPTION 'Duplicate evidence accepted';
 EXCEPTION WHEN unique_violation THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.monthly_review_candidate
   (tenant_id,reference_month,quote_sha256,evidence_sha256,advance_fee_cents,earned_fee_cents,adjustment_cents)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01',repeat('c',64),repeat('d',64),28000,26000,-2000);
   RAISE EXCEPTION 'Duplicate proposal accepted';
 EXCEPTION WHEN unique_violation THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.monthly_review_candidate
   (tenant_id,reference_month,quote_sha256,evidence_sha256,advance_fee_cents,earned_fee_cents,adjustment_cents,finance_approved)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01',repeat('e',64),repeat('d',64),28000,26000,-2000,true);
   RAISE EXCEPTION 'Unverified approval accepted';
 EXCEPTION WHEN check_violation THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.monthly_review_candidate
   (tenant_id,reference_month,quote_sha256,evidence_sha256,advance_fee_cents,earned_fee_cents,adjustment_cents,gateway_write_enabled)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01',repeat('e',64),repeat('d',64),28000,26000,-2000,true);
   RAISE EXCEPTION 'Gateway write flag accepted';
 EXCEPTION WHEN check_violation THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.monthly_review_candidate
   (tenant_id,reference_month,quote_sha256,evidence_sha256,advance_fee_cents,earned_fee_cents,adjustment_cents)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01',repeat('e',64),repeat('d',64),28000,26000,-1999);
   RAISE EXCEPTION 'Incorrect fee arithmetic accepted';
 EXCEPTION WHEN check_violation THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.spend_evidence
   (tenant_id,reference_month,channel,report_id,spend_cents,record_sha256,evidence_source)
   VALUES ('11111111-1111-4111-8111-111111111111','2026-11-01','google_ads','spoof',0,repeat('a',64),'provider_verified');
   RAISE EXCEPTION 'Spoofed provider verification accepted';
 EXCEPTION WHEN check_violation THEN NULL;
 END;
 BEGIN
   UPDATE perf_fin_lab.spend_evidence SET spend_cents = 0;
   RAISE EXCEPTION 'Application updated existing evidence';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
   DELETE FROM perf_fin_lab.monthly_review_candidate;
   RAISE EXCEPTION 'Application deleted review';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
   INSERT INTO perf_fin_lab.spend_evidence
   (tenant_id,reference_month,channel,report_id,spend_cents,record_sha256)
   VALUES ('22222222-2222-4222-8222-222222222222','2026-11-01','google_ads','other',1,repeat('e',64));
   RAISE EXCEPTION 'Cross tenant write accepted';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
END;
$test$;
SET perf_lab.tenant_id = '22222222-2222-4222-8222-222222222222';
DO $test$
BEGIN
 IF (SELECT count(*) FROM perf_fin_lab.spend_evidence) != 0 THEN
   RAISE EXCEPTION 'Cross tenant evidence readable';
 END IF;
 IF (SELECT count(*) FROM perf_fin_lab.monthly_review_candidate) != 0 THEN
   RAISE EXCEPTION 'Cross tenant settlement readable';
 END IF;
END;
$test$;
INSERT INTO perf_fin_lab.spend_evidence
(tenant_id,reference_month,channel,report_id,spend_cents,record_sha256)
VALUES ('22222222-2222-4222-8222-222222222222','2026-11-01','google_ads','g-1',1,repeat('e',64));
RESET perf_lab.tenant_id;
DO $test$
BEGIN
 IF (SELECT count(*) FROM perf_fin_lab.spend_evidence) != 0 THEN
   RAISE EXCEPTION 'Missing tenant context leaked records';
 END IF;
 BEGIN
   INSERT INTO perf_fin_lab.spend_evidence
   (tenant_id,reference_month,channel,report_id,spend_cents,record_sha256)
   VALUES ('22222222-2222-4222-8222-222222222222','2026-11-01','google_ads','no-tenant',1,repeat('f',64));
   RAISE EXCEPTION 'Missing tenant context allowed insert';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
END;
$test$;
RESET ROLE;
DO $test$
BEGIN
 IF (SELECT count(*) FROM perf_fin_lab.spend_evidence) != 3 THEN
   RAISE EXCEPTION 'Incorrect total persisted synthetic rows';
 END IF;
 IF (SELECT count(*) FROM perf_fin_lab.monthly_review_candidate) != 1 THEN
   RAISE EXCEPTION 'Unexpected persisted proposal';
 END IF;
END;
$test$;
SELECT 'OK: synthetic tenant isolation, duplicate rejection and no-charge controls' AS result;
