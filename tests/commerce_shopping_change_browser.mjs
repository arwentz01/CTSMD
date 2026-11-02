import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.CTSMD_PLAYWRIGHT_MODULE?pathToFileURL(process.env.CTSMD_PLAYWRIGHT_MODULE).href:'playwright');
const browser=await chromium.launch({headless:true,...(process.env.CTSMD_CHROMIUM_EXECUTABLE?{executablePath:process.env.CTSMD_CHROMIUM_EXECUTABLE}:{})});
const [base,suffix,password]=process.argv.slice(2);let count=0;
const ok=(v,label)=>{assert.ok(v,label);console.log('PASS '+label);count++;};
try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto(base+'/login');await page.getByRole('textbox',{name:'Email',exact:true}).fill(`admin.${suffix}@rc1.invalid`);await page.getByRole('textbox',{name:'Password',exact:true}).fill(password);await page.getByRole('button',{name:'Sign in',exact:true}).click();await page.waitForURL('**/app');
 for(const width of [390,768,1280]){
  await page.setViewportSize({width,height:900});await page.goto(base+'/tickets/cart');await page.getByRole('link',{name:'Change seat',exact:true}).first().click();
  await page.getByRole('button',{name:'Change Seat',exact:true}).waitFor();ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Seat change fits ${width}px`);
  const choices=page.locator('[data-select-map] g[role="button"][aria-disabled="false"]');await choices.first().focus();await choices.first().press('Space');ok(await choices.first().getAttribute('aria-pressed')==='true',`Keyboard selects a seat at ${width}px`);
  await choices.nth(1).focus();await choices.nth(1).press('Space');ok(await page.locator('[data-seat-check]:checked').count()===1,`Only one replacement seat can be selected at ${width}px`);
  const category=page.locator('[data-ticket-quantity][data-category="Adult"]:visible');await category.fill('1');ok(await page.getByRole('button',{name:'Change Seat',exact:true}).isEnabled(),`A valid seat change can be submitted at ${width}px`);
 }
 ok(errors.length===0,'No seat-change JavaScript errors');console.log(`PASS ${count} focused seat-change browser checks`);
}finally{await browser.close();}
