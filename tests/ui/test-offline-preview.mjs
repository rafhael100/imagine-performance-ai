import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

/** F1-54 standalone Node 22 UI smoke test: NO DOM/network dependency. */
const html=readFileSync(new URL('../../contracts/demo/performance-scope-preview-v1.html',import.meta.url),'utf8');
let n=0;
function check(label,actual,expected){
  assert.deepEqual(actual,expected,label);
  console.log('PASS F54 '+(++n)+' '+label);
}
const match=html.match(/<script id="preview-data" type="application\/json">\s*([\s\S]*?)\s*<\/script>/);
assert.ok(match,'Missing fictional JSON dataset');
const data=JSON.parse(match[1]);
check('01 versioned mock contract',data.schema_version,'perf.portal.scope-preview.synthetic.v1');
check('02 fixture-only source',data.source,'in_memory_fixture_only');
check('03 fictional tenant',data.tenant_ref,'perf-demo-aurora');
for(const flag of ['contracted','ready_for_production','authorization_eligible',
  'dispatch_enabled','billing_enabled','prices_homologated']){
  check('blocked field '+flag,data[flag],false);
}
check('read only explicitly true',data.read_only,true);
check('Google and Meta one channel each',data.channels.map(x=>x.id),['google_ads','meta']);
check('paused campaigns still occupy managed cap',data.channels[0].campaigns.used,2);
check('groups counted only in illustration',data.channels[0].groups.used,2);
check('Meta independent count',data.channels[1].campaigns.used,1);
check('no real secrets/accounts/budget fields',
  /provider_account_id|access_token|media_spend|price_brl|secret/i.test(match[1]),false);
check('no outbound links or forms',/https?:\/\/|<a\s|<form\s/i.test(html),false);
check('CSP denies all connections',
  /default-src 'none';[^">]*connect-src 'none';/.test(html),true);
check('no external JS imports',/<script[^>]+src\s*=/.test(html),false);
check('all UI buttons explicit non-submit',
  (html.match(/<button\b[^>]*>/g)||[]).length,
  (html.match(/<button\b[^>]*\btype="button"[^>]*>/g)||[]).length);
check('prominent demo wording',html.includes('Ambiente fictício'),true);

const scripts=[...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)];
const js=scripts.at(-1)[1];
new Function(js);
check('JavaScript parses',typeof js,'string');
check('no JS network/telemetry/storage primitive',
  /\b(fetch\s*\(|XMLHttpRequest|WebSocket|sendBeacon|EventSource|localStorage|sessionStorage)\b/.test(js),false);

class Element{
  constructor(id='',dataset={}){
    this.id=id;this.dataset=dataset;this.children=[];this.events={};
    this.attributes={};this.style={};this.textContent='';this.classes=new Set();
    this.classList={toggle:(name,on)=>{
      const result=on===undefined?!this.classes.has(name):Boolean(on);
      if(result)this.classes.add(name);else this.classes.delete(name);
      return result;
    }};
  }
  append(...children){this.children.push(...children);}
  replaceChildren(...children){this.children=[...children];}
  setAttribute(key,value){this.attributes[key]=value;}
  addEventListener(name,cb){this.events[name]=cb;}
  click(){assert.equal(typeof this.events.click,'function');this.events.click();}
}
const ids=new Map();
for(const id of ['preview-data','metrics','channels','approvals','campaigns',
 'page-title','campaign-count','scope-panel','approvals-panel',
 'campaigns-panel','reports-panel','method-btn','methodology']){
  ids.set(id,new Element(id));
}
ids.get('preview-data').textContent=match[1];
ids.get('methodology').classes.add('hidden');
const tabs=['overview','campaigns','approvals','reports'].map(x=>new Element('',{view:x}));
const channels=['all','google_ads','meta'].map(x=>new Element('',{channel:x}));
const document={
  getElementById:id=>ids.get(id),
  querySelectorAll:sel=>sel==='[data-view]'?tabs:sel==='[data-channel]'?channels:[],
  createElement:tag=>new Element(tag),
};
vm.runInNewContext(js,{document,Error,JSON,String,Math});
const metric=i=>ids.get('metrics').children[i].children[1].textContent;
check('all channels initial campaign total',metric(0),'3');
check('all channels initial group total',metric(1),'3');
check('mock review total',metric(2),'2');
check('commercial state clearly blocked',metric(3),'Bloqueado');
check('three initial campaign cards',ids.get('campaigns').children.length,3);
check('two capacity channel cards',ids.get('channels').children.length,2);
channels[1].click();
check('Google filter changes managed campaign total',metric(0),'2');
check('Google filter changes managed group total',metric(1),'2');
check('Google shows two campaigns',ids.get('campaigns').children.length,2);
check('Google shows only one channel capacity',ids.get('channels').children.length,1);
check('Google shows only one review',ids.get('approvals').children.length,1);
channels[2].click();
check('Meta shows one managed campaign',metric(0),'1');
check('Meta shows one campaign card',ids.get('campaigns').children.length,1);
check('Meta shows one capacity card',ids.get('channels').children.length,1);
tabs[2].click();
check('approvals nav hides capacity panel',ids.get('scope-panel').classes.has('hidden'),true);
check('approvals nav shows review panel',ids.get('approvals-panel').classes.has('hidden'),false);
check('approvals nav hides reports',ids.get('reports-panel').classes.has('hidden'),true);
tabs[3].click();
check('reports nav shows reports',ids.get('reports-panel').classes.has('hidden'),false);
tabs[0].click();
ids.get('method-btn').click();
check('capacity methodology can be shown',ids.get('methodology').classes.has('hidden'),false);
check('methodology aria-expanded updated',ids.get('method-btn').attributes['aria-expanded'],'true');
ids.get('method-btn').click();
check('methodology can be hidden again',ids.get('methodology').classes.has('hidden'),true);
check('local buttons never add provider action panel',ids.get('metrics').children.length,4);
console.log('PASS F54 '+n+' offline UI/data/security/interaction checks');
