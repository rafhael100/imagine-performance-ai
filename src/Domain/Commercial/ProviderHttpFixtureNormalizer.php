<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use JsonException;

/**
 * Parses synthetic HTTP-shaped provider reporting responses, TEST-ONLY.
 * No sockets, OAuth, credentials, provider source verification or billing.
 * An HTTP 200 JSON body never establishes provenance or completeness.
 */
final class ProviderHttpFixtureNormalizer
{
    private const MAX_BODY = 524288;

    /** @param array<string,mixed> $http @return array<string,mixed> */
    public static function googleSearchStream(
        string $customerId, string $currency, string $timeZone, array $http
    ): array {
        self::gate();
        $rows = self::parse($http);
        $original = json_decode($http['body'], false, 32, JSON_THROW_ON_ERROR);
        if (!is_array($original) || !array_is_list($rows) || count($rows) > 20) {
            throw new InvalidArgumentException('Invalid Google SearchStream batch list.');
        }
        $batches = [];
        foreach ($rows as $batch) {
            if (!is_array($batch) || array_is_list($batch) ||
                !isset($batch['results']) || !is_array($batch['results']) ||
                !array_is_list($batch['results']) || count($batch['results']) > 35) {
                throw new InvalidArgumentException('Invalid Google report batch.');
            }
            self::allow($batch, ['results','requestId','fieldMask','queryResourceConsumption']);
            foreach ($batch['results'] as $row) {
                if (!is_array($row) || array_is_list($row)) {
                    throw new InvalidArgumentException('Invalid Google daily row.');
                }
                self::exact($row, ['metrics','segments']);
                if (!is_array($row['segments']) || !is_array($row['metrics'])) {
                    throw new InvalidArgumentException('Missing daily metrics.');
                }
                self::exact($row['segments'], ['date']);
                self::exact($row['metrics'], ['costMicros']);
            }
            $batches[] = ['results' => $batch['results']];
        }
        return [
            'customer_id'=>$customerId,
            'currency_code'=>$currency,
            'time_zone'=>$timeZone,
            'complete'=>true, // mock JSON structure only, NOT official provider completeness
            'batches'=>$batches,
        ];
    }

    /** @param array<string,mixed> $http @return array<string,mixed> */
    public static function metaInsights(
        string $accountId, string $currency, string $timeZone, array $http
    ): array {
        self::gate();
        $data = self::parse($http);
        $original = json_decode($http['body'], false, 32, JSON_THROW_ON_ERROR);
        if (!is_object($original) || !property_exists($original, 'data') ||
            !is_array($original->data) ||
            array_is_list($data) || !isset($data['data']) ||
            !is_array($data['data']) || !array_is_list($data['data']) ||
            count($data['data']) > 35) {
            throw new InvalidArgumentException('Invalid Meta Insights data.');
        }
        self::allow($data, ['data','paging']);
        if (isset($data['paging'])) {
            if (!is_array($data['paging']) || array_is_list($data['paging'])) {
                throw new InvalidArgumentException('Invalid Meta pagination metadata.');
            }
            self::allow($data['paging'], ['cursors','next','previous']);
            if (isset($data['paging']['next']) && $data['paging']['next'] !== '') {
                // Do not trust or follow provider-supplied pagination URLs.
                throw new InvalidArgumentException('Incomplete Meta paginated report.');
            }
            if (isset($data['paging']['cursors']) && !is_array($data['paging']['cursors'])) {
                throw new InvalidArgumentException('Malformed Meta cursors.');
            }
        }
        foreach ($data['data'] as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException('Invalid Meta daily record.');
            }
            self::exact($row, [
                'account_currency','account_id','date_start','date_stop','spend'
            ]);
        }
        return [
            'account_id'=>$accountId,
            'currency'=>$currency,
            'time_zone'=>$timeZone,
            'complete'=>true, // structure of mock body ONLY
            'next'=>null,
            'data'=>$data['data'],
        ];
    }

    /** @param array<string,mixed> $http @return array<mixed> */
    private static function parse(array $http): array
    {
        self::exact($http, ['status','content_type','redirected','body']);
        if ($http['status'] !== 200 || $http['redirected'] !== false ||
            !is_string($http['content_type']) ||
            !preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD', $http['content_type']) ||
            !is_string($http['body']) || $http['body'] === '' ||
            strlen($http['body']) > self::MAX_BODY) {
            throw new InvalidArgumentException('Provider mock HTTP status/content refused.');
        }
        try {
            $json = json_decode($http['body'], true, 32,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new InvalidArgumentException('Malformed provider report JSON.');
        }
        if (!is_array($json)) {
            throw new InvalidArgumentException('Expected provider JSON collection.');
        }
        return $json;
    }

    private static function gate(): void
    {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_ADS_HTTP_NORMALIZER_LAB') !== '1') {
            throw new InvalidArgumentException('Raw provider report laboratory disabled.');
        }
    }

    /** @param array<mixed> $map @param list<string> $allowed */
    private static function allow(array $map, array $allowed): void
    {
        foreach (array_keys($map) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unapproved provider response field.');
            }
        }
    }

    /** @param array<mixed> $map @param list<string> $expected */
    private static function exact(array $map, array $expected): void
    {
        $actual = array_keys($map);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) {
            throw new InvalidArgumentException('Provider response keys mismatch.');
        }
    }
}
