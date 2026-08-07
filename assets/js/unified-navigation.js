(() => {
  const sidebar = document.querySelector('[data-unified-sidebar]');
  const scrim = document.querySelector('[data-nav-scrim]');
  const open = document.querySelector('[data-nav-open]');
  const close = document.querySelector('[data-nav-close]');

  const setOpen = (value) => {
    if (!sidebar || !scrim) return;
    sidebar.classList.toggle('open', value);
    scrim.classList.toggle('show', value);
    document.body.style.overflow = value ? 'hidden' : '';
  };

  open?.addEventListener('click', () => setOpen(true));
  close?.addEventListener('click', () => setOpen(false));
  scrim?.addEventListener('click', () => setOpen(false));
})();
