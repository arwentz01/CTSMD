(() => {
  const script = document.currentScript;
  if (script?.src) {
    const files = ['mobile-engagement.css','community-workspace-polish.css'];
    files.forEach(file => {
      if (document.querySelector(`link[data-ctsmd-polish="${file}"]`)) return;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = script.src.replace('/assets/js/mobile-engagement.js', `/assets/css/${file}`);
      link.dataset.ctsmdPolish = file;
      document.head.appendChild(link);
    });
  }

  const run = () => {
    if (window.innerWidth > 780 || document.querySelector('[data-mobile-engagement]')) return;

    const home = document.querySelector('.unified-brand[href], .mobile-app-tab[href$="/app"]');
    if (!home) return;
    let base = '';
    try {
      const url = new URL(home.href, window.location.href);
      base = url.pathname.replace(/\/app\/?$/, '');
    } catch (_) {}

    const storage = {
      get(key) { try { return window.localStorage.getItem(key); } catch (_) { return null; } },
      set(key, value) { try { window.localStorage.setItem(key, value); } catch (_) {} }
    };
    const installedKey = 'ctsmd-connect-installed';
    const snoozeKey = 'ctsmd-mobile-engagement-snooze-until';
    const sessionKey = 'ctsmd-mobile-engagement-shown';
    const week = 7 * 24 * 60 * 60 * 1000;
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
    const snoozed = () => Number(storage.get(snoozeKey) || 0) > Date.now();
    const markInstalled = () => storage.set(installedKey, '1');

    if (standalone()) markInstalled();
    window.addEventListener('appinstalled', () => {
      markInstalled();
      document.querySelector('[data-mobile-engagement]')?.remove();
    });

    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', event => {
      event.preventDefault();
      installPrompt = event;
      maybeShow();
    });

    const registerWorker = async () => {
      if (!('serviceWorker' in navigator)) return null;
      try {
        const registration = await navigator.serviceWorker.register(`${base}/service-worker.js`, {scope: `${base}/`});
        return await navigator.serviceWorker.ready;
      } catch (_) {
        return null;
      }
    };

    const notificationState = async () => {
      if (!('Notification' in window) || !('PushManager' in window) || !('serviceWorker' in navigator)) return 'unsupported';
      const registration = await registerWorker();
      if (!registration) return 'unsupported';
      try {
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) return 'active';
      } catch (_) {}
      if (Notification.permission === 'denied') return 'denied';
      return 'available';
    };

    const dismiss = panel => {
      storage.set(snoozeKey, String(Date.now() + week));
      try { window.sessionStorage.setItem(sessionKey, '1'); } catch (_) {}
      panel.classList.add('leaving');
      window.setTimeout(() => panel.remove(), 180);
    };

    const createPanel = ({needsInstall, notification, ios}) => {
      const panel = document.createElement('aside');
      panel.className = 'mobile-engagement';
      panel.dataset.mobileEngagement = '';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'Make CTSMD Connect easier to use');

      const copy = document.createElement('div');
      copy.className = 'mobile-engagement-copy';
      const eyebrow = document.createElement('small');
      eyebrow.textContent = needsInstall ? 'ONE TAP AWAY' : 'STAY IN THE LOOP';
      const title = document.createElement('b');
      title.textContent = needsInstall ? 'Make Connect feel like an app.' : 'Get CTSMD updates when they matter.';
      const detail = document.createElement('span');
      if (needsInstall && ios) detail.textContent = 'Add Connect to your Home Screen for faster access. Once installed, you can turn on CTSMD notifications too.';
      else if (needsInstall) detail.textContent = 'Install Connect for one-tap access to Messages, Channels, your calendar and forms.';
      else if (notification === 'denied') detail.textContent = 'Notifications are blocked on this device. Open notification settings to see how to restore them.';
      else detail.textContent = 'Enable notifications for messages, schedule changes, forms and other CTSMD updates.';
      copy.append(eyebrow, title, detail);

      const actions = document.createElement('div');
      actions.className = 'mobile-engagement-actions';
      const primary = document.createElement(needsInstall && !ios && installPrompt ? 'button' : 'a');
      primary.className = 'mobile-engagement-primary';

      if (needsInstall && ios) {
        primary.textContent = 'How to install';
        primary.href = '#';
        primary.addEventListener('click', event => {
          event.preventDefault();
          detail.textContent = 'In Safari, tap Share, choose Add to Home Screen, then open CTSMD Connect from the new icon.';
          primary.textContent = 'Got it';
          primary.addEventListener('click', event2 => { event2.preventDefault(); dismiss(panel); }, {once:true});
        }, {once:true});
      } else if (needsInstall && installPrompt) {
        primary.textContent = 'Install Connect';
        primary.type = 'button';
        primary.addEventListener('click', async () => {
          primary.disabled = true;
          try {
            await installPrompt.prompt();
            const choice = await installPrompt.userChoice;
            installPrompt = null;
            if (choice.outcome === 'accepted') {
              markInstalled();
              panel.remove();
            } else dismiss(panel);
          } catch (_) {
            dismiss(panel);
          } finally {
            primary.disabled = false;
          }
        });
      } else {
        primary.textContent = notification === 'denied' ? 'Notification help' : 'Enable notifications';
        primary.href = `${base}/push-settings`;
      }

      const later = document.createElement('button');
      later.type = 'button';
      later.className = 'mobile-engagement-later';
      later.textContent = 'Not now';
      later.addEventListener('click', () => dismiss(panel));
      actions.append(primary, later);
      panel.append(copy, actions);
      document.body.appendChild(panel);
      requestAnimationFrame(() => panel.classList.add('visible'));
      try { window.sessionStorage.setItem(sessionKey, '1'); } catch (_) {}
    };

    let showing = false;
    const maybeShow = async () => {
      if (showing || document.querySelector('[data-mobile-engagement]') || snoozed()) return;
      try { if (window.sessionStorage.getItem(sessionKey) === '1') return; } catch (_) {}
      showing = true;
      const installed = standalone() || storage.get(installedKey) === '1';
      const notification = await notificationState();
      const needsInstall = !installed && (isIOS() || Boolean(installPrompt));
      const needsNotifications = notification !== 'active' && notification !== 'unsupported';
      if (!needsInstall && !needsNotifications) { showing = false; return; }
      createPanel({needsInstall, notification, ios: isIOS()});
      showing = false;
    };

    registerWorker().catch(() => {});
    window.setTimeout(maybeShow, 6000);
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, {once:true});
  else run();
})();
