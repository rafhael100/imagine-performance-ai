<?php
declare(strict_types=1);

/**
 * F123: pinned Portal F29 Ed25519 issuer -> existing F29 verified envelope
 * -> PostgreSQL16 authenticated SQL A principal -> real F100 Laravel-backed
 * three-VIEW DEMO workbench, with NO real Portal IAM or ad dispatch.
 *
 * All SQL rows are synthetic; only public Ed25519 key is exchanged.
 */
if(PHP_SAPI!=='cli'
    ||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
    ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    ||getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
    ||!extension_loaded('sodium')){
    throw new RuntimeException('F123 must run only in disposable PG16 + ephemeral signed Portal CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalSqlWorkbenchBridge.php';
use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;

$packetFile=getenv('PERF_F123_SIGNED_PORTAL_CLAIM');
$publicKeyFile=getenv('PERF_F123_SIGNED_PORTAL_PUBLIC');
if(!is_string($packetFile)||!is_file($packetFile)
    ||!is_string($publicKeyFile)||!is_file($publicKeyFile)){
    throw new RuntimeException('F123 needs signed packet and public key from independently pinned Portal issuer');
}
$packet=trim((string)file_get_contents($packetFile));
$pub=trim((string)file_get_contents($publicKeyFile));
$now=1800000000;
$authority=SyntheticCurrentAuthority::forSyntheticLab($now);

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$tenantA='88888888-8888-4888-8888-888888888888';
$tenantB='99999999-9999-4999-8999-999999999999';
$n=0;
$check=static function(bool $v,string $label)use(&$n):void{
    if(!$v)throw new RuntimeException('FAIL F123 '.$label);
    ++$n;
};
$bridge=static fn(?string $key=null):SyntheticPortalSqlWorkbenchBridge=>
    new SyntheticPortalSqlWorkbenchBridge(
        new SignedPortalCapabilityGate($key??$pub)
    );
$deny=static function(array $dto,string $why,string $label)use($check):void{
    $check($dto['schema_version']===SyntheticPortalSqlWorkbenchBridge::SCHEMA,
        $label.' synthetic versioned contract');
    $check($dto['status']===$why,
        $label.' denied reason '.$why.' actually '.($dto['status']??'?'));
    $check($dto['synthetic_only']===true,$label.' only demonstration');
    $check($dto['read_only']===true,$label.' never mutate');
    $check($dto['cryptographic_lab_proof_verified']===false,
        $label.' no assertion of verified proof for denied response');
    $check($dto['credential_binding_confirmed']===false,$label.' SQL not authorized');
    foreach([
        'portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'
    ] as $field){
        $check($dto[$field]===false,$label.' '.$field.' false');
    }
    foreach(['accounts','campaigns','reviews','counts'] as $field){
        $check($dto[$field]===[],$label.' no '.$field.' leaked');
    }
};
$cloneProof=static function(array $override)use($authority):SyntheticCurrentAuthority{
    return new SyntheticCurrentAuthority(
        ...array_replace(get_object_vars($authority),$override)
    );
};

$check(preg_match('/^[a-f0-9]{64}$/D',$pub)===1,
    'pinned Portal issuer provides only Ed25519 public key');
$envelope=json_decode($packet,true,16,JSON_THROW_ON_ERROR);
$check(array_keys($envelope)===['version','key_id','payload','signature'],
    'actual Portal signed envelope strict shape');
$check($envelope['version']==='portal-capability-envelope.v1',
    'actual signed Portal V1 envelope');

$initial=$bridge();
$ok=$initial->preview($a,$packet,$authority,$now);
$check($ok['status']==='signed_portal_plus_sql_demo_preview_only',
    'Portal Ed25519 proof and F91 authenticated PostgreSQL login intersect');
$check($ok['cryptographic_lab_proof_verified']===true,
    'synthetic signature and current synthetic revisions verified');
$check($ok['credential_binding_confirmed']===true,
    'actual F91 SQL tenant A binding checked');
$check(count($ok['accounts'])===2,'two A-only advertiser DEMO accounts from F91 VIEW');
$check(count($ok['campaigns'])===1,'one A-only DEMO campaign from F93 VIEW');
$check(count($ok['reviews'])===3,
    'three A-only DEMO reviews incl F103/F104 from F97 VIEW');
$check($ok['counts']['account_candidates']===2,
    'composed F100 account count');
$check($ok['counts']['linked_campaigns']===1,
    'composed F100 campaign count');
$check($ok['counts']['visible_reviews']===3,
    'composed F100 review count');
$check($ok['portal_identity_authenticated']===false
    &&$ok['advertiser_approval_verified']===false
    &&$ok['provider_connected']===false
    &&$ok['authorization_eligible']===false
    &&$ok['ready_for_production']===false
    &&$ok['dispatch_enabled']===false
    &&$ok['billing_enabled']===false,
    'even signed+SQL fixture NEVER yields real identity or Ads/billing');
$check(!$a->inTransaction(),
    'F100 workbench commits read-only transaction before returning');
$encoded=json_encode($ok,JSON_THROW_ON_ERROR);
foreach([
    $tenantA,$tenantB,'ci-f91-only-a','private_key','access_token',
    'refresh_token','session-demo-a','actor-demo-a','ephemeral-lab-key-1'
] as $secret){
    $check(!str_contains($encoded,$secret),
        'bridge projection omits signed envelope, SQL tenant UUID and secrets');
}

$deny($initial->preview($a,$packet,$authority,$now),
    'signed_portal_proof_denied','same signed packet nonce replay in one gate');
$deny($bridge()->preview($b,$packet,$authority,$now),
    'sql_identity_untrusted_or_revoked',
    'valid Portal A signature cannot authorize a real B SQL principal');
$deny($bridge()->preview($admin,$packet,$authority,$now),
    'sql_identity_untrusted_or_revoked',
    'PostgreSQL superuser is not customer SQL role');
$deny($bridge()->preview($a,'{}',$authority,$now),
    'signed_portal_proof_denied','unsigned packet');
$badEnvelope=$envelope;
$badEnvelope['signature']=str_repeat('A',strlen($envelope['signature']));
$deny($bridge()->preview(
    $a,json_encode($badEnvelope,JSON_THROW_ON_ERROR),$authority,$now
),'signed_portal_proof_denied','forged signature');
$deny($bridge(bin2hex(sodium_crypto_sign_publickey(
    sodium_crypto_sign_keypair()
)))->preview($a,$packet,$authority,$now),
    'signed_portal_proof_denied','untrusted signing public key');
$deny($bridge()->preview($a,$packet,null,$now),
    'signed_portal_proof_denied','missing independently checked authority');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['serviceActive'=>false]),$now
),'signed_portal_proof_denied','revoked service in synthetic current authority');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['membershipActive'=>false]),$now
),'signed_portal_proof_denied','revoked organization membership');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['mfaVerified'=>false]),$now
),'signed_portal_proof_denied','MFA no longer valid in synthetic authority');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['recoveryEpoch'=>6]),$now
),'signed_portal_proof_denied','recovery epoch changes');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['observedAt'=>$now-6]),$now
),'signed_portal_proof_denied','independent authority snapshot is too old');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['observedAt'=>$now+20]),$now+20
),'signed_portal_proof_denied','expired signed Portal assertion');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['permission'=>'campaigns:write']),$now
),'signed_portal_proof_denied','signature cannot be used to escalate permission');
$deny($bridge()->preview(
    $a,$packet,$cloneProof(['tenantRef'=>'perf-demo-b']),$now
),'signed_portal_proof_denied','authority mismatches signed Portal tenant');

$a->beginTransaction();
$a->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
$deny($bridge()->preview($a,$packet,$authority,$now),
    'invalid_connection_state','caller-owned transaction denied');
$check($a->inTransaction(),'caller transaction left untouched');
$a->rollBack();

$a->prepare("SELECT set_config('app.performance_tenant_id',?,false)")
    ->execute([$tenantB]);
$spoof=$bridge()->preview($a,$packet,$authority,$now);
$check($spoof['status']==='signed_portal_plus_sql_demo_preview_only'
    &&$spoof['accounts']===$ok['accounts']
    &&$spoof['campaigns']===$ok['campaigns']
    &&$spoof['reviews']===$ok['reviews'],
    'legacy mutable tenant GUC does not alter signed plus SQL A projection after F119');

$revoke=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$restore=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);
$revoke->execute(['perf_f91_reader_a']);
$deny($bridge()->preview($a,$packet,$authority,$now),
    'sql_identity_untrusted_or_revoked',
    'Portal signed claim remains valid but SQL principal revoked');
$restore->execute([$tenantB,'perf_f91_reader_a']);
$deny($bridge()->preview($a,$packet,$authority,$now),
    'sql_identity_untrusted_or_revoked',
    'Portal signed claim A cannot pass after SQL login rebind to tenant B');
$restore->execute([$tenantA,'perf_f91_reader_a']);
$check($bridge()->preview($a,$packet,$authority,$now)['status']
    ==='signed_portal_plus_sql_demo_preview_only',
    'restored independent SQL A binding and fresh synthetic gate allow only A preview');

// NOTE: F29's in-memory nonce fence is per-instance. This is not a
// distributed replay guarantee; persistent SQL nonces F31/F45 are separate
// experiments. An independent Portal trusted IAM/resolver is also missing.
$check($bridge()->preview($a,$packet,$authority,$now)['status']
    ==='signed_portal_plus_sql_demo_preview_only',
    'separate in-memory gate can accept same nonce: KNOWN NO-GO caveat');

putenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB=0');
$deny($bridge()->preview($a,$packet,$authority,$now),
    'lab_disabled','F123 laboratory explicitly disabled');
putenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB=1');
putenv('APP_ENV=production');
$deny($bridge()->preview($a,$packet,$authority,$now),
    'lab_disabled','production mode always denies synthetic Portal SQL bridge');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'external IAM and operational SQL identity remain NO-GO');

echo "PASS F123 $n assertions: pinned Portal Ed25519 -> authenticated SQL A -> F100 workbench; replay/IAM caveat remains NO-GO\n";
