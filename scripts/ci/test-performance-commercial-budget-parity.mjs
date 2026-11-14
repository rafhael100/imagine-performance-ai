/**
 * CI cross-repository "golden" test. Runs only in the GitHub runner with
 * immutable checked-out Commercial source; no web/DB/PII/ad credentials.
 */
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import {resolve} from 'node:path';

if(process.env.CI!=='true'||process.env.GITHUB_ACTIONS!=='true'||
   process.env.GITHUB_REPOSITORY!=='rafhael100/imagine-performance-ai'){
  throw new Error('Cross-repository parity is CI-only.');
}
const modulePath=resolve('_commercial_calculator_ci/src/lib/media-budget-calculator.mjs');
const {calculateMediaBudget:js}=await import(pathToFileURL(modulePath).href);
const plan=(channels,objective='leads',targetLeads=0,historicalCplCents=0,monthlyLimitCents=0)=>
  ({channels,objective,targetLeads,historicalCplCents,monthlyLimitCents});
const cases=[
  plan(['meta_ads']),plan(['google_ads']),plan(['chatgpt_ads']),
  plan(['chatgpt_ads','meta_ads']),plan(['meta_ads','google_ads']),
  plan(['chatgpt_ads','google_ads']),
  plan(['meta_ads','google_ads','chatgpt_ads']),
  plan(['chatgpt_ads','meta_ads','google_ads'],'sales'),
  plan(['chatgpt_ads'],'awareness',0,0,120000),
  plan(['meta_ads'],'leads',30,5000),
  plan(['meta_ads','google_ads'],'leads',100,2500),
  plan(['meta_ads','google_ads','chatgpt_ads'],'leads',30,12000),
  plan(['meta_ads','google_ads','chatgpt_ads'],'leads',100,5000),
  plan(['meta_ads','google_ads','chatgpt_ads'],'leads',150,6000),
  plan(['meta_ads','google_ads'],'sales',1,300000),
  plan(['google_ads'],'leads',17,9500),
  plan(['meta_ads','chatgpt_ads'],'awareness',250,4000),
  plan(['chatgpt_ads','meta_ads'],'leads',7,9999),
];
const local=cases.map(c=>{
  const r=js(c);
  return {
    minimum:r.minimumMediaCents,
    baseline:r.recommendedBaselineCents,
    goal:r.goalScenarioCents,
    suggested:r.suggestedMediaCents,
    fee:r.illustrativeManagementCents,
    total:r.illustrativeTotalCents,
    warning:r.exceedsMonthlyLimit,
    usesVisitorCpl:r.historicalCplSuppliedByVisitor,
    channels:r.channels.map(row=>({
      id:row.id,floor:row.floorCents,recommended:row.recommendedBaseCents,
      suggested:row.suggestedCents
    })),
    noCheckout:r.publicCheckoutEnabled===false,
    noAdsWrites:true, // Commercial estimator never imports an ad provider
    noCharge:true      // Commercial estimator never imports the billing runtime
  };
});
const p=spawnSync('php',['scripts/ci/compute-performance-budget-parity.php'],{
  encoding:'utf8',input:JSON.stringify(cases),timeout:10000,
  env:{...process.env}
});
assert.equal(p.status,0,p.stderr);
const actual=JSON.parse(p.stdout);
assert.equal(actual.length,cases.length);
for(let i=0;i<cases.length;i++){
  assert.deepEqual(actual[i],local[i],
    'different result for case '+i+': '+JSON.stringify(cases[i]));
}
console.log('PASS: '+cases.length+' different budget scenarios produce identical JS/PHP BRL cents, floors, goals and management; no real charges');
