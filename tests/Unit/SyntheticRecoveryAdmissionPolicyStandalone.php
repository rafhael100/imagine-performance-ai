<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/Domain/Performance/SyntheticRecoveryAdmissionPolicy.php';

use Imagine\Performance\Domain\SyntheticRecoveryAdmissionPolicy;

$p = new SyntheticRecoveryAdmissionPolicy();
$w = [
    'schema_version' => 'perf.recovery.witness.untrusted.v1',
    'authority_id' => 'synthetic-authority',
    'minimum_epoch' => 2,
    'nonce' => str_repeat('a', 32),
];
$count = 0;
$assert = static function (bool $ok, string $label) use (&$count): void {
    if (!$ok) throw new RuntimeException('FAIL F74 ' . $label);
    ++$count;
};
$cases = [
    [$w, 2, 2, 'untrusted_witness_cannot_authorize'],
    [$w, 1, 1, 'rollback_suspected'],
    [$w, 1, 2, 'rollback_suspected'],
    [$w, 2, 1, 'rollback_suspected'],
    [$w, 3, 2, 'epoch_divergence'],
    [$w, 2, 3, 'epoch_divergence'],
    [$w, 3, 3, 'untrusted_witness_cannot_authorize'],
    [null, 2, 2, 'evidence_invalid'],
    [false, 2, 2, 'evidence_invalid'],
    [['schema_version'=>'x'], 2, 2, 'evidence_invalid'],
    [array_merge($w, ['extra' => true]), 2, 2, 'evidence_invalid'],
    [array_replace($w, ['minimum_epoch'=> '2']), 2, 2, 'evidence_invalid'],
    [array_replace($w, ['minimum_epoch'=> 0]), 2, 2, 'evidence_invalid'],
    [array_replace($w, ['minimum_epoch'=> 1000001]), 2, 2, 'evidence_invalid'],
    [array_replace($w, ['authority_id'=> '../evil']), 2, 2, 'evidence_invalid'],
    [array_replace($w, ['nonce'=> 'bad']), 2, 2, 'evidence_invalid'],
    [$w, '2', 2, 'evidence_invalid'],
    [$w, 2, '2', 'evidence_invalid'],
    [$w, 0, 2, 'evidence_invalid'],
    [$w, 2, 1000001, 'evidence_invalid'],
];
foreach ($cases as $i => [$witness, $db, $envelope, $status]) {
    $result = $p->inspect($witness, $db, $envelope);
    $assert($result['status'] === $status, "case {$i} status");
    $assert($result['schema_version'] === 'perf.recovery.admission.synthetic.v1', "case {$i} schema");
    foreach (['witness_is_authenticated','monotonicity_proven','read_preview_eligible',
              'authorization_eligible','ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
        $assert($result[$flag] === false, "case {$i} {$flag} never elevated");
    }
}
$old = array_replace($w, ['minimum_epoch'=>1]);
$replayed = $p->inspect($old, 1, 1);
$assert($replayed['status']==='untrusted_witness_cannot_authorize', 'triple rollback is NOT proof of safety');
$assert($replayed['authorization_eligible']===false, 'triple rollback never authorizes');
echo "PASS F74 {$count} recovery-admission contract assertions (synthetic only)\n";
