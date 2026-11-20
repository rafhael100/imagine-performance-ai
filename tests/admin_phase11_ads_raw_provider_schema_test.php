<?php
declare(strict_types=1);

/**
 * Negative/positive synthetic provider-wire schema tests. No network, Vault,
 * provider accounts or actual Ads API execution. Provider-shaped JSON is
 * untrusted even when the normalized read-only page has a closed schema.
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
    if (!$ok) throw new RuntimeException('Raw Ads schema regression: '.$name);
    ++$checks;
};

foreach (['google_ads'=>'1234567890', 'meta_ads'=>'1234567890123'] as $provider=>$account) {
    $base = $provider === 'google_ads'
        ? [
            'customer'=>['id'=>$account, 'currencyCode'=>'BRL', 'timeZone'=>'America/Sao_Paulo',
                'resourceName'=>'customers/'.$account],
            'campaign'=>['id'=>'42', 'name'=>'Synthetic campaign', 'status'=>'ENABLED',
                'resourceName'=>'customers/'.$account.'/campaigns/42'],
            'segments'=>['date'=>'2026-11-18'],
            'metrics'=>['costMicros'=>'1000000', 'impressions'=>'3', 'clicks'=>'1'],
        ]
        : [
            'account_id'=>$account, 'account_currency'=>'BRL', 'campaign_id'=>'42',
            'campaign_name'=>'Synthetic campaign', 'date_start'=>'2026-11-18',
            'date_stop'=>'2026-11-18', 'spend'=>'1.00',
            'impressions'=>'3', 'clicks'=>'1',
        ];

    $cases = $provider === 'google_ads'
        ? [
            ['google result tenant'=>['company_id'], 'scope_denied'],
            ['google result token'=>['access_token'], 'scope_denied'],
            ['google customer tenant'=>['customer','company_id'], 'scope_denied'],
            ['google customer secret'=>['customer','access_token'], 'scope_denied'],
            ['google campaign secret'=>['campaign','credential_ref'], 'scope_denied'],
            ['google segments extra'=>['segments','private_metadata'], 'scope_denied'],
            ['google metrics secret'=>['metrics','access_token'], 'metric_denied'],
            ['google metrics unknown'=>['metrics','unrequested_metric'], 'metric_denied'],
        ]
        : [
            ['meta result tenant'=>['company_id'], 'scope_denied'],
            ['meta result token'=>['access_token'], 'scope_denied'],
            ['meta result credential'=>['credential_ref'], 'scope_denied'],
            ['meta result nested secret'=>['private_metadata'], 'scope_denied'],
            ['meta result false extra'=>['unrequested_boolean'], 'scope_denied'],
        ];

    foreach ([false,true] as $secondPage) {
        foreach ($cases as $case) {
            $label = array_key_first($case);
            $path = $case[$label];
            $reason = $case[0];
            $calls = 0;
            $callback = static function (array $request) use (
                &$calls, $provider, $account, $base, $path, $secondPage
            ): array {
                ++$calls;
                $row = $base;
                if ($calls > 1) {
                    if ($provider === 'google_ads') $row['segments']['date'] = '2026-11-19';
                    else $row['date_start'] = $row['date_stop'] = '2026-11-19';
                }
                if (!$secondPage || $calls > 1) {
                    if (count($path) === 1) {
                        $row[$path[0]] = 'SECRET_TENANT_TOKEN_NOT_FOR_OUTPUT';
                    } else {
                        $row[$path[0]][$path[1]] = 'SECRET_TENANT_TOKEN_NOT_FOR_OUTPUT';
                    }
                }
                $envelope = $provider === 'google_ads'
                    ? ['results'=>[$row]]
                    : ['data'=>[$row]];
                if ($secondPage && $calls === 1) {
                    if ($provider === 'google_ads') $envelope['nextPageToken'] = 'page_two';
                    else $envelope['paging'] = [
                        'cursors'=>['after'=>'page_two'],
                        'next'=>'https://graph.facebook.com/v27.0/act_'.$account.'/insights?after=page_two',
                    ];
                }
                return [
                    'source_kind'=>'laboratory_fixture','status'=>200,
                    'content_type'=>'application/json','redirected'=>false,
                    'body'=>json_encode($envelope, JSON_THROW_ON_ERROR),
                ];
            };
            $adapter = new Adapter($provider, $account, '2026-11-18', '2026-11-19',
                'America/Sao_Paulo', $callback);
            $result = null;
            $error = null;
            try {
                $result = (new Connector(static fn(): string=>str_repeat('b',64),
                    Closure::fromCallable($adapter)))->read(
                    'company_alfa',$provider,$account,'2026-11-18','2026-11-19','America/Sao_Paulo'
                );
            } catch (Failure $failure) {
                $error = $failure;
            }
            $scenario = $provider.' '.$label.($secondPage?' page two':' page one');
            $check($result===null && $error?->reason===$reason,
                $scenario.' denied without partial result');
            $check($error?->getPrevious()===null
                && !str_contains($error?->getMessage()??'','SECRET_TENANT_TOKEN'),
                $scenario.' sanitized');
            $check($calls===($secondPage?2:1),$scenario.' no retry');
        }
    }

    $calls=0;
    $positiveCallback = static function (array $request) use (&$calls,$provider,$account,$base): array {
        ++$calls;
        $row=$base;
        if ($calls>1) {
            if ($provider==='google_ads') $row['segments']['date']='2026-11-19';
            else $row['date_start']=$row['date_stop']='2026-11-19';
        }
        $envelope=$provider==='google_ads'?['results'=>[$row]]:['data'=>[$row]];
        if ($calls===1) {
            if ($provider==='google_ads') $envelope['nextPageToken']='page_two';
            else $envelope['paging']=[
                'cursors'=>['after'=>'page_two'],
                'next'=>'https://graph.facebook.com/v27.0/act_'.$account.'/insights?after=page_two',
            ];
        }
        return ['source_kind'=>'laboratory_fixture','status'=>200,
            'content_type'=>'application/json','redirected'=>false,
            'body'=>json_encode($envelope,JSON_THROW_ON_ERROR)];
    };
    $adapter=new Adapter($provider,$account,'2026-11-18','2026-11-19',
        'America/Sao_Paulo',$positiveCallback);
    $valid=(new Connector(static fn():string=>str_repeat('b',64),
        Closure::fromCallable($adapter)))->read(
        'company_alfa',$provider,$account,'2026-11-18','2026-11-19','America/Sao_Paulo');
    $check($calls===2 && $valid['complete']===true && count($valid['rows'])===2,
        $provider.' known provider descriptors accepted on two pages');
    $check($valid['status']==='synthetic' && $valid['official_evidence']===null
        && $valid['billable']===false && $valid['external_operations_enabled']===false,
        $provider.' remains nonofficial, unbillable, nonoperational');
}

echo 'OK: '.$checks.' raw Ads provider schema checks; offline synthetic fixtures only'.PHP_EOL;
