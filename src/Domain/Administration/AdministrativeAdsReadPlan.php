<?php
declare(strict_types=1);
namespace Imagine\Performance\Administration;
use DateTimeImmutable;
use InvalidArgumentException;
/** Non-executable request preparation. This class grants NO account/tenant authority. */
final class AdministrativeAdsReadPlan
{
 public static function prepare(string $channel,string $account,string $from,string $until):array
 {
  self::validateAccount($channel,$account);
  foreach([$from,$until] as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('Invalid report date');}
  if($from>$until||(new DateTimeImmutable($from))->diff(new DateTimeImmutable($until))->days>30)throw new InvalidArgumentException('Bounded period required');
  if($channel==='google_ads')$plan=['method'=>'POST','url'=>'https://googleads.googleapis.com/v26/customers/'.$account.'/googleAds:search',
   'json'=>['query'=>"SELECT campaign.id, campaign.name, campaign.status, customer.id, customer.currency_code, customer.time_zone, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros FROM campaign WHERE segments.date BETWEEN '$from' AND '$until'"],
   'cost_unit'=>'currency_micros','api_version'=>'v26'];
  else $plan=['method'=>'GET','url'=>'https://graph.facebook.com/v27.0/act_'.$account.'/insights',
   'query'=>['level'=>'campaign','fields'=>'account_id,campaign_id,campaign_name,account_currency,date_start,date_stop,impressions,clicks,spend',
    'time_range'=>json_encode(['since'=>$from,'until'=>$until],JSON_THROW_ON_ERROR),'time_increment'=>1,'limit'=>100],
   'cost_unit'=>'currency_decimal_string','api_version'=>'v27.0'];
  return ['version'=>'imagine.performance.ads.read.plan.v1','channel'=>$channel,'status'=>'unavailable','reason'=>'live_consent_credentials_and_provider_homologation_required',
   'metrics'=>null,'official_evidence'=>null,'external_operations_enabled'=>false,'billable'=>false,'executable'=>false,
   'max_pages'=>10,'max_response_bytes'=>1048576,'read_plan'=>$plan];
 }
 /** Account metadata is a separate read, independent of campaign/report rows. */
 public static function prepareAccount(string $channel,string $account):array
 {
  self::validateAccount($channel,$account);
  $plan=$channel==='google_ads'
   ?['method'=>'POST','url'=>'https://googleads.googleapis.com/v26/customers/'.$account.'/googleAds:search',
     'json'=>['query'=>'SELECT customer.id, customer.currency_code, customer.time_zone FROM customer'],'api_version'=>'v26']
   :['method'=>'GET','url'=>'https://graph.facebook.com/v27.0/act_'.$account,
     'query'=>['fields'=>'id,account_id,currency,timezone_name'],'api_version'=>'v27.0'];
  return ['version'=>'imagine.performance.ads.account.plan.v1','channel'=>$channel,'status'=>'unavailable',
   'reason'=>'live_consent_credentials_and_provider_homologation_required','metrics'=>null,'official_evidence'=>null,
   'external_operations_enabled'=>false,'billable'=>false,'executable'=>false,'max_response_bytes'=>1048576,'read_plan'=>$plan];
 }
 private static function validateAccount(string $channel,string $account):void
 {
  if(!in_array($channel,['google_ads','meta_ads'],true))throw new InvalidArgumentException('Provider unavailable');
  if(!preg_match($channel==='google_ads'?'/\A[0-9]{10}\z/D':'/\A[0-9]{5,32}\z/D',$account))throw new InvalidArgumentException('Invalid account reference');
 }
 /** Bounded opaque base64/base64url profile; never interpreted as a URL. */
 public static function validCursor(mixed $cursor):bool
 {
  return is_string($cursor)&&preg_match('/\A[A-Za-z0-9_+=\/-]{1,1024}\z/D',$cursor)===1;
 }
}
