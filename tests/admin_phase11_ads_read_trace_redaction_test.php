<?php
declare(strict_types=1);

/**
 * Offline PHP trace boundary regression. A fixed denial message is insufficient
 * when zend.exception_ignore_args=0: method arguments could still be logged.
 * No Ads API, token, IAM or external authorization is contacted.
 */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Ads read trace redaction: '.$label);
    $checks++;
};

$previous = ini_set('zend.exception_ignore_args', '0');
if ($previous === false || ini_get('zend.exception_ignore_args') !== '0') {
    throw new RuntimeException('Cannot enable explicit sensitive-argument trace regression');
}

try {
    $company = 'tenant_PRIVATE_TRACE_SENTINEL_707';
    $provider = 'google_ads';
    $account = '1234567890';
    $from = '2026-11-18';
    $until = '2026-11-19';
    $timezone = 'America/Sao_Paulo';

    $read = static function (Closure $authorize, Closure $page, ?Closure $metadata = null) use (
        $company, $provider, $account, $from, $until, $timezone
    ): array {
        return (new Connector($authorize, $page, $metadata))->read(
            $company, $provider, $account, $from, $until, $timezone
        );
    };

    $authorized = static fn(): string => str_repeat('a', 64);
    $notCalled = static function (): never {
        throw new LogicException('PRIVATE_PROVIDER_EXCEPTION_TRACE_SENTINEL_708');
    };

    $denied = static function (Closure $call, string $expected, string $label) use (
        $assert, $company, $account, $from, $until, $timezone
    ): void {
        $error = null;
        $result = null;
        try {
            $result = $call();
        } catch (RuntimeException $e) {
            $error = $e;
        }
        $assert($result === null && $error?->getMessage() === $expected,
            $label.' fail closed with fixed denial');
        $assert($error?->getPrevious() === null,
            $label.' no raw exception chain');

        $frames = array_values(array_filter($error?->getTrace() ?? [],
            static fn(array $frame): bool => ($frame['class'] ?? '') === Connector::class
                && ($frame['function'] ?? '') === 'read'));
        $assert(count($frames) === 1 && count($frames[0]['args'] ?? []) === 6,
            $label.' read arguments visible to trace test');
        foreach ($frames[0]['args'] as $index => $value) {
            $assert($value instanceof SensitiveParameterValue,
                $label.' read argument '.$index.' redacted');
        }
        $trace = var_export($error->getTrace(), true).$error->getTraceAsString();
        foreach ([$company, $account, $from, $until, $timezone,
            'PRIVATE_PROVIDER_EXCEPTION_TRACE_SENTINEL_708'] as $needle) {
            $assert(!str_contains($trace, $needle),
                $label.' trace does not disclose private input');
        }
    };

    $denied(static fn(): array => $read(static fn(): null => null, $notCalled),
        'Account consent denied', 'initial mandate/S2S denial');
    $denied(static fn(): array => $read($authorized, $notCalled),
        'Provider report unavailable', 'provider page callback exception');
    $denied(static fn(): array => $read($authorized, $notCalled, $notCalled),
        'Provider account metadata unavailable', 'provider metadata exception');

    $row = [
        'account_id'=>$account, 'campaign_id'=>'100', 'currency'=>'BRL',
        'date'=>'2026-11-18', 'spend'=>'1000000', 'impressions'=>'8', 'clicks'=>'2'
    ];
    $pageCount = 0;
    $denied(static function () use ($read, $authorized, $row, &$pageCount): array {
        return $read($authorized, static function (array $plan, ?string $cursor) use (
            $row, &$pageCount
        ): array {
            $pageCount++;
            if ($cursor !== null) throw new LogicException('PRIVATE_PROVIDER_EXCEPTION_TRACE_SENTINEL_708');
            return ['source_kind'=>'laboratory_fixture', 'rows'=>[$row], 'next_cursor'=>'page2'];
        });
    }, 'Provider report unavailable', 'second page after synthetic first page');
    $assert($pageCount === 2, 'second-page denial does not retry or return partial rows');

    $success = $read($authorized, static fn(): array => [
        'source_kind'=>'laboratory_fixture', 'rows'=>[$row], 'next_cursor'=>null
    ]);
    $assert($success['complete'] === true && count($success['rows']) === 1
        && $success['status'] === 'synthetic' && $success['official_evidence'] === null
        && $success['billable'] === false, 'legitimate fixture path remains available');

    echo 'OK: '.$checks.' Ads read trace redaction checks (PHP args enabled, no HTTP)'.PHP_EOL;
} finally {
    ini_set('zend.exception_ignore_args', (string)$previous);
}
