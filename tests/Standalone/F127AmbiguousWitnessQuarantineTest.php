<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/src/Lab/AmbiguousWitnessQuarantine.php';

use Imagine\Performance\Lab\AmbiguousWitnessQuarantine;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
};
$f = hash('sha256', 'synthetic-tenant-A-nonce-1');
$g = hash('sha256', 'synthetic-tenant-B-nonce-1');

$one = new AmbiguousWitnessQuarantine();
$check(!$one->begin('short', $f), 'short IDs denied');
$check(!$one->begin('operation_0001', 'not-a-hash'), 'invalid fingerprint denied');
$check($one->begin('operation_0001', $f), 'first pending operation admitted');
$check(!$one->begin('operation_0001', $f), 'same operation cannot be re-admitted');
$check(!$one->witnessResult('operation_0001', $g, 'committed'), 'different fingerprint rejected');
$check($one->snapshot()['quarantined'], 'contradictory evidence closes circuit');
$check(!$one->begin('operation_0002', $g), 'quarantine blocks even unrelated operations');
$check(!$one->requestLocalRecovery(), 'local recovery forbidden');

$two = new AmbiguousWitnessQuarantine();
$check($two->begin('operation_0003', $f), 'new operation admitted');
$check($two->witnessResult('operation_0003', $f, 'committed'), 'explicit matching commit accepted');
$check(!$two->begin('operation_0003', $f), 'committed ID cannot be reused');
$check(!$two->witnessResult('operation_0003', $f, 'committed'), 'duplicate witness result rejected');
$check($two->snapshot()['quarantined'], 'contradictory repeated callback quarantines');

$three = new AmbiguousWitnessQuarantine();
$check($three->begin('operation_0004', $f), 'pending before timeout');
$check(!$three->witnessResult('operation_0004', $f, 'timeout'), 'timeout never approved');
$check($three->snapshot()['entries']['operation_0004']['state'] === 'quarantined', 'uncertain state recorded');
$check(!$three->witnessResult('operation_0004', $f, 'committed'), 'late committed response cannot clear quarantine');
$check(!$three->begin('operation_0005', $g), 'other tenant blocked by global uncertainty');

$four = new AmbiguousWitnessQuarantine();
$check($four->begin('operation_0006', $f), 'pending before explicit reject');
$check(!$four->witnessResult('operation_0006', $f, 'rejected'), 'explicit rejection not admission');
$check(!$four->snapshot()['quarantined'], 'verified rejection need not quarantine');
$check($four->begin('operation_0007', $g), 'fresh operation allowed after definite rejection');
$check(!$four->witnessResult('operation_9999', $g, 'committed'), 'unknown receipt rejected');
$check($four->snapshot()['quarantined'], 'unknown receipt closes circuit');

echo "F127 laboratory checks: {$checks} PASS / 0 FAIL\n";
