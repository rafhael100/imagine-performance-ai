import assert from 'node:assert/strict';
import {readFileSync,existsSync} from 'node:fs';
import {join,basename} from 'node:path';

/** F1-62: validate the actual exported standalone offline folder. */
const dir=process.env.F62_SHOWCASE_DIR;
if(!dir||!basename(dir).startsWith('perf-showcase-')){
  throw new Error('F62 requires isolated ephemeral showcase directory');
}
let n=0;
function check(label,got,want){
 assert.deepEqual(got,want,label);
 console.log('PASS F62 '+(++n)+' '+label);
}
const files=[
 'index.html','painel-campanhas.html','revisoes.html',
 'relatorio-capacidade.csv','marcas-contas.json','manifest.json'
];
for(const name of files){
 check('bundle contains '+name,existsSync(join(dir,name)),true);
}
const manifest=JSON.parse(readFileSync(join(dir,'manifest.json'),'utf8'));
check('versioned offline manifest',manifest.schema_version,'perf.showcase.offline.synthetic.v1');
check('bundle has only synthetic sources',manifest.source,'local_fixtures_only');
check('one tenant fixture in manifest',manifest.tenant_ref,'perf-demo-aurora');
for(const field of ['contracted','ready_for_production','provider_connected',
 'billing_enabled','dispatch_enabled','external_network_enabled']){
 check('fail-closed manifest '+field,manifest[field],false);
}
check('bundle file manifest is exact',manifest.files,files.slice(0,5));
check('manifest read-only',manifest.read_only,true);

const account=JSON.parse(readFileSync(join(dir,'marcas-contas.json'),'utf8'));
check('account read model has version',
 account.schema_version,'perf.portal.account-scope-preview.synthetic.v1');
check('account read model matches tenant',account.tenant_ref,manifest.tenant_ref);
check('two channels',account.channels.map(x=>x.id),['google_ads','meta']);
check('two fictional account references',account.accounts.length,2);
check('three scoped campaigns',account.campaigns.length,3);
check('two mapped demo campaigns',account.counts.mapped_campaigns_demo,2);
check('one unassigned demo campaign',account.counts.unassigned_campaigns_demo,1);
check('one paused campaign',account.counts.paused_campaigns_demo,1);
check('three group records are explicitly not account mapped',
 account.counts.groups_without_account_mapping_demo,3);
check('all account refs are fake internal refs',
 account.accounts.every(x=>x.ref.startsWith('acct-demo-')),true);
check('no real provider connection in account data',account.provider_connected,false);
check('all account scoped campaigns have zero provider effects',
 account.campaigns.every(x=>x.provider_action==='none_synthetic'),true);
const accountText=JSON.stringify(account);
check('account JSON contains no secrets or financial fields',
 !/access_token|oauth|provider_account_id|customer_email|media_spend|price_brl|R\$/.test(accountText),true);

const index=readFileSync(join(dir,'index.html'),'utf8');
for(const name of files.slice(1,5)){
 check('offline home links to '+name,index.includes('href="'+name+'"'),true);
}
check('index CSP disallows script and connect',
 /script-src 'none'; connect-src 'none'/.test(index),true);
check('index has no external URLs',!/https?:\/\/|<form\b/i.test(index),true);
check('landing calls it a demonstration',index.includes('demonstração'),true);
const main=readFileSync(join(dir,'painel-campanhas.html'),'utf8');
const reviews=readFileSync(join(dir,'revisoes.html'),'utf8');
check('campaign page offline CSP',main.includes("connect-src 'none'"),true);
check('review page offline CSP',reviews.includes("connect-src 'none'"),true);
check('campaign page still disclaims real ops',main.includes('Ambiente fictício'),true);
check('review page still disclaims real approvals',reviews.includes('nenhuma aprovação tem efeito real'),true);
const mainJson=main.match(/<script id="preview-data" type="application\/json">\s*([\s\S]*?)\s*<\/script>/);
const reviewJson=reviews.match(/<script id="review-data" type="application\/json">\s*([\s\S]*?)\s*<\/script>/);
assert.ok(mainJson);assert.ok(reviewJson);
const dash=JSON.parse(mainJson[1]);
const queue=JSON.parse(reviewJson[1]);
check('campaign HTML tenant matches bundle',dash.tenant_ref,manifest.tenant_ref);
check('review HTML tenant matches bundle',queue.tenant_ref,manifest.tenant_ref);
check('campaign HTML has no billing grant',dash.billing_enabled,false);
check('review HTML has no execution grant',queue.scope_changes_permitted,false);
check('campaign and account fixture counts agree',
 dash.campaigns.length,account.campaigns.length);
check('pending review count is fixture only',queue.counts.pending,1);
check('approved review count is fixture only',queue.counts.approved_mock_no_action,1);
check('external account/customer text not present',
 !/access_token|provider_account_id|customer_email|media_spend/i.test(main+reviews),true);

const csv=readFileSync(join(dir,'relatorio-capacidade.csv'));
check('CSV starts with UTF-8 BOM',csv.subarray(0,3).toString('hex'),'efbbbf');
check('CSV has header and two channel rows',csv.toString('utf8').trim().split(/\r?\n/).length,3);
check('CSV is expressly not billing',csv.toString('utf8').includes('SEM FATURAMENTO OU EXECUCAO'),true);
check('CSV excludes real Google/Meta account identifiers',
 !/provider_account_id|access_token|media_spend|price_brl/i.test(csv.toString('utf8')),true);
console.log('PASS F62 '+n+' local-only deterministic showcase file checks');
