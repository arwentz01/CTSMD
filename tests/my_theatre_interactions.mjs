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
const checks=[];const ok=(v,label)=>{if(!v)throw Error(label);checks.push(label);console.log('PASS',label)};
try{
 const p=await browser.newPage({width:390,height:950,mobile:true,reducedMotion:true});
 const id=fixture.users.AdultParent;
 await p.goto(url.origin+'/dev/identity',{settleMs:30});await p.eval(`(async()=>{const f=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${id}');if(!f?.querySelector('p')?.textContent.trim().endsWith('@rc1.invalid'))throw Error('Synthetic identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(f)});})()`);
 await p.goto(url.origin+'/student-profile',{settleMs:100});
 await p.eval(()=>{document.querySelector('.mobile-engagement-later')?.click();document.querySelector('.member-editor>summary').focus()});
 await p.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});await p.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
 ok(await p.eval(()=>document.querySelector('.member-editor').open),'Profile disclosure opens by keyboard');
 await p.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await p.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
 ok(await p.eval(()=>document.activeElement.name==='date_of_birth'),'Keyboard enters original profile controls');
 await p.eval(()=>document.querySelector('.member-editor>summary').focus());await p.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});await p.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
 ok(await p.eval(()=>!document.querySelector('.member-editor').open&&document.activeElement.tagName==='SUMMARY'),'Disclosure closes with focus retained');
 const results=[];
 for(const route of ['/my-theatre','/forms','/calendar?personal=1&view=agenda','/student-profile','/resources','/volunteer-shifts','/theatre-history']){
  await p.goto(url.origin+route,{settleMs:100});await p.eval(()=>{document.querySelector('.mobile-engagement-later')?.click();document.documentElement.style.fontSize='200%'});
  const state=await p.eval(()=>({route:location.pathname,width:innerWidth,scrollWidth:document.documentElement.scrollWidth,reduced:matchMedia('(prefers-reduced-motion: reduce)').matches,focus:getComputedStyle(document.querySelector('main a')||document.body).color}));results.push(state);ok(state.scrollWidth<=state.width,'200% root text reflow '+route);ok(state.reduced,'Reduced-motion media active '+route);
  await p.screenshot({path:out+'/large-text-'+route.slice(1).replace(/[^a-z0-9-]/gi,'-')+'.jpg',fullPage:true});
 }
 writeFileSync(out+'/interactions.json',JSON.stringify({checks,results},null,2));
}finally{await browser.close();}
