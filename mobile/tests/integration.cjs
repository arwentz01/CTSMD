// Pure native integration contracts, not a substitute for simulator/device tests.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const ts=require('typescript');
const path=require('node:path');
function load(file,session={},fetcher,dependencies={}){
 const exports={};
 const source=ts.transpileModule(fs.readFileSync(path.join(__dirname,'..',file),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText;
 vm.runInNewContext(source,{exports,require:id=>{if(id==='../auth/session')return session;if(dependencies[id])return dependencies[id];throw new Error('Unexpected dependency '+id)},process:{env:{}},URLSearchParams,AbortController,setTimeout,clearTimeout,fetch:fetcher,FormData});
 return exports;
}
test('authorization role labels do not infer authority and web handoffs use configured HTTPS base',()=>{
 const {authorizationRoleLabels:label}=load('src/participants/identity.ts');
 assert.equal(label(['member','adult_performer']),'Member, Adult Performer');assert.equal(label(['student']),'Student');assert.equal(label(),'No roles assigned');
 const {participantWebsite}=load('src/api/client.ts');
 assert.equal(participantWebsite('/student-profile'),'https://ctsmd.me/student-profile');
 assert.equal(participantWebsite('/theatre-history'),'https://ctsmd.me/theatre-history');
 assert.equal(participantWebsite('/admin/accounts'),'https://ctsmd.me/admin/accounts');
 assert.equal(participantWebsite('/admin/productions'),'https://ctsmd.me/admin/productions');
});
test('notification routes are allowlisted and reject arbitrary destinations',()=>{
 const {notificationDestination:route}=load('src/notifications/destination.ts');
 for(const [input,expected] of [
  ['/messages/thread?id=12','/messages/12'],['/channels/view?id=3','/community/3'],['/channels/view?id=3#community-post-17','/community/3?post_id=17'],['/forms/view?id=7','/forms/7'],['/calendar/event?id=42','/events/42'],['/calendar','/calendar'],['/notifications?offer=2','/action-center'],
  ['https://evil.example/messages/thread?id=1','/notifications'],['//evil.example','/notifications'],['/messages/thread?id=-1','/notifications'],['/messages/thread?id=1/../../settings','/notifications'],['/admin','/notifications'],['/messages/thread?id=0','/notifications'],['/messages/thread?id=99999999999','/notifications']
 ])assert.equal(route({action_path:input}),expected);
 assert.equal(route({action_path:{}}),'/notifications');
});
test('401 invalidates the current stored session even for non-JSON errors',async()=>{
 let token='old';const cleared=[];
 const client=load('src/api/client.ts',{readToken:async()=>token,clearToken:async expected=>{cleared.push(expected);token=null}},async()=>({status:401,ok:false,json:async()=>{throw new Error('HTML')}}));
 await assert.rejects(client.me(),e=>e.status===401);
 assert.deepEqual(cleared,['old']);assert.equal(token,null);
});
test('a stale response cannot clear or return data into a replacement session',async()=>{
 let token='old';let clears=0;
 const client=load('src/api/client.ts',{readToken:async()=>token,clearToken:async()=>{clears++}},async()=>{token='new';return {status:200,ok:true,json:async()=>({user:{id:7}})}});
 await assert.rejects(client.me(),e=>e.status===401);
 assert.equal(token,'new');assert.equal(clears,0);
});
test('logout sends the captured credential and device identity without clearing a new session',async()=>{
 let request;let clears=0;
 const client=load('src/api/client.ts',{readToken:async()=>'new',clearToken:async()=>{clears++}},async(url,options)=>{request={url,options};return {status:200,ok:true,json:async()=>({ok:true})}});
 await client.logoutSession('old','fixture-device');
 assert.equal(request.options.headers.Authorization,'Bearer old');
 assert.equal(JSON.parse(request.options.body).push_identity,'fixture-device');assert.equal(clears,0);
});
test('permission denial removes only the stored device and never requests a native token',async()=>{
 let identity='old-device',removed,tokenCalls=0;
 const device=load('src/notifications/device.ts',{},null,{
  'expo-secure-store':{getItemAsync:async()=>identity,deleteItemAsync:async()=>{identity=null}},
  'expo-notifications':{getPermissionsAsync:async()=>({granted:false}),requestPermissionsAsync:async()=>({granted:false}),getDevicePushTokenAsync:async()=>{tokenCalls++}},
  './runtime':{supportsNativePush:()=>true},
  'react-native':{Platform:{OS:'ios'}},
  '../api/client':{unregisterPush:async value=>{removed=value}}
 });
 assert.match(await device.enableDeviceNotifications(),/Permission denied/);
 assert.equal(removed,'old-device');assert.equal(identity,null);assert.equal(tokenCalls,0);
});
test('native registration rotates the prior identity but explicitly reports delivery unverified',async()=>{
 let identity='old-device',payload,previous;
 const device=load('src/notifications/device.ts',{},null,{
  'expo-secure-store':{getItemAsync:async()=>identity,setItemAsync:async(key,value)=>{identity=value}},
  'expo-notifications':{},'./runtime':{supportsNativePush:()=>true},'react-native':{Platform:{OS:'android'}},
  '../api/client':{registerPush:async(value,prior)=>{payload=value;previous=prior;return {delivery_type:'native_pending'}}}
 });
 assert.match(await device.storeDeviceToken({type:'android',data:'new-device'}),/not configured or verified/);
 assert.equal(payload.fcmToken,'new-device');assert.equal(previous,'old-device');assert.equal(identity,'new-device');
});

test('native website handoffs keep the CTSMD pilot storefront out of live ticket sales',()=>{
 const source=fs.readFileSync(path.join(__dirname,'../app/(tabs)/more.tsx'),'utf8');
 assert.match(source,/https:\/\/ctsmd\.ludus\.com\/index\.php/);
 assert.match(source,/Ticket purchases currently use Ludus/);
 assert.doesNotMatch(source,/https:\/\/ctsmd\.me\/tickets/);
 const {websiteUrl,webRoutes,openWebsite}=load('src/navigation/website.ts',{},null,{'react-native':{Linking:{canOpenURL:async()=>true,openURL:async()=>undefined}}});
 assert.equal(websiteUrl('productionWorkspace'),'https://ctsmd.me/production');
 assert.equal(websiteUrl('familyManagement'),'https://ctsmd.me/family/manage');
 assert.equal(websiteUrl('commerce'),'https://ctsmd.me/admin/commerce');
 assert.ok(!Object.values(webRoutes).includes('/tickets'),'Pilot storefront is deliberately not advertised as the current ticket vendor');
 assert.equal(typeof openWebsite,'function');
});
test('mobile navigation exposes member, staff and authorized admin workflows without a duplicate checkout',()=>{
 const more=fs.readFileSync(path.join(__dirname,'../app/(tabs)/more.tsx'),'utf8');
 for(const destination of ['My family','Forms','Volunteer','Video Library','Production workspace','Production people','Production tasks','Administration','Commerce & Box Office','Settings'])assert.ok(more.includes(destination),'Missing mobile navigation: '+destination);
 assert.match(more,/staff&&<Section title="Production tools"/);
 assert.match(more,/admin&&<Section title="Administration"/);
 const calendar=fs.readFileSync(path.join(__dirname,'../app/calendar.tsx'),'utf8');
 assert.match(calendar,/All visible/);
 assert.match(calendar,/dateHeader\(key\)/);
});

test('caregiver-only mobile route uses minimum logistics API and hides inaccessible general navigation',()=>{
 const api=fs.readFileSync(path.join(__dirname,'../../src/MobileApi.php'),'utf8');
 assert.match(api,/caregiver_only.*caregiverOnly/);
 assert.match(api,/\/'?api\/mobile\/caregiver-schedule/);
 const tabs=fs.readFileSync(path.join(__dirname,'../app/(tabs)/_layout.tsx'),'utf8');
 assert.match(tabs,/href:caregiver\?null:undefined/);
 const home=fs.readFileSync(path.join(__dirname,'../app/(tabs)/index.tsx'),'utf8');
 assert.match(home,/if\(user\?\.caregiver_only\)/);
 assert.match(home,/\/caregiver-schedule/);
 const more=fs.readFileSync(path.join(__dirname,'../app/(tabs)/more.tsx'),'utf8');
 assert.match(more,/caregiver\?caregiverItems:member/);
 const family=fs.readFileSync(path.join(__dirname,'../app/family.tsx'),'utf8');
 assert.match(family,/user\?\.caregiver_only/);
 const schedule=fs.readFileSync(path.join(__dirname,'../app/caregiver-schedule.tsx'),'utf8');
 assert.match(schedule,/caregiverSchedule\(\)/);
 assert.doesNotMatch(schedule,/\b(student_id|student_last_name|guardian_user_id|casting_offer)\b/);
});
test('authorized production detail provides website-only staff operations without new native mutations',()=>{
 const detail=fs.readFileSync(path.join(__dirname,'../app/theatre/[id].tsx'),'utf8');
 assert.match(detail,/!production\.is_public_preview&&staff/);
 for(const webRoute of ['productionTasks','productionPeople','productionDay','productionReadiness'])assert.ok(detail.includes("'"+webRoute+"'"),'Missing staff action '+webRoute);
 assert.match(detail,/openWebsite\(route\)/);
 assert.match(detail,/https:\/\/ctsmd\.ludus\.com\/index\.php/);
});
test('caregiver typed endpoint requests only authorized schedule',async()=>{
 let captured='';
 const client=load('src/api/client.ts',{readToken:async()=>null,clearToken:async()=>{}},async(url)=>{captured=url;return {status:200,ok:true,json:async()=>({calls:[]})}});
 const schedule=await client.caregiverSchedule();
 assert.deepEqual(schedule.calls,[]);
 assert.equal(captured,'https://ctsmd.me/api/mobile/caregiver-schedule');
});

test('Expo Go skips unsupported native push imports and explains development builds',async()=>{
 const device=load('src/notifications/device.ts',{},null,{
  'expo-secure-store':{getItemAsync:async()=>null,deleteItemAsync:async()=>{}},
  './runtime':{supportsNativePush:()=>false},
  'react-native':{Platform:{OS:'android'}},
  '../api/client':{registerPush:async()=>{throw Error('Unexpected registration')},unregisterPush:async()=>{}}
 });
 await assert.rejects(device.enableDeviceNotifications(),/development build/);
 const integration=fs.readFileSync(path.join(__dirname,'../src/notifications/NotificationIntegration.tsx'),'utf8');
 assert.doesNotMatch(integration,/^import \* as Notifications from 'expo-notifications'/m);
 assert.match(integration,/!supportsNativePush\(\)/);
 assert.match(integration,/import\('expo-notifications'\)/);
});

test('Expo Go detection does not disable real development builds',()=>{
 const runtime=(constants)=>load('src/notifications/runtime.ts',{},null,{'expo-constants':{__esModule:true,default:constants},'react-native':{Platform:{OS:'android'}}});
 assert.equal(runtime({appOwnership:'expo',executionEnvironment:'storeClient',expoGoConfig:null}).supportsNativePush(),false);
 assert.equal(runtime({appOwnership:null,executionEnvironment:'storeClient',expoGoConfig:{}}).supportsNativePush(),false);
 assert.equal(runtime({appOwnership:null,executionEnvironment:'storeClient',expoGoConfig:null}).supportsNativePush(),true);
 assert.equal(runtime({appOwnership:null,executionEnvironment:'standalone',expoGoConfig:null}).supportsNativePush(),true);
});

test('Community cards use compact layout with accessible posts and reactions',()=>{
 const feed=fs.readFileSync(path.join(__dirname,'../app/(tabs)/community.tsx'),'utf8');
 const screen=fs.readFileSync(path.join(__dirname,'../src/components/Screen.tsx'),'utf8');
 const heart=fs.readFileSync(path.join(__dirname,'../src/components/CommunityHeart.tsx'),'utf8');
 assert.match(feed,/<Screen title="Community" compact>/);
 assert.match(feed,/shortDate\(p\.created_at\)/);
 assert.match(feed,/postFooter:/);
 assert.match(feed,/minHeight:42/);
 assert.match(feed,/paddingVertical:10,gap:3/);
 assert.match(feed,/accessibilityLabel=\{'Open post by '/);
 assert.match(screen,/compactContent:\{paddingTop:12,gap:9\}/);
 assert.match(heart,/minHeight:44,minWidth:52/);
});
