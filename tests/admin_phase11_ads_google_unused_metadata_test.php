<?php
declare(strict_types=1);

/**
 * Google provider-shaped metadata not consumed by this offline adapter must
 * fail closed instead of being silently discarded. This is a synthetic CI
 * contract, not a statement about the official Google Ads response schema.
 * No network, live account, token, vault or operational credentials.
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
$check = static function (bool $ok, string $why) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Google unused metadata: '.$why);
    ++$checks;
};
$account = '1234567890';
$secret = 'UNTRUSTED_METADATA_SECRET_MUST_NOT_LEAK';
$row = [
    'customer'=>['id'=>$account,'currencyCode'=>'BRL','timeZone'=>'America/Sao_Paulo'],
    'campaign'=>['id'=>'42'],
    'segments'=>['date'=>'2026-11-18'],
    'metrics'=>['costMicros'=>'1000000','impressions'=>'3','clicks'=>'1'],
];
$hostile = [
    'fieldMask benign value'=>['fieldMask'=>'customer.id,campaign.id'],
    'fieldMask credential string'=>['fieldMask'=>$secret],
    'fieldMask nested credential'=>['fieldMask'=>['credential_ref'=>$secret]],
    'totalResultsCount positive numeral'=>['totalResultsCount'=>2],
    'totalResultsCount secret string'=>['totalResultsCount'=>$secret],
    'totalResultsCount tenant object'=>['totalResultsCount'=>['company_id'=>'company_beta']],
    'queryResourceConsumption numeral'=>['queryResourceConsumption'=>5],
    'queryResourceConsumption credential'=>['queryResourceConsumption'=>['access_token'=>$secret]],
    'all three metadata keys'=>[
        'fieldMask'=>'customer.id', 'totalResultsCount'=>2, 'queryResourceConsumption'=>5,
    ],
];
$http = static fn(array $page): array => [
    'source_kind'=>'laboratory_fixture','status'=>200,
    'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode($page,JSON_THROW_ON_ERROR),
];
foreach ($hostile as $label=>$extra) {
    foreach ([false,true] as $secondPage) {
        $calls=0;
        $callback=static function(array $request) use (
            &$calls,$row,$http,$extra,$secondPage
        ):array {
            ++$calls;
            $current=$row;
            if ($calls===2) $current['segments']['date']='2026-11-19';
            $page=['results'=>[$current]];
            if ($secondPage && $calls===1) $page['nextPageToken']='page_two';
            if (!$secondPage || $calls===2) $page=array_replace($page,$extra);
            return $http($page);
        };
        $adapter=new Adapter('google_ads',$account,'2026-11-18','2026-11-19',
            'America/Sao_Paulo',$callback);
        $output=null;
        $error=null;
        try {
            $output=(new Connector(
                static fn():string=>str_repeat('a',64),
                Closure::fromCallable($adapter)
            ))->read('company_alfa','google_ads',$account,
                '2026-11-18','2026-11-19','America/Sao_Paulo');
        } catch (Failure $failure) {
            $error=$failure;
        }
        $case=$label.($secondPage?' second page':' first page');
        $check($output===null && $error?->reason==='response_denied',
            $case.' denies entire response, no partial rows');
        $check($error?->getPrevious()===null
            && !str_contains($error?->getMessage()??'', $secret)
            && !str_contains($error?->getMessage()??'', 'company_beta'),
            $case.' returns only a sanitized failure');
        $check($calls===($secondPage?2:1),$case.' no retry or third-party call');
    }
}

// Positive control: supported closed fields may be omitted or supplied with
// already validated metricAttributes. Neither creates official evidence.
$calls=0;
$callback=static function(array $request) use (&$calls,$row,$http):array {
    ++$calls;
    $current=$row;
    if ($calls===2) $current['segments']['date']='2026-11-19';
    $page=['results'=>[$current],
        'metricAttributes'=>[['name'=>'metrics.impressions','attributes'=>[]]]];
    if ($calls===1) $page['nextPageToken']='page_two';
    return $http($page);
};
$adapter=new Adapter('google_ads',$account,'2026-11-18','2026-11-19',
    'America/Sao_Paulo',$callback);
$result=(new Connector(
    static fn():string=>str_repeat('a',64),Closure::fromCallable($adapter)
))->read('company_alfa','google_ads',$account,
    '2026-11-18','2026-11-19','America/Sao_Paulo');
$check($calls===2 && $result['complete']===true && count($result['rows'])===2,
    'supported synthetic response completes two pages');
$check($result['status']==='synthetic' && $result['official_evidence']===null,
    'synthetic fixture never becomes official evidence');
$check($result['billable']===false && $result['external_operations_enabled']===false,
    'synthetic fixture cannot enable Ads or billing');
$check(!str_contains(json_encode($result,JSON_THROW_ON_ERROR),'metricAttributes'),
    'metadata not returned by read-only API');
echo 'OK: '.$checks.' Google unconsumed metadata denial checks (synthetic)'.PHP_EOL;
