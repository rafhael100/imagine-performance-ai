<?php
declare(strict_types=1);

/**
 * F204 disposable CLI. Example:
 * APP_ENV=testing PERF_F204_CSV_CLI_LAB=1 php tools/perf-export-offline-portfolio-csv.php \
 *   < contracts/demo/f204-offline-portfolio-request.json > /tmp/demo.csv
 *
 * Does not create files itself, accept credentials, call a provider,
 * dispatch actions, bill customers or enable HTTP routes.
 */
if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F204_CSV_CLI_LAB') !== '1'
    || $argc !== 1) {
    fwrite(STDERR,"F204 unavailable outside explicit isolated test mode\n");
    exit(3);
}
$root=dirname(__DIR__);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetRollup.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetCsvExporter.php';

try {
    $input=stream_get_contents(STDIN,20_001);
    if (!is_string($input) || strlen($input)===0 || strlen($input)>20_000) {
        throw new DomainException('Input length out of bounds');
    }
    $decoded=json_decode($input,true,20,JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new DomainException('Expected an offline request object');
    }
    $result=\App\Performance\Planning\OfflinePortfolioBudgetCsvExporter
        ::render($decoded);
    if (($result['simulation_only']??null)!==true
        || ($result['media_execution_allowed']??null)!==false
        || ($result['billing_allowed']??null)!==false
        || !is_string($result['csv']??null)
        || !hash_equals((string)$result['sha256'],
            hash('sha256',$result['csv']))) {
        throw new DomainException('Refuse unsafe offline export');
    }
    $csv=$result['csv'];
    if(fwrite(STDOUT,$csv)!==strlen($csv)){
        throw new DomainException('Incomplete output');
    }
    exit(0);
} catch (Throwable) {
    // Uniform denial; never echo request payload, customer data or stack.
    fwrite(STDERR,"F204 invalid or unavailable synthetic export\n");
    exit(2);
}
