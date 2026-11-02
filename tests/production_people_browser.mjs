import {readFileSync, mkdirSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import {join} from 'node:path';
const base = new URL(process.env.CTSMD_UX_BASE_URL || 'http://127.0.0.1:8096');
if (base.protocol !== 'http:' || !['127.0.0.1','localhost'].includes(base.hostname) || !base.port || base.pathname !== '/') throw Error('Explicit loopback HTTP port required');
const ids = JSON.parse(readFileSync(process.env.CTSMD_UX_FIXTURE));
if (ids.isolated !== true) throw Error('Isolated fixture required');
const {Browser} = await import(pathToFileURL(join(process.env.CTSMD_BROWSER_LIB, 'cdp.mjs')).href);
const out = process.env.CTSMD_UX_OUTPUT || '/tmp/ctsmd-team-interactions'; mkdirSync(out,{recursive:true});
const browser = await Browser.launch(), checks = [], owned = [];
const ok = (name, value) => { checks.push({name,pass:!!value}); if(!value) throw Error(name); console.log('PASS',name); };
const page = await browser.newPage();
async function go(path) { await page.goto(base.origin + path,{settleMs:100}); }
async function submit(values) {
  return page.eval(`(async()=>{const form=document.querySelector('#team-editor form');const data=new FormData(form);for(const [key,value] of Object.entries(${JSON.stringify(values)}))data.set(key,value);const r=await fetch(location.pathname,{method:'POST',body:data});return {status:r.status,text:await r.text()};})()`);
}
async function assignment(title) {
  await go('/production/people?person='+ids.guardian);
  return page.eval(`(()=>{const s=[...document.querySelectorAll('#person-${ids.guardian} .people-assignment')].find(s=>s.querySelector('h3')?.textContent===${JSON.stringify(title)});return s?{id:s.querySelector('[name=assignment_id]').value,revision:s.querySelector('[name=revision]').value}:null;})()`);
}
try {
  await go('/dev/identity');
  await page.eval(`(async()=>{const form=[...document.forms].find(f=>f.querySelector('[name=user_id]')?.value==='${ids.admin}');if(!/(@example.test|@rc1.invalid)$/.test(form?.querySelector('p')?.textContent.trim()||''))throw Error('Synthetic identity required');await fetch('/dev/identity',{method:'POST',body:new FormData(form)});})()`);
  await go('/staff');
  await page.eval(`(async()=>{const form=document.querySelector('form.unified-production-switcher');const data=new FormData(form);data.set('production_id','${ids.production}');await fetch(form.action,{method:'POST',body:data});})()`);
  for(const title of ['Browser Creative Director','Browser Lighting Designer']) {
    await go('/production/people?add_team=1');
    ok('Native modal has focus inside editor', await page.eval(()=>document.getElementById('team-editor').matches(':modal') && document.getElementById('team-editor').contains(document.activeElement)));
    const r=await submit({user_id:String(ids.guardian),function_type:title.includes('Lighting')?'crew':'creative',function_code:'custom',custom_title:title,staff_notes:'PRIVATE browser rehearsal note',playbill_eligible:'1',action:'save_team'});
    ok('Real HTTP team assignment saved',r.status===200&&r.text.includes('Production Team assignment saved.'));
    const a=await assignment(title);ok('Saved assignment available in person drawer',!!a);owned.push(a);
  }
  await go('/production/people');
  ok('All People deduplicates multi-role guardian',await page.eval(`document.querySelectorAll('tr[data-person-id="${ids.guardian}"]').length===1`));
  ok('Old arbitrary audience editor is absent',await page.eval(()=>!document.querySelector('[name=audience_type]')&&!document.querySelector('[name=participation_role]')));
  await go('/production/people?view=team');
  ok('Team filter shows person once',await page.eval(`document.querySelectorAll('tr[data-person-id="${ids.guardian}"]').length===1`));
  const first=await assignment('Browser Creative Director');
  await go('/production/people?edit_team='+first.id);
  ok('Edit drawer retains selected person and notes',await page.eval(`document.querySelector('#team-editor [name=user_id]').value==='${ids.guardian}'&&document.querySelector('#team-editor [name=staff_notes]').value==='PRIVATE browser rehearsal note'`));
  await page.eval(()=>{const c=document.getElementById('team-category');c.value='operations';c.dispatchEvent(new Event('change'));});
  ok('Category limits available function options',await page.eval(()=>[...document.getElementById('team-function').options].filter(o=>!o.disabled).every(o=>o.dataset.category==='operations')));
  const edited=await submit({user_id:String(ids.guardian),function_type:'creative',function_code:'custom',custom_title:'Browser Creative Director',staff_notes:'PRIVATE browser rehearsal note updated',action:'save_team'});
  ok('Existing assignment edit succeeds',edited.text.includes('Production Team assignment saved.'));
  for(const width of [1440,768,390]) {
    await page.setViewport({width,height:900,mobile:width===390});await go('/production/people');
    ok('Roster fits viewport '+width,await page.eval(()=>document.documentElement.scrollWidth<=innerWidth));
    await page.screenshot({path:`${out}/people-${width}.jpg`,fullPage:true});
    await go('/production/people?person='+ids.guardian);
    ok('Person drawer is native modal '+width,await page.eval(`document.getElementById('person-${ids.guardian}').matches(':modal')`));
    await page.screenshot({path:`${out}/responsibilities-${width}.jpg`,fullPage:false});
    await go('/production/people?edit_team='+first.id);
    ok('Editor fits viewport '+width,await page.eval(()=>{const d=document.getElementById('team-editor');return d.scrollWidth<=d.clientWidth&&document.documentElement.scrollWidth<=innerWidth;}));
    await page.screenshot({path:`${out}/team-editor-${width}.jpg`,fullPage:false});
    await page.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await page.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await new Promise(resolve=>setTimeout(resolve,150));
    ok('Escape exits editor '+width,await page.eval(()=>!document.getElementById('team-editor').open));
  }
} finally {
  for(const a of owned) {
    await go('/production/people?person='+ids.guardian);
    await page.eval(`(async()=>{const form=[...document.querySelectorAll('#person-${ids.guardian} form')].find(f=>f.querySelector('[name=assignment_id]')?.value==='${a.id}');if(!form)return;const data=new FormData(form);data.set('action','remove_team');await fetch('/production/people',{method:'POST',body:data});})()`);
  }
  writeFileSync(out+'/checks.json',JSON.stringify(checks,null,2));await browser.close();
}
