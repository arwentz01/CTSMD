import {pathToFileURL} from 'node:url';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
const lib=process.env.CTSMD_BROWSER_LIB;
if(!lib)throw Error('Set CTSMD_BROWSER_LIB to the installed Taste scripts/lib directory.');
const {Browser}=await import(pathToFileURL(join(lib,'cdp.mjs')).href);
import {readFileSync,writeFileSync,mkdirSync,mkdtempSync} from 'node:fs';
const url=new URL(process.env.CTSMD_UX_BASE_URL||'http://127.0.0.1:8096');
if(url.protocol!=='http:'||!['127.0.0.1','localhost'].includes(url.hostname)||!url.port||url.pathname!=='/'||url.username||url.password)throw Error('Loopback HTTP port required; no live URL.');
if(!process.env.CTSMD_UX_FIXTURE)throw Error('Set CTSMD_UX_FIXTURE to isolated synthetic IDs.');
const i=JSON.parse(readFileSync(process.env.CTSMD_UX_FIXTURE));
if(i.isolated!==true)throw Error('Fixture must declare isolated:true.');
const base=url.origin,out=process.env.CTSMD_UX_OUTPUT||mkdtempSync(join(tmpdir(),'ctsmd-operations-interactions-')),b=await Browser.launch(),checks=[];
const ok=(name,value)=>{checks.push({name,value:!!value});console.log(value?'PASS':'FAIL',name);};
try { const p=await b.newPage();await p.send('Network.setCacheDisabled',{cacheDisabled:true});await p.send('Network.setBypassServiceWorker',{bypass:true});
const login=async id=>{await p.goto(base+'/dev/identity',{settleMs:50});await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${id}');if(!f?.querySelector('p')?.textContent.trim().endsWith('@example.test')&&!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic fixture identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);};
await login(i.admin);await p.goto(base+'/staff',{settleMs:50});
await p.eval(`(async()=>{const f=document.querySelector('form.unified-production-switcher');let d=new FormData(f);d.set('production_id','${i.production}');await fetch(f.action,{method:'POST',body:d});})()`);
mkdirSync(out,{recursive:true});
for(const width of [1440,768,390]) {
 await p.setViewport({width,height:900,mobile:width===390});
 for(const [name,path,trigger,dialog] of [
  ['Casting roles','/production/casting','[data-open-cast-drawer]','#cast-roles-drawer'],
  ['Task leads','/production/tasks','[data-open-leads]','[data-leads-drawer]'],
  ['Attendance details',`/attendance/take?id=${i.event}`,'.att-more-toggle','.att-more-panel:not([hidden])']
 ]) {
  await p.goto(base+path,{settleMs:100});
  const opened=await p.eval(`(()=>{const t=document.querySelector('${trigger}');if(!t)return false;t.focus();t.click();return !!document.querySelector('${dialog}')&&!document.querySelector('${dialog}').hidden;})()`);
  ok(name+' opens '+width,opened);if(!opened)continue;
  ok(name+' focus enters '+width,await p.eval(`document.querySelector('${dialog}').contains(document.activeElement)`));
  await p.eval(`(()=>{const d=document.querySelector('${dialog}'),a=[...d.querySelectorAll('button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),a[href]')].filter(e=>e.getClientRects().length);window.__uxFirst=a[0];a.at(-1).focus();})()`);
  await p.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
  await p.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
  ok(name+' Tab wraps within dialog '+width,await p.eval(()=>document.activeElement===window.__uxFirst));
  ok(name+' viewport fits '+width,await p.eval(`(()=>{const r=document.querySelector('${dialog}').getBoundingClientRect();return r.width<=innerWidth&&r.left>=-1&&r.right<=innerWidth+1})()`));
  await p.screenshot({path:`${out}/${name.replaceAll(' ','-')}-${width}.jpg`,fullPage:false});
  await p.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  ok(name+' Escape closes and returns focus '+width,await p.eval(`(()=>{const d=document.querySelector('${dialog}');return (!d||d.hidden)&&document.activeElement.matches('${trigger}');})()`));
 }
 // Browser validation + submit event only. Prevent navigation/POST before any handler.
 await p.goto(base+'/production/schedule/new',{settleMs:50});
 ok('Schedule dates fit '+width,await p.eval(()=>[...document.querySelectorAll('input[type=date],input[type=time],input[type=datetime-local]')].every(e=>{const r=e.getBoundingClientRect();return r.width>100&&r.right<=innerWidth&&r.left>=0})));
 await p.goto(base+'/production/groups/view?id='+i.group,{settleMs:50});
 ok('Group form reaches submit event without request '+width,await p.eval(()=>{const f=document.querySelector('.pg-member-form');let reached=false;f.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();reached=true;},{capture:true,once:true});f.requestSubmit();return reached&&!!f.querySelector('[name=csrf_token]');}));
}
for(const who of ['guardian','student','volunteer']) {
 await login(i[who]);
 for(const path of ['/admin/accounts','/admin/registrations','/admin/teams','/safeguarding/cases','/safeguarding/conversations','/admin/email','/admin/channels','/production/tasks']) {
  const status=await p.eval(`(async()=>{const r=await fetch('${path}');return r.status;})()`);
  ok(who+' denied '+path,[403,404].includes(status));
 }
}
await login(i.admin);await p.goto(base+'/production/groups',{settleMs:50});
await p.eval(`(async()=>{const f=document.querySelector('form.unified-production-switcher'),d=new FormData(f);d.set('production_id','${i.archive}');await fetch(f.action,{method:'POST',body:d});})()`);
await p.goto(base+'/archive/production?id='+i.archive,{settleMs:50});
ok('Archive record has no domain-changing form',await p.eval(()=>!document.querySelector('main form[method=post]')));
}finally {writeFileSync(join(out,'results.json'),JSON.stringify(checks,null,2));await b.close();}
if(checks.some(c=>!c.value))process.exitCode=1;
