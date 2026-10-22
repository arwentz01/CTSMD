/* CTSMD Connect service worker: push delivery + notification deep links. */
self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

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
  // Never navigate a CTSMD notification to a cross-origin or non-HTTP destination.
  let target;
  try {
    const requested = new URL(event.notification.data?.url || '/notifications', self.location.origin);
    target = requested.origin === self.location.origin &&
      (requested.protocol === 'https:' || requested.protocol === 'http:')
      ? requested.href
      : new URL('/notifications', self.location.origin).href;
  } catch (_) {
    target = new URL('/notifications', self.location.origin).href;
  }
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type:'window', includeUncontrolled:true});
    // Prefer the CTSMD tab that already displays the requested destination.
    const destination = windows.find(client => {
      try { return new URL(client.url).href === target; } catch (_) { return false; }
    });
    if (destination && 'focus' in destination) return destination.focus();
    // Only reuse tabs within this installation's origin; leave unrelated tabs alone.
    for (const client of windows) {
      try {
        if (new URL(client.url).origin !== self.location.origin ||
            !('navigate' in client) || !('focus' in client)) continue;
        const navigated = await client.navigate(target);
        if (navigated) return navigated.focus();
      } catch (_) { /* Try another CTSMD tab or open a new one. */ }
    }
    return clients.openWindow(target);
  })());
});

// Do not silently replace a browser push subscription here. A new endpoint
// would not be registered to the signed-in CTSMD account and could leave the
// server delivering to the stale endpoint. Registration must occur through
// the authenticated /push-settings workflow.
self.addEventListener('pushsubscriptionchange', event => {
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type:'window', includeUncontrolled:true});
    for (const client of windows) {
      try { if (new URL(client.url).origin === self.location.origin) {
        client.postMessage({type:'ctsmd-push-subscription-changed'});
      }} catch (_) {}
    }
  })());
});
