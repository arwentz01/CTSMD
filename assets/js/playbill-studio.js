(() => {
  let dirty = false;
  const status = document.querySelector('[data-save-state]');
  document.querySelectorAll('.studio form').forEach(form => {
    form.addEventListener('input', () => { dirty = true; if (status) status.textContent = 'Unsaved changes. Save this form before previewing or leaving.'; });
    form.addEventListener('submit', () => { dirty = false; });
  });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  document.querySelector('[data-copy-public-url]')?.addEventListener('click', async () => {
    const input = document.querySelector('[data-public-url]');
    const note = document.querySelector('[data-copy-status]');
    try { await navigator.clipboard.writeText(input.value); note.textContent = 'Public URL copied.'; }
    catch { input.focus(); input.select(); note.textContent = 'Select and copy the URL above.'; }
  });
  if (location.hash.startsWith('#person-')) document.getElementById(location.hash.slice(1))?.setAttribute('open', '');
})();
