<?php
declare(strict_types=1);

/**
 * Fail-closed read-only connector regression for malformed callback rows.
 * Google and Meta payloads are entirely synthetic; no network, Ads account,
 * OAuth credential, vault or operational mandate is ever accessed.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Ads malformed row regression: '.$label);
    $checks++;
};

foreach (['google_ads'=>'1234567890', 'meta_ads'=>'1234567890123'] as $provider=>$account) {
    $valid = [
        'account_id'=>$account, 'campaign_id'=>'12345', 'currency'=>'BRL',
        'date'=>'2026-11-18', 'spend'=>$provider==='google_ads'?'1000000':'1.00',
        'impressions'=>'5', 'clicks'=>'2',
    ];
    $bad = [
        'string row'=>'SYNTHETIC_PRIVATE_ROW_NOT_AN_ARRAY',
        'object row'=>(object)$valid,
        'array currency'=>array_replace($valid, ['currency'=>['BRL']]),
        'object currency'=>array_replace($valid, ['currency'=>(object)['value'=>'BRL']]),
        'integer currency'=>array_replace($valid, ['currency'=>123]),
        'boolean currency'=>array_replace($valid, ['currency'=>true]),
        'null currency'=>array_replace($valid, ['currency'=>null]),
    ];

    foreach ($bad as $label=>$invalid) {
        foreach ([false, true] as $badSecondPage) {
            $pages=0;
            $leaseReads=0;
            $authorize=static function() use (&$leaseReads): string {
                $leaseReads++;
                return str_repeat('b',64);
            };
            $callback=static function(array $plan, ?string $cursor) use (
                &$pages, $valid, $invalid, $badSecondPage
            ): array {
                $pages++;
                if ($badSecondPage && $cursor===null) {
                    return [
                        'source_kind'=>'laboratory_fixture', 'rows'=>[$valid],
                        'context_timezone'=>'America/Sao_Paulo', 'next_cursor'=>'nextpage',
                    ];
                }
                return [
                    'source_kind'=>'laboratory_fixture', 'rows'=>[$invalid],
                    'context_timezone'=>'America/Sao_Paulo', 'next_cursor'=>null,
                ];
            };
            $response=null;
            $denial=null;
            try {
                $response=(new Connector($authorize,$callback))->read(
                    'company_alfa', $provider, $account,
                    '2026-11-18', '2026-11-19', 'America/Sao_Paulo'
                );
            } catch (RuntimeException $error) {
                $denial=$error;
            }
            $scenario=$provider.' '.$label.($badSecondPage?' on second page':' on first page');
            $assert($response===null && $denial?->getMessage()==='Provider scope denied',
                $scenario.' rejects all rows with stable message');
            $assert($denial?->getPrevious()===null, $scenario.' has no raw exception chain');
            $assert($pages===($badSecondPage?2:1), $scenario.' callback called exactly once per page');
            $assert($leaseReads>=($badSecondPage?5:3), $scenario.' authority checked at page boundary');
        }
    }

    $pages=0;
    $positive=new Connector(
        static fn(): string=>str_repeat('b',64),
        static function(array $plan, ?string $cursor) use (&$pages,$valid): array {
            $pages++;
            $row=$valid;
            if ($cursor!==null) $row['date']='2026-11-19';
            return [
                'source_kind'=>'laboratory_fixture',
                'context_timezone'=>'America/Sao_Paulo',
                'rows'=>[$row],
                'next_cursor'=>$cursor===null?'nextpage':null,
            ];
        }
    );
    $success=$positive->read('company_alfa',$provider,$account,
        '2026-11-18','2026-11-19','America/Sao_Paulo');
    $assert($pages===2 && $success['complete']===true && count($success['rows'])===2,
        $provider.' legitimate paginated report remains intact');
    $assert($success['status']==='synthetic' && $success['official_evidence']===null,
        $provider.' fixture is not official evidence');
    $assert($success['billable']===false && $success['external_operations_enabled']===false,
        $provider.' does not grant Ads operations or billing');
}
echo 'OK: '.$checks.' malformed-row fail-closed checks; only local synthetic callbacks'.PHP_EOL;
