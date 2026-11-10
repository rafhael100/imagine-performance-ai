<?php

declare(strict_types=1);

// F1-25: stdout-only synthetic PostgreSQL contract exporter in disposable CI.
require_once __DIR__.'/../contracts/lab/php/SyntheticCampaignPortalProjection.php';

use Imagine\Protocol\Lab\SyntheticCampaignPortalProjection;

if (PHP_SAPI !== 'cli'
    || getenv('APP_ENV') !== 'testing'
    || getenv('PERF_F25_EXPORT_LAB') !== '1'
    || getenv('PERF_F24_READ_MODEL_LAB') !== '1'
    || getenv('PGHOST') !== '127.0.0.1'
    || getenv('PGPORT') !== '5432'
    || getenv('PGDATABASE') !== 'perf_test_campaign_registry') {
    fwrite(STDERR,"F1-25 exporter refused outside test environment\n");
    exit(2);
}
try {
    $db=new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
        'perf_f24_reader','ci-f24-read-only',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_EMULATE_PREPARES=>false]
    );
    $body=(new SyntheticCampaignPortalProjection($db))
        ->exportJson('org-demo-a','perf-demo-a');
    if (!is_string($body) || strlen($body)<200 || strlen($body)>32768) {
        throw new RuntimeException('Invalid demo-only Portal contract.');
    }
    fwrite(STDOUT,$body);
} catch (Throwable) {
    fwrite(STDERR,"F1-25 failed closed; no preview output\n");
    exit(1);
}
