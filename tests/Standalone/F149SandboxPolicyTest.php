<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Lab/F149SandboxPolicy.php';

use Imagine\Performance\Lab\F149SandboxPolicy;

if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F149_LAB')!=='1') {
    throw new RuntimeException('F149 static sandbox guard CI-only');
}
$root=dirname(__DIR__,2);
$get=static function(string $path)use($root):string {
    $contents=file_get_contents($root.'/'.$path);
    if(!is_string($contents))throw new RuntimeException('Missing F149 policy input');
    return $contents;
};
$docker=$get('Dockerfile.f148.no-egress.lab');
$runner=$get('tests/Standalone/F148NoEgressWorkloadContainerTest.php');
$entry=$get('bin/lab/f148_no_egress_service.php');
$checkCount=0;
$check=static function(bool $success,string $why)use(&$checkCount):void{
    ++$checkCount;
    if(!$success)throw new RuntimeException('F149 FAIL: '.$why);
};
$violations=static fn(string $d,string $r,string $e):array=>
    F149SandboxPolicy::violations($d,$r,$e);
$check($violations($docker,$runner,$entry)===[],
    'unchanged minimal Dockerfile, runtime flags and token guards approved');
$check(in_array('unapproved_base_image',
 $violations(str_replace('FROM php:8.4-cli-alpine','FROM ubuntu:latest',$docker),$runner,$entry),true),
 'unapproved base image fails static policy');
$check(in_array('unapproved_copy',
 $violations($docker."\nCOPY . /app/\n",$runner,$entry),true),
 'copying whole product repository into sandbox is forbidden');
$check(in_array('unexpected_docker_instruction',
 $violations($docker."\nRUN apk add curl\n",$runner,$entry),true),
 'introducing network-capable package installation fails policy');
$check(in_array('sandbox_runtime_policy_missing',
 $violations($docker,str_replace("'--network','none'","'--network','host'",$runner),$entry),true),
 'changing network mode to host fails policy');
$check(in_array('sandbox_runtime_policy_missing',
 $violations($docker,str_replace("'--read-only'","'--read-write'",$runner),$entry),true),
 'removing readonly rootfs flag fails policy');
$check(in_array('sandbox_runtime_policy_missing',
 $violations($docker,str_replace("'--cap-drop','ALL'","'--cap-drop','NONE'",$runner),$entry),true),
 'removing capability drop fails policy');
$check(in_array('sandbox_runtime_policy_missing',
 $violations($docker,str_replace("'--security-opt','no-new-privileges'","'--security-opt','apparmor=unconfined'",$runner),$entry),true),
 'removing no-new-privileges fails policy');
$check(in_array('sandbox_runtime_policy_missing',
 $violations($docker,str_replace("'--user'","'--user-id-not-set'",$runner),$entry),true),
 'removing explicit unprivileged UID fails policy');
$check(in_array('sandbox_privileged_options',
 $violations($docker,$runner."\n'--privileged'",$entry),true),
 'privileged Docker flag prohibited');
$check(in_array('executor_startup_guard_missing',
 $violations($docker,$runner,str_replace(
 "getenv('GOOGLE_ADS_ACCESS_TOKEN')!==false",
 "getenv('GOOGLE_ADS_ACCESS_TOKEN')===false",$entry
 )),true), 'removing Google Ads token denial fails policy');
$check(in_array('executor_startup_guard_missing',
 $violations($docker,$runner,str_replace(
 "getenv('DATABASE_URL')!==false",
 "getenv('DATABASE_URL')===false",$entry
 )),true),'removing database credential denial fails policy');
$check(in_array('network_or_ads_client_detected',
 $violations($docker,$runner,$entry."\ncurl_exec('https://example.invalid');\n"),true),
 'adding outbound HTTP call to executor is forbidden');
$check(in_array('unexpected_entrypoint',
 $violations(str_replace(
 'ENTRYPOINT ["php", "/app/bin/lab/f148_no_egress_service.php"]',
 'ENTRYPOINT ["sh", "-c", "php /app/bin/lab/f148_no_egress_service.php"]',
 $docker),$runner,$entry),true),
 'shell entrypoint prevents privilege minimization and fails policy');
echo "F149 sandbox policy: {$checkCount} PASS / 0 FAIL — CI only / NO-GO\n";
