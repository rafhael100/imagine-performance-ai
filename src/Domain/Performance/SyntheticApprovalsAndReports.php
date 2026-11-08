<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/** F1-05 in-memory approval queue. NO external write, spending or network. */
final class SyntheticApprovalDesk
{
    /** @var array<string,array<string,mixed>> */
    private array $proposals = [];

    /** @var list<array{tenant:string,id:string,action:string,result:string}> */
    private array $history = [];

    public function propose(
        string $tenant,
        string $id,
        string $proposer,
        string $campaignId,
        string $manifestVersion,
        int $expiresAt,
    ): string {
        foreach ([$tenant, $id, $proposer, $campaignId, $manifestVersion] as $identifier) {
            self::required($identifier);
        }
        if ($expiresAt <= 0) {
            throw new InvalidArgumentException('Invalid demo expiry');
        }
        if (isset($this->proposals[$id])) {
            return $this->record($tenant, $id, 'propose', 'already_exists_or_out_of_scope');
        }
        $this->proposals[$id] = [
            'tenant' => $tenant,
            'proposer' => $proposer,
            'campaign' => $campaignId,
            'version' => $manifestVersion,
            'expires' => $expiresAt,
            'status' => 'pending_human_demo',
            'decided_by' => null,
        ];
        return $this->record($tenant, $id, 'propose', 'submitted_demo');
    }

    /**
     * A grant is only a synthetic test fixture, NOT a session, token or S2S
     * authentication. Status change here never triggers a provider operation.
     */
    public function decide(
        string $tenant,
        string $id,
        string $reviewer,
        string $manifestVersion,
        bool $isAuthorizedReviewer,
        bool $approve,
        int $now,
    ): string {
        self::required($tenant);
        self::required($id);
        self::required($reviewer);
        self::required($manifestVersion);
        $p = $this->proposals[$id] ?? null;
        if ($p === null || $p['tenant'] !== $tenant) {
            return $this->record($tenant, $id, 'decide', 'not_found');
        }
        if ($p['status'] !== 'pending_human_demo') {
            return $this->record($tenant, $id, 'decide', 'already_decided');
        }
        if ($p['expires'] <= $now) {
            return $this->record($tenant, $id, 'decide', 'expired_denied');
        }
        if (!$isAuthorizedReviewer || $p['proposer'] === $reviewer) {
            return $this->record($tenant, $id, 'decide', 'authorization_denied');
        }
        if ($p['version'] !== $manifestVersion) {
            return $this->record($tenant, $id, 'decide', 'manifest_conflict');
        }
        $this->proposals[$id]['status'] = $approve ? 'approved_demo_no_execution' : 'rejected_demo';
        $this->proposals[$id]['decided_by'] = $reviewer;
        return $this->record($tenant, $id, 'decide', $this->proposals[$id]['status']);
    }

    public function statusFor(string $tenant, string $id): string
    {
        self::required($tenant);
        self::required($id);
        $p = $this->proposals[$id] ?? null;
        return $p !== null && $p['tenant'] === $tenant ? $p['status'] : 'not_found';
    }

    /** @return list<array{tenant:string,id:string,action:string,result:string}> */
    public function historyFor(string $tenant): array
    {
        self::required($tenant);
        return array_values(array_filter($this->history, static fn (array $row): bool => $row['tenant'] === $tenant));
    }

    private function record(string $tenant, string $id, string $action, string $result): string
    {
        $this->history[] = compact('tenant', 'id', 'action', 'result');
        return $result;
    }

    private static function required(string $id): void
    {
        if ($id === '' || trim($id) !== $id || strlen($id) > 128) {
            throw new InvalidArgumentException('Invalid demo id');
        }
    }
}

/** Read-only demo reports. In particular "approved" never means live Ads. */
final class SyntheticPerformanceReports
{
    /** @var array<string,array<string,mixed>> */
    private array $reports = [];

    public function setDemo(string $tenant, string $reportId, string $channel, int $impressions, int $clicks): void
    {
        if ($tenant === '' || $reportId === '' || !in_array($channel, ['google_ads', 'meta'], true)
            || $impressions < 0 || $clicks < 0 || $clicks > $impressions) {
            throw new InvalidArgumentException('Invalid demo report');
        }
        if (isset($this->reports[$reportId])) {
            throw new InvalidArgumentException('Duplicate report ID');
        }
        $this->reports[$reportId] = [
            'tenant' => $tenant,
            'channel' => $channel,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'source' => 'synthetic_demo',
            'status' => 'not_real_metrics',
        ];
    }

    public function forTenant(string $tenant, string $reportId): ?array
    {
        $r = $this->reports[$reportId] ?? null;
        return $r !== null && $r['tenant'] === $tenant ? $r : null;
    }
}
