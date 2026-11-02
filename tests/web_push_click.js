/* Run with: node tests/web_push_click.js (requires Node 18+) */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const worker=fs.readFileSync(require('node:path').join(__dirname,'../service-worker.js'),'utf8');
async function click(input, windows=[]){
  const handlers={};const calls=[];let waited;
  const self={location:{origin:'https://ctsmd.me'},addEventListener:(name,cb)=>{handlers[name]=cb}};
  const clients={matchAll:async()=>windows,openWindow:async url=>{calls.push(['open',url]);return {url}}};
  vm.runInNewContext(worker,{self,clients,URL,Boolean,Number});
  assert.ok(handlers.notificationclick);
  handlers.notificationclick({notification:{data:{url:input},close:()=>calls.push(['close'])},waitUntil:p=>{waited=p}});
  await waited;
  return calls;
}
(async()=>{
  let result=await click('/messages/12');assert.deepEqual(result,[['close'],['open','https://ctsmd.me/messages/12']]);
  result=await click('https://evil.example/steal');assert.deepEqual(result,[['close'],['open','https://ctsmd.me/notifications']]);
  result=await click('javascript:alert(1)');assert.deepEqual(result,[['close'],['open','https://ctsmd.me/notifications']]);
  result=await click('https://ctsmd.me/channels/view?id=6');assert.deepEqual(result,[['close'],['open','https://ctsmd.me/channels/view?id=6']]);
  const calls=[];
  const same={url:'https://ctsmd.me/app',navigate:async url=>{calls.push(['navigate',url]);return {focus:async()=>calls.push(['focus'])}},focus:async()=>calls.push(['focus-original'])};
  result=await click('/notifications',[same]);assert.deepEqual(calls,[['navigate','https://ctsmd.me/notifications'],['focus']]);assert.deepEqual(result,[['close']]);
  console.log('5 Web Push click routing checks passed');
})().catch(e=>{console.error(e);process.exitCode=1});
