<?php
declare(strict_types=1);

require_once __DIR__ . '/fixtures/SyntheticTwoChannelOrderReadiness.php';
require_once __DIR__ . '/../src/Domain/Administration/AdministrativeAdsAccountMandateGate.php';

use Imagine\Performance\Tests\Fixtures\SyntheticTwoChannelOrderReadiness as Lab;
use Imagine\Performance\Administration\AdministrativeAdsAccountMandateGate as Gate;

$checks = 0;
$check = static function(bool $condition, string $why) use (&$checks): void {
    if (!$condition) throw new RuntimeException('F423/R29 laboratory order: '.$why);
    ++$checks;
};
$now = 2000000000;
$company = 'company_laboratory_one';
$principal = 'imagine_portal_s2s';
$service = [
    'version' => Gate::SERVICE_VERSION, 'principal' => $principal,
    'company_id' => $company, 'operation' => Gate::OPERATION,
    'active' => true, 'revision' => 2, 'expires_at' => $now + 600, 'revoked_at' => null,
];
$accounts = ['google_ads'=>'1234567890','meta_ads'=>'98765432109876'];
$mandates = [];
foreach ($accounts as $provider=>$accountId) {
    // 'active' is an INJECTED TEST contract value, not an authorization
    // from Portal SQL or from a Google/Meta account.
    $mandates[$provider][$accountId] = [
        'version' => Gate::VERSION, 'company_id' => $company,
        'service_principal' => $principal, 'provider' => $provider,
        'account_id' => $accountId, 'purpose' => Gate::PURPOSE,
        'operation' => Gate::OPERATION,
        'consent_reference' => 'synthetic-consent-'. $provider,
        'credential_ref' => 'opaque-vault-demo-'.$provider,
        'status' => 'active', 'revision' => 2,
        'expires_at' => $now + 500, 'revoked_at' => null,
    ];
}
$serviceReads = 0;
$accountReads = 0;
$gate = new Gate($principal,
    static function() use (&$service,&$serviceReads): array {
        ++$serviceReads;
        return $service;
    },
    static function(string $company, string $provider, string $account) use (&$mandates,&$accountReads): ?array {
        ++$accountReads;
        return $mandates[$provider][$account] ?? null;
    },
    static fn(): int => $now
);
$authorize = static fn(string $company,string $provider,string $account): ?string =>
    $gate->lease($company,$provider,$account);
$order = [
    'order_id' => 'order_abcdef1234567890', 'company_id' => $company,
    'purchase_state' => 'synthetic_confirmed',
    'purchase_evidence' => 'fake_provider_callback_only',
    'catalog_version' => 'performance-f423-v5-lab',
    'activation_total_cents' => 98000,
    'channels' => [
        'google_ads' => ['account_id'=>$accounts['google_ads'],'media_cents'=>100000,'activation_cents'=>49000],
        'meta_ads' => ['account_id'=>$accounts['meta_ads'],'media_cents'=>70000,'activation_cents'=>49000],
    ],
    'briefing_revision' => 3,
    'briefing_sha256' => hash('sha256','synthetic-exact-approved-briefing-r3'),
];
$briefing = [
    'order_id' => $order['order_id'], 'company_id'=>$company,
    'state'=>'synthetic_review_approved',
    'revision'=>$order['briefing_revision'], 'sha256'=>$order['briefing_sha256'],
];
$ledger = [];
$positive = Lab::decide($order,$briefing,$authorize,$ledger);
$check($positive['state']==='internal_review_only', 'one order enters internal laboratory review');
$check(count($ledger)===2, 'one review entry per channel');
$check($positive['channels']['google_ads']['media_cents']===100000, 'Google media remains R$1000');
$check($positive['channels']['meta_ads']['media_cents']===70000, 'Meta media remains R$700');
$check($positive['channels']['google_ads']['state']==='internal_review_only'
    && $positive['channels']['meta_ads']['state']==='internal_review_only', 'both fictional mandates checked');
$check($serviceReads===6 && $accountReads===4, 'real mandate gate rechecks 3 S2S + 2 nominal per channel');
$check($positive['readback_candidate']['order_id']===$order['order_id']
    && $positive['readback_candidate']['briefing_revision']===3
    && $positive['readback_candidate']['briefing_sha256']===$order['briefing_sha256'],
    'readback candidate preserves exact order+approved revision');
$check($positive['portal_http_read_proven']===false
    && $positive['ads_write_enabled']===false && $positive['billable']===false
    && $positive['operational_enabled']===false, 'no API, money or operational authority claimed');
$firstKeys=array_map(static fn(array $row): string => $row['review_key'],$positive['channels']);
$replayed=Lab::decide($order,$briefing,$authorize,$ledger);
$check(count($ledger)===2 && $firstKeys===array_map(
    static fn(array $row): string => $row['review_key'],$replayed['channels']),
    'replayed fake order does not duplicate local review keys');

// Independent prerequisites: a read receipt cannot be used as paid-order proof.
foreach (['pending','admin_read_succeeded','paid','cancelled'] as $state) {
    $bad=$order;$bad['purchase_state']=$state;$empty=[];
    $result=Lab::decide($bad,$briefing,$authorize,$empty);
    $check($result['state']==='blocked' && $empty===[], 'purchase state '.$state.' denies all channels');
}
foreach (['unverified_admin_read','unsigned_webhook','portal_preview_only'] as $kind) {
    $bad=$order;$bad['purchase_evidence']=$kind;$empty=[];
    $check(Lab::decide($bad,$briefing,$authorize,$empty)['state']==='blocked' && $empty===[],
        'non-provider payment evidence '.$kind.' denied');
}
foreach ([
    ['revision',4],['sha256',str_repeat('f',64)],['state','awaiting_approval'],
    ['order_id','order_other1234567890'],['company_id','company_other_xyz'],
] as [$field,$value]) {
    $bad=$briefing;$bad[$field]=$value;$empty=[];
    $check(Lab::decide($order,$bad,$authorize,$empty)['state']==='blocked' && $empty===[],
        'briefing '.$field.' mismatch denies all');
}
$bad=$order;$bad['channels']['google_ads']['media_cents']=99999;$empty=[];
$check(Lab::decide($bad,$briefing,$authorize,$empty)['state']==='blocked' && $empty===[],
    'channel minimum is per-channel, not pooled');
$bad=$order;$bad['activation_total_cents']=98001;$empty=[];
$check(Lab::decide($bad,$briefing,$authorize,$empty)['state']==='blocked' && $empty===[],
    'activation fee cannot silently change');
$bad=$order;$bad['channels']['chatgpt_ads']=$bad['channels']['meta_ads'];$empty=[];
$check(Lab::decide($bad,$briefing,$authorize,$empty)['state']==='blocked' && $empty===[],
    'ChatGPT Ads is separate from Google+Meta order');
$bad=$order;$bad['company_id']='company_other_xyz';$wrongBrief=$briefing;$wrongBrief['company_id']=$bad['company_id'];$empty=[];
$result=Lab::decide($bad,$wrongBrief,$authorize,$empty);
$check($result['channels']['google_ads']['state']==='blocked'
    && $result['channels']['meta_ads']['state']==='blocked' && $empty===[],
    'other company cannot inherit either mandate');
$bad=$order;$bad['channels']['meta_ads']['account_id']='98765432109877';$empty=[];
$result=Lab::decide($bad,$briefing,$authorize,$empty);
$check($result['channels']['google_ads']['state']==='internal_review_only'
    && $result['channels']['meta_ads']['state']==='blocked' && count($empty)===1,
    'wrong Meta account denies only Meta');
$mandates['meta_ads'][$accounts['meta_ads']]['status']='revoked';$empty=[];
$result=Lab::decide($order,$briefing,$authorize,$empty);
$check($result['channels']['google_ads']['state']==='internal_review_only'
    && $result['channels']['meta_ads']['state']==='blocked' && count($empty)===1,
    'revoked Meta mandate denies Meta while Google remains reviewable');
$mandates['meta_ads'][$accounts['meta_ads']]['status']='active';
$mandates['google_ads'][$accounts['google_ads']]['revoked_at']=$now;$empty=[];
$result=Lab::decide($order,$briefing,$authorize,$empty);
$check($result['channels']['google_ads']['state']==='blocked'
    && $result['channels']['meta_ads']['state']==='internal_review_only' && count($empty)===1,
    'revoked Google mandate denies Google while Meta remains reviewable');
$mandates['google_ads'][$accounts['google_ads']]['revoked_at']=null;
$bad=$order;$bad['channels']['google_ads']['media_cents']=110000;$empty=[];
Lab::decide($order,$briefing,$authorize,$empty);
$result=Lab::decide($bad,$briefing,$authorize,$empty);
$check($result['channels']['google_ads']['state']==='blocked'
    && $result['channels']['google_ads']['reason']==='same_revision_conflict'
    && count($empty)===2, 'changed media on same revision cannot overwrite local review');
$public=json_encode($positive,JSON_THROW_ON_ERROR);
$check(!str_contains($public,'vault') && !str_contains($public,'consent-')
    && !str_contains($public,'token') && !str_contains($public,'access_token'),
    'readiness never returns credential or consent references');

printf("PASS F423/R29 offline order readiness: %d checks, two channels, exact briefing, channel-scoped denial, replay; NOT Portal HTTP or official consent.\n", $checks);
