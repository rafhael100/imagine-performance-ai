<?php
declare(strict_types=1);

/**
 * F147 synthetic process-only CLI. No Google/Meta API, network, billing,
 * database, production OAuth or paid-media capability.
 */
require dirname(__DIR__,2).'/src/Lab/F147StickyQuarantineExecutor.php';

use Imagine\Performance\Lab\F141SignedJournalAuthority;
use Imagine\Performance\Lab\F146SyntheticExecutor;
use Imagine\Performance\Lab\F147StickyQuarantineExecutor;

$denied=['status'=>'executor_bootstrap_denied','simulated'=>false,'action_authorized'=>false];
try {
    if (PHP_SAPI!=='cli'
        || getenv('APP_ENV')!=='testing'
        || getenv('PERF_F141_LAB')!=='1'
        || getenv('PERF_F146_LAB')!=='1'
        || getenv('PERF_F147_LAB')!=='1'
        || getenv('PERF_F146_EXECUTOR_ONLY')!=='1') {
        throw new RuntimeException('F147 not a production service');
    }
    $root=(string)getenv('RUNNER_TEMP');
    $dir=$root.'/f147-executor';
    if (!is_dir($dir) && !mkdir($dir,0700,true)) {
        throw new RuntimeException('F147 isolated receipt directory unavailable');
    }
    $secret=base64_decode((string)getenv('F146_RECEIPT_SECRET'),true);
    $public=base64_decode((string)getenv('F146_RECEIPT_PUBLIC'),true);
    $issuerPublic=base64_decode((string)getenv('F146_GRANT_ISSUER_PUBLIC'),true);
    if ($secret===false || $public===false || $issuerPublic===false) {
        throw new RuntimeException('F147 keys unavailable');
    }
    $journal=new F141SignedJournalAuthority(
        $dir.'/receipt-log.jsonl',$secret,$public
    );
    $executor=new F146SyntheticExecutor(
        $journal,$dir.'/receipt-checkpoint.json',$issuerPublic
    );
    $policy=new F147StickyQuarantineExecutor(
        $executor,$dir.'/sticky-quarantine.json'
    );
    $raw=stream_get_contents(STDIN,32769);
    if (!is_string($raw) || strlen($raw)>32768) {
        throw new RuntimeException('F147 request too large');
    }
    $request=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($request)
        || array_keys($request)!==['grant','fault']
        || !is_array($request['grant'])
        || !in_array($request['fault'],['none','ack_lost','target_down'],true)) {
        throw new RuntimeException('F147 invalid control envelope');
    }
    $result=$policy->execute(
        $request['grant'],
        $request['fault']==='ack_lost',
        $request['fault']==='target_down'
    );
} catch(Throwable $e) {
    $result=$denied;
}
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
