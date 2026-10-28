/* Progressive presentation behavior. Does not submit or alter production data. */
(() => {
  if (!document.body.classList.contains('ux-workspace')) return;
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
