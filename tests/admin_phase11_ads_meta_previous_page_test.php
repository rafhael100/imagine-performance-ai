<?php
declare(strict_types=1);

/**
 * Meta previous-page URL is ignored for transport, but must not carry an
 * unvalidated account/origin/cursor through the read-only normalization gate.
 * All provider pages are offline synthetic fixtures; no authorized Ads account.
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
    if (!$ok) throw new RuntimeException('Meta previous URL regression: '.$label);
    ++$checks;
};
$account = '1234567890123';
$baseUrl = 'https://graph.facebook.com/v27.0/act_'.$account.'/insights';
$goodPrevious = $baseUrl.'?before=prev_valid';
$goodNext = $baseUrl.'?after=page_two';
$row = [
    'account_id'=>$account, 'account_currency'=>'BRL', 'campaign_id'=>'42',
    'date_start'=>'2026-11-18', 'date_stop'=>'2026-11-18',
    'spend'=>'1.00', 'impressions'=>'3', 'clicks'=>'1',
];
$badLinks = [
    'structured previous object' => ['before'=>'prev_valid'],
    'null previous' => null,
    'insecure scheme' => str_replace('https:', 'http:', $goodPrevious),
    'foreign origin' => str_replace('graph.facebook.com', 'other.invalid', $goodPrevious),
    'wrong account' => str_replace('act_'.$account, 'act_9999999999999', $goodPrevious),
    'embedded basic auth' => str_replace('https://', 'https://user:pass@', $goodPrevious),
    'explicit port' => str_replace('graph.facebook.com', 'graph.facebook.com:443', $goodPrevious),
    'fragment' => $goodPrevious.'#private',
    'mismatched before' => $baseUrl.'?before=other_cursor',
    'duplicate before' => $goodPrevious.'&before=prev_valid',
    'invalid percent' => $goodPrevious.'&fields=%GG',
    'embedded control' => $goodPrevious."\r\nprivate",
    'missing before' => $baseUrl.'?after=page_two',
];
$http = static fn(array $body): array => [
    'source_kind'=>'laboratory_fixture', 'status'=>200,
    'content_type'=>'application/json', 'redirected'=>false,
    'body'=>json_encode($body, JSON_THROW_ON_ERROR),
];
foreach ($badLinks as $name=>$badLink) {
    foreach ([false, true] as $finalPage) {
        $calls = 0;
        $callback = static function (array $request) use (
            &$calls, $row, $badLink, $finalPage, $goodNext, $http
        ): array {
            ++$calls;
            $item = $row;
            if ($calls === 2) $item['date_start']=$item['date_stop']='2026-11-19';
            $paging = ['cursors'=>['after'=>'page_two', 'before'=>'prev_valid']];
            if (!$finalPage || $calls === 2) $paging['previous']=$badLink;
            if (!$finalPage || $calls === 1) $paging['next']=$goodNext;
            return $http(['data'=>[$item], 'paging'=>$paging]);
        };
        $adapter = new Adapter('meta_ads',$account,'2026-11-18','2026-11-19',
            'America/Sao_Paulo',$callback);
        $result = null;
        $failure = null;
        try {
            $result = (new Connector(
                static fn():string=>str_repeat('a', 64),
                Closure::fromCallable($adapter)
            ))->read('company_alfa','meta_ads',$account,
                '2026-11-18','2026-11-19','America/Sao_Paulo');
        } catch (Failure $error) {
            $failure = $error;
        }
        $label = $name.($finalPage ? ' final' : ' first');
        $check($result === null && $failure?->reason === 'pagination_denied',
            $label.' rejected, no partial results');
        $check($failure?->getPrevious() === null
            && !str_contains($failure?->getMessage()??'', 'private')
            && !str_contains($failure?->getMessage()??'', 'pass'),
            $label.' error sanitized without chain');
        $check($calls === ($finalPage ? 2 : 1), $label.' no extra calls/retries');
    }
}

// A valid previous link on the final page remains valid, but it must not
// become an outbound HTTP target or turn a fixture into official evidence.
$calls = 0;
$requests = [];
$callback = static function (array $request) use (
    &$calls, &$requests, $row, $goodNext, $goodPrevious, $http
): array {
    ++$calls;
    $requests[] = $request;
    $item = $row;
    if ($calls === 2) $item['date_start']=$item['date_stop']='2026-11-19';
    $paging = ['cursors'=>['after'=>'page_two', 'before'=>'prev_valid']];
    if ($calls === 1) $paging['next']=$goodNext;
    else $paging['previous']=$goodPrevious.'&access_token=SYNTHETIC_NOT_FORWARDED';
    return $http(['data'=>[$item], 'paging'=>$paging]);
};
$adapter = new Adapter('meta_ads',$account,'2026-11-18','2026-11-19',
    'America/Sao_Paulo',$callback);
$valid = (new Connector(
    static fn():string=>str_repeat('a', 64), Closure::fromCallable($adapter)
))->read('company_alfa','meta_ads',$account,
    '2026-11-18','2026-11-19','America/Sao_Paulo');
$check($calls === 2 && $valid['complete'] === true && count($valid['rows']) === 2,
    'legitimate two-page read remains complete');
$check(($requests[1]['url']??null) !== $goodNext && ($requests[1]['url']??null) !== $goodPrevious,
    'provider URL never becomes transport target');
$check(!str_contains(json_encode($valid, JSON_THROW_ON_ERROR), 'SYNTHETIC_NOT_FORWARDED'),
    'untrusted previous URL not included in returned result');
$check($valid['status'] === 'synthetic' && $valid['official_evidence'] === null
    && $valid['external_operations_enabled'] === false && $valid['billable'] === false,
    'fixture stays non-operational and non-billable');
echo 'OK: '.$checks.' synthetic Meta previous-page scope checks'.PHP_EOL;
