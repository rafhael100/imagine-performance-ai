<?php
declare(strict_types=1);

namespace Imagine\Performance\Tests\Fixtures;

use Closure;
use Throwable;

/**
 * CI-only policy seam. Input is a LOCAL fake order, not a Portal payment receipt.
 * The caller supplies a test-only account-mandate callback. No route, SQL access,
 * Ads API, secret resolver, draft persistence, money movement or production grant.
 * In particular this does not bridge Portal SQL001 to an S2S authority.
 */
final class SyntheticTwoChannelOrderReadiness
{
    private const PROVIDERS = ['google_ads' => 100000, 'meta_ads' => 70000];

    /** @param Closure(string,string,string):(?string) $leaseForAccount */
    public static function decide(
        array $order,
        array $briefing,
        Closure $leaseForAccount,
        array &$localLedger
    ): array {
        $blocked = static fn(string $reason): array => [
            'state' => 'blocked', 'reason' => $reason,
            'channels' => [], 'portal_http_read_proven' => false,
            'operational_enabled' => false, 'ads_write_enabled' => false,
            'billable' => false,
        ];
        $keys = static function(array $value, array $expected): bool {
            $actual = array_keys($value);
            sort($actual, SORT_STRING);
            sort($expected, SORT_STRING);
            return $actual === $expected;
        };
        if (!$keys($order, [
            'order_id', 'company_id', 'purchase_state', 'purchase_evidence',
            'catalog_version', 'activation_total_cents', 'channels',
            'briefing_revision', 'briefing_sha256',
        ]) || !$keys($briefing, [
            'order_id', 'company_id', 'state', 'revision', 'sha256',
        ])) return $blocked('invalid_shape');
        if (!is_string($order['order_id']) || preg_match('/\Aorder_[a-z0-9]{12,48}\z/D', $order['order_id']) !== 1
            || !is_string($order['company_id']) || preg_match('/\A[A-Za-z0-9_-]{8,96}\z/D', $order['company_id']) !== 1
            || $order['catalog_version'] !== 'performance-f423-v5-lab'
            || $order['purchase_state'] !== 'synthetic_confirmed'
            || $order['purchase_evidence'] !== 'fake_provider_callback_only') {
            return $blocked('purchase_not_confirmed');
        }
        if (!is_int($order['briefing_revision']) || $order['briefing_revision'] <= 0
            || !is_string($order['briefing_sha256'])
            || preg_match('/\A[a-f0-9]{64}\z/D', $order['briefing_sha256']) !== 1
            || $briefing['order_id'] !== $order['order_id']
            || $briefing['company_id'] !== $order['company_id']
            || $briefing['state'] !== 'synthetic_review_approved'
            || $briefing['revision'] !== $order['briefing_revision']
            || $briefing['sha256'] !== $order['briefing_sha256']) {
            return $blocked('briefing_not_exactly_approved');
        }
        if (!is_array($order['channels']) || !$keys($order['channels'], array_keys(self::PROVIDERS))
            || $order['activation_total_cents'] !== 98000) {
            return $blocked('invalid_two_channel_order');
        }
        foreach (self::PROVIDERS as $provider => $minimum) {
            $channel = $order['channels'][$provider];
            if (!is_array($channel) || !$keys($channel, ['account_id', 'media_cents', 'activation_cents'])
                || !is_string($channel['account_id'])
                || !is_int($channel['media_cents']) || $channel['media_cents'] < $minimum
                || $channel['media_cents'] > 100000000
                || $channel['activation_cents'] !== 49000) {
                return $blocked('invalid_channel_amount_or_account');
            }
        }

        $out = [];
        foreach (self::PROVIDERS as $provider => $_) {
            $channel = $order['channels'][$provider];
            // The lease may be derived ONLY from a trusted test callback to the
            // actual Performance mandate gate, never from the order/briefing.
            try {
                $lease = $leaseForAccount($order['company_id'], $provider, $channel['account_id']);
            } catch (Throwable) {
                $lease = null;
            }
            if (!is_string($lease) || preg_match('/\A[a-f0-9]{64}\z/D', $lease) !== 1) {
                $out[$provider] = ['state' => 'blocked', 'reason' => 'mandate_or_s2s_denied',
                    'media_cents' => $channel['media_cents'], 'campaign_state' => 'not_created'];
                continue;
            }
            $fingerprint = hash('sha256', implode("\0", [
                $order['company_id'], $order['order_id'], (string)$order['briefing_revision'],
                $order['briefing_sha256'], $provider, $channel['account_id'], (string)$channel['media_cents'],
            ]));
            $ledgerKey = hash('sha256', implode("\0", [
                $order['company_id'], $order['order_id'], (string)$order['briefing_revision'], $provider,
            ]));
            // An in-memory laboratory ledger is NOT a transaction or actual
            // idempotency across workers. Collision at same revision is denied.
            if (isset($localLedger[$ledgerKey]) && $localLedger[$ledgerKey] !== $fingerprint) {
                $out[$provider] = ['state' => 'blocked', 'reason' => 'same_revision_conflict',
                    'media_cents' => $channel['media_cents'], 'campaign_state' => 'not_created'];
                continue;
            }
            $localLedger[$ledgerKey] ??= $fingerprint;
            $out[$provider] = [
                'state' => 'internal_review_only', 'media_cents' => $channel['media_cents'],
                'campaign_state' => 'not_created', 'connection_state' => 'synthetic_candidate',
                'review_key' => 'lab_' . substr($ledgerKey, 0, 24),
            ];
        }
        return [
            'state' => 'internal_review_only', 'channels' => $out,
            'readback_candidate' => [
                'order_id' => $order['order_id'], 'company_id' => $order['company_id'],
                'briefing_revision' => $order['briefing_revision'],
                'briefing_sha256' => $order['briefing_sha256'],
                'source' => 'local_fixture_only',
            ],
            'portal_http_read_proven' => false,
            'operational_enabled' => false, 'ads_write_enabled' => false, 'billable' => false,
        ];
    }
}
