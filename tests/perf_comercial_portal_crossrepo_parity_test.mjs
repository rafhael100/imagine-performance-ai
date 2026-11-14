import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { CHANNELS, calculateMediaBudget } from
  '../comercial-reference/src/lib/media-budget-calculator.mjs';

const proposal = JSON.parse(readFileSync(
  new URL('../portal-reference/docs/decisions/performance-channel-minima-proposal-v1.json',
    import.meta.url), 'utf8'
));
const phpGuidance = JSON.parse(execFileSync('php', [
  '-r',
  'require "src/Domain/Commercial/ChannelMinimumBudgetProposal.php"; ' +
    'echo json_encode(Imagine\\Performance\\Commercial\\ChannelMinimumBudgetProposal::preview(' +
    '["meta_ads"=>70000,"google_ads"=>100000,"chatgpt_ads"=>120000]),JSON_THROW_ON_ERROR);'
], {cwd:new URL('..', import.meta.url).pathname,encoding:'utf8',timeout:10000}));

const pMinima=proposal.channel_minimums_cents;
const pRecommended=proposal.channel_recommended_cents;
const ids=['meta_ads','google_ads','chatgpt_ads'];

test('Portal and Imagine Comercial agree on owner-approved three-channel minima', () => {
  assert.equal(proposal.schema_version,'performance_channel_minima_proposal_v1');
  assert.equal(proposal.commercial_minima_approved,true);
  assert.equal(proposal.management_fee_basis_points,2000);
  assert.equal(proposal.currency,'BRL');
  assert.equal(proposal.asaas_charge_authorized,false);
  assert.equal(proposal.provider_charge_authorized,false);
  assert.equal(proposal.checkout_enabled,false);
  assert.equal(proposal.ad_write_enabled,false);
  assert.deepEqual(Object.keys(pMinima),ids);
  for(const id of ids){
    assert.equal(CHANNELS[id].floorCents,pMinima[id],id+' minimum BRL cents');
    assert.equal(CHANNELS[id].recommendedCents,pRecommended[id],id+' recommended BRL cents');
    assert.ok(pRecommended[id] >= pMinima[id],id+' recommended >= minimum');
  }
  assert.deepEqual(pMinima,{meta_ads:70000,google_ads:100000,chatgpt_ads:120000});
  assert.deepEqual(pRecommended,{meta_ads:100000,google_ads:150000,chatgpt_ads:180000});
});

test('Performance offline proposal matches approved commercial minima but cannot check out', () => {
  assert.deepEqual(Object.keys(phpGuidance.channels),['chatgpt_ads','google_ads','meta_ads']);
  for(const id of ids){
    assert.equal(phpGuidance.channels[id].proposed_monthly_floor_cents,pMinima[id]);
    assert.equal(phpGuidance.channels[id].recommended_monthly_media_cents,pRecommended[id]);
  }
  assert.equal(phpGuidance.planned_media_cents,290000);
  assert.equal(phpGuidance.illustrative_management_fee_cents,58000);
  assert.equal(phpGuidance.management_percent,20);
  assert.equal(phpGuidance.commercial_minima_owner_approved,true);
  assert.equal(phpGuidance.portal_catalog_approved,false);
  assert.equal(phpGuidance.provider_spend_verified,false);
  assert.equal(phpGuidance.asaas_write_enabled,false);
  assert.equal(phpGuidance.ads_write_enabled,false);
});

test('Comercial canonical calculator: no CPL does not fabricate leads and media is paid directly', () => {
  const quote=calculateMediaBudget({channels:ids,objective:'leads'});
  assert.equal(quote.version,'PERF-CALC-P0-2026-11-14');
  assert.equal(quote.minimumMediaCents,290000);
  assert.equal(quote.recommendedBaselineCents,430000);
  assert.equal(quote.suggestedMediaCents,430000);
  assert.equal(quote.illustrativeManagementCents,86000);
  assert.equal(quote.illustrativeTotalCents,516000);
  assert.equal(quote.goalScenarioCents,null);
  assert.equal(quote.historicalCplSuppliedByVisitor,false);
  assert.equal(quote.doesNotGuaranteeLeadsOrSales,true);
  assert.equal(quote.mediaPaidDirectlyToPlatforms,true);
  assert.equal(quote.publicCheckoutEnabled,false);
  assert.equal(quote.leadSentToCrm,false);
});

test('Comercial canonical scenario: declared historical CPL and proportional R$10 rounding', () => {
  const estimate=calculateMediaBudget({
    channels:['chatgpt_ads','google_ads','meta_ads'],
    objective:'leads',targetLeads:60,historicalCplCents:15000,monthlyLimitCents:500000
  });
  assert.equal(estimate.goalScenarioCents,900000);
  assert.deepEqual(Object.fromEntries(estimate.channels.map(x=>[x.id,x.suggestedCents])),
    {chatgpt_ads:377000,google_ads:314000,meta_ads:210000});
  assert.equal(estimate.suggestedMediaCents,901000);
  assert.equal(estimate.illustrativeManagementCents,180200);
  assert.equal(estimate.illustrativeTotalCents,1081200);
  assert.equal(estimate.exceedsMonthlyLimit,true);
  assert.equal(estimate.monthlyLimitCents,500000);
  assert.equal(estimate.historicalCplSuppliedByVisitor,true);
  assert.equal(estimate.leadSentToCrm,false);
  assert.equal(estimate.publicCheckoutEnabled,false);
});

test('No silent marketing intake or altered commercial minima on mistakes', () => {
  assert.throws(()=>calculateMediaBudget({channels:['google_ads','google_ads']}),/canais/i);
  assert.throws(()=>calculateMediaBudget({
    channels:['meta_ads'],objective:'leads',targetLeads:100,historicalCplCents:0
  }),/meta/i);
  const quote=calculateMediaBudget({channels:['chatgpt_ads'],objective:'awareness'});
  assert.equal(quote.minimumMediaCents,120000);
  assert.equal(quote.recommendedBaselineCents,180000);
  assert.equal(quote.leadSentToCrm,false);
  assert.equal(quote.publicCheckoutEnabled,false);
});
