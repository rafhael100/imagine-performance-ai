<?php
declare(strict_types=1);

namespace Imagine\Performance\Lab;

/**
 * F149: fail-closed source policy for one CI-only sandbox implementation.
 *
 * This is a test for regressions in declared Docker settings and source
 * dependencies, NOT an admission controller for real workloads or a guard
 * against administrators changing the CI code/workflow together.
 */
final class F149SandboxPolicy
{
    /** @return list<string> */
    public static function violations(
        string $dockerfile,string $runner,string $entrypoint
    ):array {
        $errors=[];
        $expected=[
            'src/Lab/F141SignedJournalAuthority.php',
            'src/Lab/F146SyntheticExecutor.php',
            'src/Lab/F147StickyQuarantineExecutor.php',
            'src/Lab/F148WorkloadConstrainedService.php',
            'bin/lab/f148_no_egress_service.php',
        ];
        $lines=preg_split('/\r?\n/',$dockerfile);
        $copy=[];
        $from=0;
        foreach($lines as $line) {
            $line=trim($line);
            if ($line==='' || str_starts_with($line,'#'))continue;
            if (str_starts_with($line,'FROM ')) {
                ++$from;
                if ($line!=='FROM php:8.4-cli-alpine')$errors[]='unapproved_base_image';
                continue;
            }
            if (preg_match('/^COPY (\S+) (\S+)$/D',$line,$m)) {
                if (!in_array($m[1],$expected,true)
                    || $m[2]!=='/app/'.$m[1]) {
                    $errors[]='unapproved_copy';
                }
                $copy[]=$m[1];
                continue;
            }
            if (str_starts_with($line,'WORKDIR ')) {
                if ($line!=='WORKDIR /app')$errors[]='unexpected_workdir';
                continue;
            }
            if (str_starts_with($line,'ENTRYPOINT ')) {
                if ($line!=='ENTRYPOINT ["php", "/app/bin/lab/f148_no_egress_service.php"]') {
                    $errors[]='unexpected_entrypoint';
                }
                continue;
            }
            // No RUN, ADD, EXPOSE, package installation, shell entrypoint,
            // multi-stage image, inherited privileged identity or packages.
            $errors[]='unexpected_docker_instruction';
        }
        sort($copy);sort($expected);
        if($copy!==$expected || $from!==1)$errors[]='docker_copy_or_stage_mismatch';

        foreach ([
            "'--network','none'","'--read-only'",
            "'--cap-drop','ALL'",
            "'--security-opt','no-new-privileges'",
            "'--pids-limit','64'","'--user'",
            "'--mount','type=bind,src='",
        ] as $required) {
            if(!str_contains($runner,$required))$errors[]='sandbox_runtime_policy_missing';
        }
        if(preg_match('/--privileged|--network.{0,12}host|--cap-add|--device(?:=|\s)/i',$runner))
            $errors[]='sandbox_privileged_options';

        foreach([
            "getenv('PERF_F148_NO_EGRESS')!=='1'",
            "getenv('GOOGLE_ADS_ACCESS_TOKEN')!==false",
            "getenv('META_ADS_ACCESS_TOKEN')!==false",
            "getenv('DATABASE_URL')!==false",
            "getenv('F146_GRANT_ISSUER_SECRET')!==false",
            "getenv('F148_WORKLOAD_SECRET')!==false",
        ] as $required) {
            if(!str_contains($entrypoint,$required))$errors[]='executor_startup_guard_missing';
        }
        if (preg_match('/\b(curl_exec|fsockopen|stream_socket_client|socket_create|guzzle|GoogleAdsClient|MetaAdsClient)\s*\(/i',$entrypoint))
            $errors[]='network_or_ads_client_detected';
        return array_values(array_unique($errors));
    }
}
