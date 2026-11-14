<?php
declare(strict_types=1);

// CLI-only synthetic demonstration. Reads fixture JSON from stdin, writes HTML to stdout.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' ||
    getenv('PERF_FIN_REVIEW_HTML_LAB') !== '1') {
    fwrite(STDERR, "Blocked: testing environment and explicit lab flag required.\n");
    exit(2);
}
require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementCandidate.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementReviewHtml.php';

use Imagine\Performance\Commercial\MonthlySettlementCandidate as Candidate;
use Imagine\Performance\Commercial\MonthlySettlementReviewHtml as Html;

try {
    $raw = stream_get_contents(STDIN, 131073);
    if (!is_string($raw) || $raw === '' || strlen($raw) > 131072) {
        throw new InvalidArgumentException('Invalid fixture size.');
    }
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid root payload.');
    }
    $keys = array_keys($input);
    sort($keys, SORT_STRING);
    if ($keys !== ['period','quote','records','tenant_id']) {
        throw new InvalidArgumentException('Unexpected fixture fields.');
    }
    if (!is_string($input['tenant_id']) || !is_string($input['period']) ||
        !is_array($input['quote']) || !is_array($input['records'])) {
        throw new InvalidArgumentException('Invalid fixture types.');
    }
    $candidate = Candidate::build($input['tenant_id'], $input['quote'], $input['period'], $input['records']);
    fwrite(STDOUT, Html::render($candidate));
} catch (Throwable $e) {
    // Never print untrusted fixture contents or stack traces.
    fwrite(STDERR, "Rejected synthetic finance review fixture.\n");
    exit(1);
}
