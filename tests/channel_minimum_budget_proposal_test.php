<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/ChannelMinimumBudgetProposal.php';
use Imagine\Performance\Commercial\ChannelMinimumBudgetProposal as Floors;

function requireProposal(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}
function denyProposal(callable $call, string $label): void
{
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected proposal rejection: ' . $label);
}

$all = Floors::preview([
    'meta_ads' => 70000,
    'chatgpt_ads' => 120000,
    'google_ads' => 100000,
]);
requireProposal($all['planned_media_cents'] === 290000, 'all floors add to BRL 2900');
requireProposal($all['illustrative_management_fee_cents'] === 58000, '20% = BRL 580');
requireProposal(array_keys($all['channels']) === ['chatgpt_ads', 'google_ads', 'meta_ads'], 'canonical order');
requireProposal($all['channels']['chatgpt_ads']['recommended_monthly_media_cents'] === 180000, 'recommendation BRL 1800');
requireProposal($all['channels']['google_ads']['proposed_monthly_floor_cents'] === 100000, 'Google floor BRL 1000');
requireProposal($all['channels']['meta_ads']['proposed_monthly_floor_cents'] === 70000, 'existing Meta floor BRL 700');
requireProposal($all['portal_catalog_approved'] === false, 'not approved');
requireProposal($all['asaas_write_enabled'] === false && $all['ads_write_enabled'] === false, 'no writes');
requireProposal($all['actual_fee_requires_verified_spend'] === true, 'real fee separate from preview');

$single = Floors::preview(['chatgpt_ads' => 180000]);
requireProposal($single['illustrative_management_fee_cents'] === 36000, 'ChatGPT recommended plan BRL 360 fee');

denyProposal(fn() => Floors::preview([]), 'no channels');
denyProposal(fn() => Floors::preview(['chatgpt_ads' => 119999]), 'ChatGPT below proposed floor');
denyProposal(fn() => Floors::preview(['google_ads' => 99999]), 'Google below floor');
denyProposal(fn() => Floors::preview(['meta_ads' => 69999]), 'Meta below floor');
denyProposal(fn() => Floors::preview(['tiktok_ads' => 100000]), 'unknown provider');
denyProposal(fn() => Floors::preview(['chatgpt_ads' => '120000']), 'string instead of integer');
denyProposal(fn() => Floors::preview(['chatgpt_ads' => 100000001]), 'capped amount');

echo "OK: offline distinct channel floors, 20% preview, no billing or ad mutation\n";
