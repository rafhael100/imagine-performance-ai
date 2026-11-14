<?php
declare(strict_types=1);

// Run as CI-only fixture creator, emitting only sanitized synthetic SQL.
// psql ingests the output as superuser BEFORE the runtime app user executes tests.
require_once __DIR__ . '/pg_v5_multiagreement_cases.php';
if (getenv('APP_ENV') !== 'testing' ||
    getenv('PERF_PG_MULTIAGREEMENT_V5_LAB') !== '1') {
    throw new RuntimeException('Synthetic agreement fixture seed forbidden.');
}
$fixture=V5MultiAgreementCases::prepare();
echo "-- CI TEST ONLY — synthetic signed agreement bindings, no PII or secrets\n";
foreach ($fixture['cases'] as $case) {
    $values=[$case['tenant'],$case['agreement'],$case['period'],
             $fixture['catalog_sha256'],$case['quote_hash']];
    foreach ($values as $value) {
        if (!preg_match('/\A[A-Za-z0-9_-]{3,100}\z/D', $value)) {
            throw new RuntimeException('Unsafe fixture SQL attribute.');
        }
    }
    echo "INSERT INTO perf_fin_multi_lab.agreement_quote_bindings " .
         "(tenant_id,agreement_id,period,catalog_sha256,quote_claim_sha256) VALUES (" .
         implode(',',array_map(static fn(string $x):string => "'".$x."'", $values)) .
         ");\n";
}
