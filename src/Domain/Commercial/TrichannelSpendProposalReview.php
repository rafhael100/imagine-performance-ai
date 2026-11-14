<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * EXPERIMENTAL three-channel spend illustration. Not a signed Portal quote,
 * official provider evidence or a PostgreSQL/Asaas settlement.
 * Uses ONLY synthetic account fixtures and proposed commercial price minima.
 */
final class TrichannelSpendProposalReview
{
    private const ALLOWED = ['google_ads', 'meta_ads', 'chatgpt_ads'];
    private const MAX_ACCOUNTS = 20;
    private const MAX_SPEND_CENTS = 100000000;

    /**
     * @param array<string,int> $plannedByChannelCents
     * @param array<string,array<string,string>> $scopes channel=>account=>IANA zone
     * @param array<string,array<string,array<string,mixed>>> $fixtures
     * @return array<string,mixed>
     */
    public static function preview(
        string $tenant, string $period, array $plannedByChannelCents,
        array $scopes, array $fixtures
    ): array {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_TRICHANNEL_SPEND_LAB') !== '1') {
            throw new InvalidArgumentException('Three-channel scenario laboratory disabled.');
        }
        if (!preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $tenant) ||
            !preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])\z/D', $period)) {
            throw new InvalidArgumentException('Invalid test company or period.');
        }
        // Includes strictly the PROPOSED, not-yet-Portal-approved floor rules.
        $planned = ChannelMinimumBudgetProposal::preview($plannedByChannelCents);
        $keys = array_keys($plannedByChannelCents);
        $scopeKeys = array_keys($scopes);
        $fixtureKeys = array_keys($fixtures);
        sort($keys, SORT_STRING);
        sort($scopeKeys, SORT_STRING);
        sort($fixtureKeys, SORT_STRING);
        if ($keys !== $scopeKeys || $keys !== $fixtureKeys ||
            array_diff($keys, self::ALLOWED) !== []) {
            throw new InvalidArgumentException('All simulated channels must have exactly matched accounts.');
        }
        $accountCount = 0;
        $byChannel = [];
        $records = [];
        foreach ($keys as $channel) {
            $channelScopes = $scopes[$channel];
            $channelFixtures = $fixtures[$channel];
            if (!is_array($channelScopes) || $channelScopes === [] ||
                !is_array($channelFixtures) || $channelFixtures === []) {
                throw new InvalidArgumentException('Accounts and fixtures are required per channel.');
            }
            $accounts = array_map('strval', array_keys($channelScopes));
            $fixtureAccounts = array_map('strval', array_keys($channelFixtures));
            sort($accounts, SORT_STRING);
            sort($fixtureAccounts, SORT_STRING);
            if ($accounts !== $fixtureAccounts) {
                throw new InvalidArgumentException('Missing, extra or mismatched report account.');
            }
            $byChannel[$channel] = 0;
            foreach ($accounts as $account) {
                $zone = $channelScopes[$account];
                $fixture = $channelFixtures[$account];
                if (!is_string($zone) || !is_array($fixture)) {
                    throw new InvalidArgumentException('Invalid account zone or report fixture.');
                }
                $item = match ($channel) {
                    'google_ads' => SyntheticProviderSpendAdapter::googleMonthlyFixture(
                        $tenant, $period, $account, $zone, $fixture),
                    'meta_ads' => SyntheticProviderSpendAdapter::metaMonthlyFixture(
                        $tenant, $period, $account, $zone, $fixture),
                    'chatgpt_ads' => ChatgptAdsOfflineAdapter::monthlyFixture(
                        $tenant, $period, $account, $zone, $fixture),
                };
                foreach ([
                    'provider_source_authenticated','provider_spend_verified',
                    'account_ownership_verified','pagination_verified_by_provider',
                    'finance_approved','asaas_write_enabled','ads_write_enabled'
                ] as $flag) {
                    if (($item[$flag] ?? null) !== false) {
                        throw new InvalidArgumentException('Synthetic report asserted unsupported authority.');
                    }
                }
                $record = $item['synthetic_account_report'] ?? null;
                if (!is_array($record) ||
                    ($record['tenant_id'] ?? null) !== $tenant ||
                    ($record['period'] ?? null) !== $period ||
                    ($record['channel'] ?? null) !== $channel ||
                    ($record['account_id'] ?? null) !== $account ||
                    ($record['currency'] ?? null) !== 'BRL' ||
                    ($record['time_zone'] ?? null) !== $zone ||
                    ($record['source'] ?? null) !== 'synthetic_readonly_report_fixture' ||
                    !is_int($record['spend_cents'] ?? null) ||
                    $record['spend_cents'] < 0 ||
                    $record['spend_cents'] > self::MAX_SPEND_CENTS - $byChannel[$channel]) {
                    throw new InvalidArgumentException('Synthetic account report scope or amount invalid.');
                }
                $byChannel[$channel] += $record['spend_cents'];
                $records[] = $record;
                if (++$accountCount > self::MAX_ACCOUNTS) {
                    throw new InvalidArgumentException('Excessive number of synthetic accounts.');
                }
            }
        }
        usort($records, static fn(array $a, array $b): int =>
            [$a['channel'], $a['account_id'], $a['report_id']] <=>
            [$b['channel'], $b['account_id'], $b['report_id']]
        );
        $actualTotal = array_sum($byChannel);
        $advance = $planned['illustrative_management_fee_cents'];
        $earned = intdiv($actualTotal * 20 + 50, 100);
        return [
            'tenant_id' => $tenant,
            'period' => $period,
            'currency' => 'BRL',
            'state' => 'synthetic_trichannel_proposal_not_an_approved_or_signed_quote',
            'channels' => $byChannel,
            'account_count' => $accountCount,
            'report_count' => count($records),
            'synthetic_provenance_sha256_not_provider_proof' =>
                hash('sha256', json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'proposed_planned_media_cents' => $planned['planned_media_cents'],
            'unverified_actual_media_cents' => $actualTotal,
            'illustrative_advance_management_fee_cents' => $advance,
            'illustrative_earned_management_fee_cents' => $earned,
            'illustrative_adjustment_cents' => $earned - $advance,
            'media_paid_directly_to_providers' => true,
            'portal_catalog_approved' => false,
            'quote_signed_by_portal' => false,
            'portal_iam_authenticated' => false,
            'provider_source_authenticated' => false,
            'provider_spend_verified' => false,
            'finance_approved' => false,
            'asaas_write_enabled' => false,
            'ads_write_enabled' => false,
        ];
    }
}
