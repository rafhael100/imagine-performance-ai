import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { calculateScenario, parseBrlCents, PROPOSED_MINIMA, REFERENCE_RECOMMENDED } from
  '../labs/commercial/trichannel-calculator.mjs';

test('BRL parsing never relies on binary float amounts', () => {
  assert.equal(parseBrlCents('80,00'), 8000);
  assert.equal(parseBrlCents('R$ 150,25'), 15025);
  assert.equal(parseBrlCents('0,01'), 1);
  assert.equal(parseBrlCents('100.5'), 10050);
  for (const value of ['0', '0,00', '-1', '12,345', '1.250,00', '1e9',
                       'Infinity', 'NaN', '100000,01', '', 'abc']) {
    assert.throws(() => parseBrlCents(value), value);
  }
});
test('PHP calculator numeric parity: goals, proposed floors, media and separate fee', () => {
  const result = calculateScenario('2026-11', 30,
    {meta_ads:12500, google_ads:25000, chatgpt_ads:10000});
  assert.deepEqual(Object.keys(result.channels), ['chatgpt_ads','google_ads','meta_ads']);
  assert.equal(result.proposed_monthly_floor_total_cents, 290000);
  assert.equal(result.suggested_monthly_media_total_cents, 555000);
  assert.equal(result.illustrative_fee_on_suggested_media_cents, 111000);
  assert.equal(result.illustrative_total_separate_outlay_cents, 666000);
  assert.equal(result.illustrative_capacity_leads_at_assumed_cpl, 38);
  assert.equal(result.channels.google_ads.desired_leads_allocated, 10);
  assert.equal(result.channels.chatgpt_ads.suggested_monthly_media_cents,180000);
  assert.equal(result.channels.google_ads.suggested_monthly_media_cents,250000);
  assert.equal(result.channels.meta_ads.suggested_monthly_media_cents,125000);
  assert.equal(result.leads_or_results_guaranteed, false);
  assert.equal(result.portal_catalog_approved, false);
  assert.equal(result.crm_lead_saved, false);
  assert.equal(result.personal_data_collected, false);
  assert.equal(result.asaas_write_enabled, false);
  assert.equal(result.ads_write_enabled, false);
});
test('all floor/reference values match PHP proposal contract', () => {
  assert.deepEqual(PROPOSED_MINIMA, {chatgpt_ads:120000,google_ads:100000,meta_ads:70000});
  assert.deepEqual(REFERENCE_RECOMMENDED, {chatgpt_ads:180000,google_ads:150000,meta_ads:100000});
});
test('single channel, remainder, sort order and defensive caps', () => {
  let r=calculateScenario('2026-11',5,{meta_ads:12500,google_ads:12500});
  assert.equal(r.channels.google_ads.desired_leads_allocated,3);
  assert.equal(r.channels.meta_ads.desired_leads_allocated,2);
  assert.equal(r.channels.google_ads.suggested_monthly_media_cents,150000);
  assert.equal(r.channels.meta_ads.suggested_monthly_media_cents,100000);
  r=calculateScenario('2026-11',1,{chatgpt_ads:1});
  assert.equal(r.suggested_monthly_media_total_cents,180000);
  const a=calculateScenario('2026-11',30,
    {chatgpt_ads:10000,google_ads:25000,meta_ads:12500});
  const b=calculateScenario('2026-11',30,
    {meta_ads:12500,google_ads:25000,chatgpt_ads:10000});
  assert.deepEqual(a,b);
  for(const bad of [
    ['2026-13',30,{google_ads:10000}],
    ['1999-01',30,{google_ads:10000}],
    ['2026-11',0,{google_ads:10000}],
    ['2026-11',3001,{google_ads:10000}],
    ['2026-11',30,{}],
    ['2026-11',30,{other_ads:10000}],
    ['2026-11',30,{google_ads:-1}],
    ['2026-11',30,{google_ads:10000001}],
    ['2026-11',30,{google_ads:'10000'}],
    ['2026-11',30,{google_ads:10000.5}],
    ['2026-11',3000,{google_ads:10000000}]
  ]) assert.throws(() => calculateScenario(...bad));
});
test('demo has no third-party dependencies, PII capture or network submission', () => {
  const html = readFileSync(new URL('../labs/commercial/trichannel-calculator.html',import.meta.url),'utf8');
  const script = readFileSync(new URL('../labs/commercial/trichannel-calculator.mjs',import.meta.url),'utf8');
  assert.match(html, /#F9941D/);
  assert.match(html, /<script type="module" src="\.\/trichannel-calculator\.mjs"><\/script>/);
  assert.match(html, /id="desired-leads"/);
  assert.match(html, /id="calc-rows"/);
  assert.match(html, /id="copy-summary"/);
  assert.doesNotMatch(html, /<form\b|<iframe\b|https?:\/\/|<script(?! type="module")/i);
  assert.doesNotMatch(script, /\bfetch\s*\(|XMLHttpRequest|sendBeacon|localStorage|sessionStorage|document\.cookie/);
  assert.doesNotMatch(html, /<input[^>]*type="(?:email|tel|password)"/i);
});
