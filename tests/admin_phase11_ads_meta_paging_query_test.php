<?php
declare(strict_types=1);

/**
 * Meta paging URLs are untrusted metadata, never transport targets. Until an
 * official account-scoped query schema is approved, the synthetic read-only
 * profile accepts only the matching after/before cursor and no other query
 * parameter (particularly no bearer/credential reference). Offline only.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';
require_once __DIR__.'/../src/Domain/Commercial/ExactJsonNumberLexicalParser.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadWireAdapter.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;
use Imagine\Performance\Administration\AdministrativeAdsReadWireAdapter as Adapter;
use Imagine\Performance\Administration\AdministrativeAdsWireFailure as Failure;

$checks=0;
$check=static function(bool $ok,string $why) use (&$checks):void {
    if (!$ok) throw new RuntimeException('Meta closed URL query: '.$why);
    ++$checks;
};
$account='1234567890123';
$url='https://graph.facebook.com/v27.0/act_'.$account.'/insights';
$secret='SENSITIVE_QUERY_MUST_NOT_LEAK';
$extra=[
    'access token'=>'access_token='.$secret,
    'encoded access token'=>'%61ccess_token='.$secret,
    'appsecret proof'=>'appsecret_proof='.$secret,
    'credential reference'=>'credential_ref='.$secret,
    'foreign company'=>'company_id=company_beta',
    'unrequested fields'=>'fields=account_id,access_token',
    'unrequested limit'=>'limit=200',
    'unrequested level'=>'level=account',
    'unrequested time range'=>'time_range=%7B%22since%22%3A%222026-11-18%22%7D',
    'unrequested authorization'=>'Authorization=Bearer'.$secret,
];
$http=static fn(array $body):array => [
    'source_kind'=>'laboratory_fixture','status'=>200,
    'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode($body,JSON_THROW_ON_ERROR),
];
$row=[
    'account_id'=>$account,'account_currency'=>'BRL','campaign_id'=>'42',
    'date_start'=>'2026-11-18','date_stop'=>'2026-11-18',
    'spend'=>'1.00','impressions'=>'3','clicks'=>'1',
];
foreach($extra as $label=>$param) {
    foreach(['next','previous'] as $direction) {
        foreach([false,true] as $secondPage) {
            $calls=0;
            $callback=static function(array $request) use (
                &$calls,$row,$http,$param,$direction,$secondPage,$url
            ):array {
                ++$calls;
                $current=$row;
                if($calls===2) $current['date_start']=$current['date_stop']='2026-11-19';
                $paging=['cursors'=>['after'=>'page_two','before'=>'prev_valid']];
                if($secondPage && $calls===1) {
                    $paging['next']=$url.'?after=page_two';
                } else {
                    $cursor=$direction==='next'?'page_two':'prev_valid';
                    $paging[$direction]=$url.'?'.($direction==='next'?'after':'before').'='.$cursor.'&'.$param;
                    if($direction==='previous' && $calls===1) $paging['next']=$url.'?after=page_two';
                }
                return $http(['data'=>[$current],'paging'=>$paging]);
            };
            $adapter=new Adapter('meta_ads',$account,'2026-11-18','2026-11-19',
                'America/Sao_Paulo',$callback);
            $result=null;
            $error=null;
            try {
                $result=(new Connector(
                    static fn():string=>str_repeat('a',64),
                    Closure::fromCallable($adapter)
                ))->read('company_alfa','meta_ads',$account,
                    '2026-11-18','2026-11-19','America/Sao_Paulo');
            } catch(Failure $failure) {$error=$failure;}
            $case=$label.' '.$direction.($secondPage?' second':' first');
            $check($result===null && $error?->reason==='pagination_denied',
                $case.' denies without exposing partial pages');
            $check($error?->getPrevious()===null
                && !str_contains($error?->getMessage()??'',$secret)
                && !str_contains($error?->getMessage()??'','company_beta'),
                $case.' sanitizes error and previous chain');
            $check($calls===($secondPage?2:1),$case.' no extra callback/retry');
        }
    }
}

// The cursor-only profile remains valid across two pages, with a backward
// link in the final response. Neither URL is used as a transport destination.
$calls=0;
$requests=[];
$callback=static function(array $request) use (&$calls,&$requests,$row,$http,$url):array {
    ++$calls;
    $requests[]=$request;
    $current=$row;
    if($calls===2) $current['date_start']=$current['date_stop']='2026-11-19';
    $paging=['cursors'=>['after'=>'page_two','before'=>'prev_valid']];
    if($calls===1) $paging['next']=$url.'?after=page_two';
    else $paging['previous']=$url.'?before=prev_valid';
    return $http(['data'=>[$current],'paging'=>$paging]);
};
$adapter=new Adapter('meta_ads',$account,'2026-11-18','2026-11-19',
    'America/Sao_Paulo',$callback);
$result=(new Connector(
    static fn():string=>str_repeat('a',64),Closure::fromCallable($adapter)
))->read('company_alfa','meta_ads',$account,
    '2026-11-18','2026-11-19','America/Sao_Paulo');
$check($calls===2 && $result['complete']===true && count($result['rows'])===2,
    'two-page legitimate synthetic response remains readable');
$check(!str_contains(json_encode($result,JSON_THROW_ON_ERROR),'graph.facebook.com'),
    'untrusted paging links never returned by public read-only result');
$check(($requests[1]['url']??null)!==$url.'?after=page_two',
    'provider paging URLs never used for HTTP transport');
$check($result['status']==='synthetic' && $result['official_evidence']===null
    && $result['billable']===false && $result['external_operations_enabled']===false,
    'synthetic source never upgraded to operational evidence');
echo 'OK: '.$checks.' Meta cursor-only URL query checks (offline)'.PHP_EOL;
