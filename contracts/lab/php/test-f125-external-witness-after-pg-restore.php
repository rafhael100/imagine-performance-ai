<?php
declare(strict_types=1);

/**
 * F125: RESTORE primary PostgreSQL16's nonce receipt table from a real
 * pg_dump made before a signed preview. The independent PostgreSQL16
 * witness on port 5433 must remember a consumed nonce across that restore.
 * All principals, tokens, campaigns and keys are synthetic CI fixtures.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
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
    throw new RuntimeException('F125 only runs in two separate disposable PostgreSQL16 services');
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

$packets=[];$publics=[];
for($i=1;$i<=3;$i++){
    $p=getenv('PERF_F125_PACKET_'.$i);
    $k=getenv('PERF_F125_PUBLIC_'.$i);
    if(!is_string($p)||!is_file($p)||!is_string($k)||!is_file($k)){
        throw new RuntimeException('F125 needs three independent signed Portal fixture files');
    }
    $packets[$i]=trim((string)file_get_contents($p));
    $publics[$i]=trim((string)file_get_contents($k));
}
$now=1800000000;
$authority=SyntheticCurrentAuthority::forSyntheticLab($now);
$opts=[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
];
$primaryDsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$externalDsn='pgsql:host=127.0.0.1;port=5433;dbname=perf_f125_witness_ci;connect_timeout=3';
$primary=new PDO($primaryDsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($primaryDsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($primaryDsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$localWriter1=new PDO($primaryDsn,'perf_f124_receipt_writer','ci-f124-receipt-write-only',$opts);
$localWriter2=new PDO($primaryDsn,'perf_f124_receipt_writer','ci-f124-receipt-write-only',$opts);
$external=new PDO($externalDsn,'postgres','ci-f125-witness-admin',$opts);
$w1=new PDO($externalDsn,'perf_f125_witness_writer','ci-f125-witness-insert-only',$opts);
$w2=new PDO($externalDsn,'perf_f125_witness_writer','ci-f125-witness-insert-only',$opts);
$n=0;
$check=static function(bool $good,string $message)use(&$n):void{
    if(!$good)throw new RuntimeException('FAIL F125 '.$message);
    ++$n;
};
$extCount=static fn():int=>(int)$external->query(
    'SELECT count(*) FROM public.perf_f125_signed_portal_witness'
)->fetchColumn();
$primaryCount=static fn():int=>(int)$primary->query(
    'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$has=static function(string $role,string $object,string $priv)use($external):bool {
    $stmt=$external->prepare('SELECT has_table_privilege(?,?,?)');
    $stmt->execute([$role,'public.'.$object,$priv]);
    return in_array($stmt->fetchColumn(),[true,'t',1,'1'],true);
};
$local=static function(int $i,PDO $writer)use($publics):SyntheticDurableSignedSqlPreviewBridge{
    return new SyntheticDurableSignedSqlPreviewBridge(
        new SyntheticPortalSqlWorkbenchBridge(
            new SignedPortalCapabilityGate($publics[$i])
        ),
        new SyntheticSignedPortalNonceFence($writer)
    );
};
$dual=static function(int $i,PDO $localWriter,PDO $witnessWriter)use($local):SyntheticExternallyWitnessedPortalPreview{
    return new SyntheticExternallyWitnessedPortalPreview(
        $local($i,$localWriter),
        new SyntheticExternalSignedNonceWitness($witnessWriter)
    );
};
$assertDenied=static function(array $dto,string $status,string $label)use($check):void{
    $check($dto['schema_version']===SyntheticExternallyWitnessedPortalPreview::SCHEMA,
        $label.' versioned schema');
    $check($dto['status']===$status,
        $label.' status '.$status.' actual '.($dto['status']??'none'));
    foreach([
        'cryptographic_lab_proof_verified','local_receipt_recorded',
        'external_witness_recorded','credential_binding_confirmed',
        'portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'
    ] as $field){
        $check(($dto[$field]??null)===false,
            $label.' '.$field.' is false');
    }
    foreach(['accounts','campaigns','reviews','counts'] as $field){
        $check($dto[$field]===[],$label.' no '.$field.' rows leak');
    }
    $check($dto['synthetic_only']===true && $dto['read_only']===true,
        $label.' remains synthetic readonly');
};
$assertAccepted=static function(array $dto,string $label)use($check):void {
    $check($dto['schema_version']===SyntheticExternallyWitnessedPortalPreview::SCHEMA,
        $label.' signed and externally witnessed schema');
    $check($dto['status']==='signed_sql_demo_with_independent_witness_only',
        $label.' valid double receipt response; got '.($dto['status']??'none'));
    foreach(['cryptographic_lab_proof_verified',
             'local_receipt_recorded','external_witness_recorded',
             'credential_binding_confirmed'] as $field){
        $check($dto[$field]===true,$label.' '.$field.' verified');
    }
    foreach(['portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'] as $field){
        $check($dto[$field]===false,$label.' still no real authority: '.$field);
    }
    $check(count($dto['accounts'])===2,$label.' exactly two synthetic A accounts');
    $check(count($dto['campaigns'])===1,$label.' only A synthetic campaign');
    $check(count($dto['reviews'])===3,$label.' only three synthetic A reviews');
};

$check($extCount()===0,'external witness starts empty');
$check($primaryCount()===2,'primary contains two prior F124 fixture receipts');
$check(count(array_unique($publics))===3,'three independently generated Ed25519 public keys');
$check(count(array_unique($packets))===3,'three independent Portal signed claim packets');
$check($primary->query('SELECT current_database()')->fetchColumn()
    ==='perf_test_campaign_registry','primary DB identity exact');
$check($external->query('SELECT current_database()')->fetchColumn()
    ==='perf_f125_witness_ci','witness DB identity exact and separate');
$check($has('perf_f125_witness_writer','perf_f125_signed_portal_witness','INSERT'),
    'external writer INSERT granted');
foreach(['SELECT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'] as $priv){
    $check(!$has('perf_f125_witness_writer','perf_f125_signed_portal_witness',$priv),
        'external writer cannot '.$priv.' witness');
}
$state=$external->query(
    "SELECT pg_catalog.pg_get_userbyid(relowner) AS owner,"
    ."relrowsecurity,relforcerowsecurity FROM pg_catalog.pg_class "
    ."WHERE oid='public.perf_f125_signed_portal_witness'::regclass"
)->fetch(PDO::FETCH_ASSOC);
$check($state['owner']==='perf_f125_witness_owner',
    'external witness owner is separate NOLOGIN role');
$check(in_array($state['relrowsecurity'],[true,'t',1,'1'],true)
    &&in_array($state['relforcerowsecurity'],[true,'t',1,'1'],true),
    'external witness remains ENABLE/FORCE RLS');
$owner=$external->query(
    "SELECT rolcanlogin,rolsuper,rolbypassrls,rolinherit "
    ."FROM pg_catalog.pg_roles WHERE rolname='perf_f125_witness_owner'"
)->fetch(PDO::FETCH_ASSOC);
foreach($owner as $attr=>$val){
    $check(!in_array($val,[true,'t',1,'1'],true),
        'external owner '.$attr.' is disabled');
}
foreach(['performance_signed_portal_demo_receipts',
         'performance_campaign_intents','performance_ad_account_candidates'] as $forbidden){
    $test=$external->prepare('SELECT to_regclass(?) IS NULL');
    $test->execute(['public.'.$forbidden]);
    $check(in_array($test->fetchColumn(),[true,'t',1,'1'],true),
        'no primary tenant/campaign tables copied to witness service: '.$forbidden);
}
$denySQL=static function(PDO $db,string $sql,string $label)use($check):void{
    $blocked=false;
    try{$db->exec($sql);}catch(PDOException){$blocked=true;}
    $check($blocked,$label);
};
$denySQL($w1,'SELECT * FROM public.perf_f125_signed_portal_witness',
    'external writer cannot enumerate nonce digests');
$denySQL($w1,'DELETE FROM public.perf_f125_signed_portal_witness',
    'external writer cannot clear external witness');
$denySQL($w1,'UPDATE public.perf_f125_signed_portal_witness SET claimed_epoch=1800000001',
    'external writer cannot mutate recorded epoch');
$check((new SyntheticExternalSignedNonceWitness($primary))
    ->witness(str_repeat('a',32),$now)==='untrusted_external_writer',
    'primary superuser cannot masquerade as external witness writer');

// Genuine data-only backup of primary's F124 receipts, before any new F125
// nonce is consumed. The restore below changes ONLY primary data, never
// touches the separate Postgres service at port 5433.
$snapshot=getenv('PERF_F125_PRIMARY_RECEIPT_SNAPSHOT');
$check(is_string($snapshot)
    &&str_starts_with($snapshot,'/tmp/perf-f125-')
    &&!is_file($snapshot) && !is_link($snapshot),
    'fresh controlled CI-only backup path');
$dumpCmd='pg_dump -h 127.0.0.1 -p 5432 -U postgres '
    .'-d perf_test_campaign_registry --data-only --column-inserts '
    .'--table=public.performance_signed_portal_demo_receipts '
    .'--file='.escapeshellarg($snapshot).' 2>&1';
$log=[];$code=-1;
exec($dumpCmd,$log,$code);
$check($code===0 &&is_file($snapshot)
    &&filesize($snapshot)>150,
    'real pg_dump snapshot of primary nonce receipts captured successfully');

$first=$dual(1,$localWriter1,$w1)->preview($a,$packets[1],$authority,$now);
$assertAccepted($first,'first signed Portal F125 preview');
$check($primaryCount()===3,'primary recorded F125 first nonce');
$check($extCount()===1,'independent Postgres witness recorded first nonce');
$assertDenied($dual(1,$localWriter2,$w2)->preview(
    $a,$packets[1],$authority,$now
),'local_signed_sql_or_replay_denied',
    'replay fails before restore in primary ledger');
$check($extCount()===1,'duplicate presentation does not alter external witness');

// Perform REAL restoration of earlier primary data-only pg_dump through psql.
// This reproduces a stale primary backup where the most recently consumed
// signed nonce has disappeared. The independent witness is never restored.
$restore=static function()use($primary,$snapshot,$check,$primaryCount):void{
    $primary->exec('TRUNCATE TABLE public.performance_signed_portal_demo_receipts');
    $cmd='psql -X -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 '
        .'-U postgres -d perf_test_campaign_registry -f '
        .escapeshellarg($snapshot).' 2>&1';
    $out=[];$exit=-1;
    exec($cmd,$out,$exit);
    $check($exit===0,'restored exact historical primary receipt pg_dump');
    $check($primaryCount()===2,
        'primary rollback actually erased F125 consumed nonce while retaining F124 baseline');
};
$restore();
$check($extCount()===1,
    'separate external witness did NOT roll back with primary receipt backup');

// Expose the *local-only* vulnerability directly in the disposable lab.
// The F124 bridge really accepts the old Portal signed claim again after
// restoring the older primary DB nonce receipts.
$replayedLocal=$local(1,$localWriter2)->preview(
    $a,$packets[1],$authority,$now
);
$check($replayedLocal['status']==='signed_sql_demo_with_local_durable_nonce_only',
    'CONFIRMED local-only F124 replay reaccepted after old primary pg_dump restore');
$check($primaryCount()===3 &&$extCount()===1,
    'local ledger reconsumed forgotten nonce but independent witness remained unchanged');

// Restore primary a second time to ensure F125 tries the same nonce only
// against the independent witness after local verification succeeds.
$restore();
$replayedDual=$dual(1,$localWriter1,$w2)->preview(
    $a,$packets[1],$authority,$now
);
$assertDenied($replayedDual,'independent_witness_rejected_or_unavailable',
    'AFTER real primary DB receipt restore external witness forbids replay');
$check($extCount()===1,
    'external witness cannot be reused even if local receipt disappeared');
$check($primaryCount()===3,
    'local receipt may be consumed again but DENIED F125 did not emit data');

// A different signed packet from Portal must still be serviceable after
// restoring primary, via a new worker and new external INSERT-only writer.
$second=$dual(2,$localWriter2,$w2)->preview($a,$packets[2],$authority,$now);
$assertAccepted($second,'fresh signed Portal packet after primary restore');
$check($primaryCount()===4 &&$extCount()===2,
    'independent valid nonces recorded in BOTH databases');

// For an external outage/wrong authority, F125 must not emit an account
// projection, even though F124 consumed the local nonce already.
$wrongWitness=$dual(3,$localWriter1,$primary)->preview(
    $a,$packets[3],$authority,$now
);
$assertDenied($wrongWitness,'independent_witness_rejected_or_unavailable',
    'invalid/unavailable independent witness fails closed even with valid local signature');
$check($extCount()===2,'external authority not updated by wrong SQL connection');
$check($primaryCount()===5,'local nonce can burn on external witness outage: documented NO-GO');
$assertDenied($dual(3,$localWriter2,$w1)->preview(
    $a,$packets[3],$authority,$now
),'local_signed_sql_or_replay_denied',
    'after failed witness, local nonce cannot be retried without recovery protocol');

// Signed Portal A cannot present through SQL login B.
$assertDenied($dual(2,$localWriter1,$w1)->preview(
    $b,$packets[2],$authority,$now
),'local_signed_sql_or_replay_denied',
    'Portal A claim never authorizes B SQL tenant');

putenv('PERF_F125_EXTERNAL_NONCE_LAB=0');
$assertDenied($dual(2,$localWriter1,$w1)->preview(
    $a,$packets[2],$authority,$now
),'external_witness_lab_disabled',
    'F125 cannot run without flag');
putenv('PERF_F125_EXTERNAL_NONCE_LAB=1');
putenv('APP_ENV=production');
$assertDenied($dual(2,$localWriter1,$w1)->preview(
    $a,$packets[2],$authority,$now
),'external_witness_lab_disabled',
    'F125 cannot run in production');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'two PostgreSQL CI services do not clear operational Portal IAM or release NO-GO');

echo "PASS F125 $n assertions: real pg_dump restore reopens local F124 nonce, separate PostgreSQL witness denies replay, no Ads/billing\n";
