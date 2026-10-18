(() => {
  const button=document.querySelector('[data-web-push-toggle]');
  if(!button)return;
  const info=document.querySelector('[data-web-push-status]');
  const message=value=>{if(info)info.textContent=value;};
  const supported='serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  if(!supported){button.disabled=true;message('This browser does not support Web Push. In-app notifications remain available.');return;}
  let publicKey='';
  const bytes=base64=>{
    const text=atob(base64.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-base64.length%4)%4));
    return Uint8Array.from(text,c=>c.charCodeAt(0));
  };
  const setup=async()=>{
    const {data}=await window.cafeteriaFetch(window.cafeteriaUrl('api/push/config'));
    if(!data.enabled){button.disabled=true;message('Push notifications have not been enabled by the administrator.');return;}
    publicKey=data.publicKey;
    const registration=await navigator.serviceWorker.register(window.cafeteriaUrl('sw.js'));
    const current=await registration.pushManager.getSubscription();
    button.textContent=current?'Disable push on this device':'Enable push on this device';
    button.addEventListener('click',async()=>{
      button.disabled=true;
      try {
        const existing=await registration.pushManager.getSubscription();
        if(existing){
          await window.cafeteriaFetch(window.cafeteriaUrl('api/push/unsubscribe'),{method:'POST',body:JSON.stringify({endpoint:existing.endpoint})});
          await existing.unsubscribe();button.textContent='Enable push on this device';message('Push notifications are off for this device.');
        } else {
          const permission=await Notification.requestPermission();
          if(permission!=='granted')throw new Error('Notification permission was not granted.');
          const sub=await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:bytes(publicKey)});
          await window.cafeteriaFetch(window.cafeteriaUrl('api/push/subscribe'),{method:'POST',body:JSON.stringify(sub)});
          button.textContent='Disable push on this device';message('Push notifications enabled.');
        }
      }catch(e){message(e.message||'Cannot change push permission.');}
      finally{button.disabled=false;}
    });
  };
  setup().catch(()=>{button.disabled=true;message('Push service unavailable. Your in-app notifications still work.');});
})();
