<?php
declare(strict_types=1);

/**
 * Offline Google provider metricAttributes must have a bounded, closed
 * metadata schema. They must never confer account authority or survive
 * into the normalized read-only result. No Google API/network/credentials.
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
    if (!$ok) throw new RuntimeException('Google metadata guard: '.$label);
    ++$checks;
};
$account = '1234567890';
$row = [
    'customer'=>['id'=>$account,'currencyCode'=>'BRL','timeZone'=>'America/Sao_Paulo'],
    'campaign'=>['id'=>'42'],
    'segments'=>['date'=>'2026-11-18'],
    'metrics'=>['costMicros'=>'1000000','impressions'=>'3','clicks'=>'1'],
];
$good = ['name'=>'metrics.impressions','attributes'=>[]];
$secret = 'SENSITIVE_NESTED_METADATA_MUST_NOT_LEAK';
$invalid = [
    'object instead of list' => ['name'=>$good],
    'untrusted string' => [$secret],
    'null item' => [null],
    'missing name' => [['attributes'=>[]]],
    'missing attributes' => [['name'=>'metrics.impressions']],
    'nonstring name' => [['name'=>false,'attributes'=>[]]],
    'extra credential reference' => [[...$good,'credential_ref'=>$secret]],
    'extra company identifier' => [[...$good,'company_id'=>'company_beta']],
    'unrequested metric' => [['name'=>'metrics.conversions','attributes'=>[]]],
    'nested token' => [['name'=>'metrics.impressions','attributes'=>[['access_token'=>$secret]]]],
    'attributes as map' => [['name'=>'metrics.impressions','attributes'=>['secret'=>$secret]]],
    'attributes as text' => [['name'=>'metrics.impressions','attributes'=>$secret]],
    'duplicate metric entries' => [$good,$good],
    'oversized metadata list' => [$good,['name'=>'metrics.clicks','attributes'=>[]],
        ['name'=>'metrics.costMicros','attributes'=>[]],$good],
];
$http = static fn(array $page): array => [
    'source_kind'=>'laboratory_fixture','status'=>200,
    'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode($page,JSON_THROW_ON_ERROR),
];

foreach ($invalid as $label=>$metadata) {
    foreach ([false,true] as $secondPage) {
        $calls = 0;
        $callback = static function (array $request) use (
            &$calls,$row,$metadata,$secondPage,$http,$good
        ): array {
            ++$calls;
            $item=$row;
            if ($calls===2) $item['segments']['date']='2026-11-19';
            $page = ['results'=>[$item]];
            $page['metricAttributes']=(!$secondPage || $calls===2) ? $metadata : [$good];
            if ($secondPage && $calls===1) $page['nextPageToken']='page_two';
            return $http($page);
        };
        $adapter = new Adapter('google_ads',$account,'2026-11-18','2026-11-19',
            'America/Sao_Paulo',$callback);
        $result = null;
        $failure = null;
        try {
            $result = (new Connector(
                static fn(): string=>str_repeat('a',64),
                Closure::fromCallable($adapter)
            ))->read('company_alfa','google_ads',$account,
                '2026-11-18','2026-11-19','America/Sao_Paulo');
        } catch (Failure $e) {
            $failure = $e;
        }
        $case=$label.($secondPage ? ' second' : ' first');
        $check($result===null && $failure?->reason==='response_denied',
            $case.' rejects entire read, no partial rows');
        $check($failure?->getPrevious()===null
            && !str_contains($failure?->getMessage()??'',$secret)
            && !str_contains($failure?->getMessage()??'','company_beta'),
            $case.' does not leak untrusted metadata');
        $check($calls===($secondPage?2:1),$case.' no extra provider calls');
    }
}

// Legitimate fixture with bounded requested empty attributes still reads
// two pages, without ever returning raw metadata or official evidence.
$calls=0;
$callback = static function (array $request) use (&$calls,$row,$http): array {
    ++$calls;
    $item=$row;
    if ($calls===2) $item['segments']['date']='2026-11-19';
    $page=['results'=>[$item],
        'metricAttributes'=>[
            ['name'=>'metrics.impressions','attributes'=>[]],
            ['name'=>'metrics.clicks','attributes'=>[]],
            ['name'=>'metrics.costMicros','attributes'=>[]],
        ]];
    if ($calls===1) $page['nextPageToken']='page_two';
    return $http($page);
};
$adapter = new Adapter('google_ads',$account,'2026-11-18','2026-11-19',
    'America/Sao_Paulo',$callback);
$valid = (new Connector(
    static fn():string=>str_repeat('a',64),Closure::fromCallable($adapter)
))->read('company_alfa','google_ads',$account,
    '2026-11-18','2026-11-19','America/Sao_Paulo');
$check($calls===2 && $valid['complete']===true && count($valid['rows'])===2,
    'two pages with approved empty metric metadata remain readable');
$check(!str_contains(json_encode($valid,JSON_THROW_ON_ERROR),'metricAttributes'),
    'metadata does not propagate to normalized response');
$check($valid['official_evidence']===null && $valid['status']==='synthetic',
    'fixture is never official evidence');
$check($valid['billable']===false && $valid['external_operations_enabled']===false,
    'fixture never enables operations or billing');
echo 'OK: '.$checks.' synthetic Google metricAttributes boundary checks'.PHP_EOL;
