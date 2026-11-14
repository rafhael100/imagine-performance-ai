<?php
declare(strict_types=1);

// Actual pinned Portal V7 issuer + independent Performance verification.
// All principals, agreements, events, money and provider data are SYNTHETIC.
$base=__DIR__.'/../portal-v7-plan-reference/apps/portal/app/Domain';
foreach([
    '/Performance/ActivationPostpaidPreview.php',
    '/PaymentRouting/GatewayRoutingPreview.php',
    '/PaymentRouting/GatewayPolicyReview.php',
    '/PaymentRouting/PortalGatewayReviewExporter.php',
    '/Performance/ActivationGatewayReviewLab.php',
    '/Performance/PerformanceChannelReviewEnvelope.php',
    '/Performance/PortalPerformanceV7AgreementPlanLabIssuer.php',
] as $path)require_once $base.$path;
foreach([
    'PortalF419SignedActivationReviewV7LabVerifier',
    'PortalV7SignedPlanAgreementLabVerifier',
    'PortalV7AcceptedPlanReviewLab',
] as $name)require_once __DIR__.'/../src/Domain/Commercial/'.$name.'.php';
require_once __DIR__.'/../labs/finance-pg/PgPortalV7AcceptedPlanNonceLab.php';

use App\Domain\Performance\ActivationGatewayReviewLab as Gateway;
use App\Domain\Performance\PerformanceChannelReviewEnvelope as ActivationIssuer;
use App\Domain\Performance\PortalPerformanceV7AgreementPlanLabIssuer as PlanIssuer;
use Imagine\Performance\Commercial\PortalV7AcceptedPlanReviewLab as Gate;
use Imagine\Performance\Commercial\Lab\PgPortalV7AcceptedPlanNonceLab as Store;

function acceptMust(bool $ok,string $description):void {
    if(!$ok)throw new RuntimeException('V7 acceptance PG18: '.$description);
}
function acceptDeny(callable $fn,string $description):void {
    try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}
    throw new RuntimeException('V7 acceptance PG18 incorrectly approved: '.$description);
}
foreach([
    'APP_ENV'=>'testing','PORTAL_PERF_V7_PLAN_AGREEMENT_LAB'=>'1',
    'PERF_PORTAL_F419_V7_REVIEW_LAB'=>'1',
    'PERF_PORTAL_V7_SIGNED_PLAN_LAB'=>'1',
    'PERF_PORTAL_V7_ACCEPTED_PLAN_LAB'=>'1',
    'PERF_PG_V6_ROLE_LAB'=>'1',
    'PERF_PG_V7_PLAN_NONCE_LAB'=>'1',
    'PERF_PG_V7_ACCEPTANCE_LAB'=>'1',
] as $env=>$required)if(getenv($env)!==$required){
    throw new RuntimeException('Missing CI-only gate: '.$env);
}
$db=static fn(string $user,string $pass):PDO=>new PDO(
    getenv('PERF_LAB_PG_DSN'),$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
$admin=$db('postgres',getenv('PERF_PG_ADMIN_PASS'));
$alpha=$db('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$alpha2=$db('perf_v6_tenant_alpha',getenv('PERF_ROLE_ALPHA_PASS'));
$beta=$db('perf_v6_tenant_beta',getenv('PERF_ROLE_BETA_PASS'));
$a='tenant_v6multi_alpha_123456';
$b='tenant_v6multi_beta_567890';
$agreementA='agreement_acceptance_alpha_123456';
$agreementB='agreement_acceptance_beta_1234567';
$period='2026-11';
$at='2026-11-14T21:05:00Z';
$issued='2026-11-14T21:00:00Z';
$now=(new DateTimeImmutable($at,new DateTimeZone('UTC')))->getTimestamp();
$activationKey=str_repeat('A',48);
$planKey=str_repeat('G',48);
$terms=hash('sha256','CI-only V7 agreement terms, full month, approved version');
$eligible=['meta_ads'=>true,'google_ads'=>true,'chatgpt_ads'=>false];
$policy=[
    'revision'=>3,'mode'=>'manual','currency'=>'BRL',
    'preferred_gateway'=>'asaas',
    'providers'=>[
        'asaas'=>[
            'enabled'=>true,'approved'=>true,'healthy'=>true,
            'fee_version'=>'SIM-TEST',
            'methods'=>['pix'=>[
                'enabled'=>true,'fixed_cents'=>100,
                'variable_bps'=>100,'settlement_days'=>1,
            ]],
        ],
        'c6'=>[
            'enabled'=>false,'approved'=>false,'healthy'=>false,
            'fee_version'=>'SIM-C6','methods'=>[],
        ],
    ],
];
$plan=['google_ads'=>100000,'meta_ads'=>70000];
$compose=static function(string $tenant,string $agreement,string $order,
    string $nonce,?array $planned=null)use(
    $activationKey,$planKey,$eligible,$policy,$plan,$period,$at,$issued,$now
):array{
    $planned??=$plan;
    $org='org-v7-acceptance-demo-001';
    $scope=[
        'tenant_id'=>$tenant,'agreement_id'=>$agreement,'period'=>$period,
        'organization_ref'=>$org,'order_ref'=>$order,'policy_revision'=>3,
    ];
    $review=Gateway::preview(['meta_ads','google_ads'],
        $eligible,$policy,'pix',$org,$order,3,$activationKey);
    $activation=ActivationIssuer::issue($review,$eligible,$org,
        $order,3,$issued,$activationKey);
    $wire=PlanIssuer::issue($activation,$review,$eligible,
        $scope,$planned,$at,'k_acceptance_v7_01',$nonce,
        $now-1,$now+90,$activationKey,$planKey);
    $expect=$scope+[
        'gateway'=>$review['gateway_quote']['gateway'],
        'method'=>$review['gateway_quote']['method'],
        'crm_review_mac'=>$review['crm_review']['mac'],
        'key_id'=>'k_acceptance_v7_01',
    ];
    return ['signed'=>$wire,'activation'=>$activation,
        'expected'=>$expect,'plan'=>$planned];
};
$canonical=null;
$canonical=static function(array $value)use(&$canonical):array {
    if(array_is_list($value))return array_map(
        static fn(mixed $v):mixed=>is_array($v)?$canonical($v):$v,$value
    );
    ksort($value,SORT_STRING);
    foreach($value as $k=>$v)if(is_array($v))$value[$k]=$canonical($v);
    return $value;
};
$planDigest=static fn(array $signed):string=>hash('sha256',
    "IMAGINE-PORTAL-PERFORMANCE-V7-PLAN-AGREEMENT-LAB\n".
    json_encode($canonical($signed['payload']),
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
);
$seed=$admin->prepare(
    'INSERT INTO perf_fin_role_lab.portal_v7_acceptance_events (
        tenant_id,agreement_id,period,event_revision,state,
        plan_sha256,terms_sha256,synthetic_actor_ref,mfa_snapshot_verified,
        full_month_reviewed,event_at,expires_at
     ) VALUES (
        :tenant,:agreement,:period,:rev,:state,
        :digest,:terms,:actor,:mfa,:full_month,:at,:expires
     )'
);
$append=static function(string $tenant,string $agreement,int $rev,
    string $state,string $digest,bool $mfa=true,bool $full=true,
    ?int $time=null
)use($seed,$period,$terms,$now):void {
    $seed->execute([
        'tenant'=>$tenant,'agreement'=>$agreement,'period'=>$period,
        'rev'=>$rev,'state'=>$state,'digest'=>$digest,
        'terms'=>$terms,'actor'=>'actor_lab_approver_123456',
        'mfa'=>$mfa,'full_month'=>$full,
        'at'=>$time??$now-30,'expires'=>$now+3600,
    ]);
};
$alphaInitial=$compose($a,$agreementA,'order-acceptance-alpha',
    'nonce_v7_accept_alpha_initial_1234');
$betaInitial=$compose($b,$agreementB,'order-acceptance_beta',
    'nonce_v7_accept_beta_initial_12345');
$alphaDigest=$planDigest($alphaInitial['signed']);
$betaDigest=$planDigest($betaInitial['signed']);
$append($a,$agreementA,1,'synthetic_accepted',$alphaDigest);
$append($b,$agreementB,1,'synthetic_accepted',$betaDigest);
$storeAlpha=new Store($alpha);
$storeAlpha2=new Store($alpha2);
$storeBeta=new Store($beta);
$keyResolver=static fn(string $issuer,string $kid):?string=>
    $issuer==='imagine-portal-ci' && $kid==='k_acceptance_v7_01'
        ?str_repeat('G',48):null;

$run=static function(array $bundle,Store $store,?string $termsOverride=null)
    use($at,$activationKey,$terms,$keyResolver):array {
    return Gate::verify(
        $bundle['signed'],$bundle['activation'],
        $bundle['expected'],array_keys($bundle['plan']),
        $bundle['plan'],$at,$activationKey,$termsOverride??$terms,
        $keyResolver,
        static fn(string $tenant,string $agreement,string $period,
            string $planHash,string $termHash,string $nonce,
            int $expires,int $now):bool=>$store->claim(
                $tenant,$agreement,$period,$planHash,$termHash,$nonce,
                $expires,$now
            )
    );
};
$ok=$run($alphaInitial,$storeAlpha);
acceptMust($ok['synthetic_acceptance_and_nonce_claimed_in_lab']===true &&
    $ok['planned_media_total_cents']===170000 &&
    $ok['estimated_management_fee_cents']===37000,
    'valid signed V7 plan matches admin-seeded acceptance and claims nonce');
acceptMust($ok['signed_plan_sha256']===$alphaDigest,
    'independent signed-plan digest equals acceptance fingerprint');
foreach([
    'real_customer_contract_authenticated','customer_acceptance_proven',
    'portal_iam_authenticated','durable_nonce_verified',
    'provider_spend_verified','finance_approved','billable',
    'asaas_write_enabled','c6_write_enabled','ads_write_enabled',
    'crm_write_enabled',
] as $flag)acceptMust(($ok[$flag]??null)===false,
    'synthetic lab NEVER authenticates real permission '.$flag);
acceptDeny(fn()=> $run($alphaInitial,$storeAlpha2),
    'same accepted signed plan replay from another SQL connection');
acceptDeny(fn()=> $run($alphaInitial,$storeBeta),
    'BETA cannot claim ALPHA signed plan with valid HMAC');
acceptDeny(fn()=> $run($betaInitial,$storeAlpha),
    'ALPHA cannot claim BETA signed plan with valid HMAC');
$betaOk=$run($betaInitial,$storeBeta);
acceptMust($betaOk['tenant_id']===$b,'BETA permitted on its own accepted plan');
$alpha->exec("SET app.tenant_id = 'tenant_v6multi_beta_567890'");
acceptMust($alpha->query(
    'SELECT perf_fin_role_lab.tenant_for_authenticated_session()'
)->fetchColumn()===$a,'forged GUC does not change SQL owner');
acceptMust((int)$alpha->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_acceptance_events'
)->fetchColumn()===1,'tenant ALPHA cannot see BETA acceptance events');
acceptMust((int)$beta->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_acceptance_events'
)->fetchColumn()===1,'tenant BETA cannot see ALPHA acceptance events');
foreach(['INSERT','UPDATE','DELETE','TRUNCATE'] as $priv){
    $authorized=$alpha->query("SELECT has_table_privilege(
        session_user,'perf_fin_role_lab.portal_v7_acceptance_events','$priv')"
    )->fetchColumn();
    acceptMust(!in_array($authorized,[true,1,'1','t'],true),
        'tenant SQL login cannot write synthetic approval event: '.$priv);
}

// Append-only revocation supersedes the older accepted event.
$append($a,$agreementA,2,'synthetic_revoked',$alphaDigest,false,false);
$revoked=$compose($a,$agreementA,'order-acceptance-alpha',
    'nonce_v7_accept_alpha_revoked_1234');
acceptDeny(fn()=> $run($revoked,$storeAlpha),
    'revoked latest event vetoes valid signed HMAC');
$countNonce=static fn(PDO $db):int=>(int)$db->query(
    'SELECT count(*) FROM perf_fin_role_lab.portal_v7_plan_nonces'
)->fetchColumn();
acceptMust($countNonce($alpha)===1,
    'revoked review did not consume nonce');
acceptDeny(fn()=> $run($revoked,$storeAlpha2),
    'revocation survives separate SQL connection');

// Reapproval by CI admin with a later revision is allowed ONLY for a fresh
// signed-plan fingerprint and a new nonce. Old nonce still cannot replay.
$append($a,$agreementA,3,'synthetic_accepted',$alphaDigest);
$afterRegrant=$compose($a,$agreementA,'order-acceptance-alpha',
    'nonce_v7_accept_alpha_regrant_123');
$regrant=$run($afterRegrant,$storeAlpha2);
acceptMust($regrant['synthetic_acceptance_and_nonce_claimed_in_lab']===true &&
    $countNonce($alpha)===2,'fresh nonce accepted after synthetic regrant');
acceptDeny(fn()=> $run($alphaInitial,$storeAlpha2),
    'regrant must NEVER revive consumed old nonce');

// Modified plan has a NEW signed digest and cannot inherit prior acceptance.
$changedPlan=$compose($a,$agreementA,'order-acceptance-alpha',
    'nonce_v7_accept_alpha_newplan_1234',
    ['google_ads'=>100001,'meta_ads'=>70000]);
acceptDeny(fn()=> $run($changedPlan,$storeAlpha),
    'different valid HMAC + media plan cannot inherit old approval');
acceptDeny(fn()=> $run($afterRegrant,$storeAlpha,$termsOther=str_repeat('f',64)),
    'different independently expected signed terms must be refused');
acceptMust($countNonce($alpha)===2,
    'invalid plan and terms cannot consume new nonce');

// Stale revoked or later event always wins, including an expired acceptance.
$append($a,$agreementA,4,'synthetic_revoked',$alphaDigest,false,false);
$append($a,$agreementA,5,'synthetic_accepted',$alphaDigest,true,true,
    $now+1); // event is in the future, must not authorize
$future=$compose($a,$agreementA,'order-acceptance-alpha',
    'nonce_v7_accept_future_event_123');
acceptDeny(fn()=> $run($future,$storeAlpha),
    'future-dated approval is not currently active');
acceptMust($countNonce($alpha)===2,'future event cannot consume nonce');

// Deny an agreement with no acceptance record, regardless of valid Portal HMAC.
$none=$compose($a,'agreement_acceptance_unseen_123456',
    'order-unseen-123','nonce_v7_accept_unseen_12345678');
acceptDeny(fn()=> $run($none,$storeAlpha),
    'no independent acceptance ledger record');
acceptMust($countNonce($alpha)===2,'no record cannot claim nonce');

putenv('PERF_PG_V7_ACCEPTANCE_LAB=0');
acceptMust(!$storeAlpha->claim($a,$agreementA,$period,$alphaDigest,
    $terms,'nonce_v7_accept_disabled_12345',$now+90,$now),
    'acceptance SQL feature disabled');
putenv('PERF_PG_V7_ACCEPTANCE_LAB=1');
putenv('APP_ENV=production');
acceptDeny(fn()=> $run($afterRegrant,$storeAlpha),
    'production signed-acceptance adapter forbidden');
echo "OK: pinned Portal V7 HMAC + latest synthetic PG18 accepted/revoked event and nonce in one statement; replay, cross-tenant, regrant, plan and terms mismatch denied; NO REAL IAM/CHARGE\n";
