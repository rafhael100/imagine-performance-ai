<?php
declare(strict_types=1);

/** Offline negative/positive boundary proof. No network, Vault or Ads calls. */
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadPlan.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsWireFailure.php';
require_once __DIR__.'/../src/Domain/Administration/AdministrativeAdsReadConnector.php';

use Imagine\Performance\Administration\AdministrativeAdsReadConnector as Connector;
use Imagine\Performance\Administration\AdministrativeAdsWireFailure as WireFailure;

$checks = 0;
$ok = static function(bool $value, string $case) use (&$checks): void {
    if (!$value) throw new RuntimeException('Provider callback failure boundary: '.$case);
    $checks++;
};
$authorized = static fn(): string => str_repeat('b', 64);
$read = static function(Closure $page, ?Closure $metadata = null) use ($authorized): array {
    return (new Connector($authorized, $page, $metadata))->read(
        'company_alfa', 'google_ads', '1234567890',
        '2026-11-18', '2026-11-19', 'America/Sao_Paulo'
    );
};
$expectDenied = static function(Closure $operation, string $message, string $case) use ($ok): void {
    $result = null;
    $error = null;
    try {
        $result = $operation();
    } catch (RuntimeException $caught) {
        $error = $caught;
    }
    $ok($result === null && $error?->getMessage() === $message, $case.' fixed message / no output');
    $ok($error?->getPrevious() === null, $case.' no secret-bearing exception chain');
    $ok(!str_contains($error?->getMessage() ?? '', 'PRIVATE_TOKEN_IN_EXCEPTION'),
        $case.' no private token in response');
};
$metadata = static fn(): array => [
    'source_kind'=>'laboratory_fixture', 'provider'=>'google_ads',
    'api_version'=>'v26', 'account_id'=>'1234567890', 'currency'=>'BRL',
    'timezone'=>'America/Sao_Paulo', 'context_timezone'=>'America/Sao_Paulo',
];
$row = [
    'account_id'=>'1234567890', 'campaign_id'=>'100',
    'currency'=>'BRL', 'date'=>'2026-11-18',
    'spend'=>'1000000', 'impressions'=>'15', 'clicks'=>'2',
];
$pages = 0;
$positive = static function(array $plan, ?string $cursor) use ($row, &$pages): array {
    $pages++;
    $next = $row;
    if ($cursor !== null) $next['date'] = '2026-11-19';
    return [
        'source_kind'=>'laboratory_fixture','context_timezone'=>'America/Sao_Paulo',
        'rows'=>[$next], 'next_cursor'=>$cursor===null ? 'page2' : null,
    ];
};
$result = $read($positive, $metadata);
$ok($pages === 2 && $result['complete'] === true && count($result['rows']) === 2,
    'positive two-page metadata-backed read still succeeds');
$ok($result['status'] === 'synthetic' && $result['official_evidence'] === null
    && $result['billable'] === false, 'fixture has no official or billing authority');

$pageReads = 0;
$notCalled = static function() use (&$pageReads): never {
    $pageReads++;
    throw new LogicException('PROVIDER_PAGE_SHOULD_NOT_RUN');
};
$expectDenied(
    static fn() => $read($notCalled, static function(): never {
        throw new LogicException('PRIVATE_TOKEN_IN_EXCEPTION');
    }),
    'Provider account metadata unavailable', 'metadata callback LogicException'
);
$ok($pageReads === 0, 'metadata exception prevents all report-page callbacks');
$expectDenied(
    static fn() => $read($notCalled, static function(): never {
        throw new Error('PRIVATE_TOKEN_IN_EXCEPTION');
    }),
    'Provider account metadata unavailable', 'metadata callback PHP Error'
);
$ok($pageReads === 0, 'metadata Error prevents all report pages');

$expectDenied(
    static fn() => $read(static function(): never {
        throw new LogicException('PRIVATE_TOKEN_IN_EXCEPTION');
    }),
    'Provider report unavailable', 'first report-page callback exception'
);
$secondPageReads = 0;
$expectDenied(
    static function() use ($read, $row, &$secondPageReads): array {
        return $read(static function(array $plan, ?string $cursor) use (&$secondPageReads, $row): array {
            $secondPageReads++;
            if ($cursor !== null) throw new LogicException('PRIVATE_TOKEN_IN_EXCEPTION');
            return ['source_kind'=>'laboratory_fixture', 'rows'=>[$row], 'next_cursor'=>'page2'];
        });
    },
    'Provider report unavailable', 'second page callback exception'
);
$ok($secondPageReads === 2, 'second-page exception does not retry or publish first page');

$expectDenied(
    static fn() => $read(static function(): never {
        throw new WireFailure('PRIVATE_TOKEN_IN_EXCEPTION', 401);
    }),
    'Provider report unavailable', 'forged wire classification rejected'
);
$expectDenied(
    static fn() => $read($notCalled, static function(): never {
        throw new WireFailure('PRIVATE_TOKEN_IN_EXCEPTION', 403);
    }),
    'Provider account metadata unavailable', 'forged metadata classification rejected'
);
$ok($pageReads === 0, 'forged metadata failure prevents report pages');

$expectDenied(
    static fn() => $read(static function(): never {
        throw new WireFailure('authentication_required', 401);
    }),
    'Ads read failed: authentication_required', 'approved wire classification preserved'
);
$expectDenied(
    static fn() => $read(static function(): never {
        throw new WireFailure('credential_changed');
    }),
    'Ads read failed: credential_changed', 'native credential rotation reason stays finite'
);
$expectDenied(
    static fn() => $read(static function(): never {
        throw new WireFailure('authentication_required', 999);
    }),
    'Provider report unavailable', 'invalid upstream HTTP status cannot escape'
);
$expectDenied(
    static fn() => $read(static function() use ($row): array {
        return ['source_kind'=>'laboratory_fixture','rows'=>[$row],
            'private_debug'=>"\xB1PRIVATE_TOKEN_IN_EXCEPTION"];
    }),
    'Provider response denied', 'invalid UTF-8 never exports raw JSON serialization error'
);
echo 'OK: '.$checks.' provider callback positive/negative sanitization checks; zero HTTP and no provider operation'.PHP_EOL;
