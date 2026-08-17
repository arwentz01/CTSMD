(() => {
  const page=document.querySelector('[data-push-page]');
  if(!page)return;
  const base=page.dataset.base||'';
  const publicKey=page.dataset.publicKey||'';
  const csrf=page.dataset.csrf||'';
  const status=page.querySelector('[data-push-status]');
  const detail=page.querySelector('[data-push-detail]');
  const enable=page.querySelector('[data-push-enable]');
  const localTest=page.querySelector('[data-push-local-test]');
  const disable=page.querySelector('[data-push-disable]');
  const diagButton=page.querySelector('[data-push-diagnostics]');
  const pageTest=page.querySelector('[data-push-page-test]');
  const workerTest=page.querySelector('[data-push-worker-test]');
  const diagPermission=page.querySelector('[data-diag-permission]');
  const diagWorker=page.querySelector('[data-diag-worker]');
  const diagSubscription=page.querySelector('[data-diag-subscription]');
  const diagEndpoint=page.querySelector('[data-diag-endpoint]');
  const diagResult=page.querySelector('[data-diag-result]');

  const b64ToUint8 = value => {
    const padding='='.repeat((4-value.length%4)%4);
    const raw=atob((value+padding).replace(/-/g,'+').replace(/_/g,'/'));
    return Uint8Array.from([...raw].map(ch=>ch.charCodeAt(0)));
  };
  const post=async(path,data)=>{
    const response=await fetch(`${base}${path}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify(data)});
    const json=await response.json().catch(()=>({ok:false,message:'Unexpected server response.'}));
    if(!response.ok||!json.ok)throw new Error(json.message||'Notification setup failed.');
    return json;
  };
  const standalone=()=>window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  const isIOS=()=>/iphone|ipad|ipod/i.test(navigator.userAgent);
  const endpointHost=value=>{try{return new URL(value).host;}catch(_){return value||'None';}};

  const registration=async()=>{
    const reg=await navigator.serviceWorker.register(`${base}/service-worker.js`,{scope:`${base}/`});
    try{await reg.update();}catch(_){}
    return navigator.serviceWorker.ready;
  };

  const diagnostics=async()=>{
    diagPermission.textContent=('Notification' in window)?Notification.permission:'Unsupported';
    if(!('serviceWorker' in navigator)){
      diagWorker.textContent='Unsupported';diagSubscription.textContent='Unavailable';diagEndpoint.textContent='Unavailable';return;
    }
    try{
      const reg=await registration();
      diagWorker.textContent=reg.active?`Active · ${reg.scope}`:'Registered, not active';
      const sub=await reg.pushManager.getSubscription();
      diagSubscription.textContent=sub?'Active':'None';
      diagEndpoint.textContent=sub?endpointHost(sub.endpoint):'None';
    }catch(error){
      diagWorker.textContent='Error';diagSubscription.textContent='Unknown';diagEndpoint.textContent='Unknown';diagResult.textContent=error.message||String(error);
    }
  };

  const refresh=async()=>{
    if(!('serviceWorker' in navigator)||!('PushManager' in window)||!('Notification' in window)){
      status.textContent='Push notifications are not supported here';detail.textContent='Use a current version of Safari, Chrome, Edge, or another push-capable browser.';enable.hidden=true;localTest.hidden=true;disable.hidden=true;await diagnostics();return;
    }
    if(isIOS()&&!standalone()){
      status.textContent='Add Connect to your Home Screen first';detail.textContent='On iPhone and iPad, open Safari → Share → Add to Home Screen, then launch Connect from the new icon.';enable.hidden=true;localTest.hidden=true;disable.hidden=true;await diagnostics();return;
    }
    const reg=await registration();
    const sub=await reg.pushManager.getSubscription();
    if(sub){status.textContent='Notifications are enabled';detail.textContent=`Browser permission is ${Notification.permission}. Use the diagnostics below to confirm whether this computer is actually presenting notifications.`;enable.hidden=true;localTest.hidden=Notification.permission!=='granted';disable.hidden=false;}
    else{status.textContent=Notification.permission==='denied'?'Notifications are blocked':'Notifications are off';detail.textContent=Notification.permission==='denied'?'Enable notifications for CTSMD Connect in your device/browser settings, then return here.':'Enable them when you are ready. You stay in control of notification categories.';enable.hidden=Notification.permission==='denied'||!publicKey;localTest.hidden=true;disable.hidden=true;}
    await diagnostics();
  };

  enable?.addEventListener('click',async()=>{
    enable.disabled=true;
    try{
      if(!publicKey)throw new Error('Push is not configured on the CTSMD server yet.');
      const permission=await Notification.requestPermission();if(permission!=='granted')throw new Error('Notification permission was not granted.');
      const reg=await registration();
      const sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToUint8(publicKey)});
      await post('/push/subscribe',sub.toJSON());
      try{if('setAppBadge' in navigator)await navigator.setAppBadge(1);}catch(_){}
      await refresh();
    }catch(error){status.textContent='Could not enable notifications';detail.textContent=error.message||String(error);}finally{enable.disabled=false;}
  });

  const runWorkerTest=async()=>{
    if(Notification.permission!=='granted')throw new Error('Browser notification permission is not granted.');
    const reg=await registration();
    await reg.showNotification('CTSMD Connect browser test',{
      body:'If you can see this, this computer is allowed to display CTSMD notifications.',
      tag:'ctsmd-browser-test',
      requireInteraction:true,
      silent:false,
      data:{url:`${base}/push-settings`}
    });
    await new Promise(resolve=>setTimeout(resolve,300));
    const visible=await reg.getNotifications({tag:'ctsmd-browser-test'});
    diagResult.textContent=visible.length
      ? `Service worker accepted the notification and still reports ${visible.length} active notification${visible.length===1?'':'s'}. If no banner is visible, macOS/browser presentation is suppressing it.`
      : 'Service worker accepted the request, but the browser does not report an active notification afterward.';
  };

  localTest?.addEventListener('click',async()=>{
    localTest.disabled=true;
    try{await runWorkerTest();status.textContent='Browser test requested';detail.textContent=diagResult.textContent;}
    catch(error){status.textContent='Browser test failed';detail.textContent=error.message||String(error);diagResult.textContent=detail.textContent;}finally{localTest.disabled=false;}
  });

  workerTest?.addEventListener('click',async()=>{
    workerTest.disabled=true;
    try{await runWorkerTest();}catch(error){diagResult.textContent=`Service-worker test failed: ${error.message||String(error)}`;}finally{workerTest.disabled=false;await diagnostics();}
  });

  pageTest?.addEventListener('click',async()=>{
    pageTest.disabled=true;
    try{
      if(!('Notification' in window))throw new Error('Notification API is not available in this browser.');
      if(Notification.permission!=='granted')throw new Error(`Browser permission is ${Notification.permission}.`);
      let shown=false;
      const notification=new Notification('CTSMD Connect direct test',{body:'This notification was created directly by the open CTSMD page.',tag:'ctsmd-direct-test',requireInteraction:true,silent:false});
      notification.onshow=()=>{shown=true;diagResult.textContent='Direct page notification fired its show event. If you still cannot see it, macOS is suppressing presentation.';};
      notification.onerror=()=>{diagResult.textContent='The browser fired an error event for the direct page notification.';};
      setTimeout(()=>{if(!shown&&diagResult)diagResult.textContent='The browser created the direct notification object, but no show event arrived within 2 seconds. That points to browser/OS notification presentation rather than CTSMD push delivery.';},2000);
    }catch(error){diagResult.textContent=`Direct page test failed: ${error.message||String(error)}`;}finally{pageTest.disabled=false;await diagnostics();}
  });

  diagButton?.addEventListener('click',async()=>{diagButton.disabled=true;try{await diagnostics();diagResult.textContent='Diagnostics refreshed.';}finally{diagButton.disabled=false;}});

  disable?.addEventListener('click',async()=>{
    disable.disabled=true;
    try{
      const reg=await navigator.serviceWorker.getRegistration(`${base}/`);const sub=await reg?.pushManager.getSubscription();
      if(sub){await post('/push/unsubscribe',{endpoint:sub.endpoint});await sub.unsubscribe();}
      try{if('clearAppBadge' in navigator)await navigator.clearAppBadge();}catch(_){}
      await refresh();
    }catch(error){status.textContent='Could not disable notifications';detail.textContent=error.message||String(error);}finally{disable.disabled=false;}
  });

  refresh().catch(error=>{status.textContent='Notification setup unavailable';detail.textContent=error.message||String(error);diagnostics().catch(()=>{});});
})();
