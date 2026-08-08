(() => {
  const script = document.currentScript;
  const loadShellStyles = () => {
    if (!script?.src) return;
    const files = [
      ['app-shell-responsive.css', 'appShellResponsive'],
      ['mobile-native-experience.css', 'mobileNativeExperience'],
      ['mobile-native-hotfixes.css', 'mobileNativeHotfixes'],
    ];
    files.forEach(([file, dataKey]) => {
      if (document.querySelector(`link[data-${dataKey.replace(/[A-Z]/g, m => '-' + m.toLowerCase())}]`)) return;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = script.src.replace('/assets/js/unified-navigation.js', `/assets/css/${file}`);
      link.dataset[dataKey] = '';
      document.head.appendChild(link);
    });
  };
  loadShellStyles();

  const sidebar = document.querySelector('[data-unified-sidebar]');
  const scrim = document.querySelector('[data-nav-scrim]');
  const openButtons = document.querySelectorAll('[data-nav-open]');
  const close = document.querySelector('[data-nav-close]');

  const viewport = document.querySelector('meta[name="viewport"]');
  if (viewport && !viewport.content.includes('viewport-fit=cover')) viewport.content = `${viewport.content},viewport-fit=cover`;

  const appHome = document.querySelector('.unified-nav-item[href$="/app"], .unified-brand[href$="/app"]');
  let appBase = '';
  if (appHome) {
    const appUrl = new URL(appHome.href, window.location.href);
    appBase = appUrl.pathname.replace(/\/app\/?$/, '');
    if (!document.querySelector('link[rel="manifest"]')) {
      const manifest = document.createElement('link');
      manifest.rel = 'manifest';
      manifest.href = `${appBase}/manifest.webmanifest`;
      document.head.appendChild(manifest);
    }
    [['apple-mobile-web-app-capable','yes'],['apple-mobile-web-app-status-bar-style','black-translucent'],['apple-mobile-web-app-title','CTSMD Connect'],['mobile-web-app-capable','yes']].forEach(([name,content]) => {
      if (document.querySelector(`meta[name="${name}"]`)) return;
      const meta = document.createElement('meta'); meta.name = name; meta.content = content; document.head.appendChild(meta);
    });
  }

  const setOpen = (value) => {
    if (!sidebar || !scrim) return;
    sidebar.classList.toggle('open', value);
    scrim.classList.toggle('show', value);
    document.body.classList.toggle('mobile-nav-open', value);
    document.body.style.overflow = value ? 'hidden' : '';
  };
  openButtons.forEach(button => button.addEventListener('click', () => setOpen(true)));
  close?.addEventListener('click', () => setOpen(false));
  scrim?.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });

  const hrefPath = anchor => { try { return new URL(anchor.href, window.location.href).pathname.replace(/\/$/, '') || '/'; } catch (_) { return ''; } };
  const links = () => [...document.querySelectorAll('.unified-nav-item[href]')];
  const findNav = suffix => links().find(link => hrefPath(link).endsWith(suffix)) || null;

  const makeTab = (source,label,fallbackIcon) => {
    if (!source) return null;
    const tab=document.createElement('a'); tab.className=`mobile-app-tab${source.classList.contains('active')?' active':''}`; tab.href=source.href; tab.setAttribute('aria-label',label);
    const icon=document.createElement('span'); icon.className='mobile-app-tab-icon'; icon.textContent=source.querySelector(':scope > i')?.textContent?.trim()||fallbackIcon;
    const text=document.createElement('span'); text.className='mobile-app-tab-label'; text.textContent=label;
    const unread=source.querySelector('.unified-unread'); if(unread){const badge=document.createElement('strong');badge.className='mobile-app-tab-badge';badge.textContent=unread.textContent?.trim()||'';icon.appendChild(badge);} tab.append(icon,text); return tab;
  };

  const buildMobileTabs = () => {
    if (!sidebar || document.querySelector('[data-mobile-app-tabs]')) return;
    const home=findNav('/app'),staff=findNav('/staff'),production=findNav('/production'),calendar=findNav('/calendar'),family=findNav('/family-hub'),community=findNav('/channels'),messages=findNav('/messages');
    const bar=document.createElement('nav');bar.className='mobile-app-tabs';bar.dataset.mobileAppTabs='';bar.setAttribute('aria-label','Primary app navigation');
    const operational=production||staff||calendar||family;
    const label=production?'Production':staff?'Staff':calendar?'Calendar':family?'Family':'Today';
    const icon=production?'★':staff?'◎':calendar?'◫':family?'♟':'◉';
    [makeTab(home,'Home','⌂'),makeTab(operational,label,icon),makeTab(community,'Community','#'),makeTab(messages,'Messages','✉')].filter(Boolean).forEach(tab=>bar.appendChild(tab));
    const more=document.createElement('button');more.type='button';more.className='mobile-app-tab mobile-app-more';more.setAttribute('aria-label','More navigation');more.innerHTML='<span class="mobile-app-tab-icon">•••</span><span class="mobile-app-tab-label">More</span>';more.addEventListener('click',()=>setOpen(true));bar.appendChild(more);document.body.appendChild(bar);
  };

  const relativePath = () => {
    let path = window.location.pathname.replace(/\/$/, '') || '/';
    if (appBase && path.startsWith(appBase)) path = path.slice(appBase.length) || '/';
    return path;
  };

  const parentFor = path => {
    if (path.startsWith('/messages/thread') || path === '/messages/new') return '/messages';
    if (path === '/channels/view') return '/channels';
    if (path.startsWith('/forms/view')) return '/forms';
    if (path.startsWith('/volunteer/shift')) return '/volunteer-shifts';
    if (path.startsWith('/attendance/take') || path.startsWith('/attendance/report')) return '/attendance';
    if (path.startsWith('/production/groups/view')) return '/production/groups';
    if (path.startsWith('/production/') && !['/production/day','/production/readiness','/production/casting','/production/people','/production/groups'].includes(path)) return '/production';
    if (path.startsWith('/archive/') && path !== '/archive') return '/archive';
    if (path.startsWith('/student-profile')) return '/theatre-history';
    if (path === '/acting-resume') return '/theatre-history';
    return null;
  };

  const configureNativeHeader = () => {
    const header=document.querySelector('.unified-header'); if(!header) return;
    const path=relativePath();
    const roots=['/app','/family-hub','/calendar','/channels','/messages','/volunteer-readiness','/production','/staff'];
    const parent=parentFor(path);
    document.body.classList.toggle('native-root-screen', roots.includes(path));
    document.body.dataset.nativeRoute = path.replace(/^\//,'').replace(/[^a-z0-9]+/gi,'-') || 'home';
    header.querySelector('.native-back')?.remove();
    if(parent){
      const back=document.createElement('a');back.className='native-back';back.href=`${appBase}${parent}`;back.setAttribute('aria-label','Back');back.textContent='‹';header.appendChild(back);
    }
  };

  const applyDeviceMode = () => {
    const width=window.innerWidth;
    document.documentElement.dataset.appViewport=width<=780?'phone':width<=1180?'tablet':'desktop';
    if(width>780)setOpen(false);
  };

  buildMobileTabs(); configureNativeHeader(); applyDeviceMode();
  window.addEventListener('resize', applyDeviceMode, {passive:true});
})();
