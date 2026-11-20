<?php
declare(strict_types=1);

/**
 * Final gate regression: the authority must be CURRENT even after the last
 * synthetic page has passed its post-callback check and row normalization.
 * Entirely deterministic; no provider network or credential lookup.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Final Ads lease check regression: '.$label);
    $checks++;
};

$cases = [
    ['no metadata, single page', false, 1, 4],
    ['metadata, single page', true, 1, 6],
    ['no metadata, second page', false, 2, 6],
    ['metadata, second page', true, 2, 8],
];

foreach ($cases as [$label, $withMetadata, $pageCountExpected, $lastAuthCall]) {
    foreach ([false, true] as $revokeAtFinalCheck) {
        $authorizations = 0;
        $pageCalls = 0;
        $metadataCalls = 0;
        $authorize = static function () use (&$authorizations, $revokeAtFinalCheck, $lastAuthCall): ?string {
            $authorizations++;
            return $revokeAtFinalCheck && $authorizations === $lastAuthCall
                ? null : str_repeat('d', 64);
        };
        $page = static function (array $plan, ?string $cursor) use (&$pageCalls, $pageCountExpected): array {
            $pageCalls++;
            $second = $cursor !== null;
            $row = [
                'account_id'=>'1234567890', 'campaign_id'=>'100',
                'currency'=>'BRL', 'date'=>$second ? '2026-11-19' : '2026-11-18',
                'spend'=>'1000000', 'impressions'=>'8', 'clicks'=>'2'
            ];
            return [
                'source_kind'=>'laboratory_fixture',
                'context_timezone'=>'America/Sao_Paulo',
                'rows'=>[$row],
                'next_cursor'=>!$second && $pageCountExpected === 2 ? 'page2' : null
            ];
        };
        $metadata = static function (array $plan) use (&$metadataCalls): array {
            $metadataCalls++;
            return [
                'source_kind'=>'laboratory_fixture', 'provider'=>'google_ads',
                'api_version'=>'v26', 'account_id'=>'1234567890', 'currency'=>'BRL',
                'timezone'=>'America/Sao_Paulo', 'context_timezone'=>'America/Sao_Paulo'
            ];
        };
        $connector = new Connector($authorize, $page, $withMetadata ? $metadata : null);
        $result = null;
        $exception = null;
        try {
            $result = $connector->read(
                'company_alfa','google_ads','1234567890',
                '2026-11-18','2026-11-19','America/Sao_Paulo'
            );
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }
        $assert($authorizations === $lastAuthCall,
            $label.' final authorization checkpoint reached (revoke='.($revokeAtFinalCheck ? 'yes':'no').')');
        $assert($pageCalls === $pageCountExpected && $metadataCalls === ($withMetadata ? 1 : 0),
            $label.' expected provider calls without retries');
        if ($revokeAtFinalCheck) {
            $assert($result === null && $exception?->getMessage() === 'Account consent revoked',
                $label.' final revocation denies all rows');
            $assert($exception?->getPrevious() === null,
                $label.' final revocation does not expose original authority details');
        } else {
            $assert($exception === null && $result !== null && $result['complete'] === true
                && $result['status'] === 'synthetic' && count($result['rows']) === $pageCountExpected,
                $label.' stable current authority yields synthetic report');
            $assert($result['official_evidence'] === null && $result['billable'] === false
                && $result['external_operations_enabled'] === false,
                $label.' no evidence or operational/billing privilege inferred');
        }
    }
}
echo 'OK: '.$checks.' final Ads lease positive/negative checks; no HTTP, no Ads account'.PHP_EOL;
