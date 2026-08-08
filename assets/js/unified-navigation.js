(() => {
  const script = document.currentScript;
  if (script?.src && !document.querySelector('link[data-app-shell-responsive]')) {
    const responsive = document.createElement('link');
    responsive.rel = 'stylesheet';
    responsive.href = script.src.replace('/assets/js/unified-navigation.js', '/assets/css/app-shell-responsive.css');
    responsive.dataset.appShellResponsive = '';
    document.head.appendChild(responsive);
  }

  const sidebar = document.querySelector('[data-unified-sidebar]');
  const scrim = document.querySelector('[data-nav-scrim]');
  const openButtons = document.querySelectorAll('[data-nav-open]');
  const close = document.querySelector('[data-nav-close]');

  const viewport = document.querySelector('meta[name="viewport"]');
  if (viewport && !viewport.content.includes('viewport-fit=cover')) {
    viewport.content = `${viewport.content},viewport-fit=cover`;
  }

  const appHome = document.querySelector('.unified-nav-item[href$="/app"], .unified-brand[href$="/app"]');
  if (appHome) {
    const appUrl = new URL(appHome.href, window.location.href);
    const appBase = appUrl.pathname.replace(/\/app\/?$/, '');
    if (!document.querySelector('link[rel="manifest"]')) {
      const manifest = document.createElement('link');
      manifest.rel = 'manifest';
      manifest.href = `${appBase}/manifest.webmanifest`;
      document.head.appendChild(manifest);
    }
    const metadata = [
      ['apple-mobile-web-app-capable', 'yes'],
      ['apple-mobile-web-app-status-bar-style', 'black-translucent'],
      ['apple-mobile-web-app-title', 'CTSMD Connect'],
      ['mobile-web-app-capable', 'yes'],
    ];
    metadata.forEach(([name, content]) => {
      if (document.querySelector(`meta[name="${name}"]`)) return;
      const meta = document.createElement('meta');
      meta.name = name;
      meta.content = content;
      document.head.appendChild(meta);
    });
  }

  const setOpen = (value) => {
    if (!sidebar || !scrim) return;
    sidebar.classList.toggle('open', value);
    scrim.classList.toggle('show', value);
    document.body.classList.toggle('mobile-nav-open', value);
    document.body.style.overflow = value ? 'hidden' : '';
  };

  openButtons.forEach((button) => button.addEventListener('click', () => setOpen(true)));
  close?.addEventListener('click', () => setOpen(false));
  scrim?.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  const hrefPath = (anchor) => {
    try {
      return new URL(anchor.href, window.location.href).pathname.replace(/\/$/, '') || '/';
    } catch (_) {
      return '';
    }
  };

  const links = () => [...document.querySelectorAll('.unified-nav-item[href]')];
  const findNav = (suffix) => links().find((link) => hrefPath(link).endsWith(suffix)) || null;

  const makeTab = (source, label, fallbackIcon) => {
    if (!source) return null;
    const tab = document.createElement('a');
    tab.className = `mobile-app-tab${source.classList.contains('active') ? ' active' : ''}`;
    tab.href = source.href;
    tab.setAttribute('aria-label', label);

    const sourceIcon = source.querySelector(':scope > i');
    const icon = document.createElement('span');
    icon.className = 'mobile-app-tab-icon';
    icon.textContent = sourceIcon?.textContent?.trim() || fallbackIcon;

    const text = document.createElement('span');
    text.className = 'mobile-app-tab-label';
    text.textContent = label;

    const unread = source.querySelector('.unified-unread');
    if (unread) {
      const badge = document.createElement('strong');
      badge.className = 'mobile-app-tab-badge';
      badge.textContent = unread.textContent?.trim() || '';
      icon.appendChild(badge);
    }

    tab.append(icon, text);
    return tab;
  };

  const buildMobileTabs = () => {
    if (!sidebar || document.querySelector('[data-mobile-app-tabs]')) return;

    const home = findNav('/app');
    const staff = findNav('/staff');
    const production = findNav('/production');
    const calendar = findNav('/calendar');
    const family = findNav('/family-hub');
    const community = findNav('/channels');
    const messages = findNav('/messages');

    const bar = document.createElement('nav');
    bar.className = 'mobile-app-tabs';
    bar.dataset.mobileAppTabs = '';
    bar.setAttribute('aria-label', 'Primary app navigation');

    const operational = production || staff || calendar || family;
    const operationalLabel = production ? 'Production' : staff ? 'Staff' : calendar ? 'Calendar' : family ? 'Family' : 'Today';
    const operationalIcon = production ? '★' : staff ? '◎' : calendar ? '◫' : family ? '♟' : '◉';

    const tabs = [
      makeTab(home, 'Home', '⌂'),
      makeTab(operational, operationalLabel, operationalIcon),
      makeTab(community, 'Community', '#'),
      makeTab(messages, 'Messages', '✉'),
    ].filter(Boolean);

    tabs.forEach((tab) => bar.appendChild(tab));

    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'mobile-app-tab mobile-app-more';
    more.setAttribute('aria-label', 'More navigation');
    more.innerHTML = '<span class="mobile-app-tab-icon">•••</span><span class="mobile-app-tab-label">More</span>';
    more.addEventListener('click', () => setOpen(true));
    bar.appendChild(more);

    document.body.appendChild(bar);
  };

  const applyDeviceMode = () => {
    const width = window.innerWidth;
    document.documentElement.dataset.appViewport = width <= 780 ? 'phone' : width <= 1180 ? 'tablet' : 'desktop';
    if (width > 780) setOpen(false);
  };

  buildMobileTabs();
  applyDeviceMode();
  window.addEventListener('resize', applyDeviceMode, { passive: true });
})();
