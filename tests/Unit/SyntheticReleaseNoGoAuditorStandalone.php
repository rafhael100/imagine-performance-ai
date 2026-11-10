<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('PERF_F79_RELEASE_AUDIT_LAB=1');
$root=dirname(__DIR__,2);
require_once $root.'/src/Domain/Performance/SyntheticReleaseNoGoAuditor.php';
use Imagine\Performance\Domain\SyntheticReleaseNoGoAuditor;
$policy=json_decode(file_get_contents($root.'/contracts/lab/ci/performance-release-no-go.json'),true,16,JSON_THROW_ON_ERROR);
$bootstrap=file_get_contents($root.'/bootstrap/app.php');
$p=new SyntheticReleaseNoGoAuditor();
$cases=[
  ['baseline',$policy,$bootstrap,'BLOCKED_AS_EXPECTED_NO_GO'],
  ['missing policy',null,$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['invalid json semantics',[],$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['no bootstrap',$policy,null,'INVALID_EVIDENCE_NO_GO'],
  ['bad policy version',array_replace($policy,['schema_version'=>'wrong']),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['mode elevated',array_replace($policy,['mode'=>'production']),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['release elevated',array_replace($policy,['release_state'=>'ready']),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['advertising enabled',array_replace($policy,['external_provider_dispatch_allowed'=>true]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['billing enabled',array_replace($policy,['billing_allowed'=>true]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['activation enabled',array_replace($policy,['operational_activation_allowed'=>true]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['merge enabled',array_replace($policy,['automatic_merge_allowed'=>true]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['portal claim',array_replace($policy,['real_portal_or_crm_authority_connected'=>true]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['P0 forged',array_replace($policy,['blocking_gates'=>array_replace($policy['blocking_gates'],['P0'=>'closed'])]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['provider fake',array_replace($policy,['blocking_gates'=>array_replace($policy['blocking_gates'],['provider_idempotency'=>'proven'])]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['appsec false approval',array_replace($policy,['blocking_gates'=>array_replace($policy['blocking_gates'],['AppSec-DBA'=>'approved'])]),$bootstrap,'INVALID_EVIDENCE_NO_GO'],
  ['web routes',$policy,$bootstrap."\n// web: routes/web.php",'INVALID_EVIDENCE_NO_GO'],
  ['api routes',$policy,$bootstrap."\n// api: routes/api.php",'INVALID_EVIDENCE_NO_GO'],
  ['no route declaration',$policy,'<?php return null;','INVALID_EVIDENCE_NO_GO'],
];
$n=0;
foreach($cases as [$label,$data,$routing,$decision]) {
  $out=$p->assess($data,$routing);
  if($out['decision']!==$decision)throw new RuntimeException("F79 $label ".json_encode($out));
  ++$n;
  foreach(['eligible_to_merge_main','eligible_to_deploy','eligible_to_dispatch',
           'eligible_to_bill','ready_for_production'] as $flag) {
    if($out[$flag]!==false)throw new RuntimeException("F79 $label/$flag");
    ++$n;
  }
}
putenv('PERF_F79_RELEASE_AUDIT_LAB=0');
if($p->assess($policy,$bootstrap)['decision']!=='LAB_DISABLED_NO_GO') throw new RuntimeException('F79 lab disabled fail');
++$n;
putenv('PERF_F79_RELEASE_AUDIT_LAB=1');
putenv('APP_ENV=production');
if($p->assess($policy,$bootstrap)['decision']!=='LAB_DISABLED_NO_GO') throw new RuntimeException('F79 production fail');
++$n;
echo "PASS F79 {$n} no-go release audit assertions; no GO path exists\n";
