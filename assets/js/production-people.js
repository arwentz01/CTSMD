/* Native dialog focus, Escape and inert background; links remain usable without JS. */
(() => {
  'use strict';
  document.querySelectorAll('dialog[data-auto-modal]').forEach(dialog => {
    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.addEventListener('cancel', event => {
      event.preventDefault();
      dialog.querySelector('[data-people-close]').click();
    });
  });
  const category = document.getElementById('team-category');
  const functions = document.getElementById('team-function');
  if (!category || !functions) return;
  function sync() {
    for (const option of functions.options) {
      option.hidden = option.dataset.category !== category.value;
      option.disabled = option.hidden;
    }
    if (!functions.selectedOptions.length || functions.selectedOptions[0].disabled) {
      functions.value = [...functions.options].find(option => !option.disabled)?.value || '';
    }
    document.getElementById('team-custom').hidden = functions.value !== 'custom';
    document.querySelector('[name=custom_title]').required = functions.value === 'custom';
  }
  category.addEventListener('change', sync);
  functions.addEventListener('change', sync);
  sync();
})();
