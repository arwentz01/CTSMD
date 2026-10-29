/* Progressive enhancement for native, accessible production management dialogs. */
(() => {
  'use strict';
  let opener = null;
  document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-pl-open]');
    if (openButton) {
      const dialog = document.getElementById(openButton.getAttribute('data-pl-open'));
      if (!(dialog instanceof HTMLDialogElement)) return;
      opener = openButton;
      if (!dialog.open) dialog.showModal();
      return;
    }
    const closeButton = event.target.closest('[data-pl-close]');
    if (closeButton) {
      const dialog = closeButton.closest('dialog');
      if (dialog && dialog.open) dialog.close();
      return;
    }
    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('pl-manage-drawer')) {
      const dialog = event.target;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    }
  });
  document.querySelectorAll('dialog.pl-manage-drawer').forEach(dialog => {
    dialog.addEventListener('close', () => {
      if (opener && opener.isConnected) opener.focus();
      opener = null;
    });
  });
})();
