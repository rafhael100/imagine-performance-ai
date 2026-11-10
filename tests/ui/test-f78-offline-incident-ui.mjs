import assert from 'node:assert/strict';
import {execFileSync,spawnSync} from 'node:child_process';

const env={...process.env,
  APP_ENV:'testing',
  PERF_F75_SIGNED_WITNESS_LAB:'1',
  PERF_F76_RECONCILIATION_LAB:'1',
  PERF_F77_INCIDENT_LAB:'1',
  PERF_F78_HTML_PREVIEW_LAB:'1'
};
const json=JSON.parse(execFileSync('php',['contracts/demo/f77-incident-preview.php'],{env,encoding:'utf8'}));
const html=execFileSync('php',['contracts/demo/f78-render-incident-preview.php'],{env,encoding:'utf8'});
const checks=[
  ['language and title',/<html lang="pt-BR">/.test(html)],
  ['explicit lab status',html.includes('DEMONSTRAÇÃO — NÃO HOMOLOGADO')],
  ['verified incident contract',html.includes('perf.recovery.incident.synthetic.v1')],
  ['reason parity',html.includes(json.reason)],
  ['code parity',html.includes(json.status)],
  ['human reviewer',html.includes('Revisão humana: obrigatória')],
  ['no Ads dispatch',html.includes('Publicidade: bloqueada')],
  ['no billing',html.includes('Cobrança: bloqueada')],
  ['no production access',html.includes('Produção: não autorizada')],
  ['CSP restricts external',html.includes("default-src 'none'")],
  ['no scripts',!/<script(?:\s|>)/i.test(html)],
  ['no forms',!/<form(?:\s|>)/i.test(html)],
  ['no external links',!/<a\s|https?:\/\//i.test(html)],
  ['no images/network elements',!/<(?:img|iframe|video|audio|link)(?:\s|>)/i.test(html)],
  ['responsive CSS',html.includes('@media(max-width:650px)')],
  ['no credentials',!/access_token|client_secret|private_key|account_id/i.test(html)],
];
for(const [label,ok] of checks){
  assert.equal(ok,true,`F78 failed: ${label}`);
}
for(const config of [
  {...env, APP_ENV:'production'},
  {...env, PERF_F78_HTML_PREVIEW_LAB:'0'},
  {...env, PERF_F75_SIGNED_WITNESS_LAB:'0'},
]){
  const result=spawnSync('php',['contracts/demo/f78-render-incident-preview.php'],{env:config,encoding:'utf8'});
  assert.notEqual(result.status,0,'F78 fixture unexpectedly exposed outside lab');
}
console.log(`PASS F78 ${checks.length+3} offline, parity, safety and environment assertions`);
