/* Disclosure navigation: native links and the existing CSRF-protected logout form. */
(() => {
  document.querySelectorAll('[data-account-menu]').forEach(menu => {
    const trigger = menu.querySelector('summary');
    const items = () => [...menu.querySelectorAll('a[href],button:not([disabled])')];
    const close = (restoreFocus = false) => {
      menu.open = false;
      trigger.setAttribute('aria-expanded', 'false');
      if (restoreFocus) trigger.focus();
    };
    menu.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(menu.open)));
    menu.addEventListener('keydown', event => {
      if (event.key === 'Escape' && menu.open) {
        event.preventDefault(); event.stopPropagation(); close(true);
      } else if (event.target === trigger && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault(); menu.open = true;
        trigger.setAttribute('aria-expanded', 'true');
        const links = items();
        (event.key === 'ArrowUp' ? links.at(-1) : links[0])?.focus();
      }
    });
    document.addEventListener('click', event => { if (!menu.contains(event.target)) close(); });
    document.addEventListener('focusin', event => { if (menu.open && !menu.contains(event.target)) close(); });
  });
})();
