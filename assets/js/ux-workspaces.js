/* Progressive presentation behavior. Does not submit or alter production data. */
(() => {
  // One outline family for the existing destinations. Keep unread badges intact.
  const paths = {
    home: 'M3 10 12 3l9 7v11h-6v-7H9v7H3Z',
    family: 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8m3 9v-3a7 7 0 0 0-3-5',
    production: 'M3 5h18v15H3ZM7 5v15m10-15v15M3 9h18',
    calendar: 'M3 5h18v16H3ZM7 2v6m10-6v6M3 11h18',
    messages: 'M3 4h18v13H9l-6 4ZM3 6l9 6 9-6',
    community: 'M5 3v18M19 3v18M2 8h20M2 16h20',
    video: 'M3 4h18v16H3Zm6 4 7 4-7 4Z',
    operations: 'M3 4h18v16H3Zm0 5h18M9 9v11',
    more: 'M4 12h2m5 0h2m5 0h2',
    theatre: 'M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4Zm13 0v3m0 3v2m0 3v1',
    account: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21v-2a8 8 0 0 1 16 0v2',
    forms: 'M8 3h8v4H8ZM8 5H5v16h14V5h-3M8 12h8m-8 4h6',
    attendance: 'M8 3h8v4H8ZM8 5H5v16h14V5h-3m-8 9 2 2 4-4',
    volunteer: 'M20 5a5 5 0 0 0-8 1 5 5 0 0 0-8-1c-4 5 2 9 8 15 6-6 12-10 8-15Z',
    casting: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21v-2a8 8 0 0 1 16 0v2m-4-5 2 2 4-4',
    resources: 'M3 4h7l2 2h9v15H3Z',
    readiness: 'm12 2 9 4v6c0 5-5 8-9 10-4-2-9-5-9-10V6Zm-4 10 3 3 5-6',
    tasks: 'M9 5h12M9 12h12M9 19h12M2 5l1 1 2-3M2 12l1 1 2-3M2 19l1 1 2-3',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    star: 'm12 3 3 6 6 1-4.5 4.5 1 6.5-5.5-3-5.5 3 1-6.5L3 10l6-1Z',
  };
  const icon = (element, name) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.6'); svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('width', '18'); svg.setAttribute('height', '18');
    const path = document.createElementNS(svg.namespaceURI, 'path');
    path.setAttribute('d', paths[name] || paths.production); svg.appendChild(path);
    const badges = [...element.querySelectorAll('strong')];
    element.replaceChildren(svg, ...badges);
  };
  const destinationIcon = href => /\/app\/?(?:[?#]|$)/.test(href) ? 'home'
    : /notification|push-settings/.test(href) ? 'bell'
    : /family|parent|pickup|onboarding/.test(href) ? 'family'
    : /messages/.test(href) ? 'messages' : /channels|community|teams/.test(href) ? 'community'
    : /videos|rehearsal-library/.test(href) ? 'video' : /calendar|schedule|production\/day/.test(href) ? 'calendar'
    : /casting|\/cast(?:[?#]|$)/.test(href) ? 'casting' : /attendance/.test(href) ? 'attendance'
    : /forms/.test(href) ? 'forms' : /volunteer/.test(href) ? 'volunteer'
    : /tasks/.test(href) ? 'tasks' : /readiness|safeguarding/.test(href) ? 'readiness'
    : /resources|files|playbill/.test(href) ? 'resources' : /account|people|student-profile/.test(href) ? 'account'
    : /operations|\/staff/.test(href) ? 'operations' : /my-theatre|history|archive/.test(href) ? 'theatre'
    : !href ? 'more' : 'production';
  document.querySelectorAll('.unified-nav-item > i,.mobile-app-tab-icon,.pw-tool-links a > i,.prod-resource-grid a > i,.home-action:not(.casting-response) > i').forEach(element => {
    const href = element.closest('a')?.getAttribute('href') || element.closest('.home-action')?.querySelector('.home-action-links a')?.getAttribute('href') || '';
    icon(element, destinationIcon(href));
  });
  if (!document.body.classList.contains('ux-workspace')) return;
  document.querySelectorAll('.staffdash-productions article > span').forEach(element => icon(element, 'star'));
  document.querySelectorAll('.comm-conversation-icon').forEach(element => icon(element, 'messages'));
  document.querySelectorAll('.community-rail-item.active').forEach(link => link.setAttribute('aria-current', 'page'));
  document.querySelectorAll('.unified-nav-item.active').forEach(link => link.setAttribute('aria-current', 'page'));
  const tabs = [...document.querySelectorAll('[data-cast-tab]')];
  const updateTabs = () => tabs.forEach(tab => {
    const selected = tab.getAttribute('aria-selected') === 'true';
    const panel = [...document.querySelectorAll('[data-cast-list]')].find(item => item.dataset.castList === tab.dataset.castTab);
    tab.id = `ux-casting-tab-${tab.dataset.castTab}`;
    tab.tabIndex = selected ? 0 : -1;
    if (panel) {
      panel.id = `ux-casting-panel-${tab.dataset.castTab}`;
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('aria-labelledby', tab.id);
      tab.setAttribute('aria-controls', panel.id);
    }
  });
  updateTabs();
  // Prefer the populated private list when there are no callback decisions.
  const callbackTab = tabs.find(tab => tab.dataset.castTab === 'callback');
  const castTab = tabs.find(tab => tab.dataset.castTab === 'cast');
  if (callbackTab && castTab && Number(callbackTab.querySelector('span')?.textContent) === 0
      && Number(castTab.querySelector('span')?.textContent) > 0) {
    castTab.click();
    updateTabs();
  }
  document.addEventListener('click', event => {
    if (event.target.closest('[data-cast-tab]')) updateTabs();
  });
  tabs.forEach((tab, index) => tab.addEventListener('keydown', event => {
    let next;
    if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
    if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    if (next === undefined) return;
    event.preventDefault();
    tabs[next].click();
    tabs[next].focus();
  }));
})();
