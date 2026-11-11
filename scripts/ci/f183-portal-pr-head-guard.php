<?php
declare(strict_types=1);

/**
 * F183: consume an authentic git ls-remote line from Portal PR#356.
 *
 * The pin is from the committed F174 versioned readiness manifest. A
 * changed PR head must BLOCK promotion until the F62/F63 interoperability
 * suite is revalidated against the new SHA, never silently fall back.
 *
 * Live network discovery is performed by GitHub Actions (git ls-remote);
 * this parser never fetches a URL or operates on the Laravel runtime.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F183_PORTAL_PIN_LAB')!=='1'
    ||count($argv)!==1){
    fwrite(STDERR,"F183 disabled outside synthetic CI\n");
    exit(2);
}
$root=dirname(__DIR__,2);
try{
    $manifest=json_decode(
        (string)file_get_contents(
            $root.'/contracts/security/f174-portal-candidate-readiness.json'
        ),
        true,32,JSON_THROW_ON_ERROR
    );
}catch(Throwable){
    fwrite(STDERR,"F183 blocked: invalid pinned Portal contract manifest\n");
    exit(3);
}
if(!is_array($manifest)
    ||($manifest['schema_version']??null)!==
        'perf.portal.integration-readiness.pinned-ci.v1'
    ||($manifest['source_repository']??null)!=='rafhael100/imagine-portal'
    ||($manifest['candidate']['pr_number']??null)!==356
    ||($manifest['candidate']['source_is_pinned_snapshot_only']??null)!==true
    ||($manifest['target_status']??null)!=='NO_GO'
    ||!is_string($manifest['candidate']['ref']??null)
    ||preg_match('/^[a-f0-9]{40}$/D',$manifest['candidate']['ref'])!==1
    ||($manifest['expected_security_assertions']['portal_iam_verified']??null)!==false
    ||($manifest['expected_security_assertions']['runtime_deployment_authorized']??null)!==false){
    fwrite(STDERR,"F183 blocked: candidate pin or NO-GO gates invalid\n");
    exit(3);
}
$line=stream_get_contents(STDIN,1024);
if(!is_string($line)
    ||!preg_match('/^([a-f0-9]{40})\trefs\/pull\/356\/head\r?\n?$/D',
        $line,$matches)){
    fwrite(STDERR,"F183 blocked: missing or malformed GitHub pull-request head\n");
    exit(3);
}
$found=$matches[1];
$pinned=$manifest['candidate']['ref'];
if(!hash_equals($pinned,$found)){
    fwrite(STDERR,"F183 BLOCKED: Imagine Portal PR #356 changed HEAD. ".
        "Pinned={$pinned}; observed={$found}. ".
        "Inspect/re-run original Portal F62/F63 contract and update pin after review.\n");
    exit(3);
}
echo "F183 PASS: Portal PR #356 HEAD is the approved CI snapshot {$pinned}; ".
    "production authorization still NO-GO\n";
