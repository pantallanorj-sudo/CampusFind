/* CampusFind - small progressive enhancements only. Every page works without JavaScript. */
(function () {
  'use strict';

  // 1. Confirmation prompts: <form data-confirm="..."> or <button data-confirm="...">.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.getAttribute) { return; }
    var submitter = event.submitter;
    var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    // 2. Prevent double submits and show progress on long forms (form[data-submit-guard]).
    if (form.hasAttribute('data-submit-guard') && !event.defaultPrevented) {
      var buttons = form.querySelectorAll('button[type=submit]');
      window.setTimeout(function () {
        Array.prototype.forEach.call(buttons, function (b) {
          b.setAttribute('data-original', b.textContent);
          b.textContent = 'Saving…';
          b.disabled = true;
        });
      }, 0);
    }
  });
  window.addEventListener('pageshow', function () {   // back button: unlock the form again
    Array.prototype.forEach.call(document.querySelectorAll('button[data-original]'), function (b) {
      b.textContent = b.getAttribute('data-original');
      b.disabled = false;
    });
  });

  // 3. Friendly client-side checks for image inputs (the server validates again, always).
  var allowed = /\.(jpe?g|png|webp)$/i;
  Array.prototype.forEach.call(document.querySelectorAll('input[type=file][data-max-mb]'), function (input) {
    input.addEventListener('change', function () {
      var maxBytes = parseFloat(input.getAttribute('data-max-mb')) * 1024 * 1024;
      var maxFiles = parseInt(input.getAttribute('data-max-files') || '1', 10);
      var problem = '';
      if (input.files.length > maxFiles) { problem = 'Choose at most ' + maxFiles + ' file(s).'; }
      Array.prototype.forEach.call(input.files, function (file) {
        if (!problem && !allowed.test(file.name)) { problem = file.name + ' is not a JPG, PNG or WebP image.'; }
        if (!problem && file.size > maxBytes) { problem = file.name + ' is larger than ' + input.getAttribute('data-max-mb') + ' MB.'; }
      });
      var note = input.parentNode.querySelector('.field-error');
      if (!note) {
        note = document.createElement('p');
        note.className = 'field-error';
        note.setAttribute('role', 'alert');
        input.parentNode.appendChild(note);
      }
      note.textContent = problem;
      if (problem) { input.value = ''; }
    });
  });

  // 4. "Select all" checkbox for bulk actions (archive screen).
  Array.prototype.forEach.call(document.querySelectorAll('[data-select-all]'), function (master) {
    master.addEventListener('change', function () {
      var form = master.form || master.closest('form');
      if (!form) { return; }
      Array.prototype.forEach.call(form.querySelectorAll('input[name="report_ids[]"]'), function (box) { box.checked = master.checked; });
    });
  });
})();
