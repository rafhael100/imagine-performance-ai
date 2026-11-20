<?php
declare(strict_types=1);

/**
 * Unicode bidi/zero-width formatting in requested-but-discarded Ads campaign
 * descriptors must not enter the synthetic read-only boundary unnoticed.
 * Pure fixture test: no provider calls, consent, IAM, cofre or official source.
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
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Campaign Unicode control: '.$label);
    ++$checks;
};
$private = 'PRIVATE_TENANT_SENTINEL_DO_NOT_EXPORT';
$invalid = [
    'right-to-left override' => "\u{202E}",
    'left-to-right override' => "\u{202D}",
    'right-to-left isolate' => "\u{2067}",
    'pop directional isolate' => "\u{2069}",
    'zero-width space' => "\u{200B}",
    'zero-width joiner' => "\u{200D}",
    'arabic letter mark' => "\u{061C}",
    'byte order mark' => "\u{FEFF}",
    'unicode line separator' => "\u{2028}",
    'unicode paragraph separator' => "\u{2029}",
];
$positive = [
    'plain' => 'Synthetic campaign',
    'diacritics' => 'Campanha São José',
    'combining-mark' => "Cafe\u{0301}",
    'emoji' => 'Campanha ✅',
];
$fixture = static fn(array $body): array => [
    'source_kind' => 'laboratory_fixture', 'status' => 200,
    'content_type' => 'application/json', 'redirected' => false,
    'body' => json_encode($body, JSON_THROW_ON_ERROR),
];
foreach (['google_ads' => '1234567890', 'meta_ads' => '1234567890123'] as $provider => $account) {
    $row = static function (string $date) use ($provider, $account): array {
        if ($provider === 'google_ads') return [
            'customer' => ['id' => $account, 'currencyCode' => 'BRL',
                'timeZone' => 'America/Sao_Paulo'],
            'campaign' => ['id' => '42', 'name' => 'Synthetic campaign', 'status' => 'ENABLED'],
            'segments' => ['date' => $date],
            'metrics' => ['costMicros' => '1000000', 'impressions' => '5', 'clicks' => '1'],
        ];
        return [
            'account_id' => $account, 'account_currency' => 'BRL',
            'campaign_id' => '42', 'campaign_name' => 'Synthetic campaign',
            'date_start' => $date, 'date_stop' => $date,
            'spend' => '1.00', 'impressions' => '5', 'clicks' => '1',
        ];
    };
    $read = static function (Closure $callback) use ($provider, $account): array {
        $adapter = new Adapter($provider, $account, '2026-11-18', '2026-11-19',
            'America/Sao_Paulo', $callback);
        return (new Connector(static fn(): string => str_repeat('a', 64),
            Closure::fromCallable($adapter)))->read(
                'company_alfa', $provider, $account,
                '2026-11-18', '2026-11-19', 'America/Sao_Paulo');
    };
    foreach ($invalid as $label => $control) {
        foreach ([false, true] as $secondPage) {
            $calls = 0;
            $callback = static function (array $request) use (
                &$calls, $row, $fixture, $provider, $account, $control, $private, $secondPage
            ): array {
                ++$calls;
                $bodyRow = $row($calls === 1 ? '2026-11-18' : '2026-11-19');
                if (!$secondPage || $calls === 2) {
                    if ($provider === 'google_ads') {
                        $bodyRow['campaign']['name'] = 'Normal'.$control.$private;
                    } else {
                        $bodyRow['campaign_name'] = 'Normal'.$control.$private;
                    }
                }
                $body = $provider === 'google_ads'
                    ? ['results' => [$bodyRow]] : ['data' => [$bodyRow]];
                if ($secondPage && $calls === 1) {
                    if ($provider === 'google_ads') $body['nextPageToken'] = 'page_two';
                    else $body['paging'] = [
                        'cursors' => ['after' => 'page_two'],
                        'next' => 'https://graph.facebook.com/v27.0/act_'.$account
                            .'/insights?after=page_two',
                    ];
                }
                return $fixture($body);
            };
            $result = null;
            $failure = null;
            try {
                $result = $read($callback);
            } catch (Failure $caught) {
                $failure = $caught;
            }
            $case = $provider.' '.$label.($secondPage ? ' second' : ' first');
            $check($result === null && $failure?->reason === 'scope_denied',
                $case.' fail closed without partial rows');
            $check($failure?->getPrevious() === null
                && !str_contains($failure?->getMessage() ?? '', $private),
                $case.' no sensitive exception chain or raw label');
            $check($calls === ($secondPage ? 2 : 1), $case.' no retry');
        }
    }
    foreach ($positive as $label => $name) {
        $calls = 0;
        $callback = static function (array $request) use (
            &$calls, $row, $fixture, $provider, $account, $name
        ): array {
            ++$calls;
            $bodyRow = $row($calls === 1 ? '2026-11-18' : '2026-11-19');
            if ($provider === 'google_ads') $bodyRow['campaign']['name'] = $name;
            else $bodyRow['campaign_name'] = $name;
            $body = $provider === 'google_ads'
                ? ['results' => [$bodyRow]] : ['data' => [$bodyRow]];
            if ($calls === 1) {
                if ($provider === 'google_ads') $body['nextPageToken'] = 'page_two';
                else $body['paging'] = [
                    'cursors' => ['after' => 'page_two'],
                    'next' => 'https://graph.facebook.com/v27.0/act_'.$account
                        .'/insights?after=page_two',
                ];
            }
            return $fixture($body);
        };
        $result = $read($callback);
        $check($calls === 2 && $result['complete'] === true
            && count($result['rows']) === 2, $provider.' '.$label.' positive pagination');
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        $check(!str_contains($encoded, $name) && !str_contains($encoded, 'campaign_name'),
            $provider.' '.$label.' label is not exported');
        $check($result['status'] === 'synthetic' && $result['official_evidence'] === null
            && $result['billable'] === false && $result['external_operations_enabled'] === false,
            $provider.' '.$label.' remains non-operational');
    }
}
echo 'OK: '.$checks.' campaign Unicode adversarial checks (offline synthetic)'.PHP_EOL;
