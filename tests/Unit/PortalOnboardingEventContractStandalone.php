<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('PERF_F83_ONBOARDING_PARITY_LAB=1');
$root=dirname(__DIR__,2);
require_once $root.'/app/Performance/Contracts/PortalOnboardingEventContract.php';

use App\Performance\Contracts\PortalOnboardingEventContract;

$portalRoot=getenv('PORTAL_CHECKOUT_ROOT');
if (!is_string($portalRoot)||$portalRoot==='') {
    throw new RuntimeException('F83 requires pinned Portal checkout as an input');
}
$portalBase=$portalRoot.'/apps/portal/app/Domain';
require_once $portalBase.'/Organizations/OrganizationId.php';
require_once $portalBase.'/PerformanceOnboarding/DemoOnboardingId.php';
require_once $portalBase.'/PerformanceOnboarding/DemoPerformanceEvent.php';

$org=new \App\Domain\Organizations\OrganizationId('org-demo-a');
$onboard=new \App\Domain\PerformanceOnboarding\DemoOnboardingId('demo_onboard-a');
$event=new \App\Domain\PerformanceOnboarding\DemoPerformanceEvent(
    'evt-demo-01','corr-demo-01',$org,$onboard,str_repeat('a',64)
);
$payload=[
    'schema_version'=>\App\Domain\PerformanceOnboarding\DemoPerformanceEvent::SCHEMA_VERSION,
    'event_type'=>\App\Domain\PerformanceOnboarding\DemoPerformanceEvent::EVENT_TYPE,
    'producer'=>\App\Domain\PerformanceOnboarding\DemoPerformanceEvent::PRODUCER,
    'event_id'=>$event->eventId,
    'correlation_id'=>$event->correlationId,
    'portal_organization_id'=>$event->organizationId->value,
    'demo_onboarding_id'=>$event->onboardingId->value,
    'proposal_fingerprint'=>$event->proposalFingerprint,
];
$raw=json_encode($payload,JSON_THROW_ON_ERROR);
$sourceSha=$event->fingerprint();
$validator=new PortalOnboardingEventContract();
$count=0;
$assert=static function(bool $ok,string $reason)use(&$count):void{
    if(!$ok) throw new RuntimeException('F83 '.$reason);
    ++$count;
};
$verify=static fn(string $json,string $org,string $ob,?string $sha=null)=>
    $validator->validate($json,$org,$ob,$sha);
$good=$verify($raw,$org->value,$onboard->value,$sourceSha);
$assert($good['status']==='matched_untrusted_demo_event','actual Portal event accepted as mock only');
$assert($good['fingerprint_sha256']===$sourceSha,'Portal SHA-256 byte-for-byte parity');
$mut=static fn(string $field,mixed $value):string=>json_encode(
    array_replace($payload,[$field=>$value]),JSON_THROW_ON_ERROR
);
$cases=[
    ['different org',$raw,'org-demo-b',$onboard->value,$sourceSha,'context_mismatch'],
    ['different onboard',$raw,$org->value,'demo_other',$sourceSha,'context_mismatch'],
    ['wrong fingerprint',$raw,$org->value,$onboard->value,str_repeat('b',64),'fingerprint_mismatch'],
    ['wrong version',$mut('schema_version','1.0'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['wrong event',$mut('event_type','performance.onboarding.real_requested'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['wrong producer',$mut('producer','portal-imagine'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['wrong event ID',$mut('event_id','evt-real'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['wrong correlation',$mut('correlation_id','corr-real'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['invalid sha',$mut('proposal_fingerprint','not-sha'),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['wrong ID type',$mut('portal_organization_id',12),$org->value,$onboard->value,null,'unsupported_portal_demo_contract'],
    ['unknown extra property',json_encode([...$payload,'billing_enabled'=>true],JSON_THROW_ON_ERROR),$org->value,$onboard->value,null,'invalid_shape_or_order'],
    ['reordered keys',json_encode(array_reverse($payload,true),JSON_THROW_ON_ERROR),$org->value,$onboard->value,null,'invalid_shape_or_order'],
    ['wrong context argument',$raw,'../bad',$onboard->value,null,'invalid_expected_context'],
    ['broken json',str_repeat('{',40),$org->value,$onboard->value,null,'invalid_json'],
    ['duplicate key',substr($raw,0,-1).',"event_id":"evt-demo-99"}',$org->value,$onboard->value,null,'noncanonical_json'],
    ['whitespace',str_replace(',"event_type"',', "event_type"',$raw),$org->value,$onboard->value,null,'noncanonical_json'],
];
foreach($cases as [$label,$json,$o,$ob,$sha,$expected]) {
    $v=$verify($json,$o,$ob,$sha);
    $assert($v['status']===$expected,"case $label: ".json_encode($v));
    $assert($v['fingerprint_sha256']===null,"case $label cannot expose accepted digest");
    foreach(['portal_authenticated','provisioning_allowed','provider_connected',
        'authorization_eligible','dispatch_enabled','billing_enabled','ready_for_production'] as $flag) {
        $assert($v[$flag]===false,"case $label must deny $flag");
    }
}
foreach(['portal_authenticated','provisioning_allowed','provider_connected',
    'authorization_eligible','dispatch_enabled','billing_enabled','ready_for_production'] as $flag) {
    $assert($good[$flag]===false,"valid mock cannot enable $flag");
}
putenv('PERF_F83_ONBOARDING_PARITY_LAB=0');
$assert($verify($raw,$org->value,$onboard->value,$sourceSha)['status']==='lab_disabled','flag disabled');
putenv('PERF_F83_ONBOARDING_PARITY_LAB=1');
putenv('APP_ENV=production');
$assert($verify($raw,$org->value,$onboard->value,$sourceSha)['status']==='lab_disabled','production denied');
echo "PASS F83 {$count} validations against actual Portal event/fingerprint\n";
