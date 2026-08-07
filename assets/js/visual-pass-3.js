(() => {
  const toast = document.querySelector('.vp3-toast');
  let toastTimer;

  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2600);
  };

  document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.dialogOpen);
      if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    });
  });

  document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });

  document.querySelectorAll('[data-preview-action]').forEach((button) => {
    button.addEventListener('click', () => {
      showToast(button.dataset.previewAction || 'Preview only — nothing was changed.');
      button.closest('dialog')?.close();
    });
  });
})();
