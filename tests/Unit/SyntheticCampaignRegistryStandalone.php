<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticCampaignRegistry.php';

use Imagine\Performance\Domain\SyntheticCampaignRegistry;

$registry = new SyntheticCampaignRegistry();
$n = 0;
$assert = static function (string $label, mixed $actual, mixed $expected) use (&$n): void {
    if ($actual !== $expected) {
        throw new RuntimeException("FAIL {$label}: got ".var_export($actual, true).' expected '.var_export($expected, true));
    }
    ++$n;
    echo "PASS {$label}\n";
};

$registry->setDemoCapacity('a', 'google_ads', 2);
$registry->setDemoCapacity('b', 'google_ads', 1);
$registry->setDemoCapacity('a', 'meta', null);
$registry->setDemoCapacity('b', 'meta', 1);
$assert('01 add campaign one', $registry->add('a', 'google_ads', 'a1'), 'accepted_demo');
$assert('02 add campaign two', $registry->add('a', 'google_ads', 'a2'), 'accepted_demo');
$assert('03 quota reached', $registry->add('a', 'google_ads', 'a3'), 'capacity_review_required');
$assert('04 count active', $registry->count('a', 'google_ads'), 2);
$assert('05 pause', $registry->pause('a', 'a1'), 'paused_demo');
$assert('06 paused still uses capacity', $registry->count('a', 'google_ads'), 2);
$assert('07 paused state', $registry->state('a', 'a1'), 'paused_demo');
$assert('08 cross tenant pause denied', $registry->pause('b', 'a1'), 'not_found');
$assert('09 cross tenant read denied', $registry->state('b', 'a1'), 'not_found');
$assert('10 cross tenant release denied', $registry->release('b', 'a1', true), 'not_found');
$assert('11 release without approval denied', $registry->release('a', 'a1', false), 'human_approval_required');
$assert('12 still full after denied release', $registry->count('a', 'google_ads'), 2);
$assert('13 B own quota', $registry->add('b', 'google_ads', 'b1'), 'accepted_demo');
$assert('14 B count isolated', $registry->count('b', 'google_ads'), 1);
$assert('15 B cannot claim A ID', $registry->add('b', 'google_ads', 'a2'), 'already_exists_or_out_of_scope');
$assert('16 unknown quota blocks', $registry->add('a', 'meta', 'meta-a1'), 'capacity_unknown');
$assert('17 null capacity not unlimited', $registry->capacity('a', 'meta')['remaining'], null);
$assert('18 unknown not for sale', $registry->capacity('a', 'meta')['status'], 'unknown_not_for_sale_demo');
$assert('19 formal release', $registry->release('a', 'a1', true), 'released_demo');
$assert('20 release frees slot', $registry->count('a', 'google_ads'), 1);
$assert('21 cannot pause archived', $registry->pause('a', 'a1'), 'not_found');
$assert('22 can add after release', $registry->add('a', 'google_ads', 'a3'), 'accepted_demo');
$assert('23 replacement requires approval', $registry->replace('a', 'a2', 'a4', false), 'human_approval_required');
$assert('24 new not created', $registry->state('a', 'a4'), 'not_found');
$assert('25 replacement cannot claim B ID', $registry->replace('a', 'a2', 'b1', true), 'already_exists_or_out_of_scope');
$assert('26 cross tenant replacement denied', $registry->replace('b', 'a2', 'a5', true), 'not_found');
$assert('27 approved replacement', $registry->replace('a', 'a2', 'a4', true), 'replaced_demo_no_fee');
$assert('28 replacement keeps usage', $registry->count('a', 'google_ads'), 2);
$assert('29 old archived', $registry->state('a', 'a2'), 'archived_demo');
$assert('30 new fake active', $registry->state('a', 'a4'), 'active_demo');
$assert('31 B Meta has own quota', $registry->add('b', 'meta', 'meta-b1'), 'accepted_demo');
$assert('32 B Meta quota reached', $registry->add('b', 'meta', 'meta-b2'), 'capacity_review_required');
$assert('33 Google and Meta quota distinct', $registry->count('b', 'google_ads'), 1);
$assert('34 A audit isolated', count(array_filter($registry->auditFor('a'), static fn (array $item): bool => $item['tenant'] !== 'a')), 0);
$assert('35 B audit isolated', count(array_filter($registry->auditFor('b'), static fn (array $item): bool => $item['tenant'] !== 'b')), 0);
$assert('36 no charge computed', array_key_exists('price', $registry->capacity('a', 'google_ads')), false);
$assert('37 remaining floor zero', $registry->capacity('a', 'google_ads')['remaining'], 0);

echo "PASS {$n} F1-04 in-memory synthetic checks\n";
