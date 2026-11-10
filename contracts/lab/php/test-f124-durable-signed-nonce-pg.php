<?php
declare(strict_types=1);

/**
 * F124 PostgreSQL16 nonce ledger: fresh issuer signatures, multiple verifier
 * instances and independent INSERT-only SQL writers share one unique ledger.
 * Requires F112/F117/F119 and pinned Portal F29 issuer already tested.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F123_SIGNED_SQL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'
    ||getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
    ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
    ||getenv('PERF_F92_CREDENTIAL_READER_LAB')!=='1'
    ||getenv('PERF_F94_CREDENTIAL_CAMPAIGN_LAB')!=='1'
    ||getenv('PERF_F98_CREDENTIAL_REVIEW_LAB')!=='1'
    ||getenv('PGHOST')!=='127.0.0.1'
    ||getenv('PGPORT')!=='5432'
    ||getenv('PGDATABASE')!=='perf_test_campaign_registry'){
    throw new RuntimeException('F124 is disposable PostgreSQL16 CI only');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';
require_once __DIR__.'/SyntheticPortalSqlWorkbenchBridge.php';
require_once __DIR__.'/SyntheticSignedPortalNonceFence.php';
require_once __DIR__.'/SyntheticDurableSignedSqlPreviewBridge.php';

use App\Performance\Security\SyntheticCurrentAuthority;
use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticPortalSqlWorkbenchBridge;
use App\Performance\Security\SyntheticSignedPortalNonceFence;
use App\Performance\Security\SyntheticDurableSignedSqlPreviewBridge;

$packetFiles=[
    getenv('PERF_F124_SIGNED_PACKET_1'),
    getenv('PERF_F124_SIGNED_PACKET_2'),
];
$pubFiles=[
    getenv('PERF_F124_SIGNED_PUB_1'),
    getenv('PERF_F124_SIGNED_PUB_2'),
];
foreach([...$packetFiles,...$pubFiles] as $file){
    if(!is_string($file)||!is_file($file)){
        throw new RuntimeException('F124 missing independent pinned Portal signer artifacts');
    }
}
$packet1=trim((string)file_get_contents($packetFiles[0]));
$packet2=trim((string)file_get_contents($packetFiles[1]));
$public1=trim((string)file_get_contents($pubFiles[0]));
$public2=trim((string)file_get_contents($pubFiles[1]));
$now=1800000000;
$authority=SyntheticCurrentAuthority::forSyntheticLab($now);

$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($dsn,'postgres','ci-f22-db-only',$opts);
$a=new PDO($dsn,'perf_f91_reader_a','ci-f91-only-a',$opts);
$b=new PDO($dsn,'perf_f91_reader_b','ci-f91-only-b',$opts);
$w1=new PDO($dsn,'perf_f124_receipt_writer','ci-f124-receipt-write-only',$opts);
$w2=new PDO($dsn,'perf_f124_receipt_writer','ci-f124-receipt-write-only',$opts);
$n=0;
$check=static function(bool $ok,string $message)use(&$n):void {
    if(!$ok)throw new RuntimeException('FAIL F124 '.$message);
    ++$n;
};
$active=static fn(mixed $v):bool=>in_array($v,[true,'t',1,'1'],true);
$can=static function(string $role,string $table,string $priv)use($admin,$active):bool{
    $s=$admin->prepare('SELECT has_table_privilege(?,?,?)');
    $s->execute([$role,'public.'.$table,$priv]);
    return $active($s->fetchColumn());
};
$count=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM public.performance_signed_portal_demo_receipts'
)->fetchColumn();
$fixture=static function(string $pub,PDO $writer):SyntheticDurableSignedSqlPreviewBridge {
    return new SyntheticDurableSignedSqlPreviewBridge(
        new SyntheticPortalSqlWorkbenchBridge(
            new SignedPortalCapabilityGate($pub)
        ),
        new SyntheticSignedPortalNonceFence($writer)
    );
};
$denied=static function(array $r,string $reason,string $label)use($check):void {
    $check($r['schema_version']===SyntheticDurableSignedSqlPreviewBridge::SCHEMA,
        $label.' retains v1 safe schema');
    $check($r['status']===$reason,
        $label.' status '.($r['status']??'null').' expected '.$reason);
    $check($r['synthetic_only']===true && $r['read_only']===true,
        $label.' remains synthetic read-only');
    $check($r['replay_receipt_recorded']===false
        &&$r['cryptographic_lab_proof_verified']===false
        &&$r['credential_binding_confirmed']===false,
        $label.' no accepted proof or SQL entitlement');
    foreach([
        'portal_identity_authenticated','advertiser_approval_verified',
        'provider_connected','authorization_eligible','ready_for_production',
        'dispatch_enabled','billing_enabled'
    ] as $flag){
        $check($r[$flag]===false,$label.' '.$flag.' false');
    }
    foreach(['accounts','campaigns','reviews','counts'] as $field){
        $check($r[$field]===[],$label.' has no '.$field.' rows');
    }
};
$reject=static function(PDO $db,string $sql,string $label)use($check):void {
    $blocked=false;
    try{$db->exec($sql);}catch(PDOException){$blocked=true;}
    $check($blocked,$label);
};

$check($count()===0,'receipt table initially empty in disposable DB');
$check($public1!==$public2,'independent Portal emitter runs have distinct public keys');
$check($packet1!==$packet2,'independent Portal signed claims have distinct nonce/key material');
$check(preg_match('/^[a-f0-9]{64}$/D',$public1)===1
    &&preg_match('/^[a-f0-9]{64}$/D',$public2)===1,
    'Ed25519 public keys are exact 32-byte hex');

$table='performance_signed_portal_demo_receipts';
$check($can('perf_f124_receipt_writer',$table,'INSERT'),
    'dedicated SQL receipt writer has INSERT');
foreach(['SELECT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'] as $priv){
    $check(!$can('perf_f124_receipt_writer',$table,$priv),
        'receipt writer lacks '.$priv.' receipt permissions');
}
foreach([
    'performance_ad_account_candidates',
    'performance_campaign_intents',
    'performance_demo_budget_scenarios',
    'performance_db_principal_tenant_bindings'
] as $other){
    foreach(['SELECT','INSERT','UPDATE','DELETE'] as $priv){
        $check(!$can('perf_f124_receipt_writer',$other,$priv),
            'receipt writer lacks '.$priv.' on '.$other);
    }
}
$check(!$can('perf_f91_reader_a',$table,'INSERT')
    &&!$can('perf_f91_reader_a',$table,'SELECT'),
    'authenticated business SQL reader cannot manipulate nonce table');
$check(!$can('perf_f91_reader_b',$table,'INSERT'),
    'other tenant cannot inject nonce receipts');
$meta=$admin->query(
    "SELECT pg_catalog.pg_get_userbyid(relowner) AS owner,"
    ."relrowsecurity,relforcerowsecurity "
    ."FROM pg_catalog.pg_class WHERE oid='public.performance_signed_portal_demo_receipts'::regclass"
)->fetch(PDO::FETCH_ASSOC);
$check($meta['owner']==='perf_f124_receipt_owner',
    'NOLOGIN dedicated receipt owner');
$check($active($meta['relrowsecurity'])
    &&$active($meta['relforcerowsecurity']),
    'receipt table has ENABLE and FORCE RLS');
$role=$admin->query(
    "SELECT rolcanlogin,rolsuper,rolbypassrls,rolinherit "
    ."FROM pg_catalog.pg_roles WHERE rolname='perf_f124_receipt_owner'"
)->fetch(PDO::FETCH_ASSOC);
foreach($role as $k=>$v){
    $check(!$active($v),'receipt owner role '.$k.' disabled');
}

$valid=$fixture($public1,$w1)->preview($a,$packet1,$authority,$now);
$check($valid['status']==='signed_sql_demo_with_local_durable_nonce_only',
    'signed Portal + SQL A workbench + first durable nonce receipt succeed');
$check($valid['cryptographic_lab_proof_verified']===true
    &&$valid['replay_receipt_recorded']===true
    &&$valid['credential_binding_confirmed']===true,
    'first proof cryptographically verified and nonce journaled');
$check(count($valid['accounts'])===2 &&count($valid['campaigns'])===1
    &&count($valid['reviews'])===3,
    'first accepted response contains only SQL-bound A DEMO projections');
$check($count()===1,'one permanent receipt after first signed preview');
$check($valid['portal_identity_authenticated']===false
    &&$valid['advertiser_approval_verified']===false
    &&$valid['authorization_eligible']===false
    &&$valid['provider_connected']===false
    &&$valid['ready_for_production']===false
    &&$valid['dispatch_enabled']===false
    &&$valid['billing_enabled']===false,
    'cryptographic demo plus SQL receipt is NEVER real Portal/Ads authorization');

$denied($fixture($public1,$w2)->preview($a,$packet1,$authority,$now),
    'nonce_already_consumed_or_storage_unavailable',
    'new independent PHP verifier and second SQL writer replay same signed packet');
$check($count()===1,'replay does not insert second nonce row');

$denied($fixture($public1,$w1)->preview($b,$packet1,$authority,$now),
    'signed_or_sql_scope_denied',
    'tenant B cannot reuse A signed Portal claim despite valid Ed25519');
$check($count()===1,'wrong tenant never consumes another nonce');
$denied($fixture($public1,$w1)->preview($a,'{}',$authority,$now),
    'signed_or_sql_scope_denied','unsigned claim');
$check($count()===1,'unsigned claim cannot burn nonce');

$denied($fixture($public2,$admin)->preview($a,$packet2,$authority,$now),
    'nonce_already_consumed_or_storage_unavailable',
    'PostgreSQL superuser is not a valid dedicated receipt writer');
$check($count()===1,'wrong writer role has not written any receipt');

$nonce1=static function(string $envelope):string{
    $outer=json_decode($envelope,true,16,JSON_THROW_ON_ERROR);
    $inner=json_decode(
        (string)base64_decode(strtr($outer['payload'],'-_','+/'),true),
        true,24,JSON_THROW_ON_ERROR
    );
    return $inner['nonce'];
};
$check($nonce1($packet1)!==$nonce1($packet2),
    'independent Portal signed packets contain distinct random nonces');

$ledgerRows=$admin->query(
    'SELECT nonce_sha256,seen_epoch FROM public.performance_signed_portal_demo_receipts'
)->fetchAll(PDO::FETCH_ASSOC);
$check(count($ledgerRows)===1,'one receipt inspectable only by DB admin');
$check($ledgerRows[0]['nonce_sha256']===hash('sha256',
    "IMAGINE-PERF-F124-SIGNED-NONCE\x00".$nonce1($packet1)),
    'stored receipt is domain-separated SHA256 nonce digest');
$check(!str_contains(
    json_encode($ledgerRows,JSON_THROW_ON_ERROR),$nonce1($packet1)
),'nonce stored only as digest, not plaintext');
$check((int)$ledgerRows[0]['seen_epoch']===$now,
    'receipt uses synthetic claim clock, no real customer date or activity');

$reject($w1,'SELECT * FROM public.'.$table,
    'INSERT-only receipt writer cannot read nonce contents');
$reject($w1,'DELETE FROM public.'.$table,
    'INSERT-only receipt writer cannot delete receipts');
$reject($w1,'UPDATE public.'.$table." SET seen_epoch=$now",
    'INSERT-only receipt writer cannot overwrite receipt timestamp');
$reject($a,"INSERT INTO public.$table (nonce_sha256,seen_epoch) "
    ."VALUES ('".str_repeat('a',64)."',$now)",
    'business SQL reader cannot inject nonce into durable store');

$w2->beginTransaction();
$check((new SyntheticSignedPortalNonceFence($w2))
    ->claim($nonce1($packet2),$now)==='invalid_receipt_writer_state',
    'nonce fence rejects client-managed transaction and rollback exposure');
$w2->rollBack();
$check($count()===1,'transaction rejection does not consume fresh packet B');

$second=$fixture($public2,$w2)->preview($a,$packet2,$authority,$now);
$check($second['status']==='signed_sql_demo_with_local_durable_nonce_only'
    &&$second['replay_receipt_recorded']===true,
    'second independent valid signed Portal packet accepted once');
$check($count()===2,'two distinct signed claims produce two SQL receipts');
$denied($fixture($public2,$w1)->preview($a,$packet2,$authority,$now),
    'nonce_already_consumed_or_storage_unavailable',
    'second nonce replay denied across fresh verifier/writer instance');
$check($count()===2,'second replay cannot overwrite receipt ledger');

$revoke=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET revoked_at=clock_timestamp() WHERE db_login=?'
);
$restore=$admin->prepare(
    'UPDATE public.performance_db_principal_tenant_bindings '
    .'SET tenant_id=?,revoked_at=NULL WHERE db_login=?'
);
$revoke->execute(['perf_f91_reader_a']);
$denied($fixture($public2,$w1)->preview($a,$packet2,$authority,$now),
    'signed_or_sql_scope_denied',
    'SQL revocation still defeats signed packet even with receipt present');
$restore->execute(['88888888-8888-4888-8888-888888888888','perf_f91_reader_a']);
$check($count()===2,'revocation and restore never clear consumed nonces');

putenv('PERF_F124_SIGNED_NONCE_LAB=0');
$denied($fixture($public2,$w1)->preview($a,$packet2,$authority,$now),
    'lab_disabled','nonce bridge off when flag disabled');
$check((new SyntheticSignedPortalNonceFence($w1))
    ->claim($nonce1($packet2),$now)==='lab_disabled',
    'direct nonce insertion also off without CI flag');
putenv('PERF_F124_SIGNED_NONCE_LAB=1');
putenv('APP_ENV=production');
$denied($fixture($public2,$w1)->preview($a,$packet2,$authority,$now),
    'lab_disabled','signed receipt bridge in production explicitly refused');
putenv('APP_ENV=testing');

$risk=json_decode((string)file_get_contents(
    dirname(__DIR__,3).'/contracts/lab/ci/performance-legacy-guc-risk-v1.json'
),true,16,JSON_THROW_ON_ERROR);
$check($risk['risk_state']==='open_no_go'
    &&$risk['release_allowed']===false
    &&$risk['direct_sql_client_access_allowed']===false,
    'a local SQL nonce ledger does not clear independent IAM/backup-restore NO-GO');

echo "PASS F124 $n assertions: pinned Portal signed + SQL read with INSERT-only durable local nonce, cross-worker replay denied; restore/IAM NO-GO\n";
