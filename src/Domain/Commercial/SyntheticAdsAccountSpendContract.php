<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use DateTimeZone;
use InvalidArgumentException;

/**
 * LAB-ONLY account-scoped interpretation of SYNTHETIC read-only Ads reports.
 * It is deliberately NOT a Google/Meta API adapter or evidence attestation.
 * No network, tokens, mutations, charges or real account data.
 */
final class SyntheticAdsAccountSpendContract
{
    private const MAX_ROWS = 200;
    private const MAX_ACCOUNTS = 20;
    private const MAX_CHANNEL_SPEND_CENTS = 100000000;
    private const SOURCE = 'synthetic_readonly_report_fixture';

    /**
     * @param array<string,array<string,string>> $scopes channel => account_id => IANA time zone
     * @param list<array<string,mixed>> $rows Unverified synthetic account reports
     * @return array<string,mixed>
     */
    public static function summarize(string $tenant, string $period, array $scopes, array $rows): array
    {
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $period)) {
            throw new InvalidArgumentException('Invalid synthetic tenant or billing period.');
        }
        if ($scopes === [] || count($scopes) > 2 ||
            array_diff(array_keys($scopes), ['google_ads','meta_ads']) !== [] ||
            count($rows) < 1 || count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Expected supported account scopes and 1..200 reports.');
        }
        $scope = [];
        $totals = [];
        $channels = [];
        $accountCount = 0;
        $timeZones = DateTimeZone::listIdentifiers();
        foreach ($scopes as $channel => $accounts) {
            if (!is_array($accounts) || $accounts === []) {
                throw new InvalidArgumentException('At least one account must be contracted per channel.');
            }
            foreach ($accounts as $accountKey => $zone) {
                $account = (string)$accountKey;
                if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{2,63}\z/D', $account) ||
                    !is_string($zone) || !in_array($zone, $timeZones, true)) {
                    throw new InvalidArgumentException('Invalid account identifier or IANA timezone.');
                }
                $scope[$channel][$account] = $zone;
                $totals[$channel][$account] = 0;
                $accountCount++;
            }
            $channels[$channel] = 0;
        }
        if ($accountCount > self::MAX_ACCOUNTS) {
            throw new InvalidArgumentException('Too many account scopes for offline lab.');
        }

        $seen = [];
        $canonical = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Each report must be a map.');
            }
            $fields = array_keys($row);
            sort($fields, SORT_STRING);
            if ($fields !== ['account_id','channel','currency','period','report_id','source','spend_cents','tenant_id','time_zone']) {
                throw new InvalidArgumentException('Unrecognized or missing synthetic report fields.');
            }
            $channel = $row['channel'];
            $account = $row['account_id'];
            $report = $row['report_id'];
            $spend = $row['spend_cents'];
            if (!is_string($channel) || !is_string($account) || !is_string($report) ||
                !isset($scope[$channel]) || !array_key_exists($account, $scope[$channel]) ||
                !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $report) ||
                $row['tenant_id'] !== $tenant ||
                $row['period'] !== $period ||
                $row['time_zone'] !== $scope[$channel][$account] ||
                $row['currency'] !== 'BRL' ||
                $row['source'] !== self::SOURCE ||
                !is_int($spend) || $spend < 0 || $spend > self::MAX_CHANNEL_SPEND_CENTS) {
                throw new InvalidArgumentException('Account, tenant, period or source mismatch.');
            }
            $id = $channel . "\0" . $account . "\0" . $report;
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Repeated report in account scope.');
            }
            $seen[$id] = true;
            if ($channels[$channel] > self::MAX_CHANNEL_SPEND_CENTS - $spend) {
                throw new InvalidArgumentException('Channel total exceeds Portal laboratory cap.');
            }
            $channels[$channel] += $spend;
            $totals[$channel][$account] += $spend;
            $canonical[] = [
                'account_id' => $account,
                'channel' => $channel,
                'currency' => 'BRL',
                'period' => $period,
                'report_id' => $report,
                'source' => self::SOURCE,
                'spend_cents' => $spend,
                'tenant_id' => $tenant,
                'time_zone' => $scope[$channel][$account],
            ];
        }
        foreach ($scope as $channel => $accounts) {
            foreach ($accounts as $account => $_) {
                $prefix = $channel . "\0" . $account . "\0";
                $exists = false;
                foreach ($seen as $id => $_used) {
                    if (str_starts_with($id, $prefix)) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    // A missing account is unknown, NEVER assumed to have spent zero.
                    throw new InvalidArgumentException('Missing account spend report; explicit zero row required.');
                }
            }
        }
        ksort($totals, SORT_STRING);
        foreach ($totals as &$items) ksort($items, SORT_STRING);
        unset($items);
        ksort($channels, SORT_STRING);
        usort($canonical, static fn(array $a, array $b): int =>
            [$a['channel'],$a['account_id'],$a['report_id']] <=>
            [$b['channel'],$b['account_id'],$b['report_id']]
        );

        return [
            'tenant_id' => $tenant,
            'period' => $period,
            'currency' => 'BRL',
            'unverified_spend_by_account_cents' => $totals,
            'unverified_spend_by_channel_cents' => $channels,
            'report_count' => count($canonical),
            'account_count' => $accountCount,
            'fingerprint_sha256_not_provider_proof' =>
                hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'provider_source_authenticated' => false,
            'provider_spend_verified' => false,
            'portal_iam_authenticated' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
            'state' => 'synthetic_readonly_account_scope_requires_official_provider_evidence',
        ];
    }
}
