/* Presentation only. Preserve real links, form protocols and server authorization. */
(() => {
  'use strict';
  if (!document.body.classList.contains('ux-member')) return;
  document.querySelectorAll('main table').forEach((table, index) => {
    if (table.closest('.member-table-scroll,[role="region"]')) return;
    const wrap = document.createElement('div');
    wrap.className = 'member-table-scroll'; wrap.tabIndex = 0;
    wrap.setAttribute('role', 'region');
    wrap.setAttribute('aria-label', table.caption?.textContent.trim() || `Scrollable information table ${index + 1}`);
    table.before(wrap); wrap.append(table);
  });
})();
