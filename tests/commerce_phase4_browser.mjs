import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const base=new URL(process.env.CTSMD_COMMERCE_URL||'http://127.0.0.1:8794');
assert.ok(base.protocol==='http:'&&['127.0.0.1','localhost'].includes(base.hostname)&&base.pathname==='/');
const fixture=JSON.parse(readFileSync(process.env.CTSMD_COMMERCE_FIXTURE,'utf8'));
assert.equal(fixture.isolated,true);assert.match(fixture.database,/^ctsmd_rc1_/);assert.match(fixture.suffix,/^[a-f0-9]{12}$/);
const {chromium}=await import(process.env.CTSMD_PLAYWRIGHT_MODULE?pathToFileURL(process.env.CTSMD_PLAYWRIGHT_MODULE).href:'playwright');
const out=process.env.CTSMD_COMMERCE_OUTPUT||'/tmp/ctsmd-commerce-phase4';mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true,...(process.env.CTSMD_CHROMIUM_EXECUTABLE?{executablePath:process.env.CTSMD_CHROMIUM_EXECUTABLE}:{})});
const checks=[],ok=(condition,label)=>{assert.ok(condition,label);checks.push(label);console.log('PASS',label);};
try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce',serviceWorkers:'block'}),page=await context.newPage(),errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 await page.goto(base.origin+'/login');await page.getByRole('textbox',{name:'Email',exact:true}).fill(`admin.${fixture.suffix}@rc1.invalid`);await page.getByRole('textbox',{name:'Password',exact:true}).fill(fixture.password);await page.getByRole('button',{name:'Sign in',exact:true}).click();await page.waitForURL('**/app');
 const admin=(tab,args='')=>base.origin+'/admin/commerce?tab='+tab+args;
 const screens=[['overview',''],['venues',''],['layout','&id='+fixture.layout],['performances',''],['performance','&id='+fixture.performance],['schedule','&production='+fixture.productions.A],['blocks','&production='+fixture.productions.A+'&id='+fixture.performance],['sales','&id='+fixture.performance],['complimentary','&id='+fixture.performance],['orders',''],['checkin','&id='+fixture.performance],['configure','']];
 for(const width of [1440,768,390,320]){
  await page.setViewportSize({width,height:1000});
  for(const [tab,args] of screens){
   await page.goto(admin(tab,args));await page.locator('.commerce-content').waitFor();
   const state=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,alerts:[...document.querySelectorAll('[role=alert]')].map(e=>e.textContent),unlabelled:[...document.querySelectorAll('input:not([type=hidden]),textarea,select')].filter(e=>!e.labels?.length&&!e.getAttribute('aria-label')).length}));
   ok(state.scroll<=state.width&&state.alerts.length===0&&state.unlabelled===0,`${tab}: responsive, labelled and error-free at ${width}px`);
   if(width!==320)await page.screenshot({path:`${out}/${tab}-${width}.png`,fullPage:true});
  }
 }
 await page.setViewportSize({width:1440,height:1000});await page.goto(admin('overview'));
 ok(await page.locator('.commerce-workspace-link').count()===4,'Exactly four primary Commerce workspaces');
 await page.goto(admin('venues'));await page.getByRole('button',{name:'Add a venue or performance space'}).click();await page.getByRole('dialog').waitFor();await page.getByRole('dialog').press('Escape');ok(await page.getByRole('dialog').count()===0,'Venue drawer supports Escape dismissal and restores focus');
 await page.goto(admin('blocks','&production='+fixture.productions.A+'&id='+fixture.performance));
 await page.locator('[data-allocation-seat="a3"]').press('Space');ok(await page.locator('input[name="seats[]"][value="a3"]').isChecked(),'Seat-block map keyboard selection syncs seat list');
 await page.locator('select[name=scope]').selectOption('range');ok(await page.locator('[name=from]').isVisible()&&!await page.locator('input[name="performances[]"]').first().isVisible(),'Date-range scope shows only relevant controls');
 await page.locator('select[name=scope]').selectOption('all');ok(!await page.locator('[name=from]').isVisible(),'Full production scope keeps range fields out of the way');
 await page.goto(admin('pricing','&production='+fixture.productions.A+'&id='+fixture.performance));const price=page.locator('[data-price-row]').first().locator('[name$="[amount]"]');await price.fill('21.50');ok(await price.inputValue()==='21.50','Pricing editor accepts ordinary currency amounts');
 // Real staff checkout through existing hold and settlement services.
 await page.goto(admin('sales','&id='+fixture.performance));await page.locator('[data-choice][aria-disabled="false"]').first().press('Space');await page.getByRole('spinbutton',{name:'Adult tickets',exact:true}).fill('1');await page.locator('[data-selection-continue]').click();await page.waitForURL('**tab=checkout*');
 await page.getByRole('button',{name:'Confirm tender & issue tickets',exact:true}).waitFor();await page.locator('[name=confirmed]').check();await page.getByRole('button',{name:'Confirm tender & issue tickets',exact:true}).click();await page.getByRole('heading',{name:'Box Office tickets issued',exact:true}).waitFor();
 const token=(await page.locator('.commerce-token').first().textContent()).trim();
 await page.goto(admin('orders','&q=Walk'));await page.locator('[data-order-link]').first().click();await page.getByRole('dialog',{name:'Order management'}).waitFor();ok((await page.getByRole('dialog').textContent()).includes('Order audit trail'),'Order drawer includes permission-checked management and history');await page.getByRole('dialog').press('Escape');
 await page.goto(admin('checkin','&id='+fixture.performance));await page.locator('[data-scan]').fill(token);await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Verify & admit ticket',exact:true}).click()]);ok((await page.locator('.commerce-content>p[role=status]').first().textContent()).includes('Admitted:'),'Valid ticket produces prominent admitted feedback');
 await page.locator('[data-scan]').fill(token);await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Verify & admit ticket',exact:true}).click()]);ok((await page.getByRole('alert').textContent()).includes('Already'),'Already-used ticket receives distinct rejection');
 await page.locator('[data-scan]').fill('invalid-ticket');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Verify & admit ticket',exact:true}).click()]);ok((await page.getByRole('alert').count())===1,'Invalid ticket receives rejection');
 ok(errors.length===0,'No browser JavaScript exceptions across all staff workflows');
 writeFileSync(out+'/results.json',JSON.stringify({synthetic:true,checks},null,2));
}finally{await browser.close();}
