<?php
declare(strict_types=1);

$root=__DIR__.'/../portal-v7-plan-reference/apps/portal/app/Domain';
foreach([
    '/Performance/ActivationPostpaidPreview.php',
    '/PaymentRouting/GatewayRoutingPreview.php',
    '/PaymentRouting/GatewayPolicyReview.php',
    '/PaymentRouting/PortalGatewayReviewExporter.php',
    '/Performance/ActivationGatewayReviewLab.php',
    '/Performance/PerformanceChannelReviewEnvelope.php',
    '/Performance/PortalPerformanceV7AgreementPlanLabIssuer.php',
] as $path)require_once $root.$path;
foreach([
    'PortalF419SignedActivationReviewV7LabVerifier',
    'PortalV7SignedPlanAgreementLabVerifier',
] as $class)require_once __DIR__.'/../src/Domain/Commercial/'.$class.'.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalV7SignedPlanNonceLab.php';

use App\Domain\Performance\ActivationGatewayReviewLab as Gateway;
use App\Domain\Performance\PerformanceChannelReviewEnvelope as ActivationIssuer;
use App\Domain\Performance\PortalPerformanceV7AgreementPlanLabIssuer as PlanIssuer;
use Imagine\Performance\Commercial\PortalV7SignedPlanAgreementLabVerifier as PlanVerifier;
use Imagine\Performance\Commercial\Lab\PgPortalV7SignedPlanNonceLab as Nonce;

function dbPlanMust(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('V7 PG18 signed plan: '.$label);
}
function dbPlanDeny(callable $fn,string $label):void {
    try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('V7 PG18 signed plan accepted: '.$label);
}
foreach([
    'APP_ENV'=>'testing',
    'PORTAL_PERF_V7_PLAN_AGREEMENT_LAB'=>'1',
    'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
    'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
    'PERF_PG_V6_ROLE_LAB'=>'1',
    'PERF_PG_V7_PLAN_NONCE_LAB'=>'1',
] as $key=>$required) {
    if(getenv($key)!==$required)throw new RuntimeException('Missing CI scope: '.$key);
}
$make=static fn(string $login,string $password):PDO=>new PDO(
    getenv('PERF_LAB_PG_DSN'),$login,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$alpha=$make('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$alphaSecond=$make('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$make('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$tenantA='tenant_v6multi_alpha_123456';
$tenantB='tenant_v6multi_beta_567890';
$agreementA='agreement_v7_pg18_alpha_123456';
$agreementB='agreement_v7_pg18_beta_1234567';
$reviewKey=str_repeat('A',48);
$planKey=str_repeat('G',48);
$time='2026-11-14T21:05:00Z';
$issueTime='2026-11-14T21:00:00Z';
$epoch=(new DateTimeImmutable($time))->getTimestamp();
$policy=[
    'revision'=>3,'mode'=>'manual','currency'=>'BRL','preferred_gateway'=>'asaas',
    'providers'=>[
        'asaas'=>[
            'enabled'=>true,'approved'=>true,'healthy'=>true,
            'fee_version'=>'SIM-PG18',
            'methods'=>['pix'=>['enabled'=>true,'fixed_cents'=>100,
                'variable_bps'=>100,'settlement_days'=>1]],
        ],
        'c6'=>['enabled'=>false,'approved'=>false,'healthy'=>false,
               'fee_version'=>'SIM-C6','methods'=>[]],
    ],
];
$eligible=['google_ads'=>true,'meta_ads'=>true,'chatgpt_ads'=>false];
$planned=['google_ads'=>100000,'meta_ads'=>70000];
$construct=static function(string $tenant,string $agreement,string $org,
    string $order,string $nonce)use($reviewKey,$planKey,$policy,$eligible,
        $planned,$time,$issueTime,$epoch):array {
    $scope=[
        'tenant_id'=>$tenant,
        'agreement_id'=>$agreement,
        'period'=>'2026-11',
        'organization_ref'=>$org,
        'order_ref'=>$order,
        'policy_revision'=>3,
    ];
    $review=Gateway::preview(
        ['google_ads','meta_ads'],$eligible,$policy,'pix',$org,$order,3,$reviewKey
    );
    $activation=ActivationIssuer::issue(
        $review,$eligible,$org,$order,3,$issueTime,$reviewKey
    );
    $wire=PlanIssuer::issue(
        $activation,$review,$eligible,$scope,$planned,
        $time,'k_v7_plan_pg18_01',$nonce,$epoch-1,$epoch+90,
        $reviewKey,$planKey
    );
    $expected=$scope+[
        'gateway'=>$review['gateway_quote']['gateway'],
        'method'=>$review['gateway_quote']['method'],
        'crm_review_mac'=>$review['crm_review']['mac'],
        'key_id'=>'k_v7_plan_pg18_01',
    ];
    return [$wire,$activation,$expected];
};
$keyResolver=static fn(string $issuer,string $keyId):?string=>
    $issuer==='imagine-portal-ci' && $keyId==='k_v7_plan_pg18_01'
        ?str_repeat('G',48):null;
$storeA=new Nonce($alpha);
$storeA2=new Nonce($alphaSecond);
$storeB=new Nonce($beta);
$verify=static function(array $data,Nonce $store)use(
    $planned,$time,$reviewKey,$keyResolver
):array {
    [$wire,$activation,$expected]=$data;
    return PlanVerifier::verify(
        $wire,$activation,$expected,array_keys($planned),$planned,
        $time,$reviewKey,$keyResolver,
        static fn(string $tenant,string $agreement,string $nonce,int $expires):bool=>
            $store->claim($tenant,$agreement,$nonce,$expires)
    );
};
$nonce='nonce_v7_pg18_same_123456789';
$aData=$construct($tenantA,$agreementA,'org-v7-pg-alpha-001',
    'order-v7-pg-alpha-001',$nonce);
$bData=$construct($tenantB,$agreementB,'org-v7-pg-beta-001',
    'order-v7-pg-beta-001',$nonce);
dbPlanDeny(fn()=> $verify($aData,$storeB),'beta login cannot claim ALPHA signed plan');
dbPlanDeny(fn()=> $verify($bData,$storeA),'alpha login cannot claim BETA signed plan');
$alpha->exec("SET app.tenant_id = 'tenant_v6multi_beta_567890'");
dbPlanMust($alpha->query(
    'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
)->fetchColumn()===$tenantA,'forged custom GUC does not change authenticated login');

$aOk=$verify($aData,$storeA);
dbPlanMust($aOk['hmac_verified_in_lab']===true &&
    $aOk['billable']===false &&
    $aOk['durable_nonce_verified']===false &&
    $aOk['estimated_management_fee_cents']===37000,
    'real pinned HMAC and R170 fee logic valid; verifier cannot assert durable provider');
dbPlanDeny(fn()=> $verify($aData,$storeA2),
    'same signed plan replay blocked on different SQL connection');
dbPlanMust(!$storeA2->claim($tenantA,$agreementA,$nonce,$epoch+90),
    'raw nonce replay blocked across connections');
dbPlanMust(!$storeA->claim($tenantB,$agreementB,
    'nonce_v7_pg18_foreign_12345',$epoch+90),
    'foreign tenant rejected despite fake GUC');

$bOk=$verify($bData,$storeB);
dbPlanMust($bOk['agreement_id']===$agreementB,
    'beta may claim same textual nonce for its own signed agreement');
dbPlanDeny(fn()=> $verify($bData,$storeB),
    'same beta plan replay denied');

// Same tenant, separate agreement scope gets an independent namespace.
$agreementA2='agreement_v7_pg18_alpha_other1234';
$another=$construct($tenantA,$agreementA2,
    'org-v7-pg-alpha-001','order-v7-pg-alpha-001',$nonce);
$other=$verify($another,$storeA2);
dbPlanMust($other['agreement_id']===$agreementA2,
    'same nonce is scoped by AGREEMENT as well as authenticated tenant');

foreach([
    [$alpha,$tenantA,2],[$beta,$tenantB,1],
] as [$db,$tenant,$count]) {
    dbPlanMust((int)$db->query(
        'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
    )->fetchColumn()===$count,'RLS shows only own accepted V7 plans');
    foreach(['UPDATE','DELETE','TRUNCATE'] as $priv){
        $allowed=$db->query(
            "SELECT has_table_privilege(session_user,
            'perf_fin_role_lab.portal_v7_plan_nonces','$priv')"
        )->fetchColumn();
        dbPlanMust(!in_array($allowed,[true,1,'1','t'],true),
            'tenant cannot '.$priv.' signed plan nonce');
    }
}

putenv('PERF_PG_V7_PLAN_NONCE_LAB=0');
dbPlanMust(!$storeA->claim($tenantA,$agreementA,
    'nonce_v7_pg18_disabled_123456',$epoch+90),
    'disabled feature cannot consume nonce');
putenv('PERF_PG_V7_PLAN_NONCE_LAB=1');
putenv('APP_ENV=production');
dbPlanMust(!$storeA->claim($tenantA,$agreementA,
    'nonce_v7_pg18_prod_12345678',$epoch+90),
    'production refuses CI adapter');
echo "OK: pinned Portal V7 HMAC then PostgreSQL18 tenant+agreement RLS nonce, cross-connection replay, forged GUC and SQL privilege limits; no acceptance or charge\n";
