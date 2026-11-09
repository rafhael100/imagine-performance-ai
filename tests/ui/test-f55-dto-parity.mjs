import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

/** F1-55: inspect the PHP-generated DTO and HTML, without DOM/network. */
const htmlFile=process.env.F55_RENDERED_PREVIEW_FILE;
const dtoFile=process.env.F55_PROJECTED_JSON_FILE;
if(!htmlFile||!dtoFile)throw new Error('F55 requires explicit CI output files');
const html=readFileSync(htmlFile,'utf8');
const dto=JSON.parse(readFileSync(dtoFile,'utf8'));
const match=html.match(/<script id="preview-data" type="application\/json">\s*([\s\S]*?)\s*<\/script>/);
assert.ok(match,'F55 HTML lacks the unique embedded DTO');
const ui=JSON.parse(match[1]);
let n=0;
const check=(label,got,want)=>{
  assert.deepEqual(got,want,label);
  console.log('PASS F55 '+(++n)+' '+label);
};
for(const field of [
 'schema_version','source','tenant_ref','read_only','contracted',
 'ready_for_production','authorization_eligible','dispatch_enabled',
 'billing_enabled','prices_homologated','commercial'
]){
  check('PHP to HTML parity '+field,ui[field],dto[field]);
}
check('exact same channel count, labels and capacities',ui.channels,dto.channels);
check('exact same tenant-scoped group data',ui.groups,dto.groups);
check('exact same simulated audit counters',ui.review_summary,dto.review_summary);
check('no campaign silently added or removed',
  ui.campaigns.map(x=>x.ref),dto.campaigns.map(x=>x.ref));
check('no campaign status drift',
  ui.campaigns.map(x=>x.state),dto.campaigns.map(x=>x.state));
check('fictional campaign titles only',
  ui.campaigns.map(x=>x.title),
  ['Pesquisa de serviços','Presença regional','Reconhecimento da marca']);
check('known Google and Meta channel mapping',
  ui.campaigns.map(x=>x.channel),['google_ads','google_ads','meta']);
check('paused managed campaign still counted',ui.channels[0].campaigns.used,2);
check('paused campaign still shown as paused',ui.campaigns[1].state,'paused_demo');
check('all campaigns show only synthetic states',
  ui.campaigns.every(x=>x.state.endsWith('_demo')),true);
check('never ready for production',ui.ready_for_production,false);
check('never dispatch',ui.dispatch_enabled,false);
check('never bill',ui.billing_enabled,false);
check('no real approval source',ui.review_summary.approval_source,'none_real');
check('UI suggestions remain clearly fictional',
  ui.approvals.every(x=>/fictícia|real/.test(x.status)),true);
check('HTML CSP denies network',
  /default-src 'none';[^">]*connect-src 'none';/.test(html),true);
check('HTML has no forms, URLs or external links',
  !/<form\b|<a\b|https?:\/\//i.test(html),true);
check('foreign tenant not present',!html.includes('perf-demo-b'),true);
check('secret/provider account/spend fields not included',
  !/access_token|provider_account_id|media_spend|price_brl|secret/i.test(match[1]),true);
check('exactly one embedded JSON script',
  [...html.matchAll(/<script id="preview-data"/g)].length,1);
console.log('PASS F55 '+n+' deterministic PHP DTO -> offline UI parity checks');
