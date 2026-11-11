<?php
declare(strict_types=1);

/**
 * One-shot F148 lab contract entrypoint.
 * No network calls, providers, advertising credentials or billing code.
 */
require dirname(__DIR__,2).'/src/Lab/F148WorkloadConstrainedService.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F146SyntheticExecutor;
use Imagine\Performance\Lab\F147StickyQuarantineExecutor;
use Imagine\Performance\Lab\F148WorkloadConstrainedService;

$denied=['status'=>'executor_bootstrap_denied','simulated'=>false,'action_authorized'=>false];
try {
    if (PHP_SAPI!=='cli'
        || getenv('APP_ENV')!=='testing'
        || getenv('PERF_F141_LAB')!=='1'
        || getenv('PERF_F146_LAB')!=='1'
        || getenv('PERF_F147_LAB')!=='1'
        || getenv('PERF_F148_LAB')!=='1'
        || getenv('PERF_F146_EXECUTOR_ONLY')!=='1'
        || getenv('PERF_F148_NO_EGRESS')!=='1'
        || getenv('GOOGLE_ADS_ACCESS_TOKEN')!==false
        || getenv('META_ADS_ACCESS_TOKEN')!==false
        || getenv('GOOGLE_ADS_REFRESH_TOKEN')!==false
        || getenv('META_ACCESS_TOKEN')!==false
        || getenv('DATABASE_URL')!==false
        || getenv('F146_GRANT_ISSUER_SECRET')!==false
        || getenv('F148_WORKLOAD_SECRET')!==false) {
        throw new RuntimeException('F148 forbids production environment/credentials');
    }
    $root=(string)getenv('RUNNER_TEMP');
    if ($root!=='/sandbox') throw new RuntimeException('F148 fixed sandbox only');
    $dir=$root.'/f148-executor';
    if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) {
        throw new RuntimeException('F148 receipt directory cannot be created');
    }
    $decodeKey=static function(string $env):string {
        $value=base64_decode((string)getenv($env),true);
        if ($value===false) throw new RuntimeException('F148 test key invalid');
        return $value;
    };
    $receipts=new F141SignedJournalAuthority(
        $dir.'/receipt-log.jsonl',
        $decodeKey('F146_RECEIPT_SECRET'),
        $decodeKey('F146_RECEIPT_PUBLIC')
    );
    $executor=new F146SyntheticExecutor(
        $receipts,
        $dir.'/receipt-checkpoint.json',
        $decodeKey('F146_GRANT_ISSUER_PUBLIC')
    );
    $quarantine=new F147StickyQuarantineExecutor(
        $executor,$dir.'/sticky-quarantine.json'
    );
    $service=new F148WorkloadConstrainedService(
        $quarantine,
        $decodeKey('F148_WORKLOAD_PUBLIC')
    );
    $raw=stream_get_contents(STDIN,32769);
    if (!is_string($raw) || strlen($raw)>32768) {
        throw new RuntimeException('F148 request exceeds limit');
    }
    $request=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($request)) throw new RuntimeException('F148 invalid JSON');
    $result=$service->handle($request);
} catch(Throwable $e) {
    $result=$denied;
}
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
