import {readFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.CTSMD_PLAYWRIGHT_MODULE?pathToFileURL(process.env.CTSMD_PLAYWRIGHT_MODULE).href:'playwright');
const browser=await chromium.launch({headless:true,...(process.env.CTSMD_CHROMIUM_EXECUTABLE?{executablePath:process.env.CTSMD_CHROMIUM_EXECUTABLE}:{})});let count=0;
const ok=(value,label)=>{assert.ok(value,label);console.log('PASS',label);count++;};
try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 const base='http://127.0.0.1:8799/admin/commerce',overview=base+'?tab=overview&q=retained-filter';
 await page.route('**/*',route=>{
  const url=new URL(route.request().url()),tab=url.searchParams.get('tab');let content='';
  if(tab==='overview')content='<div style="height:800px">Overview</div><a data-commerce-drawer-link href="?tab=performance&id=1">Manage</a>';
  else if(tab==='performance')content='<div data-production-drawer><h2>Manage performance</h2><div data-performance-context><h3>Test production</h3><p>Friday · Main stage</p></div><a data-commerce-drawer-link href="?tab=pricing&id=1">Pricing</a><a href="?tab=orders&performance=1">Orders</a><form method="post"><label>Fee<input name="fee_amount" value="1.00"></label><button>Save</button></form></div>';
  else if(tab==='pricing')content='<div data-production-drawer><h2>Pricing</h2><label>Amount<input value="20.00"></label></div>';
  else content='<h2>Orders</h2><a href="?tab=orders&reference=one">Order one</a>';
  route.fulfill({contentType:'text/html',body:'<!doctype html><html><body><section class="commerce-content">'+content+'</section></body></html>'});
 });
 await page.goto(overview);await page.addScriptTag({content:readFileSync(new URL('../assets/js/commerce-productions.js',import.meta.url),'utf8')});
 await page.getByText('Manage',{exact:true}).click();const dialog=page.getByRole('dialog');await dialog.getByRole('heading',{name:'Manage performance',exact:true}).waitFor();
 ok(await page.getByRole('dialog').count()===1,'One accessible drawer with title, Back and Close');
 await dialog.getByText('Pricing',{exact:true}).click();await dialog.getByRole('heading',{name:'Pricing',exact:true}).waitFor();
 ok(await dialog.getByText('Test production',{exact:true}).isVisible(),'Pricing retains performance context');
 await dialog.getByRole('button',{name:'Back',exact:true}).click();await dialog.getByRole('heading',{name:'Manage performance',exact:true}).waitFor();
 await dialog.getByText('Orders',{exact:true}).click();await dialog.getByRole('heading',{name:'Orders',exact:true}).waitFor();ok(await page.getByRole('dialog').count()===1,'Orders reuses the same drawer');
 await page.goBack();await dialog.getByRole('heading',{name:'Manage performance',exact:true}).waitFor();ok(true,'Browser Back returns to parent performance');
 await dialog.locator('[name=fee_amount]').fill('2.00');page.once('dialog',d=>d.dismiss());await dialog.getByRole('button',{name:'Close',exact:true}).click();ok(await dialog.isVisible(),'Close protects unsaved changes');
 page.once('dialog',d=>d.dismiss());await page.goBack();await page.waitForURL('**tab=performance&id=1');ok(await dialog.isVisible(),'Cancelled browser Back preserves drawer and URL');
 page.once('dialog',d=>d.accept());await dialog.getByRole('button',{name:'Close',exact:true}).click();await page.waitForURL(overview);await dialog.waitFor({state:'detached'});
 ok(page.url()===overview,'Close restores original Overview filter');ok(await page.evaluate(()=>scrollY)>0,'Close restores workspace scroll');await page.goForward();await page.getByRole('dialog').getByRole('heading',{name:'Manage performance',exact:true}).waitFor();ok(await page.getByRole('dialog').count()===1,'Browser Forward restores the drawer');await page.getByRole('dialog').press('Escape');await page.waitForURL(overview);ok(await page.getByRole('dialog').count()===0,'Escape closes to the workspace');ok(errors.length===0,'No drawer JavaScript errors');
 console.log(`PASS ${count} focused drawer navigation checks`);
}finally{await browser.close();}
