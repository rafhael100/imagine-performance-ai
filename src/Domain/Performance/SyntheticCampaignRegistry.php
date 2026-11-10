<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/** F1-04 fake-only in-memory domain. Never connects to Google, Meta or money. */
final class SyntheticCampaignRegistry
{
    private array $items = [];
    private array $limits = [];
    private array $audit = [];

    public function setDemoCapacity(string $tenant, string $channel, ?int $value): void
    {
        self::valid($tenant);
        self::validChannel($channel);
        if ($value !== null && $value < 0) {
            throw new InvalidArgumentException('Invalid synthetic quota');
        }
        $this->limits[$tenant][$channel] = $value;
    }

    public function add(string $tenant, string $channel, string $id): string
    {
        self::valid($tenant);
        self::valid($id);
        self::validChannel($channel);
        if (isset($this->items[$id])) {
            return $this->note($tenant, $id, 'already_exists_or_out_of_scope');
        }
        $limit = $this->limits[$tenant][$channel] ?? null;
        if ($limit === null) {
            return $this->note($tenant, $id, 'capacity_unknown');
        }
        if ($this->count($tenant, $channel) >= $limit) {
            return $this->note($tenant, $id, 'capacity_review_required');
        }
        $this->items[$id] = ['tenant' => $tenant, 'channel' => $channel, 'state' => 'active_demo', 'managed' => true];
        return $this->note($tenant, $id, 'accepted_demo');
    }

    public function pause(string $tenant, string $id): string
    {
        if (!$this->ownsManaged($tenant, $id)) {
            return $this->note($tenant, $id, 'not_found');
        }
        $this->items[$id]['state'] = 'paused_demo';
        return $this->note($tenant, $id, 'paused_demo');
    }

    public function release(string $tenant, string $id, bool $humanApproved): string
    {
        if (!$this->ownsManaged($tenant, $id)) {
            return $this->note($tenant, $id, 'not_found');
        }
        if (!$humanApproved) {
            return $this->note($tenant, $id, 'human_approval_required');
        }
        $this->items[$id]['managed'] = false;
        $this->items[$id]['state'] = 'archived_demo';
        return $this->note($tenant, $id, 'released_demo');
    }

    /** Atomic in-memory replacement, no overlap, quota increase or swap fee. */
    public function replace(string $tenant, string $fromId, string $newId, bool $humanApproved): string
    {
        self::valid($newId);
        if (!$this->ownsManaged($tenant, $fromId)) {
            return $this->note($tenant, $fromId, 'not_found');
        }
        if (!$humanApproved) {
            return $this->note($tenant, $fromId, 'human_approval_required');
        }
        if (isset($this->items[$newId])) {
            return $this->note($tenant, $fromId, 'already_exists_or_out_of_scope');
        }
        $channel = $this->items[$fromId]['channel'];
        if (($this->limits[$tenant][$channel] ?? null) === null) {
            return $this->note($tenant, $fromId, 'capacity_unknown');
        }
        $this->items[$fromId]['managed'] = false;
        $this->items[$fromId]['state'] = 'archived_demo';
        $this->items[$newId] = ['tenant' => $tenant, 'channel' => $channel, 'state' => 'active_demo', 'managed' => true];
        return $this->note($tenant, $newId, 'replaced_demo_no_fee');
    }

    public function count(string $tenant, string $channel): int
    {
        self::valid($tenant);
        self::validChannel($channel);
        return count(array_filter($this->items, static fn (array $row): bool =>
            $row['tenant'] === $tenant && $row['channel'] === $channel && $row['managed']));
    }

    public function state(string $tenant, string $id): string
    {
        self::valid($tenant);
        self::valid($id);
        $row = $this->items[$id] ?? null;
        return $row !== null && $row['tenant'] === $tenant ? $row['state'] : 'not_found';
    }

    public function capacity(string $tenant, string $channel): array
    {
        $used = $this->count($tenant, $channel);
        $limit = $this->limits[$tenant][$channel] ?? null;
        return ['used' => $used, 'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'status' => $limit === null ? 'unknown_not_for_sale_demo' : 'demo_only'];
    }

    public function auditFor(string $tenant): array
    {
        self::valid($tenant);
        return array_values(array_filter($this->audit, static fn (array $row): bool => $row['tenant'] === $tenant));
    }

    private function ownsManaged(string $tenant, string $id): bool
    {
        self::valid($tenant);
        self::valid($id);
        $row = $this->items[$id] ?? null;
        return $row !== null && $row['tenant'] === $tenant && $row['managed'];
    }

    private function note(string $tenant, string $id, string $result): string
    {
        $this->audit[] = ['tenant' => $tenant, 'campaign' => $id, 'result' => $result];
        return $result;
    }

    private static function validChannel(string $channel): void
    {
        if (!in_array($channel, ['google_ads', 'meta'], true)) {
            throw new InvalidArgumentException('Invalid demo channel');
        }
    }

    private static function valid(string $id): void
    {
        if ($id === '' || trim($id) !== $id || strlen($id) > 128) {
            throw new InvalidArgumentException('Invalid synthetic identifier');
        }
    }
}
