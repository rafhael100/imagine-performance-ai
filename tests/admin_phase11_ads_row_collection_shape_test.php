<?php
declare(strict_types=1);

/**
 * Reject object-shaped or sparse row collections from a callback. Everything
 * below is synthetic: no Ads network, tokens, account authority or billing.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Ads row collection boundary: '.$label);
    $checks++;
};

foreach (['google_ads'=>'1234567890', 'meta_ads'=>'1234567890123'] as $provider=>$account) {
    $valid = [
        'account_id'=>$account, 'campaign_id'=>'42', 'currency'=>'BRL',
        'date'=>'2026-11-18', 'spend'=>$provider==='google_ads'?'1000000':'1.00',
        'impressions'=>'10', 'clicks'=>'3',
    ];
    $second = $valid;
    $second['date'] = '2026-11-19';
    $invalidCollections = [
        'keyed object'=>['campaign_42'=>$valid],
        'numeric offset one'=>[1=>$valid],
        'out of order numeric keys'=>[1=>$valid,0=>$second],
        'sparse numeric keys'=>[0=>$valid,2=>$second],
        'mixed row map'=>[0=>$valid,'private_key'=>'PRIVATE_TOKEN_IN_ROWS'],
    ];
    foreach ($invalidCollections as $kind=>$invalid) {
        foreach ([false,true] as $secondPage) {
            $calls = 0;
            $leaseChecks = 0;
            $read = new Connector(
                static function() use (&$leaseChecks): string {
                    $leaseChecks++;
                    return str_repeat('a',64);
                },
                static function(array $plan, ?string $cursor) use (&$calls,$valid,$invalid,$secondPage): array {
                    $calls++;
                    return [
                        'source_kind'=>'laboratory_fixture',
                        'context_timezone'=>'America/Sao_Paulo',
                        'rows'=>$secondPage && $cursor===null ? [$valid] : $invalid,
                        'next_cursor'=>$secondPage && $cursor===null ? 'nextpage' : null,
                    ];
                }
            );
            $result = null;
            $error = null;
            try {
                $result = $read->read(
                    'company_alfa',$provider,$account,
                    '2026-11-18','2026-11-19','America/Sao_Paulo'
                );
            } catch (RuntimeException $caught) {
                $error = $caught;
            }
            $label=$provider.' '.$kind.($secondPage?' after page one':' on page one');
            $check($result===null && $error?->getMessage()==='Provider rows denied',
                $label.' rejected with no partial result');
            $check($error?->getPrevious()===null && !str_contains($error?->getMessage()??'', 'PRIVATE_TOKEN'),
                $label.' has no raw exception chain or private marker');
            $check($calls===($secondPage?2:1),$label.' does not retry callback');
            $check($leaseChecks>=($secondPage?5:3),$label.' still revalidates authorization');
        }
    }

    $calls=0;
    $read=new Connector(
        static fn():string=>str_repeat('a',64),
        static function(array $plan, ?string $cursor) use (&$calls,$valid,$second):array {
            $calls++;
            return [
                'source_kind'=>'laboratory_fixture',
                'context_timezone'=>'America/Sao_Paulo',
                'rows'=>[$cursor===null?$valid:$second],
                'next_cursor'=>$cursor===null?'nextpage':null,
            ];
        }
    );
    $ok=$read->read('company_alfa',$provider,$account,
        '2026-11-18','2026-11-19','America/Sao_Paulo');
    $check($calls===2 && $ok['complete']===true && count($ok['rows'])===2,
        $provider.' legitimate list-shaped paginated rows are accepted');
    $check($ok['status']==='synthetic' && $ok['official_evidence']===null,
        $provider.' never identifies fixture as official source');
    $check($ok['billable']===false && $ok['external_operations_enabled']===false,
        $provider.' no Ads write or billing authority');
}
echo 'OK: '.$checks.' row-collection shape checks; offline fixtures only'.PHP_EOL;
