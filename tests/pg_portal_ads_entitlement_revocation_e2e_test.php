<?php
declare(strict_types=1);

require_once __DIR__.'/../portal-ads-read-reference/apps/portal/app/Homologation/Commerce/PortalPerformanceAdsReadGrantLabIssuer.php';
foreach([
    'PortalAdsReadS2SLabVerifier','PortalAdsGrantedReadOnlySessionLab',
    'PortalAdsEntitlementSnapshotLab','PortalAdsEntitledReadOnlySessionLab',
    'SyntheticProviderSpendAdapter','ChatgptAdsOfflineAdapter',
    'AdsProviderReadOnlyBoundary','AdsReadOnlyHttpResponseReview',
    'ChatgptAdsAccountPreflightLab','ExactJsonNumberLexicalParser',
    'ChatgptAdsExactWireSpendLab','ChatgptAdsReadOnlySessionLab',
] as $name) require_once __DIR__.'/../src/Domain/Commercial/'.$name.'.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalAdsEntitlementSnapshotLab.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalAdsReadGrantNonceLab.php';

use App\Homologation\Commerce\PortalPerformanceAdsReadGrantLabIssuer as Issuer;
use Imagine\Performance\Commercial\PortalAdsEntitledReadOnlySessionLab as Gate;
use Imagine\Performance\Commercial\Lab\PgPortalAdsEntitlementSnapshotLab as EntitlementReader;
use Imagine\Performance\Commercial\Lab\PgPortalAdsReadGrantNonceLab as DurableNonce;

function pgEntMust(bool $condition,string $label):void {
    if(!$condition)throw new RuntimeException('PG entitlement CI: '.$label);
}
function pgEntDeny(callable $fn,string $label):void{
    try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('PG entitlement CI accepted: '.$label);
}
foreach([
    'APP_ENV'=>'testing',
    'PORTAL_PERF_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_PORTAL_ADS_S2S_READ_GRANT_LAB'=>'1',
    'PERF_PG_ADS_S2S_NONCE_LAB'=>'1',
    'PERF_PG_V6_ROLE_LAB'=>'1',
    'PERF_PG_ADS_ENTITLEMENT_LAB'=>'1',
    'PERF_PORTAL_ENTITLEMENT_SNAPSHOT_LAB'=>'1',
    'PERF_ADS_READONLY_BOUNDARY_LAB'=>'1',
    'PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB'=>'1',
    'PERF_CHATGPT_EXACT_WIRE_MONEY_LAB'=>'1',
    'PERF_CHATGPT_READONLY_SESSION_LAB'=>'1',
] as $key=>$value)if(getenv($key)!==$value)throw new RuntimeException('Missing SQL CI gate: '.$key);

$dsn=getenv('PERF_LAB_PG_DSN');
$make=static fn(string $login,string $password):PDO=>new PDO($dsn,$login,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$admin=$make('postgres',getenv('PERF_PG_ADMIN_PASS'));
$alpha=$make('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$make('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$a='tenant_v6multi_alpha_123456';
$b='tenant_v6multi_beta_567890';
$agreement='agreement_registry_revocation_1234';
$period='2026-11';
$account='adacct_12345678';
$otherAccount='adacct_87654321';
$credential='ref_pg_entitlement_alpha_123456';
$consent='consent_pg_entitlement_alpha_123456';
$zone='America/Sao_Paulo';
$now=time();
$keyId='k_pg_entitlement_01';
$secret=str_repeat('G',48);
$table='perf_fin_role_lab.ads_read_entitlement_snapshots';

$seed=$admin->prepare(
    "INSERT INTO $table (
      tenant_id,agreement_id,period,provider,account_id,
      credential_reference,consent_reference,agreement_status,
      agreement_valid_from,agreement_valid_until,account_status,
      owner_tenant_id,consent_status,consent_granted_at,
      consent_expires_at,consent_revoked_at,permission,snapshot_version
    ) VALUES(
      :tenant,:agreement,:period,'chatgpt_ads',:account,
      :cred,:consent,'synthetic_active',
      :starts,:ends,'synthetic_active',
      :owner,'synthetic_granted',:granted,
      :expires,NULL,'ads.insights.read',1
    )"
);
foreach([
    [$a,$account,$credential,$consent],
    [$b,$otherAccount,'ref_pg_entitlement_beta_1234567','consent_pg_entitlement_beta_1234567'],
] as [$tenant,$acct,$cred,$cons]) {
    $seed->execute([
        'tenant'=>$tenant,'agreement'=>$agreement,'period'=>$period,
        'account'=>$acct,'cred'=>$cred,'consent'=>$cons,
        'starts'=>$now-600,'ends'=>$now+3600,'owner'=>$tenant,
        'granted'=>$now-300,'expires'=>$now+3000,
    ]);
}
$selectorA=[
    'tenant_id'=>$a,'agreement_id'=>$agreement,
    'period'=>$period,'account_id'=>$account,
];
$selectorB=[
    'tenant_id'=>$b,'agreement_id'=>$agreement,
    'period'=>$period,'account_id'=>$otherAccount,
];
// Pin the CI observation clock to the same test instant as the signed
// synthetic grant. A real adapter would use a trusted server clock, but
// wall-second drift during CI must not turn a valid snapshot into a future one.
$ciClock=static fn():int=>$now;
$readerA=new EntitlementReader($alpha,$ciClock);
$readerB=new EntitlementReader($beta,$ciClock);
pgEntMust($readerA->resolve($selectorA)['consent_status']==='synthetic_granted',
    'authenticated ALPHA can read its own consent snapshot');
pgEntMust($readerB->resolve($selectorB)['owner_tenant_id']===$b,
    'authenticated BETA can read only its own account');
pgEntDeny(fn()=> $readerA->resolve($selectorB),'ALPHA cannot obtain BETA snapshot');
pgEntDeny(fn()=> $readerB->resolve($selectorA),'BETA cannot obtain ALPHA snapshot');
$alpha->exec("SET app.tenant_id = 'tenant_v6multi_beta_567890'");
pgEntMust($readerA->resolve($selectorA)['tenant_id']===$a,
    'forgeable GUC cannot override authenticated session_user');
pgEntDeny(fn()=> $readerA->resolve($selectorB),'GUC fake BETA still cannot read BETA');
pgEntMust((int)$alpha->query("SELECT count(*) FROM $table")->fetchColumn()===1,
    'forced RLS only exposes own row');
foreach(['INSERT','UPDATE','DELETE','TRUNCATE'] as $priv) {
    $result=$alpha->query(
        "SELECT has_table_privilege(session_user,'$table','$priv')"
    )->fetchColumn();
    pgEntMust(!in_array($result,['1','t',1,true],true),'tenant has no '.$priv.' rights');
}

$issue=static fn(string $nonce):array=>Issuer::issue(
    $a,$agreement,$period,$account,$credential,$consent,
    $keyId,$nonce,$now-1,$now+90,$secret
);
$binding=[
    'tenant_id'=>$a,'agreement_id'=>$agreement,'period'=>$period,
    'provider'=>'chatgpt_ads','account_id'=>$account,
    'credential_reference'=>$credential,'consent_reference'=>$consent,
    'consent_state'=>'synthetic_active',
    'consent_issued_at'=>$now-300,'consent_expires_at'=>$now+3000,
    'account_currency'=>'BRL','account_time_zone'=>$zone,
    'permission'=>'advertiser_api_key',
    'account_access'=>'synthetic_reporting_only',
    'portal_iam_authenticated'=>false,
    'provider_authorization_verified'=>false,
    'account_ownership_verified'=>false,
    'secret_material_embedded'=>false,
    'openai_ads_account_status'=>'synthetic_eligible',
];
$start=(new DateTimeImmutable('2026-11-01 00:00:00',new DateTimeZone($zone)))->getTimestamp();
$end=(new DateTimeImmutable('2026-11-02 00:00:00',new DateTimeZone($zone)))->getTimestamp();
$id="start=$start:end=$end:entity_id=$account";
$metadata=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>json_encode([
        'id'=>$account,'name'=>'Synthetic Business','url'=>'https://example.invalid',
        'preview_url'=>null,'status'=>'active','currency_code'=>'BRL',
        'timezone'=>$zone,'review'=>['status'=>'approved'],
    ],JSON_THROW_ON_ERROR)
];
$insights=[
    'status'=>200,'content_type'=>'application/json','redirected'=>false,
    'body'=>'{"object":"list","data":[{"id":'.
        json_encode($id,JSON_THROW_ON_ERROR).',"start_time":'.$start.
        ',"end_time":'.$end.',"readable_time":"2026-11-01",'.
        '"timezone":'.json_encode($zone,JSON_THROW_ON_ERROR).
        ',"spend":18.42,"impressions":12,"clicks":2}],'.
        '"count":1,"first_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"last_id":'.json_encode($id,JSON_THROW_ON_ERROR).
        ',"has_more":false}',
];
$nonceStore=new DurableNonce($alpha);
$resolveKey=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_pg_entitlement_01'
        ? str_repeat('G',48):null;
$nonceClaim=static fn(string $tenant,string $nonce,int $expires):bool=>
    $nonceStore->claim($tenant,$nonce,$expires);
$requests=[];
$transport=static function(array $req)use(&$requests,$metadata,$insights):array{
    $requests[]=$req['operation'];
    return $req['operation']==='ad_account_metadata'?$metadata:$insights;
};
$run=static function(array $grant,?callable $transportOverride=null)use(
    $binding,$a,$agreement,$period,$account,$credential,$consent,$zone,$now,
    $readerA,$resolveKey,$nonceClaim,$transport
):array{
    return Gate::collect(
        $grant,$binding,$a,$agreement,$period,$account,
        $credential,$consent,$zone,$now,[$readerA,'resolve'],
        $resolveKey,$nonceClaim,$transportOverride??$transport
    );
};
$grant1=$issue('nonce_pg_entitlement_positive_123456');
$out=$run($grant1);
pgEntMust($out['entitlement_snapshot_verified_in_lab']===true &&
    $out['provider_spend_verified']===false &&
    $out['portal_iam_authenticated']===false &&
    $out['billable']===false,'DB-linked S2S is still unverified synthetic');
pgEntMust($requests===['ad_account_metadata','ad_account_insights'],
    'only two mocked GETs after RLS authorizes ALPHA');
pgEntMust($out['synthetic_account_report']['spend_cents']===1842,
    'exact spend cents preserved');
pgEntDeny(fn()=> $run($grant1),'replay denied by durable PostgreSQL ledger');
pgEntMust(count($requests)===2,'replay cannot reach transport');

$revoke=$admin->prepare(
    "UPDATE $table SET consent_status='synthetic_revoked',
      consent_revoked_at=:at,snapshot_version=snapshot_version+1,
      changed_at=clock_timestamp()
     WHERE tenant_id=:tenant AND agreement_id=:agreement
       AND period=:period AND account_id=:account"
);
$restore=$admin->prepare(
    "UPDATE $table SET consent_status='synthetic_granted',
      consent_revoked_at=NULL,snapshot_version=snapshot_version+1,
      changed_at=clock_timestamp()
     WHERE tenant_id=:tenant AND agreement_id=:agreement
       AND period=:period AND account_id=:account"
);
$scope=['tenant'=>$a,'agreement'=>$agreement,'period'=>$period,'account'=>$account];
$revoke->execute(['at'=>$now]+$scope);
pgEntMust($readerA->resolve($selectorA)['consent_status']==='synthetic_revoked',
    'revocation committed by administrator is visible on tenant SQL connection');
$before=count($requests);
pgEntDeny(fn()=> $run($issue('nonce_pg_entitlement_revoked_123456')),
    'valid signed grant denied after durable consent revocation');
pgEntMust(count($requests)===$before,'revoked grant fails before ANY GET');
$restore->execute($scope);

// Revoke between the two simulated reads: recheck sees the committed change,
// so neither a successful HMAC nor an already-claimed nonce permits Insights.
$seen=[];
$revokeDuringMetadata=static function(array $request)use(
    &$seen,$revoke,$scope,$now,$metadata,$insights
):array{
    $seen[]=$request['operation'];
    if($request['operation']==='ad_account_metadata') {
        $revoke->execute(['at'=>$now]+$scope);
        return $metadata;
    }
    return $insights;
};
$midNonce='nonce_pg_entitlement_midflight_12345';
pgEntDeny(fn()=> $run($issue($midNonce),$revokeDuringMetadata),
    'persistently revoked consent between the two mocked GETs');
pgEntMust($seen===['ad_account_metadata'],
    'PG18 revocation prevents Insights after metadata');
$stmt=$alpha->prepare('SELECT count(*) FROM perf_fin_role_lab.ads_read_grant_nonces WHERE tenant_id=:t AND nonce=:n');
$stmt->execute(['t'=>$a,'n'=>$midNonce]);
pgEntMust((int)$stmt->fetchColumn()===1,
    'nonce remains spent after midflight revocation');
$restore->execute($scope);
pgEntDeny(fn()=> $run($issue($midNonce)),
    'committed nonce cannot be replayed after consent regrant');

// A privileged synthetic admin may change account ownership; a tenant SQL
// reader sees that row but the independent policy guard MUST deny access.
$admin->prepare("UPDATE $table SET owner_tenant_id=:owner,snapshot_version=snapshot_version+1
    WHERE tenant_id=:tenant AND agreement_id=:agreement AND period=:period AND account_id=:account")
    ->execute(['owner'=>$b]+$scope);
$before=count($requests);
pgEntDeny(fn()=> $run($issue('nonce_pg_entitlement_wrongowner_123')),
    'registry owner changed, valid signature still denied');
pgEntMust(count($requests)===$before,'owner mismatch must block before provider transport');

putenv('PERF_PG_ADS_ENTITLEMENT_LAB=0');
pgEntDeny(fn()=> $readerA->resolve($selectorA),'disabled SQL entitlement reader');
putenv('PERF_PG_ADS_ENTITLEMENT_LAB=1');
putenv('APP_ENV=production');
pgEntDeny(fn()=> $readerA->resolve($selectorA),'production SQL lab disabled');
echo "OK: PG18 authenticated tenant RLS entitlement, durable revocation and nonce replay; read-only CI fixtures, no Portal IAM/Ads/network/billing\n";
