<?php
declare(strict_types=1);

/**
 * F1-94 integration: PostgreSQL16 RLS VIEW -> Laravel app read model ->
 * actual Portal PHP classes; no credentials/tokens from live ad accounts.
 */
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F94 may only run with disposable PostgreSQL CI');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';
$portal=getenv('PORTAL_CHECKOUT_ROOT');
if (!is_string($portal)||$portal===''||!is_dir($portal)) {
    throw new RuntimeException('F94 requires pinned Portal checkout');
}
require_once $portal.'/apps/portal/app/Domain/Catalog/PerformanceChannel.php';
require_once $portal.'/apps/portal/app/Domain/PerformanceOnboarding/DemoAdAccount.php';
require_once $portal.'/apps/portal/app/Domain/PerformanceDashboard/PerformanceCampaignObservationState.php';

use App\Performance\ReadSide\CredentialBoundDemoCampaignReader;
use App\Domain\Catalog\PerformanceChannel;
use App\Domain\PerformanceOnboarding\DemoAdAccount;
use App\Domain\PerformanceDashboard\PerformanceCampaignObservationState;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$options);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$options);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$options);
$reader=new CredentialBoundDemoCampaignReader();
$n=0;
$check=static function(bool $okay,string $label)use(&$n):void {
    if(!$okay)throw new RuntimeException('FAIL F94 '.$label);
    ++$n;
};
$checkDenied=static function(array $dto,string $label)use($check):void {
    foreach (['portal_identity_authenticated','provider_connected','authorization_eligible',
              'ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
        $check(($dto[$flag]??null)===false,"$label/$flag must be false");
    }
    $check(($dto['simulation_only']??null)===true,"$label demo only");
};
$one=$reader->read($a);$two=$reader->read($b);
$check($one['status']==='credential_scoped_demo_preview_only','A credential-scoped preview');
$check($two['status']==='credential_scoped_demo_preview_only','B credential-scoped preview');
$check($one['credential_binding_confirmed']===true,'A db credential mapped');
$check($two['credential_binding_confirmed']===true,'B db credential mapped');
$check(count($one['campaigns'])===1,'A has one linked campaign');
$check(count($two['campaigns'])===1,'B has one linked campaign');
$check($one['campaigns'][0]['campaign_ref']==='cam-demo-f89-a','A campaign scoped');
$check($two['campaigns'][0]['campaign_ref']==='cam-demo-f89-b','B campaign scoped');
$checkDenied($one,'A');
$checkDenied($two,'B');
foreach([['A',$one],['B',$two]] as [$label,$dto]) {
    $check($dto['schema_version']==='perf.portal.credential-bound-campaigns.synthetic.v1',
        "$label DTO version");
    foreach($dto['campaigns'] as $campaign) {
        $portalEnum=PerformanceChannel::from($campaign['portal_channel']);
        $account=new DemoAdAccount($portalEnum,$campaign['account_ref']);
        $state=PerformanceCampaignObservationState::from($campaign['campaign_observation_state']);
        $check($portalEnum===PerformanceChannel::MetaAds,"$label Portal Meta enum");
        $check($account->reference==='demo_meta_a',"$label real Portal DemoAdAccount reference");
        $check($state===PerformanceCampaignObservationState::DraftDemo,"$label display-only draft state");
        $check(!$state->externallyConfirmed() && !$state->canExecute(),
            "$label DEMO state cannot grant media");
        $check($campaign['portal_stage']===null,"$label no fabricated Portal workflow stage");
        $check($campaign['account_state']==='unverified_demo',"$label account never verified");
    }
}
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['88888888-8888-4888-8888-888888888888']);
$check($reader->read($a)['campaigns']===$one['campaigns'],
    'A fabricated GUC cannot change Laravel campaign preview');
$check($reader->read($b)['campaigns']===$two['campaigns'],
    'B fabricated GUC cannot change Laravel campaign preview');

$adminDto=$reader->read($admin);
$check($adminDto['status']==='untrusted_database_login'
    && $adminDto['campaigns']===[],
    'admin credential is not a tenant login');
$checkDenied($adminDto,'admin');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$revoked=$reader->read($a);
$check($revoked['status']==='database_login_revoked' && $revoked['campaigns']===[],
    'revoked A sees no campaigns');
$check($revoked['credential_binding_confirmed']===false,'revoked credential not confirmed');
$checkDenied($revoked,'revoked');
$check($reader->read($b)['campaigns']===$two['campaigns'],
    'B still sees its campaign after revoking A');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($reader->read($a)['campaigns']===$one['campaigns'],
    'admin restoration only restores original A scope');

putenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB=0');
$disabled=$reader->read($a);
$check($disabled['status']==='lab_disabled' && $disabled['campaigns']===[],
    'reader does not run without lab flag');
$checkDenied($disabled,'disabled');
putenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB=1');
putenv('APP_ENV=production');
$prod=$reader->read($a);
$check($prod['status']==='lab_disabled' && $prod['campaigns']===[],
    'reader does not execute in production');
$checkDenied($prod,'production');
$encoded=json_encode($one,JSON_THROW_ON_ERROR);
foreach(['88888888-8888-4888-8888-888888888888','ci-f91-only-a',
         'refresh_token','access_token','credit_card','provider_account_id'] as $hidden) {
    $check(!str_contains($encoded,$hidden),'do not expose tenant UUID, passwords or account secrets');
}
echo "PASS F94 $n assertions: PostgreSQL login-bound campaign/account -> Laravel -> real Portal observation state\n";
