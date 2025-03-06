/*global dotclear */
'use strict';

dotclear.ready(() => {
  // DOM ready and content loaded

  const data = dotclear.getData('accessible-captcha');

  for (const elt of document.querySelectorAll('.checkboxes-helpers')) {
    dotclear.checkboxesHelpers(
      elt,
      undefined,
      '#accessible-captcha-list td input[type=checkbox]',
      '#accessible-captcha-list #delete',
    );
  }

  dotclear.enableShiftClick('#accessible-captcha-list td input[type=checkbox]');
  dotclear.condSubmit('#accessible-captcha-list td input[type=checkbox]', '#accessible-captcha-list #delete');
  dotclear.responsiveCellHeaders(document.querySelector('#accessible-captcha-list table'), '#accessible-captcha-list table', 1);

  // Ask confirmation before question(s) deletion
  document.getElementById('accessible-captcha-list')?.addEventListener('submit', (event) => {
    const total = document.querySelectorAll('input[name="c_d_questions[]"]').length;
    const number = document.querySelectorAll('input[name="c_d_questions[]"]:checked').length;
    if (number < total) {
      return dotclear.confirm(data.confirm_delete.replace('%s', number), event);
    }
    // Keep at least one question
    window.alert(data.at_least_one);
    event.preventDefault();
    return false;
  });

  // Ask confirmation before reset all questions
  document.getElementById('accessible-captcha-reset')?.addEventListener('submit', (event) => {
    const total = document.querySelectorAll('input[name="c_d_questions[]"]').length;
    if (total) {
      return dotclear.confirm(data.confirm_reset.replace('%s', total), event);
    }
  });
});
