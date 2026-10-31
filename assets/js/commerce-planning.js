'use strict';
(() => {
  const rows = document.querySelector('[data-showtime-rows]');
  if (!rows) return;
  const makeRow = () => {
    const row = document.createElement('div');
    row.className = 'commerce-showtime-row';
    for (const field of [
      ['Exact date', 'date', 'dates[]'],
      ['Local time', 'time', 'times[]'],
      ['UTC offset if needed', 'text', 'offsets[]'],
    ]) {
      const label = document.createElement('label');
      label.textContent = field[0];
      const input = document.createElement('input');
      input.type = field[1]; input.name = field[2];
      if (field[1] === 'date') input.dataset.exactDate = '';
      if (field[1] === 'time') input.step = '60';
      if (field[1] === 'text') { input.placeholder = 'For example -04:00'; input.maxLength = 6; }
      label.append(input); row.append(label);
    }
    const remove = document.createElement('button');
    remove.type = 'button'; remove.dataset.removeShowtime = ''; remove.textContent = 'Remove showtime';
    row.append(remove); rows.append(row); return row;
  };
  document.querySelector('[data-add-showtime]')?.addEventListener('click', () => {
    makeRow().querySelector('input').focus();
  });
  rows.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-showtime]');
    if (!button) return;
    button.closest('.commerce-showtime-row').remove();
    if (!rows.children.length) makeRow();
    rows.querySelector('input').focus();
  });
  document.querySelectorAll('[data-calendar-date]').forEach(button => {
    button.addEventListener('click', () => {
      let input = [...rows.querySelectorAll('[data-exact-date]')].find(field => !field.value);
      if (!input) input = makeRow().querySelector('[data-exact-date]');
      input.value = button.dataset.calendarDate;
      input.closest('.commerce-showtime-row').querySelector('[type="time"]').focus();
    });
  });
})();
