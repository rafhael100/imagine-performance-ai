<?php

declare(strict_types=1);

require_once dirname(__DIR__,3).'/src/Domain/Performance/ServiceActionAdmissionPolicy.php';
require_once __DIR__.'/SyntheticSqlAdmissionEvidenceReader.php';

use Imagine\Performance\Domain\ServiceActionAdmissionPolicy;
use Imagine\Performance\Domain\ServiceActionRequest;
use Imagine\Protocol\Lab\SyntheticSqlAdmissionEvidenceReader;

$phase = getenv('PERF_F19_PHASE');
if (PHP_SAPI !== 'cli'
    || !in_array($phase, ['post_restore','post_reconcile','epoch_ready'], true)
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_relay_main'
    || getenv('PORTAL_RECONCILE_DATABASE') !== 'portal_lab'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F18_ADMISSION_LAB') !== '1'
    || getenv('PERF_F19_SQL_ADAPTER_LAB') !== '1'
    || getenv('PERF_F19_MOCK_ATTESTED') !== '1') {
    throw new RuntimeException('F1-19 SQL adapter runs only in disposable CI.');
}
$now = 1800000000;
$connect=static fn (string $db,string $name,string $password):PDO => new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname='.$db.';connect_timeout=3',
    $name,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$source=$connect('portal_lab','portal_perf_source_reader','ci-only-portal-perf-reader');
$motor=$connect('perf_test_relay_main','perf_test_wire_ingress','ci-only-direct-wire-ingress');
$adminSource=$connect('portal_lab','postgres',(string)getenv('PGPASSWORD'));
$adminMotor=$connect('perf_test_relay_main','postgres',(string)getenv('PGPASSWORD'));
$reader=new SyntheticSqlAdmissionEvidenceReader($source,$motor);
$req=new ServiceActionRequest(
    'org-demo-b','perf-demo-b','svc-demo-b','actor-demo-b',
    'campaign-demo-b1','campaign.pause','manifest-demo-v1'
);
$policy=ServiceActionAdmissionPolicy::forSyntheticLabOnly();
$n=0;
$check=static function(string $title,mixed $actual,mixed $expected) use (&$n,$phase):void {
    if($actual!==$expected){
        throw new RuntimeException('FAIL F19 '.$phase.' '.$title.' got '
            .var_export($actual,true).' expected '.var_export($expected,true));
    }
    ++$n;
    echo "PASS F19 $phase $n $title\n";
};
$e=$reader->read('org-demo-b','perf-demo-b','svc-demo-b',$now);
if($e===null){
    throw new RuntimeException('F19 expected both role-bound mock rows.');
}
$check('01 source is a SQL observed revoked grant', $e->sourceState, 'revoked_mock');
$check('02 source revision is 2', $e->sourceRevision, 2);
$check('03 B binding comes from DB', $e->serviceRef, 'svc-demo-b');
$check('04 synthetic actor is explicit fixture, not login', $e->actorRef, 'actor-demo-b');
$check('05 source epoch comes from other DB', $e->externalRecoveryEpoch, 2);
$check('06 source recovery state is ready',
    $adminSource->query(
        "SELECT state FROM lab_portal_perf_restore_epochs WHERE service_ref='svc-demo-b'"
    )->fetchColumn(), 'ready_mock');
$check('07 local epoch and revision match phase',
    [$e->localRecoveryEpoch,$e->localRevision],
    $phase==='post_restore'?[1,1]:
        ($phase==='post_reconcile'?[1,2]:[2,2]));

$decision=$policy->decide($req,$e,null,$now);
$expected=$phase==='post_restore'?'service_revision_mismatch':
    ($phase==='post_reconcile'?'recovery_quarantine':'service_not_eligible');
$check('08 policy rejects restored or revoked state', $decision->code,$expected);
$check('09 denied decision never signals operational action', $decision->allowed,false);
$check('10 source/local equality is constrained', $e->organizationRef,'org-demo-b');
$check('11 wrong organization cannot read source', $reader->read(
    'org-demo-a','perf-demo-b','svc-demo-b',$now),null);
$check('12 wrong tenant cannot read source', $reader->read(
    'org-demo-b','perf-demo-a','svc-demo-b',$now),null);
$check('13 wrong service cannot read source', $reader->read(
    'org-demo-b','perf-demo-b','svc-demo-a',$now),null);
$wrongRole=new SyntheticSqlAdmissionEvidenceReader($adminSource,$motor);
$check('14 admin/SUPERUSER cannot be substituted as source reader', $wrongRole->read(
    'org-demo-b','perf-demo-b','svc-demo-b',$now),null);
$wrongRole=new SyntheticSqlAdmissionEvidenceReader($source,$adminMotor);
$check('15 admin/SUPERUSER cannot be substituted as motor reader', $wrongRole->read(
    'org-demo-b','perf-demo-b','svc-demo-b',$now),null);

$adminSource->exec('REVOKE SELECT ON lab_portal_perf_restore_epochs FROM portal_perf_source_reader');
$check('16 missing source epoch table privilege fails closed',
    $reader->read('org-demo-b','perf-demo-b','svc-demo-b',$now),null);
$missing=$policy->decide($req,
    $reader->read('org-demo-b','perf-demo-b','svc-demo-b',$now),null,$now);
$check('17 missing evidence produces explicit deny',$missing->code,'missing_evidence');
$adminSource->exec('GRANT SELECT ON lab_portal_perf_restore_epochs TO portal_perf_source_reader');

$adminMotor->exec('REVOKE SELECT ON perf_s2s_lab.mock_restore_epochs FROM perf_test_wire_ingress');
$check('18 missing local epoch privilege fails closed',
    $reader->read('org-demo-b','perf-demo-b','svc-demo-b',$now),null);
$adminMotor->exec('GRANT SELECT ON perf_s2s_lab.mock_restore_epochs TO perf_test_wire_ingress');
$check('19 authority recovers to original source data',
    $reader->read('org-demo-b','perf-demo-b','svc-demo-b',$now)?->sourceRevision,2);
$check('20 production-constructed domain policy denies even evidence assembled from DB',
    (new ServiceActionAdmissionPolicy())->decide($req,$e,null,$now)->code,
    'deployment_gate_closed');
putenv('PERF_F19_MOCK_ATTESTED=0');
$check('21 disabled test attestation flag refuses input', $reader->read(
    'org-demo-b','perf-demo-b','svc-demo-b',$now),null);
putenv('PERF_F19_MOCK_ATTESTED=1');
$check('22 role-bound reader cannot select A authorization',
    (int)$motor->query(
        "SELECT count(*) FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-a'"
    )->fetchColumn(),0);
echo "PASS F19 $n source/local read and central-policy checks in $phase\n";
