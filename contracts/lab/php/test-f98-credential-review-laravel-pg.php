<?php
declare(strict_types=1);

/**
 * F1-98 Laravel-autoloadable review read model, consuming F97 on disposable
 * PostgreSQL16 and validating observed states with real Portal PHP enum.
 * No route, provider, human approval, OAuth, or real advertisers.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F98 permits disposable PostgreSQL CI only');
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';
$portal=getenv('PORTAL_CHECKOUT_ROOT');
if(!is_string($portal)||$portal===''||!is_dir($portal)) {
    throw new RuntimeException('F98 requires pinned Portal source');
}
require_once $portal.'/apps/portal/app/Domain/Catalog/PerformanceChannel.php';
require_once $portal.'/apps/portal/app/Domain/PerformanceDashboard/PerformanceReviewObservationState.php';
use App\Performance\ReadSide\CredentialBoundDemoReviewReader;
use App\Domain\Catalog\PerformanceChannel;
use App\Domain\PerformanceDashboard\PerformanceReviewObservationState as Review;

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$reader=new CredentialBoundDemoReviewReader();
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F98 '.$msg);
    ++$n;
};
$mustDeny=static function(array $dto,string $label)use($check):void{
    foreach(['portal_identity_authenticated','real_human_approval_verified',
        'authorization_eligible','provider_connected','ready_for_production',
        'dispatch_enabled','billing_enabled'] as $field) {
        $check(($dto[$field]??null)===false,"$label $field false");
    }
    $check(($dto['simulation_only']??null)===true,"$label DEMO-only");
};

$one=$reader->read($a);$two=$reader->read($b);
$check($one['status']==='credential_scoped_demo_review_preview_only','A scoped');
$check($two['status']==='credential_scoped_demo_review_preview_only','B scoped');
$check($one['credential_binding_confirmed']===true,'login A bound');
$check($two['credential_binding_confirmed']===true,'login B bound');
$check(count($one['reviews'])===1,'A rogue-journal pending quarantined, only approved');
$check(count($two['reviews'])===1,'B sees one rejected');
$check($one['reviews'][0]['request_ref']==='req-demo-f97-a-approved','A actual SQL review');
$check($two['reviews'][0]['request_ref']==='req-demo-f97-b-rejected','B actual SQL review');
$check($one['reviews'][0]['review_observation_state']==='approved_demo_no_execution',
    'A approval cannot imply provider execution');
$check($two['reviews'][0]['review_observation_state']==='rejected_demo',
    'B rejection');
foreach([['A',$one],['B',$two]] as [$label,$dto]){
    $mustDeny($dto,$label);
    $check($dto['schema_version']==='perf.portal.credential-bound-reviews.synthetic.v1',
        "$label schema version");
    foreach($dto['reviews'] as $review){
        $status=Review::from($review['review_observation_state']);
        $channel=PerformanceChannel::from($review['portal_channel']);
        $check($channel===PerformanceChannel::MetaAds,"$label real Portal Meta enum");
        $check(!$status->authorizesExternalOperation() && !$status->isRealApproval(),
            "$label observed status never grants operations");
        $check($review['journaled']===true,"$label F27 final journal exists");
        $check($review['portal_stage']===null,"$label no fabricated Portal workflow stage");
    }
}
$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['99999999-9999-4999-8999-999999999999']);
$b->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute(['88888888-8888-4888-8888-888888888888']);
$check($reader->read($a)['reviews']===$one['reviews'],'A cannot spoof B reviews');
$check($reader->read($b)['reviews']===$two['reviews'],'B cannot spoof A reviews');

$untrusted=$reader->read($admin);
$check($untrusted['status']==='untrusted_database_login'
    && $untrusted['reviews']===[],'admin not accepted as customer login');
$mustDeny($untrusted,'untrusted');

$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=clock_timestamp() WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$revoked=$reader->read($a);
$check($revoked['status']==='database_login_revoked' && $revoked['reviews']===[],
    'revoked A sees nothing');
$check($revoked['credential_binding_confirmed']===false,'revoked A loses binding');
$mustDeny($revoked,'revoked');
$check($reader->read($b)['reviews']===$two['reviews'],'B unaffected');
$admin->prepare(
    'UPDATE performance_db_principal_tenant_bindings SET revoked_at=NULL WHERE db_login=?'
)->execute(['perf_f91_reader_a']);
$check($reader->read($a)['reviews']===$one['reviews'],'DB-admin restores only A');

putenv('PERF_F98_CREDENTIAL_REVIEW_LAB=0');
$disabled=$reader->read($a);
$check($disabled['status']==='lab_disabled' && $disabled['reviews']===[],
    'environment flag off');
$mustDeny($disabled,'lab flag off');
putenv('PERF_F98_CREDENTIAL_REVIEW_LAB=1');
putenv('APP_ENV=production');
$prod=$reader->read($a);
$check($prod['status']==='lab_disabled' && $prod['reviews']===[],
    'production execution blocked');
$mustDeny($prod,'production');

$serialized=json_encode($one,JSON_THROW_ON_ERROR);
foreach(['88888888-8888-4888-8888-888888888888','ci-f91-only-a',
        'actor-reviewer-demo-a','payload_sha256','refresh_token','access_token'] as $secret) {
    $check(!str_contains($serialized,$secret),'no tenant/actor/secret leak');
}
echo "PASS F98 $n assertions: real F27 journal → login-bound F97 VIEW → Laravel reader → Portal enum; NO-GO\n";
