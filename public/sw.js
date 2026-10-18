/* Same-origin service worker: only displays explicitly subscribed cafeteria push events. */
self.addEventListener('push', event => {
  let message={title:'Cafeteria update',body:'A new notification is available.',url:'/notifications'};
  try{if(event.data)message={...message,...event.data.json()};}catch(_){}
  event.waitUntil(self.registration.showNotification(String(message.title).slice(0,100),{
    body:String(message.body).slice(0,300),icon:'assets/img/jrmsu-cafeteria-logo.webp',
    data:{url:String(message.url)},tag:'cafeteria-notification-'+Date.now(),renotify:false,
  }));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target=new URL(event.notification.data?.url||'/notifications',self.location.origin);
  if(target.origin!==self.location.origin)return;
  event.waitUntil(clients.openWindow(target.href));
});
