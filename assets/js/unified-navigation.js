(() => {
  const script = document.currentScript;
  const loadShellStyles = () => {
    if (!script?.src) return;
    const files = [
      ['app-shell-responsive.css', 'appShellResponsive'],
      ['mobile-native-experience.css', 'mobileNativeExperience'],
      ['mobile-native-hotfixes.css', 'mobileNativeHotfixes'],
      ['mobile-theatre-polish.css', 'mobileTheatrePolish'],
      ['product-polish.css', 'productPolish'],
      ['mobile-community-curtain.css', 'mobileCommunityCurtain'],
      ['mobile-calendar-disclosure.css', 'mobileCalendarDisclosure'],
      ['mobile-forms-notifications.css', 'mobileFormsNotifications'],
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
    if (path === '/forms') return '/app';
    if (path.startsWith('/forms/view')) return '/forms';
    if (path === '/notifications') return '/app';
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
    const roots=['/app','/family-hub','/calendar','/channels','/messages','/volunteer-readiness','/volunteer-shifts','/production','/staff'];
    const parent=parentFor(path);
    document.body.classList.toggle('native-root-screen', roots.includes(path));
    document.body.dataset.nativeRoute = path.replace(/^\//,'').replace(/[^a-z0-9]+/gi,'-') || 'home';
    header.querySelector('.native-back')?.remove();
    if(parent){
      const back=document.createElement('a');back.className='native-back';back.href=`${appBase}${parent}`;back.setAttribute('aria-label','Back');back.textContent='‹';header.appendChild(back);
    }
  };

  const configureHelp = () => {
    const header=document.querySelector('.unified-header'); if(!header || header.querySelector('.unified-help')) return;
    const path=relativePath();
    const help=document.createElement('a');
    help.className='unified-help';
    help.href=`${appBase}/help${path.startsWith('/messages')?'#messages':''}`;
    help.setAttribute('aria-label',path.startsWith('/messages')?'Messaging safety help':'Help and safety information');
    help.title=path.startsWith('/messages')?'Messaging safety':'Help & Safety';
    help.textContent='?';
    const utilities=header.querySelector('.unified-utilities');
    if(utilities) utilities.prepend(help); else header.appendChild(help);
  };

  const trimDemoCopy = () => {
    const path=relativePath();
    if(path==='/messages'){
      const intro=document.querySelector('.comm-message-intro');
      const heading=intro?.querySelector('h2');
      const paragraph=intro?.querySelector('p');
      if(heading) heading.textContent='Your conversations';
      paragraph?.remove();
    }
    if(path==='/messages/new'){
      const head=document.querySelector('.comm-channel-head');
      const paragraph=head?.querySelector('p');
      paragraph?.remove();
    }
    if(path.startsWith('/messages/thread')){
      document.querySelectorAll('.comm-safety-banner').forEach(node=>node.remove());
      const threadHead=document.querySelector('.comm-thread-head');
      const participantCopy=threadHead?.querySelector('p');
      if(participantCopy) participantCopy.textContent=participantCopy.textContent?.trim()||'';
    }
  };

  const configureCommunityCurtain = () => {
    if (window.innerWidth > 780 || relativePath() !== '/channels') return;
    const hero=document.querySelector('.comm-hero');
    if(!hero || hero.querySelector('.community-curtain-toggle')) return;
    const summary=document.createElement('span');
    summary.className='community-curtain-summary';
    summary.textContent='Community';
    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='community-curtain-toggle';
    const storageKey='ctsmd-community-curtain-collapsed';
    let collapsed=false;
    try{collapsed=window.localStorage.getItem(storageKey)==='1';}catch(_){collapsed=false;}
    const render=()=>{
      hero.classList.toggle('community-curtain-collapsed',collapsed);
      toggle.textContent=collapsed?'Curtain Up':'Curtain Down';
      toggle.setAttribute('aria-expanded',collapsed?'false':'true');
      toggle.setAttribute('aria-label',collapsed?'Expand Community introduction':'Collapse Community introduction');
      try{window.localStorage.setItem(storageKey,collapsed?'1':'0');}catch(_){}
    };
    toggle.addEventListener('click',()=>{collapsed=!collapsed;render();});
    hero.append(summary,toggle);
    render();
  };

  const configureCalendarSubscription = () => {
    if (window.innerWidth > 780 || relativePath() !== '/calendar') return;
    const card=document.querySelector('.cal-subscribe');
    if(!card || card.querySelector('.cal-subscribe-toggle')) return;
    card.classList.add('cal-subscribe-disclosure','collapsed');
    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='cal-subscribe-toggle';
    toggle.innerHTML='<span><b>Calendar subscription</b><small>Apple · Google · Outlook</small></span><i>⌄</i>';
    toggle.setAttribute('aria-expanded','false');
    toggle.setAttribute('aria-label','Show Calendar subscription details');
    toggle.addEventListener('click',()=>{
      const collapsed=card.classList.toggle('collapsed');
      toggle.setAttribute('aria-expanded',collapsed?'false':'true');
      toggle.setAttribute('aria-label',collapsed?'Show Calendar subscription details':'Hide Calendar subscription details');
    });
    card.prepend(toggle);
  };

  const configureVolunteerLanding = () => {
    const path=relativePath();
    const params=new URLSearchParams(window.location.search);
    if(path==='/volunteer-shifts'){
      document.querySelectorAll('a[href]').forEach(anchor=>{
        try{
          const target=new URL(anchor.href,window.location.href);
          if(target.pathname.replace(/\/$/,'').endsWith('/volunteer-readiness')){
            target.searchParams.set('show','1');
            anchor.href=target.toString();
          }
        }catch(_){}
      });
      return false;
    }
    if(path!=='/volunteer-readiness'||params.get('show')==='1') return false;
    const score=document.querySelector('.vol-score b')?.textContent?.trim()||'';
    const match=score.match(/^(\d+)\s*\/\s*(\d+)$/);
    if(!match) return false;
    const current=Number(match[1]),total=Number(match[2]);
    if(current<total) return false;
    window.location.replace(`${appBase}/volunteer-shifts`);
    return true;
  };

  let messageThreadScrolled=false;
  const configureMessageThreadViewport = () => {
    if (relativePath() !== '/messages/thread') return;
    const page=document.querySelector('.comm-page');
    const head=page?.querySelector('.comm-thread-head');
    const participants=page?.querySelector('.comm-participants');
    const layout=page?.querySelector('.comm-thread-layout');
    const composer=document.querySelector('.comm-composer');
    if(!page||!head||!layout||!composer) return;

    let screen=page.querySelector(':scope > .comm-thread-screen');
    if(!screen){
      screen=document.createElement('section');
      screen.className='comm-thread-screen';
      head.before(screen);
      screen.appendChild(head);
      if(participants) screen.appendChild(participants);
      screen.appendChild(layout);
      screen.appendChild(composer);
    }

    const viewportHeight=window.visualViewport?.height||window.innerHeight;
    const top=Math.max(0,screen.getBoundingClientRect().top);
    const tabs=window.innerWidth<=780?document.querySelector('[data-mobile-app-tabs]'):null;
    const bottomInset=tabs?.getBoundingClientRect().height||0;
    const breathingRoom=window.innerWidth<=780?0:14;
    const available=Math.max(260,Math.floor(viewportHeight-top-bottomInset-breathingRoom));
    screen.style.height=`${available}px`;

    const thread=screen.querySelector('.comm-thread');
    if(thread&&!messageThreadScrolled){
      requestAnimationFrame(()=>{
        thread.scrollTop=thread.scrollHeight;
        messageThreadScrolled=true;
      });
    }
  };

  const configureCommunityPostMenus = () => {
    if (relativePath() !== '/channels/view') return;
    const menus=[...document.querySelectorAll('.comm-post-actions')];
    if(!menus.length) return;
    const closeMenus=except=>menus.forEach(menu=>{
      if(menu===except) return;
      menu.classList.remove('open');
      menu.querySelector('.comm-post-menu-toggle')?.setAttribute('aria-expanded','false');
    });
    menus.forEach(menu=>{
      if(menu.querySelector('.comm-post-menu-toggle')) return;
      const button=document.createElement('button');
      button.type='button';
      button.className='comm-post-menu-toggle';
      button.textContent='•••';
      button.setAttribute('aria-label','Post options');
      button.setAttribute('aria-expanded','false');
      button.addEventListener('click',event=>{
        event.stopPropagation();
        const open=!menu.classList.contains('open');
        closeMenus(menu);
        menu.classList.toggle('open',open);
        button.setAttribute('aria-expanded',open?'true':'false');
      });
      menu.prepend(button);
    });
    document.addEventListener('click',()=>closeMenus(null));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMenus(null);});
  };

  const preferMobileAgenda = () => {
    if (window.innerWidth > 780 || relativePath() !== '/calendar') return false;
    const params = new URLSearchParams(window.location.search);
    if (params.has('view')) return false;
    params.set('view', 'agenda');
    const target = `${window.location.pathname}?${params.toString()}`;
    window.location.replace(target);
    return true;
  };

  const applyDeviceMode = () => {
    const width=window.innerWidth;
    document.documentElement.dataset.appViewport=width<=780?'phone':width<=1180?'tablet':'desktop';
    if(width>780)setOpen(false);
    configureMessageThreadViewport();
  };

  if (preferMobileAgenda()) return;
  if (configureVolunteerLanding()) return;
  buildMobileTabs(); configureNativeHeader(); configureHelp(); trimDemoCopy(); configureCommunityCurtain(); configureCalendarSubscription(); configureMessageThreadViewport(); configureCommunityPostMenus(); applyDeviceMode();
  window.addEventListener('resize', applyDeviceMode, {passive:true});
  window.visualViewport?.addEventListener('resize', configureMessageThreadViewport, {passive:true});
})();
