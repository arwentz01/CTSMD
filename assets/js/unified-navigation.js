(() => {
  const sidebar = document.querySelector('[data-unified-sidebar]');
  const scrim = document.querySelector('[data-nav-scrim]');
  const openButtons = document.querySelectorAll('[data-nav-open]');
  const close = document.querySelector('[data-nav-close]');

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

  const findNav = (suffix) => {
    const links = [...document.querySelectorAll('.unified-nav-item[href]')];
    return links.find((link) => hrefPath(link).endsWith(suffix)) || null;
  };

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
    const production = findNav('/production');
    const calendar = findNav('/calendar');
    const community = findNav('/channels');
    const messages = findNav('/messages');

    const bar = document.createElement('nav');
    bar.className = 'mobile-app-tabs';
    bar.dataset.mobileAppTabs = '';
    bar.setAttribute('aria-label', 'App navigation');

    const tabs = [
      makeTab(home, 'Home', '⌂'),
      makeTab(production || calendar, production ? 'Production' : 'Calendar', production ? '★' : '◫'),
      makeTab(community, 'Community', '#'),
      makeTab(messages, 'Messages', '✉'),
    ].filter(Boolean);

    tabs.forEach((tab) => bar.appendChild(tab));

    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'mobile-app-tab mobile-app-more';
    more.setAttribute('aria-label', 'More');
    more.innerHTML = '<span class="mobile-app-tab-icon">•••</span><span class="mobile-app-tab-label">More</span>';
    more.addEventListener('click', () => setOpen(true));
    bar.appendChild(more);

    document.body.appendChild(bar);
  };

  buildMobileTabs();
})();
