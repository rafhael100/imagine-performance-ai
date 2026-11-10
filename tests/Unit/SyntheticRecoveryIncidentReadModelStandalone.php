<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('PERF_F75_SIGNED_WITNESS_LAB=1');
putenv('PERF_F76_RECONCILIATION_LAB=1');
putenv('PERF_F77_INCIDENT_LAB=1');
$root=dirname(__DIR__,2);
require_once $root.'/src/Domain/Performance/SyntheticSignedRecoveryWitness.php';
require_once $root.'/src/Domain/Performance/SyntheticOfflineProviderReconciler.php';
require_once $root.'/src/Domain/Performance/SyntheticRecoveryIncidentReadModel.php';
use Imagine\Performance\Domain\SyntheticSignedRecoveryWitness;
use Imagine\Performance\Domain\SyntheticOfflineProviderReconciler;
use Imagine\Performance\Domain\SyntheticRecoveryIncidentReadModel;

$key=sodium_crypto_sign_keypair();
$secret=sodium_bin2hex(sodium_crypto_sign_secretkey($key));
$public=sodium_bin2hex(sodium_crypto_sign_publickey($key));
$tenant='tenant_test_a';
$recovery=str_repeat('b',32);
$t=1800000000;
$payload=[
 'schema_version'=>'perf.recovery.witness.payload.synthetic.v1',
 'tenant'=>$tenant,'recovery_id'=>$recovery,'epoch'=>2,
 'not_before'=>$t-5,'expires_at'=>$t+100,'nonce'=>str_repeat('a',32)
];
$envelope=SyntheticSignedRecoveryWitness::signFixture($payload,$secret);
$witness=(new SyntheticSignedRecoveryWitness())->verify($envelope,$public,$tenant,$recovery,2,2,$t);
$intent=[['intent_id'=>str_repeat('c',32),'tenant'=>$tenant,'operation'=>'pause',
 'request_hash'=>str_repeat('d',64),'epoch'=>2,'approved_mock'=>true]];
$receipt=[['receipt_id'=>str_repeat('e',32),'intent_id'=>str_repeat('c',32),
 'tenant'=>$tenant,'request_hash'=>str_repeat('d',64),'epoch'=>2,'status'=>'accepted_mock']];
$mock=(new SyntheticOfflineProviderReconciler())->inspect($intent,$receipt,$tenant,2);
$pending=(new SyntheticOfflineProviderReconciler())->inspect($intent,[],$tenant,2);
$conflict=(new SyntheticOfflineProviderReconciler())->inspect($intent,[array_replace($receipt[0],['request_hash'=>str_repeat('0',64)])],$tenant,2);
$triage=new SyntheticRecoveryIncidentReadModel();
$cases=[
 ['matched mock',$witness,$mock,'manual_governance_required'],
 ['pending',$witness,$pending,'manual_reconciliation_required'],
 ['conflict',$witness,$conflict,'security_quarantine'],
 ['not current',array_replace($witness,['status'=>'not_current','signature_valid'=>false]),$mock,'security_quarantine'],
 ['bad witness schema',array_replace($witness,['schema_version'=>'wrong']),$mock,'invalid_evidence_quarantine'],
 ['untrusted boolean elevated',array_replace($witness,['external_root_trusted'=>true]),$mock,'invalid_evidence_quarantine'],
 ['fake dispatch',array_replace($witness,['dispatch_enabled'=>true]),$mock,'invalid_evidence_quarantine'],
 ['fake provider', $witness,array_replace($mock,['provider_was_contacted'=>true]),'invalid_evidence_quarantine'],
 ['fake billing', $witness,array_replace($mock,['billing_enabled'=>true]),'invalid_evidence_quarantine'],
 ['invalid counts',$witness,array_replace($mock,['counts'=>['intents'=>-1,'receipts'=>1,'pending'=>0]]),'invalid_evidence_quarantine'],
 ['missing counts',$witness,array_replace($mock,['counts'=>[]]),'invalid_evidence_quarantine'],
 ['wrong order',$witness,array_reverse($mock,true),'invalid_evidence_quarantine'],
 ['null witness',null,$mock,'invalid_evidence_quarantine'],
 ['null receipt',$witness,null,'invalid_evidence_quarantine'],
 ['invalid status',$witness,array_replace($mock,['status'=>'authorized']),'invalid_evidence_quarantine'],
 ['unknown field',array_merge($witness,['access_token'=>'secret']),$mock,'invalid_evidence_quarantine'],
];
$assertions=0;
foreach($cases as [$name,$w,$r,$expected]) {
 $out=$triage->project($w,$r);
 if($out['status']!==$expected) throw new RuntimeException("F77 $name ".json_encode($out));
 ++$assertions;
 foreach(['external_root_trusted','provider_evidence_trusted','authorization_eligible',
          'read_preview_eligible','ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
   if($out[$flag]!==false)throw new RuntimeException("F77 $name unsafe $flag");
   ++$assertions;
 }
 if($out['human_review_required']!==true || $out['simulation_only']!==true) {
   throw new RuntimeException("F77 $name must remain simulation requiring review");
 }
 $assertions+=2;
}
putenv('APP_ENV=production');
if($triage->project($witness,$mock)['status']!=='lab_disabled') throw new RuntimeException('F77 production fail');
++$assertions;
echo "PASS F77 {$assertions} incident triage assertions; all operational flags denied\n";
