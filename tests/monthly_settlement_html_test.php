<?php
declare(strict_types=1);

require_once __DIR__ . '/monthly_settlement_candidate_test.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementReviewHtml.php';

use Imagine\Performance\Commercial\MonthlySettlementCandidate as Candidate;
use Imagine\Performance\Commercial\MonthlySettlementReviewHtml as Html;

$tenant = '33333333-3333-4333-8333-333333333333';
$details = Candidate::build($tenant, syntheticQuote(), '2026-11', syntheticRows());
$html = Html::render($details);
foreach ([
    'DEMONSTRAÇÃO NÃO HOMOLOGADA',
    'Honorários antecipados',
    'R$ 280,00',
    'R$ 240,00',
    '−R$ 40,00',
    'R$ 1.200,00',
    'Google Ads',
    'Meta Ads',
    'Content-Security-Policy',
    'noindex,nofollow,noarchive',
] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Missing expected HTML evidence: ' . $expected);
    }
}
foreach (['<script','<iframe','<form','<input','<button','href=','http://','https://','onload='] as $unsafe) {
    if (stripos($html, $unsafe) !== false) {
        throw new RuntimeException('Unsafe offline HTML element: ' . $unsafe);
    }
}
if (Html::render($details) !== $html) {
    throw new RuntimeException('Rendered HTML must be deterministic');
}
$altered = $details;
$altered['finance_approved'] = true;
try { Html::render($altered); throw new RuntimeException('Accepted false approval'); }
catch (InvalidArgumentException) {}
$altered = $details;
$altered['review_row']['adjustment_cents'] = 0;
try { Html::render($altered); throw new RuntimeException('Accepted manipulated adjustment'); }
catch (InvalidArgumentException) {}
$altered = $details;
$altered['spend_rows'][0]['spend_cents'] = 100;
try { Html::render($altered); throw new RuntimeException('Accepted manipulated media sum'); }
catch (InvalidArgumentException) {}
echo "OK: safe offline finance HTML preview\n";
