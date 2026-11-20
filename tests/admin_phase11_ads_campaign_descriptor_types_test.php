<?php
declare(strict_types=1);

/**
 * Requested but unconsumed campaign descriptors cannot hide nested account
 * selectors, credentials or unexpected provider types. Synthetic only:
 * no Google/Meta compatibility claim, network, vault or operational mandate.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadWireAdapter.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;
use Imagine\Performance\Administration\AdministrativeAdsReadWireAdapter as Adapter;
use Imagine\Performance\Administration\AdministrativeAdsWireFailure as Failure;

$checks = 0;
$check = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Ads campaign descriptor: '.$name);
    ++$checks;
};
$secret = 'PRIVATE_CREDENTIAL_REFERENCE_MUST_NOT_LEAK';
$cases = [
    'google_ads' => [
        ['name', ['credential_ref' => $secret]],
        ['name', ['company_id' => 'company_beta']],
        ['name', true],
        ['name', 17],
        ['name', null],
        ['name', str_repeat('x', 513)],
        ['name', "campaign\n".$secret],
        ['status', ['access_token' => $secret]],
        ['status', true],
        ['status', null],
        ['status', 'UNEXPECTED_'.$secret],
    ],
    'meta_ads' => [
        ['campaign_name', ['credential_ref' => $secret]],
        ['campaign_name', ['company_id' => 'company_beta']],
        ['campaign_name', false],
        ['campaign_name', 42],
        ['campaign_name', null],
        ['campaign_name', str_repeat('x', 513)],
        ['campaign_name', "campaign\r".$secret],
    ],
];
$fixture = static fn(array $body): array => [
    'source_kind' => 'laboratory_fixture',
    'status' => 200,
    'content_type' => 'application/json',
    'redirected' => false,
    'body' => json_encode($body, JSON_THROW_ON_ERROR),
];
$makeRow = static function (string $provider, string $account, string $date): array {
    if ($provider === 'google_ads') return [
        'customer' => ['id' => $account, 'currencyCode' => 'BRL', 'timeZone' => 'America/Sao_Paulo'],
        'campaign' => ['id' => '42', 'name' => 'Synthetic campaign', 'status' => 'ENABLED'],
        'segments' => ['date' => $date],
        'metrics' => ['costMicros' => '1000000', 'impressions' => '3', 'clicks' => '1'],
    ];
    return [
        'account_id' => $account, 'account_currency' => 'BRL',
        'campaign_id' => '42', 'campaign_name' => 'Synthetic campaign',
        'date_start' => $date, 'date_stop' => $date,
        'spend' => '1.00', 'impressions' => '3', 'clicks' => '1',
    ];
};
foreach ($cases as $provider => $invalidCases) {
    $account = $provider === 'google_ads' ? '1234567890' : '1234567890123';
    foreach ($invalidCases as $bad) {
        foreach ([false, true] as $secondPage) {
            $calls = 0;
            $callback = static function (array $request) use (
                &$calls, $account, $provider, $bad, $secondPage, $fixture, $makeRow
            ): array {
                ++$calls;
                $row = $makeRow($provider, $account, $calls === 1 ? '2026-11-18' : '2026-11-19');
                if (!$secondPage || $calls === 2) {
                    if ($provider === 'google_ads') $row['campaign'][$bad[0]] = $bad[1];
                    else $row[$bad[0]] = $bad[1];
                }
                $body = $provider === 'google_ads' ? ['results' => [$row]] : ['data' => [$row]];
                if ($secondPage && $calls === 1) {
                    if ($provider === 'google_ads') $body['nextPageToken'] = 'page_two';
                    else $body['paging'] = [
                        'cursors' => ['after' => 'page_two'],
                        'next' => 'https://graph.facebook.com/v27.0/act_'.$account.'/insights?after=page_two',
                    ];
                }
                return $fixture($body);
            };
            $adapter = new Adapter($provider, $account, '2026-11-18', '2026-11-19',
                'America/Sao_Paulo', $callback);
            $result = null;
            $error = null;
            try {
                $result = (new Connector(
                    static fn(): string => str_repeat('a', 64),
                    Closure::fromCallable($adapter)
                ))->read('company_alfa', $provider, $account,
                    '2026-11-18', '2026-11-19', 'America/Sao_Paulo');
            } catch (Failure $failure) {
                $error = $failure;
            }
            $label = $provider.' '.$bad[0].($secondPage ? ' second' : ' first');
            $check($result === null && $error?->reason === 'scope_denied',
                $label.' fail closed without partial rows');
            $check($error?->getPrevious() === null
                    && !str_contains($error?->getMessage() ?? '', $secret)
                    && !str_contains($error?->getMessage() ?? '', 'company_beta'),
                $label.' sanitized exception');
            $check($calls === ($secondPage ? 2 : 1), $label.' no retry');
        }
    }
    // Two-page positive controls preserve the previously requested legitimate
    // descriptors without exposing or billing their values.
    foreach (['default', 'empty', 'unicode'] as $variant) {
        $calls = 0;
        $callback = static function (array $request) use (
            &$calls, $account, $provider, $variant, $fixture, $makeRow
        ): array {
            ++$calls;
            $row = $makeRow($provider, $account, $calls === 1 ? '2026-11-18' : '2026-11-19');
            $label = match ($variant) {
                'empty' => '',
                'unicode' => 'Campanha São José ✓',
                default => 'Synthetic campaign',
            };
            if ($provider === 'google_ads') {
                $row['campaign']['name'] = $label;
                $row['campaign']['status'] = $variant === 'default' ? 'ENABLED'
                    : ($variant === 'empty' ? 'PAUSED' : 'REMOVED');
            } else $row['campaign_name'] = $label;
            $body = $provider === 'google_ads' ? ['results' => [$row]] : ['data' => [$row]];
            if ($calls === 1) {
                if ($provider === 'google_ads') $body['nextPageToken'] = 'page_two';
                else $body['paging'] = [
                    'cursors' => ['after' => 'page_two'],
                    'next' => 'https://graph.facebook.com/v27.0/act_'.$account.'/insights?after=page_two',
                ];
            }
            return $fixture($body);
        };
        $adapter = new Adapter($provider, $account, '2026-11-18', '2026-11-19',
            'America/Sao_Paulo', $callback);
        $result = (new Connector(
            static fn(): string => str_repeat('a', 64),
            Closure::fromCallable($adapter)
        ))->read('company_alfa', $provider, $account,
            '2026-11-18', '2026-11-19', 'America/Sao_Paulo');
        $check($calls === 2 && $result['complete'] === true && count($result['rows']) === 2,
            $provider.' '.$variant.' positive pagination');
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        $check(!str_contains($encoded, 'campaign_name') && !str_contains($encoded, 'Synthetic campaign'),
            $provider.' '.$variant.' metadata does not reach read-only rows');
        $check($result['status'] === 'synthetic' && $result['official_evidence'] === null
            && $result['billable'] === false && $result['external_operations_enabled'] === false,
            $provider.' '.$variant.' cannot enable operational Ads');
    }
}
echo 'OK: '.$checks.' campaign descriptor checks (offline synthetic)'.PHP_EOL;
