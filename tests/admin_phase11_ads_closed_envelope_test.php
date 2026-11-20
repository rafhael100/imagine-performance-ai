<?php
declare(strict_types=1);

/**
 * Fail closed for undeclared page/row keys in injected Ads callbacks.
 * Fixtures are synthetic, do not access Google, Meta, Vault or network.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Closed Ads envelope regression: '.$label);
    $checks++;
};

foreach (['google_ads'=>'1234567890', 'meta_ads'=>'1234567890123'] as $provider=>$account) {
    $row = [
        'account_id'=>$account, 'campaign_id'=>'42', 'currency'=>'BRL',
        'date'=>'2026-11-18', 'spend'=>$provider==='google_ads'?'1000000':'1.00',
        'impressions'=>'10', 'clicks'=>'2',
    ];
    $hostilePages = [
        'unexpected token'=>['access_token'=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE'],
        'untrusted company'=>['company_id'=>'company_beta'],
        'unknown nested document'=>['private_metadata'=>['access_token'=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE']],
        'numeric page field'=>[8=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE'],
    ];
    $hostileRows = [
        'unexpected credential reference'=>['credential_ref'=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE'],
        'row tenant confusion'=>['company_id'=>'company_beta'],
        'unknown nested payload'=>['raw_metadata'=>['access_token'=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE']],
        'numeric row field'=>[7=>'PRIVATE_TOKEN_MUST_NOT_ESCAPE'],
        'unknown false value'=>['unexpected_boolean'=>false],
    ];
    foreach (['page'=>$hostilePages, 'row'=>$hostileRows] as $location=>$cases) {
        foreach ($cases as $name=>$fields) {
            foreach ([false,true] as $secondPage) {
                $pages=0;
                $leaseReads=0;
                $authorization=static function () use (&$leaseReads):string {
                    $leaseReads++;
                    return str_repeat('b',64);
                };
                $callback=static function (array $plan, ?string $cursor) use (
                    &$pages,$row,$fields,$location,$secondPage
                ):array {
                    $pages++;
                    $current=$row;
                    if ($cursor!==null) $current['date']='2026-11-19';
                    $page=[
                        'source_kind'=>'laboratory_fixture',
                        'context_timezone'=>'America/Sao_Paulo',
                        'rows'=>[$current],
                        'next_cursor'=>$secondPage && $cursor===null?'page_two':null,
                    ];
                    if (!$secondPage || $cursor!==null) {
                        if ($location==='page') $page=array_replace($page,$fields);
                        else $page['rows'][0]=array_replace($page['rows'][0],$fields);
                    }
                    return $page;
                };
                $output=null;
                $error=null;
                try {
                    $output=(new Connector($authorization,$callback))->read(
                        'company_alfa',$provider,$account,'2026-11-18','2026-11-19','America/Sao_Paulo'
                    );
                } catch (RuntimeException $failure) {
                    $error=$failure;
                }
                $label=$provider.' '.$location.' '.$name.($secondPage?' second page':' first page');
                $message=$location==='page'?'Provider source denied':'Provider scope denied';
                $check($output===null && $error?->getMessage()===$message,
                    $label.' denied without exposing partial rows');
                $check($error?->getPrevious()===null
                    && !str_contains($error?->getMessage()??'','PRIVATE_TOKEN'),
                    $label.' sanitized without chained exception');
                $check($pages===($secondPage?2:1),$label.' no retry');
                $check($leaseReads>=($secondPage?4:2),$label.' lease checked before denied callback page');
            }
        }
    }

    $pages=0;
    $valid=new Connector(
        static fn():string=>str_repeat('b',64),
        static function(array $plan,?string $cursor) use (&$pages,$row):array {
            $pages++;
            $r=$row;
            if ($cursor!==null) {
                $r['date']='2026-11-19';
                unset($r['impressions']); // optional existing metric stays optional
            }
            return [
                'source_kind'=>'laboratory_fixture','context_timezone'=>'America/Sao_Paulo',
                'rows'=>[$r],'next_cursor'=>$cursor===null?'page_two':null,
            ];
        }
    );
    $result=$valid->read('company_alfa',$provider,$account,
        '2026-11-18','2026-11-19','America/Sao_Paulo');
    $check($pages===2 && $result['complete']===true && count($result['rows'])===2,
        $provider.' valid paginated closed envelopes still accepted');
    $check($result['rows'][1]['impressions']===null,
        $provider.' optional metric stays null');
    $check($result['official_evidence']===null && $result['billable']===false
        && $result['external_operations_enabled']===false,
        $provider.' fixture never upgraded to official/billable');

    // Preserve explicitly documented legacy fixture compatibility: absence
    // of optional page cursor/timezone and optional metrics is not a grant.
    $legacyRow=$row;
    unset($legacyRow['impressions'],$legacyRow['clicks']);
    $legacy=(new Connector(
        static fn():string=>str_repeat('b',64),
        static fn():array=>['source_kind'=>'laboratory_fixture','rows'=>[$legacyRow]]
    ))->read('company_alfa',$provider,$account,
        '2026-11-18','2026-11-19','America/Sao_Paulo');
    $check($legacy['complete']===true && count($legacy['rows'])===1,
        $provider.' legacy optional page keys preserved');
    $check($legacy['official_evidence']===null && $legacy['billable']===false,
        $provider.' legacy remains nonofficial and unbillable');
}

echo 'OK: '.$checks.' closed Ads page/row envelope checks; synthetic callbacks only'.PHP_EOL;
