<?php
declare(strict_types=1);
/** A reproducible OFFLINE preview. No external provider, no Portal route. */
if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
   || getenv('PERF_F77_INCIDENT_LAB')!=='1'
   || getenv('PERF_F75_SIGNED_WITNESS_LAB')!=='1'
   || getenv('PERF_F76_RECONCILIATION_LAB')!=='1') {
  fwrite(STDERR,"F77 preview forbidden outside testing mode\n");
  exit(2);
}
$root=dirname(__DIR__,2);
require_once $root.'/src/Domain/Performance/SyntheticSignedRecoveryWitness.php';
require_once $root.'/src/Domain/Performance/SyntheticOfflineProviderReconciler.php';
require_once $root.'/src/Domain/Performance/SyntheticRecoveryIncidentReadModel.php';
$key=sodium_crypto_sign_keypair();
$tenant='tenant_demo_a';
$recovery=str_repeat('c',32);
$ts=1800000000;
$payload=[
  'schema_version'=>'perf.recovery.witness.payload.synthetic.v1',
  'tenant'=>$tenant,'recovery_id'=>$recovery,'epoch'=>3,
  'not_before'=>$ts-2,'expires_at'=>$ts+100,'nonce'=>str_repeat('b',32)
];
$envelope=\Imagine\Performance\Domain\SyntheticSignedRecoveryWitness::signFixture(
    $payload,sodium_bin2hex(sodium_crypto_sign_secretkey($key))
);
$w=(new \Imagine\Performance\Domain\SyntheticSignedRecoveryWitness())->verify(
    $envelope,sodium_bin2hex(sodium_crypto_sign_publickey($key)),
    $tenant,$recovery,3,3,$ts
);
$i=[['intent_id'=>str_repeat('d',32),'tenant'=>$tenant,'operation'=>'pause',
     'request_hash'=>str_repeat('e',64),'epoch'=>3,'approved_mock'=>true]];
$r=(new \Imagine\Performance\Domain\SyntheticOfflineProviderReconciler())
    ->inspect($i,[],$tenant,3);
$report=(new \Imagine\Performance\Domain\SyntheticRecoveryIncidentReadModel())
    ->project($w,$r);
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
