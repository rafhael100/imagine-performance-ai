<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/src/Lab/SyntheticWitnessReconciliation.php';
use Imagine\Performance\Lab\SyntheticWitnessReconciliation;

$subject = new SyntheticWitnessReconciliation();
$base = ['operation_id' => 'operation_0001',
    'fingerprint' => hash('sha256', 'synthetic-only'),
    'epoch' => 8, 'outcome' => 'committed', 'authority' => 'primary'];
$other = $base;
$other['authority'] = 'witness';
$n = 0;
$check = static function (bool $ok, string $label) use (&$n): void {
    ++$n;
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
};
$run = static fn(array $a,array $b,int $floor=8): array => $subject->inspect($a,$b,$floor);
$ok = $run($base,$other);
$check($ok['classification'] === 'consistent_committed', 'matching committed evidence classified');
$check($ok['quarantine'] === true, 'matching receipts never clear quarantine');
$check($ok['reason'] === 'matching_fixture_requires_independent_review', 'matching is not authorization');
$a=$base; $a['outcome']='rejected'; $b=$other; $b['outcome']='rejected';
$check($run($a,$b)['classification'] === 'consistent_rejected', 'matching reject classified');
$check($run($a,$b)['quarantine'], 'matching reject also remains quarantined');
$a=$base; $a['outcome']='absent'; $b=$other; $b['outcome']='absent';
$check($run($a,$b)['classification'] === 'ambiguous', 'two absences not proof');
$a=$base; $a['outcome']='rejected';
$check($run($a,$other)['reason']==='outcome_disagreement', 'conflicting outcomes fail closed');
$a=$base; $a['epoch']=7;
$check($run($a,$other)['reason']==='invalid_or_stale_evidence', 'stale primary epoch denied');
$check($run($base,$other,9)['reason']==='invalid_or_stale_evidence', 'floor cannot roll back');
$check($run($base,$other,0)['reason']==='missing_epoch_floor', 'zero floor denied');
$a=$base; $a['epoch']=9;
$check($run($a,$other)['reason']==='epoch_disagreement', 'epochs must match');
$a=$base; $a['operation_id']='operation_0002';
$check($run($a,$other)['reason']==='different_operation', 'IDs must match');
$a=$base; $a['fingerprint']=hash('sha256','different');
$check($run($a,$other)['reason']==='different_operation', 'fingerprints must match');
$a=$base; $a['authority']='witness';
$check($run($a,$other)['reason']==='wrong_authority_roles', 'role substitution denied');
$a=$base; unset($a['fingerprint']);
$check($run($a,$other)['reason']==='missing_fields', 'missing field denied');
$a=$base; $a['epoch']='8';
$check($run($a,$other)['reason']==='invalid_or_stale_evidence', 'string epoch denied');
$a=$base; $a['outcome']='timeout';
$check($run($a,$other)['reason']==='unknown_outcome', 'unknown outcome denied');
$check($run($base,$other,8)['quarantine'], 'consistent case remains quarantined');
echo "F128 synthetic reconciliation: {$n} PASS / 0 FAIL\n";
