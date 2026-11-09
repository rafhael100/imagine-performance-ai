import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

/** F1-59 interactive smoke test: offline DOM stub, no remote calls. */
const rendered=process.env.F59_RENDERED_FILE;
const dtoFile=process.env.F59_DTO_FILE;
if(!rendered||!dtoFile)throw new Error('F59 needs both disposable CI files');
const html=readFileSync(rendered,'utf8');
const dto=JSON.parse(readFileSync(dtoFile,'utf8'));
const match=html.match(/<script id="review-data" type="application\/json">\s*([\s\S]*?)\s*<\/script>/);
assert.ok(match,'One embedded F58 review DTO required');
const data=JSON.parse(match[1]);
let n=0;
function check(label,got,expected){
  assert.deepEqual(got,expected,label);
  console.log('PASS F59 '+(++n)+' '+label);
}
check('DTO renderer parity',data,dto);
check('versioned review model',data.schema_version,'perf.portal.review-queue.synthetic.v1');
check('fake fixture tenant',data.tenant_ref,'perf-demo-aurora');
check('read-only mode',data.read_only,true);
check('contract not active',data.contracted,false);
check('human identity not trusted',data.human_identity_verified,false);
check('no approval is an authorization',data.authorization_eligible,false);
check('cannot go to production',data.ready_for_production,false);
check('no dispatch',data.dispatch_enabled,false);
check('no billing',data.billing_enabled,false);
check('no scope changes via UI',data.scope_changes_permitted,false);
check('source explicitly in-memory fake',data.source,'in_memory_review_fixture_only');
check('two fixture requests',data.items.length,2);
check('first pending',data.items[0].category,'pending');
check('second approved only in mock',data.items[1].category,'approved_mock_no_action');
check('actual provider action absent',
 data.items.every(x=>x.provider_action==='none_synthetic'),true);
check('no reviewer or operator identity leakage',
 !/actor-demo-|reviewer_email|user_token/i.test(match[1]),true);
check('embedded output contains no injected script termination',
 [...html.matchAll(/<script id="review-data"/g)].length,1);
check('offline CSP explicitly forbids network',
 /default-src 'none';[^">]*connect-src 'none';/.test(html),true);
check('no external URL, link or form',
 !/https?:\/\/|<form\b|<a\b/i.test(html),true);
check('no script import',!/<script[^>]+src=/.test(html),true);
check('buttons cannot submit',
 (html.match(/<button\b[^>]*>/g)||[]).length,
 (html.match(/<button\b[^>]*type="button"[^>]*>/g)||[]).length);
const js=[...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)].at(-1)[1];
new Function(js);
check('javascript parses',typeof js,'string');
check('no storage, HTTP or tracking APIs in script',
 !/\b(fetch\s*\(|XMLHttpRequest|WebSocket|sendBeacon|localStorage|sessionStorage|EventSource)\b/.test(js),true);
class El{
 constructor(id='',dataset={}){
  this.id=id;this.dataset=dataset;this.children=[];this.events={};
  this.attributes={};this.textContent='';this.classes=new Set();
  this.classList={toggle:(key,on)=>{
   const yes=on===undefined?!this.classes.has(key):Boolean(on);
   if(yes)this.classes.add(key);else this.classes.delete(key);
   return yes;
  }};
 }
 append(...x){this.children.push(...x);}
 replaceChildren(...x){this.children=[...x];}
 addEventListener(k,cb){this.events[k]=cb;}
 setAttribute(k,v){this.attributes[k]=v;}
 click(){assert.equal(typeof this.events.click,'function');this.events.click();}
}
const nodes=new Map(['review-data','items','stats'].map(id=>[id,new El(id)]));
nodes.get('review-data').textContent=match[1];
const buttons=['all','pending','approved_mock_no_action','applied_mock'].map(filter=>new El('',{filter}));
const document={
 getElementById:id=>nodes.get(id),
 createElement:tag=>new El(tag),
 querySelectorAll:q=>q==='[data-filter]'?buttons:[],
};
vm.runInNewContext(js,{document,Error,JSON,String});
check('four summary indicators',nodes.get('stats').children.length,4);
check('initial request cards',nodes.get('items').children.length,2);
check('initial mock pending count',nodes.get('stats').children[0].children[1].textContent,'1');
check('initial mock approved count',nodes.get('stats').children[1].children[1].textContent,'1');
check('initial applied mock count',nodes.get('stats').children[2].children[1].textContent,'0');
buttons[1].click();
check('pending filter only one item',nodes.get('items').children.length,1);
check('pending request visible',nodes.get('items').children[0].children[0].children[0].textContent,
 'req-demo-review-one');
check('pending filter aria pressed',buttons[1].attributes['aria-pressed'],'true');
buttons[2].click();
check('approved mock filter only one item',nodes.get('items').children.length,1);
check('approved mock request visible',nodes.get('items').children[0].children[0].children[0].textContent,
 'req-demo-review-two');
check('approved mock label never says live execution',
 nodes.get('items').children[0].children[1].textContent,
 'Aprovada na simulação, sem execução');
buttons[3].click();
check('applied filter without items gives empty state',nodes.get('items').children.length,1);
check('empty state message',nodes.get('items').children[0].textContent,
 'Nenhum pedido fictício neste filtro.');
buttons[0].click();
check('all filter restores two entries',nodes.get('items').children.length,2);
check('fake UI does not mutate DTO approvals',data.counts.approved_mock_no_action,1);
console.log('PASS F59 '+n+' offline read-only review queue UI/parity checks');
