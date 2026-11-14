/* Obtain one fresh reCAPTCHA token for the actual form submission. */
(function () {
	'use strict';
	var states = new WeakMap();
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) return;
		var field = form.querySelector('.contactpanda-recaptcha-response');
		if (!field) return;
		var state = states.get(form);
		if (state && state.ready) {
			state.ready = false;
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		if (state && state.pending) return;
		state = { pending: true, ready: false };
		states.set(form, state);
		field.value = '';
		var submitter = event.submitter;
		var message = form.querySelector('[data-contactpanda-challenge-error]');
		if (message) message.remove();

		function failed() {
			state.pending = false;
			state.ready = false;
			field.value = '';
			var error = document.createElement('p');
			error.dataset.contactpandaChallengeError = '1';
			error.setAttribute('role', 'alert');
			error.textContent = field.dataset.error;
			field.parentNode.insertBefore(error, field.nextSibling);
		}
		if (!window.grecaptcha || !field.dataset.sitekey) { failed(); return; }
		window.grecaptcha.ready(function () {
			var request;
			try {
				request = window.grecaptcha.execute(field.dataset.sitekey, { action: field.dataset.action });
			} catch (error) { failed(); return; }
			Promise.resolve(request).then(function (token) {
				if (!token) { failed(); return; }
				field.value = token;
				state.pending = false;
				state.ready = true;
				if (submitter && submitter.form === form) {
					HTMLFormElement.prototype.requestSubmit.call(form, submitter);
				} else {
					HTMLFormElement.prototype.requestSubmit.call(form);
				}
				// If native form validation prevented submission, the next attempt
				// still obtains a new token instead of reusing this response.
				state.ready = false;
			}, failed);
		});
	}, true);
}());
