import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
const url=new URL(process.env.CTSMD_UX_BASE_URL||'http://127.0.0.1:8096');
if(url.protocol!=='http:'||!['127.0.0.1','localhost'].includes(url.hostname)||!url.port||url.username||url.password||url.pathname!=='/')throw Error('Explicit loopback URL required.');
const fixture=JSON.parse(readFileSync(process.env.CTSMD_MEMBER_FIXTURE||join(tmpdir(),'ctsmd-my-theatre-fixture.json')));
if(fixture.isolated!==true)throw Error('Isolated synthetic fixture required.');
const {Browser}=await import(pathToFileURL(join(process.env.CTSMD_BROWSER_LIB,'cdp.mjs')).href);
const out=process.env.CTSMD_UX_OUTPUT||'/tmp/ctsmd-my-theatre-browser';mkdirSync(out,{recursive:true});
const browser=await Browser.launch(),results=[];
const routes=(process.env.CTSMD_MEMBER_ROUTES||'/my-theatre').split(',');
const personas=(process.env.CTSMD_MEMBER_PERSONAS||'GuardianTwo,AdultParent,StudentSingle,Adult,Volunteer,StaffPerformer,FormerGuardian,Member,HistoryAdult').split(',');
const widths=(process.env.CTSMD_MEMBER_WIDTHS||'1440,768,390,320').split(',').map(Number);
try{
 const p=await browser.newPage();await p.send('Network.setCacheDisabled',{cacheDisabled:true});await p.send('Network.setBypassServiceWorker',{bypass:true});
 for(const persona of personas){const id=fixture.users[persona];if(!id)throw Error('Unknown fixture persona');
  await p.goto(url.origin+'/dev/identity',{settleMs:30});await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${id}');if(!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);
  for(const route of routes){
   for(const width of widths){await p.setViewport({width,height:950,mobile:width<=390});await p.goto(url.origin+route,{settleMs:70});await p.eval(async()=>{const button=document.querySelector('.mobile-engagement-later');if(button){button.click();await new Promise(resolve=>setTimeout(resolve,300));}});
    const state=await p.eval(async()=>{const r=await fetch(location.href,{cache:'no-store'}),html=await r.text();return {status:r.status,complete:/<\/html>\s*$/i.test(html),error:/Fatal error|SQLSTATE|Uncaught |Warning:|Deprecated:/.test(html),adopted:document.body.classList.contains('ux-member'),width:innerWidth,scrollWidth:document.documentElement.scrollWidth,title:document.title,productionIds:[...document.querySelectorAll('[data-production-id]')].map(e=>e.dataset.productionId),text:document.querySelector('main')?.innerText||''};});
    const name=persona+'-'+route.split('?')[0].slice(1).replaceAll('/','-')+'-'+width;
    await p.screenshot({path:out+'/'+name+'.jpg',fullPage:true});writeFileSync(out+'/'+name+'.json',JSON.stringify(state,null,2));results.push({persona,route,width,...state,text:undefined});console.log(persona,route,width,state.status,state.scrollWidth,state.adopted);
   }
  }
 }
 writeFileSync(out+'/results.json',JSON.stringify(results,null,2));
 if(results.some(r=>r.status!==200||!r.complete||r.error||!r.adopted||r.scrollWidth>r.width))process.exitCode=1;
}finally{await browser.close();}
