/* Progressive table affordance only: no requests, action rewriting or permission decisions. */
(() => {
  if (!document.body.classList.contains('ux-operations')) return;
  const main = document.querySelector('main.unified-main');
  if (main && !document.querySelector('.ux-skip')) {
    main.id ||= 'ux-main';
    main.tabIndex = -1;
    const skip = document.createElement('a');
    skip.className = 'ux-skip';
    skip.href = `#${main.id}`;
    skip.textContent = 'Skip to main content';
    document.body.prepend(skip);
  }
  document.querySelectorAll('.ops-page table').forEach((table, index) => {
    if (table.parentElement?.classList.contains('ops-table-region')) return;
    if (table.closest('[role="dialog"],.resume-print,.vv-letter')) return;
    const region = document.createElement('div');
    region.className = 'ops-table-region';
    region.tabIndex = 0;
    region.setAttribute('role', 'region');
    const heading = table.closest('.ops-panel,section')?.querySelector('h2,h3');
    region.setAttribute('aria-label', `${table.caption?.textContent.trim() || heading?.textContent.trim() || 'Records'} — scrollable table ${index + 1}`);
    table.before(region);
    region.append(table);
    table.querySelectorAll('thead th:not([scope])').forEach(th => th.scope = 'col');
  });
})();
