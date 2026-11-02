import {pathToFileURL} from 'node:url';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
const lib=process.env.CTSMD_BROWSER_LIB;
if(!lib)throw Error('Set CTSMD_BROWSER_LIB to the installed Taste scripts/lib directory.');
const {Browser}=await import(pathToFileURL(join(lib,'cdp.mjs')).href);
const {probe}=await import(pathToFileURL(join(lib,'probe.mjs')).href);
const {judge,APP_SKIP}=await import(pathToFileURL(join(lib,'rules.mjs')).href);
import {mkdirSync,mkdtempSync,writeFileSync,readFileSync} from 'node:fs';
const mode='after',batch=process.argv[2]||'A';
const base=new URL(process.env.CTSMD_UX_BASE_URL||'http://127.0.0.1:8096');
if(base.protocol!=='http:'||!['127.0.0.1','localhost'].includes(base.hostname)||!base.port||base.pathname!=='/'||base.username||base.password)throw Error('Only explicit loopback HTTP ports are supported. No live URL.');
const origin=base.origin;
if(!process.env.CTSMD_UX_FIXTURE)throw Error('Provide an isolated synthetic fixture JSON via CTSMD_UX_FIXTURE.');
const ids=JSON.parse(readFileSync(process.env.CTSMD_UX_FIXTURE));
if(ids.isolated!==true)throw Error('Fixture must explicitly declare isolated:true.');
const selectedRoutes=process.env.CTSMD_UX_ROUTES?.split(',');
const rows=JSON.parse(readFileSync('tests/fixtures/operations-routes.json')).rows.filter(r=>(batch==='all'||r.batch===batch)&&r.kind==='screen'&&(!selectedRoutes||selectedRoutes.includes(r.route)));
if(!rows.length)throw Error('No screen routes selected. Transport/public/print routes are intentionally not exercised.');
const queries={
 '/admin/accounts/view':`id=${ids.guardian}`, '/admin/registrations/edit':`id=${ids.registration}`,'/admin/registrations/view':`id=${ids.registration}`,'/admin/registrations/intake':`submission=${ids.intake}`,
 '/people/view':`id=${ids.student}`,'/admin/teams/view':`id=${ids.team}`,
 '/student-profile':`student=${ids.student}`,'/student-profile/family':`student=${ids.student}`,'/student-profile/family/review':`student=${ids.student}`,'/theatre-history':`student=${ids.student}`,'/theatre-history/resume':`student=${ids.student}`,
 '/safeguarding/case':`id=${ids.case}`,'/safeguarding/review':`id=${ids.credential||0}`,
 '/admin/productions/view':`id=${ids.production}`,'/production/groups/view':`id=${ids.group}`,
 '/archive/production':`id=${ids.archive}`,'/archive/community':`production=${ids.archive}&channel=${ids.archivechannel||0}`,
 '/admin/resources/edit':`id=${ids.resource}`,'/resources/view':`id=${ids.resource}`,
 '/attendance/take':`id=${ids.event}`,'/attendance/report':`id=${ids.event}`,'/production/edit':`id=${ids.event}`,
 '/admin/volunteer-shifts/view':`id=${ids.shift}`,'/volunteer/shift':`id=${ids.shift}`,'/admin/volunteer-approvals/review':`id=${ids.approval}`,
 '/admin/member-resources/edit':`id=${ids.orgresource}`,'/admin/channels/edit':`id=${ids.channel}`,
 '/admin/files/edit':`id=${ids.file||0}`,'/files/view':`id=${ids.file||0}`,
 '/production/notice':`id=${ids.notice||0}`,'/volunteer/verification':`id=${ids.verification||0}`,
 '/videos/view':`id=${ids.video||0}`,'/production/rehearsal-video':`id=${ids.video||0}`,
 '/forms/view':`id=${ids.assignment}`,'/admin/forms/review':`id=${ids.submission}`,
 '/admin/forms/manage/edit':`id=${ids.form}`,'/admin/forms/manage/assign':`id=${ids.form}`,'/admin/forms/builder':`id=${ids.form}`,
};
const out=process.env.CTSMD_UX_OUTPUT||mkdtempSync(join(tmpdir(),'ctsmd-operations-'));mkdirSync(out,{recursive:true});
const b=await Browser.launch(), results=[];
try {const p=await b.newPage();await p.send('Network.setCacheDisabled',{cacheDisabled:true});await p.send('Network.setBypassServiceWorker',{bypass:true});
 await p.goto(origin+'/dev/identity',{settleMs:50});
 await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${ids.admin}');if(!f)throw Error('Fixture identity unavailable');if(!f?.querySelector('p')?.textContent.trim().endsWith('@example.test')&&!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic fixture identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);
 await p.goto(origin+'/staff',{settleMs:100});
 await p.eval(`(async()=>{const f=document.querySelector('.unified-production-switcher form')||document.querySelector('form.unified-production-switcher');if(!f)throw Error('Production selector unavailable');const d=new FormData(f);d.set('production_id','${ids.production}');await fetch(f.action,{method:'POST',body:d});})()`);
 for(const row of rows){
  if(['/family/caregiver-schedule','/forms/view'].includes(row.route)) {
   await p.goto(origin+'/dev/identity',{settleMs:50});
   await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${row.route==='/forms/view'?ids.guardian:ids.volunteer}');if(!f?.querySelector('p')?.textContent.trim().endsWith('@example.test')&&!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic fixture identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);
  } else {
   // Previous route can be the restricted caregiver view; restore the synthetic administrator.
   await p.goto(origin+'/dev/identity',{settleMs:50});
   await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${ids.admin}');if(!f?.querySelector('p')?.textContent.trim().endsWith('@example.test')&&!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic fixture identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);
  }
  const path=row.route+(queries[row.route]?'?'+queries[row.route]:'');const name=row.route.slice(1).replaceAll('/','-');
  for(const width of [1440,768,390]) {
   await p.setViewport({width,height:900,mobile:width===390});
   try {await p.goto(origin+path,{settleMs:100});} catch(e) {if(!e.message.includes('ERR_HTTP_RESPONSE_CODE_FAILURE'))throw e;}
   await p.eval(()=>document.querySelector('.mobile-engagement-later')?.click());
   // A PHP failure inside a select can truncate HTML without visible error text.
   // Inspect the local response too, rather than treating a partial DOM as success.
   const responseShape=await p.eval(async()=>{const response=await fetch(location.href,{credentials:'same-origin',cache:'no-store'});const html=await response.text();return {status:response.status,complete:/<\/html>\s*$/i.test(html),serverError:/Fatal error|Uncaught (?:Error|TypeError|PDOException)|SQLSTATE\[/.test(html)};});
   const state=await p.eval(()=>({
    components:[...new Set([...document.querySelectorAll('main [class]')].flatMap(e=>[...e.classList].filter(c=>c.startsWith('ops-'))))],
    title:document.title,width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
    adopted:document.body.classList.contains('ux-operations')&&!!document.querySelector('main .ops-page'),
    text:document.querySelector('main')?.innerText||document.body.innerText,
    forms:[...document.forms].map(f=>({action:f.getAttribute('action'),method:f.method,fields:[...f.elements].map(e=>[e.name,e.type])})),
    links:[...document.querySelectorAll('main a[href]')].map(a=>[a.textContent.trim(),a.getAttribute('href')]),
    controlStyles:[...document.querySelectorAll('main input,main select,main textarea')]
     .filter(e=>e.getClientRects().length&&!['hidden','checkbox','radio','file'].includes(e.type))
     .map(e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect();return {type:e.type,disabled:e.disabled,border:s.borderTopColor,background:s.backgroundColor,height:r.height,width:r.width};}),
   }));
   const findings=judge(await p.eval(probe),{width,allow:[]}).filter(f=>!APP_SKIP.has(f.id));
   writeFileSync(`${out}/${name}-${width}.json`,JSON.stringify({...state,responseShape,route:row.route,findings},null,2));
   await p.screenshot({path:`${out}/${name}-${width}.jpg`,fullPage:true});
   results.push({route:row.route,width,adopted:state.adopted,scrollWidth:state.scrollWidth,title:state.title,error:responseShape.status>=400||!responseShape.complete||responseShape.serverError||/Fatal error|Uncaught|SQLSTATE/.test(state.text)});
   console.log(row.route,width,state.scrollWidth,state.adopted);
  }
 }
 writeFileSync(`${out}/batch-${batch}.json`,JSON.stringify(results,null,2));
}finally {await b.close();}
console.log('Evidence directory:',out);
// The known Playbill synchronization dependency remains a failure in evidence, not a passed route.
if(results.some(r=>r.route!=='/admin/playbill/media'&&(r.error||!r.adopted||r.scrollWidth>r.width)))process.exitCode=1;
else if(results.some(r=>r.route==='/admin/playbill/media'&&!r.adopted))process.exitCode=2;
