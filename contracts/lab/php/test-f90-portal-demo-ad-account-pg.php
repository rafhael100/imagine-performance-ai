<?php
declare(strict_types=1);

/**
 * F1-90 — Compare rows from actual RLS SQL with the REAL Portal PHP
 * DemoAdAccount class pinned from the Portal repository.
 * No producer, network, ad API or mutable account link is involved.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F90_REAL_PORTAL_ACCOUNT_PG_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F90 only allowed in disposable PostgreSQL CI');
}

$portal=getenv('PORTAL_CHECKOUT_ROOT');
if(!is_string($portal)||$portal===''||!is_dir($portal)) {
    throw new RuntimeException('F90 requires pinned Portal checkout');
}
$root=dirname(__DIR__,3);
require_once $root.'/app/Performance/Contracts/PortalPerformanceChannelContract.php';
require_once $portal.'/apps/portal/app/Domain/Catalog/PerformanceChannel.php';
require_once $portal.'/apps/portal/app/Domain/PerformanceOnboarding/DemoAdAccount.php';

use App\Performance\Contracts\PortalPerformanceChannelContract as ChannelMap;
use App\Domain\Catalog\PerformanceChannel as PortalChannel;
use App\Domain\PerformanceOnboarding\DemoAdAccount;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$pdo=new PDO($dsn,'perf_f90_projection_reader','ci-f90-reader-only',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);

$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$checkN=0;
$check=static function(bool $okay,string $label)use(&$checkN):void {
    if(!$okay)throw new RuntimeException('FAIL F90 '.$label);
    ++$checkN;
};
$read=static function(PDO $db):array {
    return $db->query(
       'SELECT l.tenant_id,l.portal_service_ref,l.channel,l.link_state,'
       .'l.dispatch_allowed,a.account_ref,a.channel AS account_channel,'
       .'a.connection_state,a.provider_connected,a.billable,'
       .'c.campaign_ref,c.channel AS campaign_channel,'
       .'c.lifecycle_state,c.dispatch_state AS campaign_dispatch_state '
       .'FROM performance_campaign_account_links l '
       .'JOIN performance_ad_account_candidates a '
       .'ON a.id=l.account_candidate_id AND a.tenant_id=l.tenant_id '
       .'AND a.portal_service_ref=l.portal_service_ref AND a.channel=l.channel '
       .'JOIN performance_managed_campaigns c '
       .'ON c.id=l.campaign_id AND c.tenant_id=l.tenant_id '
       .'AND c.portal_service_ref=l.portal_service_ref AND c.channel=l.channel'
    )->fetchAll(PDO::FETCH_ASSOC);
};
$scoped=static function(string $tenant)use($pdo,$read):array{
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT set_config('app.performance_tenant_id', ?, true)");
        $stmt->execute([$tenant]);
        $rows=$read($pdo);
        $pdo->commit();
        return $rows;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
};
$check($pdo->query('SELECT current_user')->fetchColumn()==='perf_f90_projection_reader',
    'least-privileged projection identity');
foreach([[$tenantA,'A'],[$tenantB,'B']] as [$tenant,$label]) {
    $rows=$scoped($tenant);
    $check(count($rows)===1,'one RLS account link per '.$label);
    $row=$rows[0];
    $check($row['tenant_id']===$tenant,'tenant '.$label.' isolated');
    $check($row['portal_service_ref']==='svc-demo-f88','service '.$label);
    $check($row['channel']==='meta' && $row['account_channel']==='meta'
        && $row['campaign_channel']==='meta','channel '.$label.' consistent');
    $portalValue=ChannelMap::toPortal($row['channel']);
    $check($portalValue==='meta_ads','F82 Meta alias mapped');
    $enum=PortalChannel::tryFrom($portalValue);
    $check($enum===PortalChannel::MetaAds,'actual Portal enum matches');
    $ref=new DemoAdAccount($enum,$row['account_ref']);
    $check($ref->reference==='demo_meta_a','actual Portal DemoAdAccount accepts stored synthetic ref');
    $check($ref->channel===PortalChannel::MetaAds,'Portal typed account channel');
    $check($row['connection_state']==='unverified_demo','account unverified '.$label);
    $check($row['link_state']==='unverified_demo','link unverified '.$label);
    $check($row['lifecycle_state']==='draft','campaign not live '.$label);
    foreach(['dispatch_allowed','provider_connected','billable'] as $flag) {
        $check(in_array($row[$flag],[false,'f','0',0],true),$flag.' always false '.$label);
    }
    $check($row['campaign_dispatch_state']==='disabled','campaign dispatch disabled '.$label);
}
$check($read($pdo)===[],'transaction GUC removed; no account rows leak');
foreach(['act_123456','123-456-7890','demo_../../bad','demo_','', 'real_google_account'] as $bad) {
    $failed=false;
    try{
        new DemoAdAccount(PortalChannel::MetaAds,$bad);
    } catch(InvalidArgumentException) { $failed=true; }
    $check($failed,'Portal rejects real/invalid account reference');
}
$check(ChannelMap::toPortal('meta_ads')===null,'do not accept Portal spelling as engine storage');
$check(ChannelMap::fromPortal('meta_ads')==='meta','explicit reverse mapping');
$check(!in_array($pdo->query("SELECT has_table_privilege(current_user,'performance_campaign_account_links','UPDATE')")->fetchColumn(),[true,'t','1',1],true),'reader cannot update link');
$check(!in_array($pdo->query("SELECT has_table_privilege(current_user,'performance_ad_account_candidates','INSERT')")->fetchColumn(),[true,'t','1',1],true),'reader cannot insert candidate');
$check(!in_array($pdo->query("SELECT has_table_privilege(current_user,'performance_managed_campaigns','DELETE')")->fetchColumn(),[true,'t','1',1],true),'reader cannot delete campaign');

echo "PASS F90 {$checkN} PostgreSQL RLS -> real Portal DemoAdAccount parity assertions\n";
