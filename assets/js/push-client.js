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

  const registration=async()=>{
    const reg=await navigator.serviceWorker.register(`${base}/service-worker.js`,{scope:`${base}/`});
    try{await reg.update();}catch(_){}
    return navigator.serviceWorker.ready;
  };

  const refresh=async()=>{
    if(!('serviceWorker' in navigator)||!('PushManager' in window)||!('Notification' in window)){
      status.textContent='Push notifications are not supported here';detail.textContent='Use a current version of Safari, Chrome, Edge, or another push-capable browser.';enable.hidden=true;localTest.hidden=true;disable.hidden=true;return;
    }
    if(isIOS()&&!standalone()){
      status.textContent='Add Connect to your Home Screen first';detail.textContent='On iPhone and iPad, open Safari → Share → Add to Home Screen, then launch Connect from the new icon.';enable.hidden=true;localTest.hidden=true;disable.hidden=true;return;
    }
    const reg=await registration();
    const sub=await reg.pushManager.getSubscription();
    if(sub){status.textContent='Notifications are enabled';detail.textContent=`Browser permission is ${Notification.permission}. Use “Show browser test” to confirm this computer can display a notification before testing remote push.`;enable.hidden=true;localTest.hidden=Notification.permission!=='granted';disable.hidden=false;}
    else{status.textContent=Notification.permission==='denied'?'Notifications are blocked':'Notifications are off';detail.textContent=Notification.permission==='denied'?'Enable notifications for CTSMD Connect in your device/browser settings, then return here.':'Enable them when you are ready. You stay in control of notification categories.';enable.hidden=Notification.permission==='denied'||!publicKey;localTest.hidden=true;disable.hidden=true;}
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

  localTest?.addEventListener('click',async()=>{
    localTest.disabled=true;
    try{
      if(Notification.permission!=='granted')throw new Error('Browser notification permission is not granted.');
      const reg=await registration();
      await reg.showNotification('CTSMD Connect browser test',{
        body:'If you can see this, this computer is allowed to display CTSMD notifications.',
        tag:'ctsmd-browser-test',
        data:{url:`${base}/push-settings`}
      });
      status.textContent='Browser test requested';
      detail.textContent='A desktop notification should appear now. If it does not, check this browser in macOS System Settings → Notifications.';
    }catch(error){status.textContent='Browser test failed';detail.textContent=error.message||String(error);}finally{localTest.disabled=false;}
  });

  disable?.addEventListener('click',async()=>{
    disable.disabled=true;
    try{
      const reg=await navigator.serviceWorker.getRegistration(`${base}/`);const sub=await reg?.pushManager.getSubscription();
      if(sub){await post('/push/unsubscribe',{endpoint:sub.endpoint});await sub.unsubscribe();}
      try{if('clearAppBadge' in navigator)await navigator.clearAppBadge();}catch(_){}
      await refresh();
    }catch(error){status.textContent='Could not disable notifications';detail.textContent=error.message||String(error);}finally{disable.disabled=false;}
  });

  refresh().catch(error=>{status.textContent='Notification setup unavailable';detail.textContent=error.message||String(error);});
})();
