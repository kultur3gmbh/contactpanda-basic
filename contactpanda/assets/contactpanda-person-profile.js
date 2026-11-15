(function () {
 'use strict';
 document.addEventListener('submit', function (event) {
  var form = event.target;
  if (!form.matches('.contactpanda-biography-form')) return;
  event.preventDefault();
  if (window.tinymce) window.tinymce.triggerSave();
  var button = form.querySelector('button[type="submit"]'), message = form.querySelector('[role="status"]');
  button.disabled = true; message.textContent = '';
  fetch(CP_PERSON_PROFILE.ajax, {method:'POST', credentials:'same-origin', body:new FormData(form)})
   .then(function (r) { return r.json(); }).then(function (r) { if (!r.success) throw new Error(r.data && r.data.message || CP_PERSON_PROFILE.error); message.textContent = CP_PERSON_PROFILE.saved; })
   .catch(function (error) { message.textContent = error.message || CP_PERSON_PROFILE.error; })
   .finally(function () { button.disabled = false; });
 });
}());
