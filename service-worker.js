/* CTSMD Connect service worker: push delivery + notification deep links. */
self.addEventListener('push', event => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) { data = {title:'CTSMD Connect', body:event.data ? event.data.text() : ''}; }
  const title = data.title || 'CTSMD Connect';
  const options = {
    body: data.body || '',
    tag: data.tag || undefined,
    renotify: Boolean(data.tag),
    data: {url: data.url || '/app'},
    icon: data.icon || undefined,
    badge: data.badge || undefined,
    vibrate: [100, 50, 100]
  };
  event.waitUntil((async () => {
    await self.registration.showNotification(title, options);
    try { if (self.navigator && 'setAppBadge' in self.navigator) await self.navigator.setAppBadge(Number(data.badgeCount || 1)); } catch (_) {}
  })());
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || '/app', self.location.origin).href;
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type:'window', includeUncontrolled:true});
    for (const client of windows) {
      if ('focus' in client) {
        try { await client.navigate(target); } catch (_) {}
        return client.focus();
      }
    }
    return clients.openWindow(target);
  })());
});

self.addEventListener('pushsubscriptionchange', event => {
  event.waitUntil(self.registration.pushManager.subscribe(event.oldSubscription?.options || {userVisibleOnly:true}).then(() => undefined).catch(() => undefined));
});
