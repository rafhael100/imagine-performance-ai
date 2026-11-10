<?php
declare(strict_types=1);

/**
 * F126: reduce avoidable local nonce consumption when external witness is
 * visibly unavailable. Use two real PG16 services after F125 restore test.
 * Also demonstrate remaining preflight→INSERT race without leaking DEMO DTO.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F126_WITNESS_PREFLIGHT_LAB')!=='1'
    ||getenv('PERF_F125_EXTERNAL_NONCE_LAB')!=='1'
    ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'
    ||getenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
    ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    ||getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
    ||getenv('PERF_F125_EXTERNAL_DB')!=='perf_f125_witness_ci'
    ||getenv('PERF_F125_EXTERNAL_PORT')!=='5433'){
    throw new RuntimeException('F126 requires dual disposable PG16 CI after F125');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalSqlWorkbenchBridge.php';
require_once __DIR__.'/SyntheticSignedPortalNonceFence.php';
require_once __DIR__.'/SyntheticDurableSignedSqlPreviewBridge.php';
require_once __DIR__.'/SyntheticExternalSignedNonceWitness.php';
require_once __DIR__.'/SyntheticExternallyWitnessedPortalPreview.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;
use App\Performance\Security\SyntheticExternalSignedNonceWitness;
use App\Performance\Security\SyntheticExternallyWitnessedPortalPreview;

final class F126InterleavedTenantPdo extends PDO
{
    public ?Closure $afterAccountSelect=null;
    public function query(
        string $query,
        ?int $fetchMode=null,
        mixed ...$fetchModeArgs
    ):PDOStatement|false {
        $result=parent::query($query);
        if($result!==false
            &&$this->afterAccountSelect!==null
            &&str_contains($query,'FROM public.perf_f91_demo_account_read')){
            $trigger=$this->afterAccountSelect;
            $this->afterAccountSelect=null;
            $trigger();
        }
        return $result;
    }
}

$packets=[];$publics=[];
for($i=1;$i<=3;$i++){
    $p=getenv('PERF_F126_PACKET_'.$i);
    $k=getenv('PERF_F126_PUBLIC_'.$i);
    if(!is_string($p)||!is_file($p)||!is_string($k)||!is_file($k)){
        throw new RuntimeException('F126 missing independently signed Portal CI packets');
    }
    $packets[$i]=trim((string)file_get_contents($p));
    $publics[$i]=trim((string)file_get_contents($k));
}
$now=1800000000;
$authority=SyntheticCurrentAuthority::forSyntheticLab($now);
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$main='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$other='pgsql:host=127.0.0.1;port=5433;dbname=perf_f125_witness_ci;connect_timeout=3';
$primary=new PDO($main,'postgres','ci-f22-db-only',$opt);
$a=new PDO($main,'perf_f91_reader_a','ci-f91-only-a',$opt);
$localWriter=new PDO($main,'perf_f124_receipt_writer','ci-f124-receipt-write-only',$opt);
$extAdmin=new PDO($other,'postgres','ci-f125-witness-admin',$opt);
$extWriter=new PDO($other,'perf_f125_witness_writer','ci-f125-witness-insert-only',$opt);
$extraWriter=new PDO($other,'perf_f125_witness_writer','ci-f125-witness-insert-only',$opt);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
    if(!$ok)throw new RuntimeException('FAIL F126 '.$label);
    ++$n;
};
$localCount=static fn():int=>(int)$primary->query(
    'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$externalCount=static fn():int=>(int)$extAdmin->query(
    'SELECT count(*) FROM public.perf_f125_signed_portal_witness'
)->fetchColumn();
$preview=static function(int $index,PDO $writer,PDO $witness)
    use($publics,$localWriter):SyntheticExternallyWitnessedPortalPreview {
    return new SyntheticExternallyWitnessedPortalPreview(
        new SyntheticDurableSignedSqlPreviewBridge(
            new SyntheticPortalSqlWorkbenchBridge(
                new SignedPortalCapabilityGate($publics[$index])
            ),
            new SyntheticSignedPortalNonceFence($localWriter)
        ),
        new SyntheticExternalSignedNonceWitness($witness)
    );
};
$denied=static function(array $dto,string $status,string $label)use($check):void{
    $check($dto['schema_version']===SyntheticExternallyWitnessedPortalPreview::SCHEMA,
        $label.' schema remains versioned');
    $check($dto['status']===$status,
        $label.' reason '.$status.' got '.($dto['status']??'null'));
    foreach([
        'cryptographic_lab_proof_verified','local_receipt_recorded',
        'external_witness_recorded','credential_binding_confirmed',
        'portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'
    ] as $key){
        $check($dto[$key]===false,$label.' '.$key.' denies');
    }
    foreach(['accounts','campaigns','reviews','counts'] as $key){
        $check($dto[$key]===[],$label.' no '.$key);
    }
};
$accepted=static function(array $dto,string $label)use($check):void{
    $check($dto['status']==='signed_sql_demo_with_independent_witness_only',
        $label.' admitted only as synthetic, status '.($dto['status']??'none'));
    $check($dto['local_receipt_recorded']===true
        &&$dto['external_witness_recorded']===true,
        $label.' both local and external receipts');
    $check(count($dto['accounts'])===2
        &&count($dto['campaigns'])===1
        &&count($dto['reviews'])===3,
        $label.' precise SQL A-only demo counts');
    $check($dto['portal_identity_authenticated']===false
        &&$dto['authorization_eligible']===false
        &&$dto['dispatch_enabled']===false
        &&$dto['billing_enabled']===false,
        $label.' never production IAM/Ads/billing');
};
$grant='GRANT INSERT ON TABLE public.perf_f125_signed_portal_witness '
    .'TO perf_f125_witness_writer';
$revoke='REVOKE INSERT ON TABLE public.perf_f125_signed_portal_witness '
    .'FROM perf_f125_witness_writer';

$check($localCount()===5,
    'expected F125 post-restore local nonce receipts remain five');
$check($externalCount()===2,
    'expected F125 two independently witnessed nonces persist');
$check(count(array_unique($packets))===3
    &&count(array_unique($publics))===3,
    'three independent signed fixtures from pinned Portal emitter');

$good=new SyntheticExternalSignedNonceWitness($extWriter);
$check($good->preflight()==='external_witness_preflight_ready_ci_only',
    'external writer has correct SQL login/DB, INSERT and NO SELECT');
$check((new SyntheticExternalSignedNonceWitness($primary))->preflight()
    ==='preflight_untrusted_or_unavailable',
    'primary SQL admin cannot certify external witness writer');
$check($localCount()===5 &&$externalCount()===2,
    'non-mutating preflight neither burns nor inserts nonce');

$extAdmin->exec($revoke);
$check($good->preflight()==='preflight_untrusted_or_unavailable',
    'preflight detects lost external INSERT grant without SELECT');
$denied($preview(1,$localWriter,$extWriter)->preview(
    $a,$packets[1],$authority,$now
),'independent_witness_not_ready',
    'witness lacks INSERT BEFORE local signed nonce consumption');
$check($localCount()===5 &&$externalCount()===2,
    'early deny preserves Portal nonce on PRIMARY and EXTERNAL DB');
$extAdmin->exec($grant);
$check($good->preflight()==='external_witness_preflight_ready_ci_only',
    'preflight accepts exact restored INSERT-only privilege');
$accepted($preview(1,$localWriter,$extWriter)->preview(
    $a,$packets[1],$authority,$now
),'same Portal packet becomes usable once witness grant restored');
$check($localCount()===6 &&$externalCount()===3,
    'same packet now consumed exactly once in both DBs');
$denied($preview(1,$localWriter,$extraWriter)->preview(
    $a,$packets[1],$authority,$now
),'local_signed_sql_or_replay_denied',
    'replay of first packet denied even with fresh verifier');

$extraWriter->beginTransaction();
$extraWriter->exec('SET TRANSACTION READ ONLY');
$check((new SyntheticExternalSignedNonceWitness($extraWriter))->preflight()
    ==='preflight_invalid_connection',
    'caller-owned external READ ONLY transaction rejected');
$denied($preview(2,$localWriter,$extraWriter)->preview(
    $a,$packets[2],$authority,$now
),'independent_witness_not_ready',
    'external transaction blocked before burning second nonce');
$check($extraWriter->inTransaction(),
    'F126 never commits externally-owned SQL transaction');
$extraWriter->rollBack();
$check($localCount()===6 &&$externalCount()===3,
    'transaction preflight denial did not change either nonce ledger');
$accepted($preview(2,$localWriter,$extraWriter)->preview(
    $a,$packets[2],$authority,$now
),'second Portal packet after external session rollback');
$check($localCount()===7 &&$externalCount()===4,
    'second nonce successfully recorded in local and external SQL');

// Expose and preserve documented TOCTOU boundary: preflight can pass,
// then the external permission can disappear *during* the local workbench
// SQL projection, before the external witness INSERT. This stays NO-GO.
$interleaved=new F126InterleavedTenantPdo(
    $main,'perf_f91_reader_a','ci-f91-only-a',$opt
);
$interleaved->afterAccountSelect=static function()use($extAdmin,$revoke):void{
    $extAdmin->exec($revoke);
};
$race=$preview(3,$localWriter,$extWriter)->preview(
    $interleaved,$packets[3],$authority,$now
);
$denied($race,'independent_witness_rejected_or_unavailable',
    'INSERT grant revoked AFTER preflight before witness INSERT');
$check($localCount()===8,
    'KNOWN LIMIT: local nonce burned despite successful early preflight');
$check($externalCount()===4,
    'remote record absent but NO DEMO DTO was returned');
$extAdmin->exec($grant);
$denied($preview(3,$localWriter,$extWriter)->preview(
    $a,$packets[3],$authority,$now
),'local_signed_sql_or_replay_denied',
    'failed external witness after preflight leaves consumed local nonce');
$check($localCount()===8 &&$externalCount()===4,
    'no unsafe silent retry or compensating receipt deletion');

putenv('PERF_F126_WITNESS_PREFLIGHT_LAB=0');
$check((new SyntheticExternalSignedNonceWitness($extWriter))->preflight()
    ==='preflight_lab_disabled','preflight CI flag strict');
putenv('PERF_F126_WITNESS_PREFLIGHT_LAB=1');
putenv('APP_ENV=production');
$denied($preview(2,$localWriter,$extWriter)->preview(
    $a,$packets[2],$authority,$now
),'external_witness_lab_disabled',
    'external workbench never enabled for production');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'preflight is not atomic IAM/2PC and never changes the release NO-GO');
echo "PASS F126 $n assertions: witness ACL/connection preflight avoids known nonce burn; post-preflight failure stays fail-closed, NO-GO\n";
