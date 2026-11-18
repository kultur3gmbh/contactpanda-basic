(function () {
	'use strict';
	function setVatButtonState(button, verified) {
		if (!button) return;
		var defaultLabel = button.dataset.defaultLabel || button.textContent.trim() || 'Validate VAT ID';
		var successLabel = button.dataset.successLabel || 'VAT ID successfully validated';
		button.dataset.defaultLabel = defaultLabel;
		button.classList.toggle('is-valid', Boolean(verified));
		button.textContent = verified ? '✓' : defaultLabel;
		button.setAttribute('aria-label', verified ? successLabel : defaultLabel);
		button.title = verified ? successLabel : '';
	}

	function shippingMatchesBilling(form) {
		var keys = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'];
		return keys.every(function (key) {
			var shipping = form.querySelector('[name="shipping_' + key + '"]');
			var billing = form.querySelector('[name="billing_' + key + '"]');
			return !shipping || !billing || String(shipping.value || '').trim() === String(billing.value || '').trim();
		});
	}

	function configureShippingFields(form, enabled, clearCopied) {
		var panel = form.querySelector('.contactpanda-shipping-account-fields');
		if (!panel) return;
		if (enabled && clearCopied && shippingMatchesBilling(form)) {
			panel.querySelectorAll('[name^="shipping_"]').forEach(function (field) {
				if (field.name !== 'shipping_country') field.value = '';
			});
		}
		panel.hidden = !enabled;
		panel.querySelectorAll('input, select, textarea').forEach(function (field) {
			field.disabled = !enabled;
			var key = field.name.replace(/^shipping_/, '');
			field.required = enabled && ['first_name', 'last_name', 'address_1', 'postcode', 'city', 'country'].indexOf(key) >= 0;
		});
	}
	document.addEventListener('toggle', function (event) {
		if (!event.target.matches || !event.target.matches('.contactpanda-shipping-section')) return;
		var state = event.target.querySelector('[name="shipping_different"]');
		if (state) state.value = event.target.open ? '1' : '0';
	}, true);

	document.addEventListener('invalid', function (event) {
		var details = event.target.closest ? event.target.closest('details') : null;
		if (details) details.open = true;
	}, true);

	document.addEventListener('input', function (event) {
		if (event.target.name !== 'vat_id') return;
		var field = event.target.closest('.contactpanda-vat-field');
		var message = field ? field.querySelector('.contactpanda-vat-message') : null;
		if (message) {
			message.textContent = '';
			message.classList.remove('is-success');
		}
		event.target.dataset.verified = '0';
		setVatButtonState(field ? field.querySelector('.contactpanda-vat-button') : null, false);
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('.contactpanda-profile-phone .contactpanda-phone-national')) return;
		var input = event.target;
		var raw = input.value.trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		if (raw.charAt(0) !== '+') return;
		var select = input.closest('.contactpanda-profile-phone').querySelector('.contactpanda-phone-prefix');
		var options = Array.prototype.slice.call(select.options).sort(function (left, right) { return right.value.length - left.value.length; });
		for (var i = 0; i < options.length; i++) {
			if (raw.indexOf(options[i].value) === 0) {
				select.value = options[i].value;
				var national = raw.slice(options[i].value.length);
				if (options[i].value !== '+39') national = national.replace(/^0/, '');
				input.value = national;
				return;
			}
		}
	});

	document.addEventListener('blur', function (event) {
		if (!event.target.matches('.contactpanda-profile-phone .contactpanda-phone-national')) return;
		var input = event.target;
		var select = input.closest('.contactpanda-profile-phone').querySelector('.contactpanda-phone-prefix');
		var national = input.value.replace(/\D/g, '');
		if (select.value !== '+39') national = national.replace(/^0/, '');
		if (select.value === '+43' && national) {
			var prefix = '';
			['316', '463', '512', '662', '732'].some(function (known) {
				if (national.indexOf(known) === 0) { prefix = known; return true; }
				return false;
			});
			if (!prefix) {
				var mobile = national.match(/^(65[0-9]|66[0-9]|67[0-9]|68[0-9]|69[0-9]|710|720|730|740|780|800|810|820|828|900|901|930|931|939)/);
				if (mobile) prefix = mobile[1];
			}
			if (!prefix && national.charAt(0) === '1') prefix = '1';
			if (!prefix) {
				var local = national.match(/^[2-7][0-9]{3}/);
				if (local) prefix = local[0];
			}
			input.value = prefix && national.length > prefix.length ? prefix + ' ' + national.slice(prefix.length) : national;
		} else {
			input.value = national;
		}
	}, true);

	document.addEventListener('click', function (event) {
		var copyAddress = event.target.closest('.contactpanda-copy-profile-address');
		if (copyAddress) {
			var form = copyAddress.closest('form');
			var profile = {};
			try { profile = JSON.parse(copyAddress.dataset.profile || '{}'); } catch (ignore) {}
			Object.keys(profile).forEach(function (key) {
				var field = form.querySelector('[name="billing_' + key + '"]');
				if (field) field.value = profile[key] || '';
			});
			return;
		}
		var registrationToggle = event.target.closest('.contactpanda-registration-toggle');
		if (registrationToggle) {
			var target = document.getElementById(registrationToggle.getAttribute('aria-controls'));
			if (!target) return;
			var willOpen = target.hidden;
			target.hidden = !willOpen;
			registrationToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			registrationToggle.textContent = willOpen ? (registrationToggle.dataset.closeLabel || 'Close registration') : (registrationToggle.dataset.openLabel || 'Register now');
			if (willOpen) target.scrollIntoView({behavior: 'smooth', block: 'start'});
			return;
		}
		var button = event.target.closest('.contactpanda-vat-button');
		if (!button) return;
		var field = button.closest('.contactpanda-vat-field');
		var input = field ? field.querySelector('[name="vat_id"]') : null;
		var message = field ? field.querySelector('.contactpanda-vat-message') : null;
		var config = typeof CONTACTPANDA_ACCOUNT !== 'undefined' ? CONTACTPANDA_ACCOUNT : (typeof CONTACTPANDA_REGISTRATION !== 'undefined' ? CONTACTPANDA_REGISTRATION : null);
		if (!input || !message || !config) return;
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_registration_validate_vat');
		data.set('nonce', config.vatNonce);
		data.set('value', input.value);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = config.i18n.checkingVat;
		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: data.toString()
		}).then(function (response) {
			return response.json().then(function (result) {
				if (!response.ok || !result.success) throw new Error(result.data && result.data.message ? result.data.message : config.i18n.vatValidationFailed);
				return result;
			});
		}).then(function (result) {
			message.textContent = result.data.message;
			message.classList.add('is-success');
			input.dataset.verified = '1';
			setVatButtonState(button, true);
		}).catch(function (error) {
			message.textContent = error.message || config.i18n.vatValidationFailed;
		}).finally(function () {
			button.disabled = false;
		});
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest ? event.target.closest('.contactpanda-account-ajax-form') : null;
		if (!form || typeof CONTACTPANDA_ACCOUNT === 'undefined') return;
		event.preventDefault();
		var activePanel=form.dataset.activeAddress ? form.querySelector('.contactpanda-address-panel[data-address-prefix="'+form.dataset.activeAddress+'"]') : null;
		var button = activePanel ? activePanel.querySelector('.contactpanda-address-save') : form.querySelector('[type="submit"]');
		var message = activePanel ? activePanel.querySelector('[role="status"]') : form.querySelector('.contactpanda-account-message');
		var original = button ? button.innerHTML : '';
		var data = new FormData(form);
		if(activePanel){form.querySelectorAll('.contactpanda-address-panel').forEach(function(panel){if(panel===activePanel)return;(panel._savedFields||[]).forEach(function(f){if(!f.name)return;if(f.type==='checkbox'){data.delete(f.name);if(f.checked)data.set(f.name,f.value);}else data.set(f.name,f.value);});});}
		data.set('action', form.dataset.ajaxAction);
		data.set('nonce', CONTACTPANDA_ACCOUNT.nonce);
		if (button) {
			button.disabled = true;
			button.textContent = CONTACTPANDA_ACCOUNT.i18n.saving;
		}
		if (message) {
			message.textContent = CONTACTPANDA_ACCOUNT.i18n.saving;
			message.classList.remove('is-success', 'is-error');
		}
		fetch(CONTACTPANDA_ACCOUNT.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data})
			.then(function (response) {
				return response.json().then(function (result) {
					if (!response.ok || !result.success) throw new Error(result.data && result.data.message ? result.data.message : CONTACTPANDA_ACCOUNT.i18n.saveFailed);
					return result;
				});
			})
			.then(function (result) {
				if (message) {
					message.textContent = result.data.message;
					message.classList.add('is-success');
				}
				if(activePanel&&activePanel._finishAddress)activePanel._finishAddress(true);
				var vatInput = form.querySelector('[name="vat_id"]');
				var vatButton = form.querySelector('.contactpanda-vat-button');
				if (vatInput && result.data.vatVerified) vatInput.dataset.verified = '1';
				setVatButtonState(vatButton, Boolean(result.data.vatVerified));
			})
			.catch(function (error) {
				if (message) {
					message.textContent = error.message || CONTACTPANDA_ACCOUNT.i18n.saveFailed;
					message.classList.add('is-error');
				}
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
					button.innerHTML = original;
				}
			});
	}, true);

	document.addEventListener('change', function (event) {
		if (!event.target.matches('.contactpanda-shipping-toggle input')) return;
		configureShippingFields(event.target.closest('form'), event.target.checked, event.target.checked);
	});

	document.querySelectorAll('.contactpanda-shipping-toggle input').forEach(function (toggle) {
		configureShippingFields(toggle.closest('form'), toggle.checked, false);
	});

	document.querySelectorAll('.contactpanda-vat-field').forEach(function (field) {
		var input = field.querySelector('[name="vat_id"]');
		setVatButtonState(field.querySelector('.contactpanda-vat-button'), Boolean(input && input.dataset.verified === '1'));
	});

	var redirectNotice = document.querySelector('[data-contactpanda-redirect]');
	if (redirectNotice) {
		var delay = Math.max(0, parseInt(redirectNotice.dataset.contactpandaDelay || '0', 10));
		window.setTimeout(function () {
			window.location.assign(redirectNotice.dataset.contactpandaRedirect);
		}, delay * 1000);
	}

	var accountRegistration = document.querySelector('.contactpanda-account-registration-form');
	if (accountRegistration && accountRegistration.querySelector('.contactpanda-registration-notice')) {
		accountRegistration.hidden = false;
		var accountToggle = document.querySelector('.contactpanda-registration-toggle');
		if (accountToggle) accountToggle.setAttribute('aria-expanded', 'true');
	}

	var accountOverview = document.querySelector('.contactpanda-account-overview');
	if (accountOverview) {
		var sibling = accountOverview.previousElementSibling;
		while (sibling && sibling.tagName === 'P') {
			sibling.classList.add('contactpanda-default-dashboard-text');
			sibling = sibling.previousElementSibling;
		}
		Array.prototype.slice.call(accountOverview.parentNode.children).forEach(function (node) {
			if (node !== accountOverview && node.children.length === 0 && node.textContent.trim().toLocaleUpperCase() === 'ÜBERSICHTS-BEREICH') node.hidden = true;
		});
	}

	var addressForm = document.querySelector('.contactpanda-address-account-form');
	if (addressForm) {
		var accountContent = addressForm.closest('.woocommerce-MyAccount-content') || addressForm.parentNode;
		Array.prototype.slice.call(accountContent.querySelectorAll('label')).forEach(function (label) {
			if (!label.classList.contains('contactpanda-vat-field') && /EU\s*Umsatzsteuer[\s-]*Identifikationsnummer/i.test(label.textContent)) {
				var row = label.closest('.form-row, p, .woocommerce-form-row') || label;
				row.hidden = true;
			}
		});
	}
}());

(function(){
 'use strict';var form=document.querySelector('.contactpanda-address-account-form'),config=window.CONTACTPANDA_ACCOUNT;if(!form||!config)return;
 form.noValidate=true;var copy=config.i18n,panels=Array.from(form.querySelectorAll('.contactpanda-address-panel'));
 var grid=document.createElement('div');grid.className='contactpanda-address-columns';form.prepend(grid);
 function snapshot(panel){panel._savedFields=Array.from(panel.querySelectorAll('input,select,textarea')).map(function(f){return {element:f,name:f.name,value:f.value,type:f.type,checked:f.checked,disabled:f.disabled};});}
 panels.forEach(function(panel,index){
  var shipping=index===1,heading=panel.querySelector('.contactpanda-account-subheading'),editor=document.createElement('div');editor.className='contactpanda-address-editor';
  var prefix=shipping?'shipping':'billing';panel.dataset.addressPrefix=prefix;panel.hidden=false;panel.classList.remove('contactpanda-shipping-account-fields');
  Array.from(panel.children).forEach(function(child){if(child!==heading)editor.appendChild(child);});
  if(!shipping){var vat=form.querySelector('.contactpanda-vat-field');if(vat)editor.appendChild(vat);}
  var summary=document.createElement('address'),toggle=document.createElement('button'),actions=document.createElement('div'),save=document.createElement('button'),cancel=document.createElement('button'),status=document.createElement('div');
  toggle.type='button';toggle.className='button-link contactpanda-address-edit';toggle.setAttribute('aria-expanded','false');
  actions.className='contactpanda-address-actions';save.type='button';save.className='button contactpanda-address-save';save.innerHTML='<span class="dashicons dashicons-saved" aria-hidden="true"></span>';save.append(document.createTextNode(' '+copy.saveAddress));
  cancel.type='button';cancel.className='button';cancel.innerHTML='<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';cancel.append(document.createTextNode(' '+copy.cancel));status.setAttribute('role','status');status.setAttribute('aria-live','polite');
  function renderSummary(){var names=['first_name','last_name','company','address_1','address_2','postcode','city','country'];var values=names.map(function(key){var field=panel.querySelector('[name="'+prefix+'_'+key+'"]');return field&&field.value||'';}).filter(Boolean);var hasAddress=names.filter(function(key){return key!=='country';}).some(function(key){var f=panel.querySelector('[name="'+prefix+'_'+key+'"]');return f&&f.value.trim();});summary.textContent=hasAddress?values.join(', '):copy.noAddress;toggle.textContent=shipping?(hasAddress?copy.editShipping:copy.addShipping):copy.editBilling;}
  panel._finishAddress=function(saved){if(saved){snapshot(panel);renderSummary();}else(panel._savedFields||[]).forEach(function(f){f.element.value=f.value;f.element.checked=f.checked;f.element.disabled=f.disabled;});editor.hidden=true;summary.hidden=false;toggle.hidden=false;panel.dataset.editing='false';toggle.setAttribute('aria-expanded','false');};
  toggle.onclick=function(){status.textContent='';if(shipping)panel.querySelectorAll('input,select,textarea').forEach(function(f){f.disabled=false;});editor.hidden=false;summary.hidden=true;toggle.hidden=true;panel.dataset.editing='true';toggle.setAttribute('aria-expanded','true');editor.querySelector('input,select').focus();};
  cancel.onclick=function(){panel._finishAddress(false);toggle.focus();};save.onclick=function(){var invalid=Array.from(panel.querySelectorAll('input,select,textarea')).find(function(f){return !f.disabled&&!f.checkValidity();});if(invalid){invalid.reportValidity();return;}form.dataset.activeAddress=prefix;if(shipping){var different=form.querySelector('[name="shipping_different"]');if(different)different.checked=true;panel.querySelectorAll('input,select').forEach(function(f){f.disabled=false;});}form.requestSubmit();};
  actions.append(save,cancel);editor.append(actions);panel.append(summary,toggle,editor,status);grid.append(panel);snapshot(panel);renderSummary();editor.hidden=true;
 });
 var oldSave=form.querySelector('.contactpanda-account-save');if(oldSave)oldSave.hidden=true;
 var different=form.querySelector('.contactpanda-shipping-toggle');if(different)different.hidden=true;
})();
