<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * CI ONLY — exact JSON numeric-spend converter for OpenAI Ads Insights.
 * Read-only, no API key, transport, data authenticity, finance or Ads writes.
 *
 * Unlike json_decode's PHP float conversion, cents are derived from the
 * original JSON number lexeme and passed as decimal STRINGS to the existing
 * offline account-level/period fixture validation.
 */
final class ChatgptAdsExactWireSpendLab
{
    private const MAX_CENTS = 100000000;
    private const MAX_DAILY_ROWS = 35;

    /**
     * @param array<string,mixed> $scope from AdsProviderReadOnlyBoundary
     * @param array<string,mixed> $preflight from ChatgptAdsAccountPreflightLab
     * @param array<string,mixed> $http synthetic HTTP-shaped Insights response
     * @return array<string,mixed>
     */
    public static function normalize(
        array $scope,
        array $preflight,
        array $http
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_ADS_READONLY_BOUNDARY_LAB') !== '1' ||
            getenv('PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB') !== '1' ||
            getenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB') !== '1') {
            throw new RuntimeException('Exact ChatGPT Ads wire-money laboratory disabled.');
        }
        foreach (['tenant_id','agreement_id','period','account_id',
                  'account_currency','account_time_zone'] as $field) {
            if (!is_string($scope[$field] ?? null) ||
                ($preflight[$field] ?? null) !== $scope[$field]) {
                throw new InvalidArgumentException('Account preflight or contract scope mismatch.');
            }
        }
        if (($scope['provider'] ?? null) !== 'chatgpt_ads' ||
            ($scope['host'] ?? null) !== 'api.ads.openai.com' ||
            ($scope['method'] ?? null) !== 'GET' ||
            ($scope['path'] ?? null) !== '/v1/ad_account/insights' ||
            ($scope['state'] ?? null) !== 'synthetic_provider_scope_inspected_without_transport' ||
            ($preflight['state'] ?? null) !==
                'synthetic_chatgpt_account_preflight_no_api_key_verification' ||
            ($preflight['metadata_shape_matches_expected_scope'] ?? null) !== true ||
            ($scope['account_currency'] ?? null) !== 'BRL' ||
            !in_array($scope['account_time_zone'], DateTimeZone::listIdentifiers(), true) ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $scope['tenant_id']) ||
            !preg_match('/\Aagreement_[A-Za-z0-9_-]{8,64}\z/D', $scope['agreement_id']) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $scope['period'])) {
            throw new InvalidArgumentException('Untrusted preflight or unexpected reporting channel.');
        }
        foreach (['network_enabled','provider_spend_verified','billable',
                  'asaas_write_enabled','ads_write_enabled'] as $flag) {
            if (($scope[$flag] ?? null) !== false ||
                ($preflight[$flag] ?? null) !== false) {
                throw new InvalidArgumentException('Synthetic input cannot grant operational privileges.');
            }
        }
        foreach (['provider_account_owner_verified','api_key_verified_by_provider',
                  'portal_iam_authenticated'] as $flag) {
            if (($preflight[$flag] ?? null) !== false) {
                throw new InvalidArgumentException('Mock cannot verify provider key or IAM.');
            }
        }
        self::exact($http, ['status','content_type','redirected','body']);
        if ($http['status'] !== 200 ||
            $http['redirected'] !== false ||
            !is_string($http['content_type']) ||
            !preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD', $http['content_type']) ||
            !is_string($http['body']) ||
            $http['body'] === '') {
            throw new InvalidArgumentException('Offline report must be HTTP 200 JSON with no redirect.');
        }
        $root = ExactJsonNumberLexicalParser::decode($http['body']);
        if (!$root instanceof stdClass) {
            throw new InvalidArgumentException('Insights result must be a JSON object.');
        }
        self::exactObject($root, [
            'object','count','data','first_id','last_id','has_more'
        ]);
        if ($root->object !== 'list' ||
            $root->has_more !== false ||
            !$root->count instanceof ExactJsonNumberLiteral ||
            !is_array($root->data) || count($root->data) > self::MAX_DAILY_ROWS ||
            self::unsignedInt($root->count, self::MAX_DAILY_ROWS) !== count($root->data)) {
            throw new InvalidArgumentException('Incomplete, malformed or paginated Insights mock.');
        }

        $rawRows = [];
        $seen = [];
        $count = count($root->data);
        if ($count === 0) {
            if ($root->first_id !== null || $root->last_id !== null) {
                throw new InvalidArgumentException('Empty insights must have null cursors.');
            }
        } elseif (!is_string($root->first_id) || !is_string($root->last_id)) {
            throw new InvalidArgumentException('Insights first and last row cursors missing.');
        }
        foreach ($root->data as $index => $entry) {
            if (!$entry instanceof stdClass) {
                throw new InvalidArgumentException('Insights daily record must be a JSON object.');
            }
            self::exactObject($entry, [
                'id','start_time','end_time','readable_time',
                'timezone','spend','impressions','clicks'
            ]);
            if (!is_string($entry->id) || strlen($entry->id) < 8 ||
                strlen($entry->id) > 256 ||
                !preg_match('/\A[A-Za-z0-9:_=\-.]+\z/D', $entry->id) ||
                isset($seen[$entry->id]) ||
                !is_string($entry->readable_time) ||
                !preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])-([0-3][0-9])\z/D',
                    $entry->readable_time, $m) ||
                !checkdate((int)$m[2],(int)$m[3],(int)$m[1]) ||
                substr($entry->readable_time, 0, 7) !== $scope['period'] ||
                $entry->timezone !== $scope['account_time_zone'] ||
                !$entry->start_time instanceof ExactJsonNumberLiteral ||
                !$entry->end_time instanceof ExactJsonNumberLiteral ||
                !$entry->spend instanceof ExactJsonNumberLiteral ||
                !$entry->impressions instanceof ExactJsonNumberLiteral ||
                !$entry->clicks instanceof ExactJsonNumberLiteral) {
                throw new InvalidArgumentException('Daily insights ID, local day, timezone or metric invalid.');
            }
            $seen[$entry->id] = true;
            if ($index === 0 && $entry->id !== $root->first_id ||
                $index === $count-1 && $entry->id !== $root->last_id) {
                throw new InvalidArgumentException('List cursors are not bound to returned row IDs.');
            }
            $zone = new DateTimeZone($scope['account_time_zone']);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $entry->readable_time, $zone);
            if ($date === false || $date->format('Y-m-d') !== $entry->readable_time ||
                self::unsignedInt($entry->start_time, 5000000000) !== $date->getTimestamp() ||
                self::unsignedInt($entry->end_time, 5000000000) !==
                    $date->modify('+1 day')->getTimestamp()) {
                throw new InvalidArgumentException('Insights bucket does not match account-local calendar day.');
            }
            $cents = self::amountCents($entry->spend);
            $impressions = self::unsignedInt($entry->impressions, 1000000000);
            $clicks = self::unsignedInt($entry->clicks, 1000000000);
            if ($clicks > $impressions) {
                throw new InvalidArgumentException('Invalid click-to-impression totals.');
            }
            // Existing adapter expects lossless decimal string and performs
            // duplicate-day detection and bounded integer-cents aggregation.
            $rawRows[] = [
                'readable_time' => $entry->readable_time,
                'spend' => self::centsDecimalString($cents),
                'impressions' => $impressions,
                'clicks' => $clicks
            ];
        }
        $offline = ChatgptAdsOfflineAdapter::monthlyFixture(
            $scope['tenant_id'], $scope['period'],
            $scope['account_id'], $scope['account_time_zone'],
            [
                'account_id'=>$scope['account_id'],
                'complete'=>true,
                'currency_code'=>'BRL',
                'data'=>$rawRows,
                'has_more'=>false,
                'time_zone'=>$scope['account_time_zone'],
            ]
        );
        foreach (['provider_spend_verified','finance_approved',
                  'asaas_write_enabled','ads_write_enabled'] as $flag) {
            if (($offline[$flag] ?? null) !== false) {
                throw new RuntimeException('Synthetic parser unexpectedly granted authority.');
            }
        }
        return [
            'tenant_id'=>$scope['tenant_id'],
            'agreement_id'=>$scope['agreement_id'],
            'period'=>$scope['period'],
            'account_id'=>$scope['account_id'],
            'currency'=>'BRL',
            'time_zone'=>$scope['account_time_zone'],
            'synthetic_account_report'=>$offline['synthetic_account_report'],
            'reported_day_count'=>$offline['reported_day_count'],
            'impressions'=>$offline['impressions'],
            'clicks'=>$offline['clicks'],
            'source_wire_json_sha256'=>hash('sha256',$http['body']),
            'numeric_money_parsed_without_binary_float'=>true,
            'state'=>'synthetic_chatgpt_ads_numeric_wire_money_only',
            'network_enabled'=>false,
            'api_key_verified_by_provider'=>false,
            'portal_iam_authenticated'=>false,
            'account_ownership_verified'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false,
        ];
    }

    private static function amountCents(ExactJsonNumberLiteral $value):int
    {
        $raw=$value->text;
        // Explicitly reject exponents, negatives and sub-cent values rather
        // than guess the provider's rounding/accounting convention.
        if (!preg_match('/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?\z/D', $raw,$m)) {
            throw new InvalidArgumentException('Spending must be an exact nonnegative JSON decimal with <=2 places.');
        }
        $cents=((int)$m[1])*100+(int)str_pad($m[2]??'',2,'0',STR_PAD_RIGHT);
        if($cents>self::MAX_CENTS){
            throw new InvalidArgumentException('Synthetic spending over maximum.');
        }
        return $cents;
    }

    private static function centsDecimalString(int $cents):string
    {
        return intdiv($cents,100) . '.' . str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private static function unsignedInt(ExactJsonNumberLiteral $value,int $limit):int
    {
        if(!preg_match('/\A(0|[1-9][0-9]{0,12})\z/D',$value->text)) {
            throw new InvalidArgumentException('Expected integer JSON counter without decimal or exponent.');
        }
        $int=(int)$value->text;
        if($int>$limit){
            throw new InvalidArgumentException('Out-of-range unsigned JSON counter.');
        }
        return $int;
    }

    /** @param array<string,mixed> $map @param list<string> $expected */
    private static function exact(array $map,array $expected):void
    {
        $keys=array_keys($map);
        sort($keys,SORT_STRING);
        sort($expected,SORT_STRING);
        if($keys!==$expected){
            throw new InvalidArgumentException('Unexpected raw Insights HTTP fields.');
        }
    }

    /** @param list<string> $expected */
    private static function exactObject(stdClass $object,array $expected):void
    {
        $keys=array_keys(get_object_vars($object));
        sort($keys,SORT_STRING);
        sort($expected,SORT_STRING);
        if($keys!==$expected){
            throw new InvalidArgumentException('Unexpected or missing Insights response property.');
        }
    }
}
