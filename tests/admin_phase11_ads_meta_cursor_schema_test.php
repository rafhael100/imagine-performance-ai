<?php
declare(strict_types=1);

/**
 * Hostile Meta paging cursor objects, including a final page with no "next".
 * Provider-shaped fixtures only: no network, OAuth, Google/Meta accounts or Vault.
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
    if (!$ok) throw new RuntimeException('Meta cursor schema regression: '.$label);
    ++$checks;
};
$account = '1234567890123';
$base = [
    'account_id'=>$account, 'account_currency'=>'BRL', 'campaign_id'=>'42',
    'campaign_name'=>'Synthetic campaign', 'date_start'=>'2026-11-18',
    'date_stop'=>'2026-11-18', 'spend'=>'1.00',
    'impressions'=>'3', 'clicks'=>'1',
];
$next = 'https://graph.facebook.com/v27.0/act_'.$account.'/insights?after=page_two';
$badCursors = [
    'foreign company' => ['after'=>'page_two', 'company_id'=>'company_beta'],
    'opaque credential ref' => ['after'=>'page_two', 'credential_ref'=>'vault:other-tenant'],
    'bearer token' => ['after'=>'page_two', 'access_token'=>'SENSITIVE_TEST_TOKEN'],
    'numeric unknown property' => ['after'=>'page_two', 7=>'SENSITIVE_TEST_TOKEN'],
    'structured before cursor' => ['after'=>'page_two', 'before'=>['token'=>'SENSITIVE_TEST_TOKEN']],
    'invalid after type' => ['after'=>['value'=>'page_two']],
    'empty before cursor' => ['after'=>'page_two', 'before'=>''],
    'control in before cursor' => ['after'=>'page_two', 'before'=>"a\r\nsecret"],
];

foreach ($badCursors as $name=>$bad) {
    foreach ([false,true] as $secondPage) {
        $calls = 0;
        $callback = static function (array $request) use (
            &$calls, $base, $bad, $secondPage, $next
        ): array {
            ++$calls;
            $row = $base;
            if ($calls > 1) $row['date_start']=$row['date_stop']='2026-11-19';
            $cursor = ($secondPage && $calls === 1)
                ? ['after'=>'page_two', 'before'=>'previous_page']
                : $bad;
            $paging = ['cursors'=>$cursor];
            if ($secondPage && $calls === 1 || !$secondPage) $paging['next']=$next;
            $payload = ['data'=>[$row], 'paging'=>$paging];
            return [
                'source_kind'=>'laboratory_fixture', 'status'=>200,
                'content_type'=>'application/json', 'redirected'=>false,
                'body'=>json_encode($payload, JSON_THROW_ON_ERROR),
            ];
        };
        $adapter = new Adapter('meta_ads', $account, '2026-11-18', '2026-11-19',
            'America/Sao_Paulo', $callback);
        $result = null;
        $error = null;
        try {
            $result = (new Connector(
                static fn(): string => str_repeat('b', 64),
                Closure::fromCallable($adapter)
            ))->read('company_alfa', 'meta_ads', $account,
                '2026-11-18', '2026-11-19', 'America/Sao_Paulo');
        } catch (Failure $failure) {
            $error = $failure;
        }
        $label = $name.($secondPage ? ' final page' : ' first page');
        $check($result===null && $error?->reason==='pagination_denied',
            $label.' rejects entire response, never partial rows');
        $check($error?->getPrevious()===null
            && !str_contains($error?->getMessage()??'', 'SENSITIVE_TEST_TOKEN'),
            $label.' sanitized without chained provider payload');
        $check($calls===($secondPage?2:1), $label.' no retries/extra pages');
    }
}

$calls = 0;
$positive = static function (array $request) use (&$calls,$base,$next): array {
    ++$calls;
    $row=$base;
    if ($calls>1) $row['date_start']=$row['date_stop']='2026-11-19';
    $paging = ['cursors'=>['before'=>'previous_page', 'after'=>$calls===1?'page_two':'last_page']];
    if ($calls===1) $paging['next']=$next;
    return [
        'source_kind'=>'laboratory_fixture', 'status'=>200,
        'content_type'=>'application/json', 'redirected'=>false,
        'body'=>json_encode(['data'=>[$row], 'paging'=>$paging], JSON_THROW_ON_ERROR),
    ];
};
$adapter = new Adapter('meta_ads', $account, '2026-11-18', '2026-11-19',
    'America/Sao_Paulo', $positive);
$valid = (new Connector(
    static fn():string=>str_repeat('b',64),
    Closure::fromCallable($adapter)
))->read('company_alfa','meta_ads',$account,
    '2026-11-18','2026-11-19','America/Sao_Paulo');
$check($calls===2 && $valid['complete']===true && count($valid['rows'])===2,
    'known before/after cursor fields remain valid across two pages');
$check($valid['status']==='synthetic' && $valid['official_evidence']===null,
    'provider-shaped fixture not classified as official evidence');
$check($valid['billable']===false && $valid['external_operations_enabled']===false,
    'pagination cannot enable operational access or billing');

echo 'OK: '.$checks.' synthetic Meta cursor schema checks'.PHP_EOL;
