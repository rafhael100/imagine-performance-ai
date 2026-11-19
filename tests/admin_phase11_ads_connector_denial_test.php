<?php
declare(strict_types=1);

/**
 * Offline integration: independent S2S + account authority must reject BEFORE
 * the read-only connector invokes a provider page callback. No HTTP or Vault.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsAccountMandateGate.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsAccountMandateGate as Gate;
use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$ok = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Scoped connector: '.$label);
    $checks++;
};
$now = 2000000000;
$company = 'company_alfa';
$account = '1234567890';
$principal = 'imagine_portal_s2s';
$service = [
    'version'=>Gate::SERVICE_VERSION,'principal'=>$principal,'company_id'=>$company,
    'operation'=>Gate::OPERATION,'active'=>true,'revision'=>3,
    'expires_at'=>$now+500,'revoked_at'=>null,
];
$mandate = [
    'version'=>Gate::VERSION,'company_id'=>$company,'service_principal'=>$principal,
    'provider'=>'google_ads','account_id'=>$account,'purpose'=>Gate::PURPOSE,
    'operation'=>Gate::OPERATION,'consent_reference'=>'synthetic-consent-001',
    'credential_ref'=>'synthetic-vault-001','status'=>'active','revision'=>8,
    'expires_at'=>$now+400,'revoked_at'=>null,
];
$clock = static function () use (&$now): int { return $now; };
$gate = static function (Closure $serviceSource, Closure $accountSource) use ($principal, $clock): Gate {
    return new Gate($principal, $serviceSource, $accountSource, $clock);
};
$run = static function (
    Gate $authorization, string $companyId, string $provider, string $accountId,
    Closure $page
): array {
    $connector = new Connector(Closure::fromCallable([$authorization, 'lease']), $page);
    return $connector->read($companyId, $provider, $accountId, '2026-11-18', '2026-11-19', 'America/Sao_Paulo');
};
$blocked = static function (
    string $label, Gate $authorization, string $companyId, string $provider, string $accountId
) use ($run, $ok): void {
    $pageReads = 0;
    $forbiddenPage = static function () use (&$pageReads): never {
        $pageReads++;
        throw new RuntimeException('SECRET_PROVIDER_CALLBACK_MUST_NOT_RUN');
    };
    try {
        $run($authorization, $companyId, $provider, $accountId, $forbiddenPage);
        throw new RuntimeException('Authorization unexpectedly allowed');
    } catch (RuntimeException $error) {
        $ok($error->getMessage() === 'Account consent denied', $label.' denial at connector');
        $ok($error->getPrevious() === null, $label.' no chained secret');
    }
    $ok($pageReads === 0, $label.' no provider callback');
};
$stableService = static function () use ($service): array { return $service; };
$stableAccount = static function () use ($mandate): array { return $mandate; };

$pageReads = 0;
$syntheticPage = static function () use (&$pageReads, $account): array {
    $pageReads++;
    return ['source_kind'=>'laboratory_fixture','context_timezone'=>'America/Sao_Paulo',
        'rows'=>[['account_id'=>$account,'campaign_id'=>'11223344','currency'=>'BRL',
            'date'=>'2026-11-19','spend'=>'10000000','impressions'=>'100','clicks'=>'3']],
        'next_cursor'=>null];
};
$success = $run($gate($stableService, $stableAccount), $company, 'google_ads', $account, $syntheticPage);
$ok($pageReads === 1 && $success['complete'] === true, 'explicit mandate reads one synthetic page');
$ok($success['status'] === 'synthetic' && $success['official_evidence'] === null,
    'fixture never becomes official evidence');
$ok($success['external_operations_enabled'] === false && $success['billable'] === false,
    'synthetic read has no operational or billing authority');
$ok(count($success['rows']) === 1 && $success['rows'][0]['account_id'] === $account,
    'positive result scoped to account');

$accountReads = 0;
$deniedAccount = static function () use (&$accountReads, $mandate): array {
    $accountReads++;
    return $mandate;
};
$inactive = array_replace($service, ['active'=>false]);
$blocked('inactive delegation', $gate(static fn():array=>$inactive, $deniedAccount),
    $company, 'google_ads', $account);
$ok($accountReads === 0, 'inactive S2S cannot enumerate account authority');

$accountReads = 0;
$blocked('other company', $gate($stableService, $deniedAccount),
    'company_beta', 'google_ads', $account);
$ok($accountReads === 0, 'different company denied before nominal lookup');

$blocked('other provider', $gate($stableService, $stableAccount),
    $company, 'meta_ads', '987654321');
$blocked('other account', $gate($stableService, $stableAccount),
    $company, 'google_ads', '9999999999');

$liveService = $service;
$readService = 0;
$revokeDuringAccount = static function () use (&$liveService, $mandate): array {
    $liveService['active'] = false;
    return $mandate;
};
$serviceSource = static function () use (&$readService, &$liveService): array {
    $readService++;
    return $liveService;
};
$blocked('S2S revoked during first nominal lookup',
    $gate($serviceSource, $revokeDuringAccount), $company, 'google_ads', $account);
$ok($readService === 2, 'S2S rechecked after privileged account lookup');

$liveAccount = $mandate;
$serviceReads = 0;
$accountReads = 0;
$serviceMutatesAccount = static function () use (&$serviceReads, &$liveAccount, $service): array {
    if (++$serviceReads === 2) $liveAccount['consent_reference'] = 'synthetic-consent-rotated';
    return $service;
};
$accountSource = static function () use (&$accountReads, &$liveAccount): array {
    $accountReads++;
    return $liveAccount;
};
$blocked('consent rotated during second S2S lookup',
    $gate($serviceMutatesAccount, $accountSource), $company, 'google_ads', $account);
$ok($serviceReads === 2 && $accountReads === 2,
    'both scopes re-read, no page following concurrent consent change');

$accountErrors = 0;
$accountThrows = static function () use (&$accountErrors): never {
    $accountErrors++;
    throw new RuntimeException('SENSITIVE_ACCOUNT_RESOLVER_EXCEPTION');
};
$blocked('nominal resolver failure', $gate($stableService, $accountThrows),
    $company, 'google_ads', $account);
$ok($accountErrors === 1, 'nominal source error fails without retry or callback');

$metaAccount = '987654321';
$metaMandate = array_replace($mandate, [
    'provider'=>'meta_ads','account_id'=>$metaAccount,
    'consent_reference'=>'synthetic-meta-consent-001',
    'credential_ref'=>'synthetic-meta-vault-001',
]);
$metaPages = 0;
$metaPage = static function () use (&$metaPages, $metaAccount): array {
    $metaPages++;
    return ['source_kind'=>'laboratory_fixture','context_timezone'=>'America/Sao_Paulo',
        'rows'=>[['account_id'=>$metaAccount,'campaign_id'=>'9988','currency'=>'BRL',
            'date'=>'2026-11-19','spend'=>'12.34','impressions'=>'5','clicks'=>'1']],
        'next_cursor'=>null];
};
$metaResult = $run($gate($stableService, static fn():array=>$metaMandate),
    $company, 'meta_ads', $metaAccount, $metaPage);
$ok($metaPages === 1 && $metaResult['status'] === 'synthetic'
    && $metaResult['rows'][0]['account_id'] === $metaAccount,
    'separately mandated Meta account can read synthetic data');
$ok($metaResult['official_evidence'] === null && $metaResult['billable'] === false,
    'Meta fixture is never billable or official');

echo 'OK: '.$checks.' scoped connector positive/negative assertions; zero HTTP, no Vault or Ads API'.PHP_EOL;
