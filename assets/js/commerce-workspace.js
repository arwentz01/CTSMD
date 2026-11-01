'use strict';
(() => {
  // Dialogs enhance ordinary links/details; all server routes still work without JS.
  const page = document.querySelector('.commerce-page:last-of-type');
  if (!page) return;
  const openDrawer = (content, title, trigger) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'commerce-page commerce-drawer';
    dialog.setAttribute('aria-label', title);
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'commerce-drawer-close'; close.textContent = 'Close';
    close.addEventListener('click', () => dialog.close());
    const body = document.createElement('div'); body.className = 'commerce-drawer-body'; body.append(content);
    dialog.append(close, body); document.body.append(dialog);
    dialog.addEventListener('close', () => { dialog.remove(); trigger?.focus(); });
    dialog.showModal(); close.focus(); return dialog;
  };
  document.querySelectorAll('[data-order-link]').forEach(link => {
    link.addEventListener('click', async event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault(); link.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(link.href, {credentials:'same-origin'});
        if (!response.ok) throw Error();
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const detail = doc.querySelector('[data-order-detail]');
        if (!detail) throw Error();
        // Submitting a drawer form uses the original detail URL, preserving CSRF and redirects.
        detail.querySelectorAll('form').forEach(form => form.action = link.href);
        openDrawer(detail, 'Order management', link);
      } catch { window.location.assign(link.href); }
      finally { link.removeAttribute('aria-busy'); }
    });
  });
  document.querySelectorAll('.commerce-venue-create').forEach(details => {
    const summary = details.querySelector('summary');
    const button = document.createElement('button'); button.type = 'button'; button.textContent = summary.textContent;
    details.before(button); details.hidden = true;
    button.addEventListener('click', () => {
      const content = document.createElement('div');
      const heading = document.createElement('h2'); heading.textContent = summary.textContent; content.append(heading);
      const fields = details.querySelector('.commerce-columns'); content.append(fields);
      const drawer = openDrawer(content, summary.textContent, button);
      drawer.addEventListener('close', () => details.append(fields));
    });
  });
  const scan = document.querySelector('[data-scan]');
  if (scan && !document.querySelector('[data-commerce-error]')) scan.focus();
  // Keep scope controls relevant without changing the posted allocation contract.
  const scope = document.querySelector('select[name=scope]');
  if (scope) {
    const selected = scope.closest('form').querySelector('fieldset');
    const range = scope.closest('form').querySelector('[name=from]')?.closest('.commerce-columns');
    const update = () => { selected.hidden = scope.value !== 'selected'; if (range) range.hidden = scope.value !== 'range'; };
    scope.addEventListener('change', update); update();
  }
})();
