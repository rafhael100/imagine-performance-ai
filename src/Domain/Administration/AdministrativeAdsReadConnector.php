<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use Closure;
use RuntimeException;
/** Injectable read connector. Source labels never grant official evidence or product operations. */
final class AdministrativeAdsReadConnector {
 private string $sourceKind='laboratory_fixture';
 private ?array $boundContext=null;
 public function __construct(#[\SensitiveParameter] private Closure $authorize,#[\SensitiveParameter] private Closure $page,#[\SensitiveParameter] private ?Closure $accountMetadata=null){}
 /** HTTP origin can only be composed from the final codec bound to the native transport. */
 public static function fromHttpAdapter(#[\SensitiveParameter] Closure $authorize,#[\SensitiveParameter] AdministrativeAdsReadWireAdapter $adapter):self {
  $boundContext=$adapter->httpContext();
  if($boundContext===null)throw new RuntimeException('Provider source context required');
  $keys=array_keys($boundContext);sort($keys,SORT_STRING);
  if($keys!==['account_id','company_id','from','provider','until'])throw new RuntimeException('Provider source context denied');
  foreach($boundContext as $value)if(!is_string($value)||$value==='')throw new RuntimeException('Provider source context denied');
  AdministrativeAdsReadPlan::prepare($boundContext['provider'],$boundContext['account_id'],$boundContext['from'],$boundContext['until']);
  $connector=new self($authorize,Closure::fromCallable($adapter),Closure::fromCallable([$adapter,'account']));
  $connector->sourceKind='http_unverified';$connector->boundContext=$boundContext;
  return $connector;
 }
 /** An authorization resolver can throw private tenant details: never leak them. */
 private function authorizedLease(string $company,string $provider,string $account):?string {
  try {
   $lease=($this->authorize)($company,$provider,$account);
   return is_string($lease)&&preg_match('/\A[a-f0-9]{64}\z/D',$lease)===1?$lease:null;
  } catch (\Throwable) {
   return null;
  }
 }
 /** Keep only the wire codec's finite safe classifications. Injected callbacks
  * may throw errors containing tokens, private tenant identifiers or URL bodies. */
 private function invokeProvider(#[\SensitiveParameter] Closure $callback,#[\SensitiveParameter] array $arguments,string $message):mixed {
  try{return $callback(...$arguments);}
  catch(AdministrativeAdsWireFailure $failure){
   $safe=['authentication_required','permission_denied','rate_limited','upstream_unavailable',
    'provider_error','request_denied','timezone_denied','cursor_denied','transport_failed',
    'response_denied','response_limit','invalid_json','scope_denied','pagination_denied',
    'date_denied','metric_denied','credentials_unavailable','credentials_denied',
    'credentials_expired','credential_changed','authorization_denied','authorization_revoked',
    'read_deadline_exceeded','transport_unavailable','transport_timeout','tls_failed',
    'response_headers_denied','response_encoding_denied','redirect_denied'];
   if(in_array($failure->reason,$safe,true)
    && ($failure->httpStatus===null || ($failure->httpStatus>=100 && $failure->httpStatus<=599)))
    throw $failure;
   throw new RuntimeException($message);
  }
  catch(\Throwable){throw new RuntimeException($message);}
 }
 public function read(string $company,string $provider,string $account,string $from,string $until,string $timezone):array {
  if($this->boundContext!==null){
   foreach(['company_id'=>$company,'provider'=>$provider,'account_id'=>$account,'from'=>$from,'until'=>$until] as $key=>$value)
    if($this->boundContext[$key]!==$value)throw new RuntimeException('Provider source context denied');
  }
  $plan=AdministrativeAdsReadPlan::prepare($provider,$account,$from,$until);
  if(!in_array($timezone,\DateTimeZone::listIdentifiers(),true))throw new RuntimeException('Account timezone required');
  $lease=$this->authorizedLease($company,$provider,$account);
  if($lease===null)throw new RuntimeException('Account consent denied');
  $rows=[];$cursor=null;$seen=[];$rowKeys=[];$currency=null;$complete=false;
  $accountContext=null;
  if($this->accountMetadata!==null){
   if($this->authorizedLease($company,$provider,$account)!==$lease)throw new RuntimeException('Account consent revoked');
   $metadata=$this->invokeProvider($this->accountMetadata,[$plan],'Provider account metadata unavailable');
   if($this->authorizedLease($company,$provider,$account)!==$lease)throw new RuntimeException('Account consent revoked');
   if(!is_array($metadata))throw new RuntimeException('Provider account metadata denied');
   $metadataKeys=array_keys($metadata);sort($metadataKeys,SORT_STRING);
   if($metadataKeys!==['account_id','api_version','context_timezone','currency','provider','source_kind','timezone']
    ||$metadata['source_kind']!==$this->sourceKind||$metadata['provider']!==$provider
    ||$metadata['api_version']!==$plan['read_plan']['api_version']||$metadata['account_id']!==$account
    ||$metadata['context_timezone']!==$timezone||$metadata['timezone']!==$timezone
    ||!is_string($metadata['currency'])||!preg_match('/\A[A-Z]{3}\z/D',$metadata['currency']))throw new RuntimeException('Provider account metadata denied');
   $currency=$metadata['currency'];
   $accountContext=['source_kind'=>$this->sourceKind,'provider'=>$provider,'api_version'=>$metadata['api_version'],
    'account_id'=>$account,'currency'=>$currency,'timezone'=>$timezone];
  }
  for($i=0;$i<10;$i++){
   if($this->authorizedLease($company,$provider,$account)!==$lease)throw new RuntimeException('Account consent revoked');
   $page=$this->invokeProvider($this->page,[$plan,$cursor],'Provider report unavailable');
   if(!is_array($page)||($page['source_kind']??null)!==$this->sourceKind)throw new RuntimeException('Provider source denied');
   if(array_key_exists('context_timezone',$page)&&$page['context_timezone']!==$timezone)throw new RuntimeException('Provider report timezone denied');
   try{$bytes=json_encode($page,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new RuntimeException('Provider response denied');}
   if(strlen($bytes)>1048576)throw new RuntimeException('Provider response limit');
   if($this->authorizedLease($company,$provider,$account)!==$lease)throw new RuntimeException('Account consent revoked');
   if(!is_array($page['rows']??null)||count($page['rows'])>10000)throw new RuntimeException('Provider rows denied');
   foreach($page['rows'] as $r){
    $date=$r['date']??null;
    if(!is_string($date)||!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$date)
     ||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))
     ||$date<$from||$date>$until)throw new RuntimeException('Provider report date denied');
    if(($r['account_id']??null)!==$account||!is_string($r['campaign_id']??null)||!preg_match('/\A[0-9]{1,32}\z/D',$r['campaign_id'])
     ||!preg_match('/\A[A-Z]{3}\z/D',$r['currency']??''))throw new RuntimeException('Provider scope denied');
    if($currency!==null&&$currency!==$r['currency'])throw new RuntimeException('Mixed currency denied');$currency=$r['currency'];
    $cost=$r['spend']??null;
    if($cost!==null){
     if(!is_string($cost)||!preg_match($provider==='google_ads'?'/\A[0-9]{1,24}\z/D':'/\A[0-9]{1,18}(\.[0-9]{1,6})?\z/D',$cost))throw new RuntimeException('Exact decimal string required');
     if($provider==='google_ads'){$cost=str_pad($cost,7,'0',STR_PAD_LEFT);$cost=substr($cost,0,-6).'.'.substr($cost,-6);}
    }
    foreach(['impressions','clicks'] as $metric)if(isset($r[$metric])&&(!is_string($r[$metric])||!preg_match('/\A[0-9]{1,24}\z/D',$r[$metric])))throw new RuntimeException('Integer metric string required');
    $rowKey=$r['campaign_id'].':'.$date;
    if(isset($rowKeys[$rowKey]))throw new RuntimeException('Duplicate campaign report date denied');
    $rowKeys[$rowKey]=true;
    $rows[]=['account_id'=>$account,'campaign_id'=>$r['campaign_id'],'currency'=>$currency,'date'=>$date,'spend'=>$cost,'impressions'=>$r['impressions']??null,'clicks'=>$r['clicks']??null];
    if(count($rows)>20000)throw new RuntimeException('Total rows exceeded');
   }
   $cursor=$page['next_cursor']??null;
   if($cursor===null){$complete=true;break;}
   if(!AdministrativeAdsReadPlan::validCursor($cursor)||isset($seen[$cursor]))throw new RuntimeException('Invalid pagination cursor');$seen[$cursor]=true;
  }
  return ['version'=>'imagine.performance.ads.read.result.v1','source_kind'=>$this->sourceKind,'status'=>$complete?($this->sourceKind==='http_unverified'?'unverified':'synthetic'):'partial',
   'company_id'=>$company,'provider'=>$provider,'api_version'=>$plan['read_plan']['api_version'],'timezone'=>$timezone,'currency'=>$currency,'from'=>$from,'until'=>$until,
   'complete'=>$complete,'rows'=>$rows,'account_metadata'=>$accountContext,'http_transport_used'=>$this->sourceKind==='http_unverified',
   'official_evidence'=>null,'external_operations_enabled'=>false,'billable'=>false];
 }
}
