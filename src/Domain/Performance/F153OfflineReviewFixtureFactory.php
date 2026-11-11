<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use RuntimeException;

require_once __DIR__.'/F151SyntheticCreativeReviewQueue.php';

/**
 * F153: one deterministic fictional review source for both HTML and CSV.
 * No database, IAM, Ads, provider metrics, human approval, or billing.
 */
final class F153OfflineReviewFixtureFactory
{
    /** @return array<string,mixed>|null */
    public static function build(
        SyntheticManagedScopeLedger $scope,string $tenant
    ):?array {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F52_SCOPE_LAB')!=='1'
            ||getenv('PERF_F151_APPROVAL_LAB')!=='1'
            ||getenv('PERF_F153_PARITY_LAB')!=='1'
            ||$tenant!=='perf-demo-aurora') {
            return null;
        }
        $queue=new F151SyntheticCreativeReviewQueue($scope);
        if($queue->submit($tenant,'rev-search-demo','cam-search-demo',
                'google_ads','Revisar peça de pesquisa')!=='submitted_demo'
            ||$queue->submit($tenant,'rev-social-demo','cam-social-demo',
                'meta','Revisar campanha social')!=='submitted_demo'
            ||$queue->decide($tenant,'rev-social-demo',1,'request_changes',
                'reviewer-demo-maria')!=='changes_requested_demo') {
            throw new RuntimeException('F153 demo review fixture cannot be prepared');
        }
        $dto=$queue->project($tenant);
        if(!is_array($dto)
            ||$dto['review_count']!==2
            ||$dto['human_approval_verified']!==false
            ||$dto['authorization_eligible']!==false
            ||$dto['dispatch_enabled']!==false
            ||$dto['billing_enabled']!==false
            ||$dto['ready_for_production']!==false) {
            throw new RuntimeException('F153 unsafe fictional review fixture');
        }
        return $dto;
    }
}
