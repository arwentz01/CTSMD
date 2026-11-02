(() => {
  const menu = document.querySelector('.mobile-menu');
  const sidebar = document.querySelector('.sidebar');

  if (menu && sidebar) {
    menu.addEventListener('click', () => {
      const open = sidebar.classList.toggle('mobile-open');
      menu.setAttribute('aria-expanded', String(open));
    });
  }

  document.querySelectorAll('button').forEach((button) => {
    if (button.hasAttribute('disabled')) return;
    button.addEventListener('click', () => {
      if (button.closest('.filter-bar')) {
        button.parentElement.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
      }
    });
  });
})();
