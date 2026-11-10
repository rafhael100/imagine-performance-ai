<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/Performance/Contracts/PortalPerformanceChannelContract.php';
use App\Performance\Contracts\PortalPerformanceChannelContract as Channel;

$count=0;
$assert=static function(bool $ok,string $name)use(&$count):void{
    if(!$ok)throw new RuntimeException('F82 '.$name);
    ++$count;
};
$expected=['google_ads'=>'google_ads','meta'=>'meta_ads'];
foreach($expected as $internal=>$portal){
    $assert(Channel::toPortal($internal)===$portal,"toPortal $internal");
    $assert(Channel::fromPortal($portal)===$internal,"fromPortal $portal");
    $dto=Channel::describe($internal);
    $assert($dto!==null,"descriptor $internal");
    $assert($dto['schema_version']===Channel::SCHEMA,"schema $internal");
    $assert($dto['internal_channel']===$internal,"internal $internal");
    $assert($dto['portal_channel']===$portal,"portal $internal");
    foreach(['provider_connected','authorization_eligible','ready_for_production',
             'dispatch_enabled','billing_enabled'] as $field) {
        $assert($dto[$field]===false,"no go $internal/$field");
    }
    $assert($dto['source']==='offline_contract_only',"offline $internal");
}
foreach(['meta_ads','Meta','META','facebook','instagram','google','tiktok','',
          'meta ',' meta',"meta\n",'google-ads'] as $bad) {
    $assert(Channel::toPortal($bad)===null,"internal reject ".json_encode($bad));
    $assert(Channel::describe($bad)===null,"descriptor reject ".json_encode($bad));
}
foreach(['meta','Meta Ads','META_ADS','facebook','instagram','google','tiktok','',
          'meta_ads ',"meta_ads\n"] as $bad) {
    $assert(Channel::fromPortal($bad)===null,"portal reject ".json_encode($bad));
}
foreach([null,false,true,[],42,4.2,new stdClass()] as $bad) {
    $assert(Channel::fromPortal($bad)===null,'fromPortal type reject');
    $assert(Channel::toPortal($bad)===null,'toPortal type reject');
    $assert(Channel::describe($bad)===null,'describe type reject');
}
$reference=getenv('PORTAL_PERFORMANCE_ENUM_PATH');
if($reference!==false && $reference!==''){
    if(!is_file($reference)) throw new RuntimeException('Missing pinned Portal enum');
    $contents=(string)file_get_contents($reference);
    preg_match_all('/case\s+([A-Za-z][A-Za-z0-9]*)\s*=\s*\x27([^\x27]+)\x27\s*;/',$contents,$m,PREG_SET_ORDER);
    $pairs=[];
    foreach($m as $row) $pairs[$row[1]]=$row[2];
    $assert($pairs===['GoogleAds'=>'google_ads','MetaAds'=>'meta_ads'],'pinned Portal enum full parity');
    $assert(Channel::toPortal('meta')===$pairs['MetaAds'],'real Meta enum parity');
    $assert(Channel::toPortal('google_ads')===$pairs['GoogleAds'],'real Google enum parity');
}
echo "PASS F82 {$count} Portal↔Performance channel contract assertions\n";
