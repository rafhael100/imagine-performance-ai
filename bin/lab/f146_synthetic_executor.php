<?php
declare(strict_types=1);

/**
 * F146 child-process entrypoint. Reads ONE JSON synthetic grant from stdin.
 * It has no provider SDK imports, network client, credentials or Ads calls.
 */
require dirname(__DIR__,2).'/src/Lab/F146SyntheticExecutor.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F146SyntheticExecutor;

$denied=['status'=>'executor_bootstrap_denied','simulated'=>false,'action_authorized'=>false];
try {
    if (PHP_SAPI!=='cli'
        || getenv('APP_ENV')!=='testing'
        || getenv('PERF_F146_LAB')!=='1'
        || getenv('PERF_F146_EXECUTOR_ONLY')!=='1') {
        throw new RuntimeException('F146 not a production worker');
    }
    $runner=(string)getenv('RUNNER_TEMP');
    $dir=$runner.'/f146-executor';
    if (!is_dir($dir) && !mkdir($dir,0700,true)) {
        throw new RuntimeException('F146 isolated receipt directory unavailable');
    }
    $secret=base64_decode((string)getenv('F146_RECEIPT_SECRET'),true);
    $public=base64_decode((string)getenv('F146_RECEIPT_PUBLIC'),true);
    $issuerPublic=base64_decode((string)getenv('F146_GRANT_ISSUER_PUBLIC'),true);
    if ($secret===false || $public===false || $issuerPublic===false) {
        throw new RuntimeException('F146 missing synthetic-only execution keys');
    }
    $journal=new F141SignedJournalAuthority(
        $dir.'/receipt-log.jsonl',$secret,$public
    );
    $worker=new F146SyntheticExecutor(
        $journal,$dir.'/receipt-checkpoint.json',$issuerPublic
    );
    $raw=stream_get_contents(STDIN,32769);
    if (!is_string($raw) || strlen($raw)>32768) {
        throw new RuntimeException('F146 input too large');
    }
    $request=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($request)) throw new RuntimeException('F146 invalid input object');
    $result=$worker->execute($request);
} catch(Throwable $e) {
    // Never leak secrets or stacktraces to the caller in real integrations.
    $result=$denied;
}
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
