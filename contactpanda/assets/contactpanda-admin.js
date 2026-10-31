(function () {
	document.addEventListener('change', function (event) {
		if (!event.target.matches('#screen-options-wrap input.hide-column-tog') || !window.CONTACTPANDA_CONFIG || !CONTACTPANDA_CONFIG.screenId) return;
		var hidden = [];
		document.querySelectorAll('#screen-options-wrap input.hide-column-tog').forEach(function (box) { if (!box.checked) hidden.push(box.value); });
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_save_hidden_columns'); data.set('nonce', CONTACTPANDA_CONFIG.columnsNonce); data.set('screen', CONTACTPANDA_CONFIG.screenId);
		hidden.forEach(function (column) { data.append('hidden[]', column); });
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: data.toString()});
	});
	'use strict';
	var i18n = (window.CONTACTPANDA_CONFIG && CONTACTPANDA_CONFIG.i18n) || {};
	function tr(key, fallback) { return i18n[key] || fallback; }
	function format(template, value) { return String(template).replace(/%[sd]/, value); }

	document.addEventListener('click', function (event) {
		var inlineOpen = event.target.closest('.contactpanda-inline-edit-open');
		if (inlineOpen) {
			var inlineRow = inlineOpen.closest('.contactpanda-list-inline-edit');
			if (inlineRow) {
				inlineRow.classList.add('is-editing');
				inlineRow.querySelectorAll('.contactpanda-inline-edit-fields').forEach(function (fields) { fields.hidden = false; });
				inlineRow.querySelectorAll('.contactpanda-inline-edit-display, .contactpanda-inline-edit-open').forEach(function (display) { display.hidden = true; });
				var firstInput = inlineRow.querySelector('.contactpanda-inline-edit-fields input, .contactpanda-inline-edit-fields textarea');
				if (firstInput) firstInput.focus();
			}
			return;
		}
		var inlineCancel = event.target.closest('.contactpanda-inline-edit-cancel');
		if (inlineCancel) {
			var cancelRow = inlineCancel.closest('.contactpanda-list-inline-edit');
			if (cancelRow) {
				cancelRow.classList.remove('is-editing');
				cancelRow.querySelectorAll('.contactpanda-inline-edit-fields').forEach(function (fields) { fields.hidden = true; });
				cancelRow.querySelectorAll('.contactpanda-inline-edit-display, .contactpanda-inline-edit-open').forEach(function (display) { display.hidden = false; });
			}
			return;
		}
		var rollback = event.target.closest('.contactpanda-rollback-import');
		if (rollback && !window.confirm(tr('confirmRollbackImport', 'Roll back this import?'))) { event.preventDefault(); return; }
		var resetOpen = event.target.closest('[data-contactpanda-reset-open]');
		if (resetOpen) {
			var resetForm = resetOpen.closest('.contactpanda-reset-form');
			var resetModal = resetForm && resetForm.parentNode.querySelector('.contactpanda-reset-modal');
			if (resetModal) resetModal.hidden = false;
			return;
		}
		var resetCancel = event.target.closest('.contactpanda-reset-cancel');
		if (resetCancel) {
			resetCancel.closest('.contactpanda-reset-modal').hidden = true;
			return;
		}
		var resetConfirm = event.target.closest('.contactpanda-reset-confirm');
		if (resetConfirm) {
			var modal = resetConfirm.closest('.contactpanda-reset-modal');
			var form = modal && modal.parentNode.querySelector('.contactpanda-reset-form');
			if (form) form.submit();
			return;
		}
		if (!event.target.closest('.contactpanda-token-entry')) {
			document.querySelectorAll('.contactpanda-token-suggestions').forEach(function (list) { list.hidden = true; });
		}
		var invoiceLink = event.target.closest('a.contactpanda-invoice-preview');
		if (invoiceLink) {
			event.preventDefault();
			return openPreviewFrame(invoiceLink.dataset.title || tr('invoice', 'Invoice'), invoiceLink.href, '', invoiceLink.href);
		}
		if (event.target.classList.contains('contactpanda-company-relation-modal')) {
			return toggleLocalModal(event.target, false);
		}
		var button = event.target.closest('button');
		if (!button) return;
		if (button.classList.contains('contactpanda-settings-color-swatch')) {
			var colorField = button.closest('.contactpanda-settings-color-field');
			var colorInput = colorField && colorField.querySelector('input[type="color"]');
			if (colorInput) {
				colorInput.value = button.dataset.color;
				var colorValue = colorField.querySelector('.contactpanda-settings-color-value');
				if (colorValue) colorValue.textContent = button.dataset.color;
				colorInput.dispatchEvent(new Event('change', {bubbles: true}));
			}
			return;
		}
		if (button.classList.contains('contactpanda-tab')) return activateTab(button);
		if (button.classList.contains('contactpanda-open-consent')) return openConsent(button.closest('.contactpanda-marketing'));
		if (button.classList.contains('contactpanda-close-consent')) return closeConsent(button.closest('.contactpanda-marketing'));
		if (button.classList.contains('contactpanda-save-consent')) return saveConsent(button.closest('.contactpanda-marketing'));
		if (button.classList.contains('contactpanda-open-bulk')) return document.getElementById('contactpanda-bulk-action').value === 'newsletter' ? openNewsletterSelection() : openBulkMarketing();
		if (button.classList.contains('contactpanda-close-bulk')) return closeBulkMarketing();
		if (button.classList.contains('contactpanda-close-newsletter-selection')) return closeNewsletterSelection();
		if (button.classList.contains('contactpanda-select-all-matching')) return selectAllMatchingPeople();
		if (button.classList.contains('contactpanda-save-bulk')) return saveBulkMarketing();
		if (button.classList.contains('contactpanda-open-export')) return openContactOutput('export');
		if (button.classList.contains('contactpanda-open-print')) return openContactOutput('print');
		if (button.classList.contains('contactpanda-close-output')) return closeContactOutput();
		if (button.classList.contains('contactpanda-open-new-person')) return openNewPerson();
		if (button.classList.contains('contactpanda-close-new-person')) return closeNewPerson();
		if (button.classList.contains('contactpanda-save-new-person')) return saveNewPerson();
		if (button.classList.contains('contactpanda-open-new-company')) return openNewCompany();
		if (button.classList.contains('contactpanda-close-new-company')) return closeNewCompany();
		if (button.classList.contains('contactpanda-save-new-company')) return saveNewCompany();
		if (button.classList.contains('contactpanda-preview-broadcast')) return previewBroadcast(button);
		if (button.classList.contains('contactpanda-preview-order')) return previewRecord(button, 'contactpanda_preview_order', 'order_id', button.dataset.order, tr('order', 'Order'));
		if (button.classList.contains('contactpanda-preview-event')) return previewRecord(button, 'contactpanda_preview_event', 'event_id', button.dataset.event, tr('event', 'Event'));
		if (button.classList.contains('contactpanda-close-preview')) return closePreviewFrame();
		if (button.classList.contains('contactpanda-open-roles')) return toggleLocalModal(button.closest('.contactpanda-master-data').querySelector('.contactpanda-roles-modal'), true);
		if (button.classList.contains('contactpanda-close-roles')) return toggleLocalModal(button.closest('.contactpanda-roles-modal'), false);
		if (button.classList.contains('contactpanda-save-roles')) return saveRoles(button);
		if (button.classList.contains('contactpanda-open-user-account')) return toggleLocalModal(button.closest('.contactpanda-master-data').querySelector('.contactpanda-user-account-modal'), true);
		if (button.classList.contains('contactpanda-close-user-account')) return toggleLocalModal(button.closest('.contactpanda-user-account-modal'), false);
		if (button.classList.contains('contactpanda-save-user-account')) return saveUserAccount(button);
		if (button.classList.contains('contactpanda-tag-suggestion')) return manageTag(button, 'add');
		if (button.classList.contains('contactpanda-tags-summary')) return openTags(button.closest('.contactpanda-tags-card'));
		if (button.classList.contains('contactpanda-close-tags')) return closeTags(button.closest('.contactpanda-tags-card'));
		if (button.classList.contains('contactpanda-role-suggestion')) return addRole(button);
		if (button.classList.contains('contactpanda-remove-tag')) return manageTag(button, 'remove');
		if (button.classList.contains('contactpanda-remove-role')) return button.closest('.contactpanda-role-token').remove();
		if (button.classList.contains('contactpanda-save-address')) return saveAddress(button.closest('.contactpanda-address-form'));
		if (button.classList.contains('contactpanda-salutation-value')) return openSalutation(button.closest('.contactpanda-salutation-field'));
		if (button.classList.contains('contactpanda-cancel-salutation')) return closeSalutation(button.closest('.contactpanda-salutation-field'));
		if (button.classList.contains('contactpanda-add-channel')) return addContactChannel(button.closest('.contactpanda-channel-group'));
		if (button.classList.contains('contactpanda-channel-value')) return openContactChannel(button.closest('.contactpanda-channel-row'));
		if (button.classList.contains('contactpanda-cancel-channel')) return closeContactChannel(button.closest('.contactpanda-channel-row'));
		if (button.classList.contains('contactpanda-save-channel')) return saveContactChannel(button.closest('.contactpanda-channel-row'));
		if (button.classList.contains('contactpanda-delete-channel')) return deleteContactChannel(button.closest('.contactpanda-channel-row'));
		if (button.classList.contains('contactpanda-open-company-relation')) {
			return toggleLocalModal(document.getElementById(button.dataset.modal || ''), true);
		}
		if (button.classList.contains('contactpanda-close-company-relation')) {
			return toggleLocalModal(button.closest('.contactpanda-company-relation-modal'), false);
		}
		if (button.classList.contains('contactpanda-edit-address-summary')) {
			var addressTab = document.querySelector('.contactpanda-tab[aria-controls$="-addresses"]');
			if (addressTab) {
				activateTab(addressTab);
				var addressPanel = document.getElementById(addressTab.getAttribute('aria-controls'));
				var firstAddressInput = addressPanel ? addressPanel.querySelector('.contactpanda-address-form input:not([disabled])') : null;
				if (firstAddressInput) firstAddressInput.focus();
			}
			return;
		}

		var field = button.closest('.contactpanda-editable');
		if (!field) return;
		if (button.classList.contains('contactpanda-edit')) startEditing(field);
		if (button.classList.contains('contactpanda-cancel')) stopEditing(field);
		if (button.classList.contains('contactpanda-save')) saveField(field);
		if (button.classList.contains('contactpanda-validate-vat')) validateVat(field);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			var companyModal = document.querySelector('.contactpanda-company-relation-modal:not([hidden])');
			if (companyModal) {
				toggleLocalModal(companyModal, false);
				return;
			}
		}
		if (event.key === 'Escape' && event.target.classList.contains('contactpanda-token-input')) {
			closeTokenSuggestions(event.target);
			return;
		}
		if (event.key === 'Enter' && event.target.classList.contains('contactpanda-tag-input')) {
			event.preventDefault();
			return manageTag(event.target, 'commit');
		}
		if (event.key === 'Enter' && event.target.classList.contains('contactpanda-role-input')) {
			event.preventDefault();
			return addRole(event.target);
		}
		var tab = event.target.closest('.contactpanda-tab');
		if (!tab || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) return;
		var tabs = Array.prototype.slice.call(tab.closest('[role="tablist"]').querySelectorAll('.contactpanda-tab'));
		var index = tabs.indexOf(tab);
		if (event.key === 'Home') index = 0;
		else if (event.key === 'End') index = tabs.length - 1;
		else index = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
		event.preventDefault();
		activateTab(tabs[index]);
		tabs[index].focus();
	});

	document.addEventListener('change', function (event) {
		if (event.target.closest('.contactpanda-central-library-form')) updateCentralLibraryForm(event.target.closest('.contactpanda-central-library-form'));
		if (event.target.classList.contains('contactpanda-salutation-select')) saveSalutation(event.target);
		if (event.target.classList.contains('contactpanda-new-salutation')) event.target.dataset.autoAssigned = '0';
		if (event.target.classList.contains('contactpanda-consent-decision')) updateConsentConfirmation(event.target.closest('.contactpanda-marketing'));
		if (event.target.classList.contains('contactpanda-select-all')) {
			allMatchingPeopleSelected = false;
			document.querySelectorAll('.contactpanda-person-select').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
			updateBulkSelection();
		}
		if (event.target.classList.contains('contactpanda-person-select')) allMatchingPeopleSelected = false;
		if (event.target.classList.contains('contactpanda-person-select') || event.target.id === 'contactpanda-bulk-action') updateBulkSelection();
	});

	function updateCentralLibraryForm(form) {
		if (!form) return;
		var enabled = form.querySelector('[name="enabled"]');
		var confirmed = form.querySelector('[name="confirmed"]');
		var auto = form.querySelector('[name="auto"]');
		var save = form.querySelector('[name="save"]');
		if (auto) auto.disabled = !enabled.checked;
		if (save) save.disabled = !!(enabled.checked && confirmed && !confirmed.checked);
	}

	document.querySelectorAll('.contactpanda-central-library-form').forEach(updateCentralLibraryForm);

	document.addEventListener('input', function (event) {
		if (!event.target.classList.contains('contactpanda-new-first-name')) return;
		updateNewPersonSalutationSuggestion(event.target.closest('.contactpanda-new-person-modal'));
	});

	document.addEventListener('click', function (event) {
		var suggestion = event.target.closest('.contactpanda-salutation-suggestion');
		if (!suggestion) return;
		var field = suggestion.closest('.contactpanda-salutation-field');
		var select = field.querySelector('.contactpanda-salutation-select');
		select.value = suggestion.dataset.value;
		saveSalutation(select);
	});

	function saveSalutation(select) {
		var field = select.closest('.contactpanda-salutation-field');
		var message = field.querySelector('.contactpanda-field-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_update_contact_field'); data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', field.dataset.contact); data.set('field', 'anrede'); data.set('value', select.value);
		select.disabled = true; message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			message.textContent = tr('saved', 'Saved.'); message.classList.add('is-success');
			var value = field.querySelector('.contactpanda-salutation-value');
			if (value) { value.dataset.value = select.value; value.textContent = result.data.display; value.classList.toggle('is-empty', !select.value); }
			var suggestion = field.querySelector('.contactpanda-salutation-suggestion'); if (suggestion) suggestion.remove();
			closeSalutation(field, true);
		}).catch(function (error) { message.textContent = error.message; }).finally(function () { select.disabled = false; });
	}

	function openSalutation(field) {
		if (!field) return;
		var editor = field.querySelector('.contactpanda-salutation-editor');
		var value = field.querySelector('.contactpanda-salutation-value');
		if (editor) editor.hidden = false;
		if (value) value.hidden = true;
		var select = editor && editor.querySelector('select');
		if (select) select.focus();
	}

	function closeSalutation(field, keepValue) {
		if (!field) return;
		var editor = field.querySelector('.contactpanda-salutation-editor');
		var value = field.querySelector('.contactpanda-salutation-value');
		var select = editor && editor.querySelector('select');
		if (editor) editor.hidden = true;
		if (value) value.hidden = false;
		if (!keepValue && select && value) select.value = value.dataset.value || '';
	}

	function openContactChannel(row) {
		if (!row) return;
		var editor = row.querySelector('.contactpanda-channel-editor');
		var summary = row.querySelector('.contactpanda-channel-summary');
		if (editor) editor.hidden = false;
		if (summary) summary.hidden = true;
		var input = row.querySelector('.contactpanda-channel-input, .contactpanda-phone-national');
		if (input) input.focus();
	}

	function closeContactChannel(row) {
		if (!row) return;
		if (row.classList.contains('is-new')) {
			var list = row.parentNode;
			row.remove();
			var empty = list && list.querySelector('.contactpanda-channel-empty');
			if (empty && !list.querySelector('.contactpanda-channel-row')) empty.hidden = false;
			return;
		}
		var editor = row.querySelector('.contactpanda-channel-editor');
		var summary = row.querySelector('.contactpanda-channel-summary');
		if (editor) editor.hidden = true;
		if (summary) summary.hidden = false;
	}

	function addContactChannel(group) {
		if (!group || group.querySelector('.contactpanda-channel-row.is-new')) return;
		var template = group.querySelector('.contactpanda-channel-template');
		var list = group.querySelector('.contactpanda-channel-list');
		if (!template || !list) return;
		var row = template.content.firstElementChild.cloneNode(true);
		list.insertBefore(row, list.firstChild);
		var empty = list.querySelector('.contactpanda-channel-empty');
		if (empty) empty.hidden = true;
		openContactChannel(row);
	}

	function saveContactChannel(row) {
		if (!row) return;
		var button = row.querySelector('.contactpanda-save-channel');
		var group = row.closest('.contactpanda-channel-group');
		var message = group.querySelector('.contactpanda-channel-message');
		var type = row.dataset.channelType;
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_save_contact_channel');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', row.dataset.contact);
		data.set('channel_id', row.dataset.channelId || '');
		data.set('channel_type', type);
		data.set('channel_label', row.querySelector('.contactpanda-channel-label-select').value);
		data.set('value', type === 'phone' ? composePhone(row.querySelector('.contactpanda-phone-input-group')) : row.querySelector('.contactpanda-channel-input').value);
		data.set('is_primary', row.querySelector('.contactpanda-channel-primary-input').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			row.dataset.channelId = result.data.id;
			row.dataset.primary = result.data.is_primary ? '1' : '0';
			row.classList.remove('is-new');
			var value = row.querySelector('.contactpanda-channel-value');
			value.dataset.value = result.data.value;
			value.textContent = result.data.display;
			row.querySelector('.contactpanda-channel-label').textContent = result.data.label_text;
			if (type === 'email') row.querySelector('.contactpanda-channel-marketing').textContent = result.data.marketing_label;
			if (result.data.is_primary) {
				group.querySelectorAll('.contactpanda-channel-row').forEach(function (other) {
					var own = other === row;
					other.dataset.primary = own ? '1' : '0';
					var badge = other.querySelector('.contactpanda-primary-badge'); if (badge) badge.hidden = !own;
					var primary = other.querySelector('.contactpanda-channel-primary-input'); if (primary) { primary.checked = own; primary.disabled = own; }
				});
			}
			var deleteButton = row.querySelector('.contactpanda-delete-channel');
			if (deleteButton) deleteButton.hidden = !!result.data.is_primary;
			button.disabled = false;
			closeContactChannel(row);
			message.textContent = tr('saved', 'Saved.'); message.classList.add('is-success');
			window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2500);
		}).catch(function (error) { button.disabled = false; message.textContent = error.message; });
	}

	function deleteContactChannel(row) {
		if (!row || !window.confirm(tr('confirmDeleteChannel', 'Remove this contact detail?'))) return;
		var group = row.closest('.contactpanda-channel-group');
		var message = group.querySelector('.contactpanda-channel-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_delete_contact_channel'); data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', row.dataset.contact); data.set('channel_id', row.dataset.channelId);
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
			row.remove(); message.textContent = result.data.message; message.classList.add('is-success');
		}).catch(function (error) { message.textContent = error.message; });
	}

	document.addEventListener('submit', function (event) {
		if (!event.target.classList.contains('contactpanda-output-form')) return;
		var form = event.target;
		form.querySelector('input[name="action"]').value = event.submitter && event.submitter.classList.contains('contactpanda-export-vcard') ? 'contactpanda_export_contacts_vcard' : 'contactpanda_export_contacts';
		var selectedScope = form.querySelector('input[name="scope"]:checked');
		var selected = selectedContactIds();
		if (selectedScope && selectedScope.value === 'selected' && !selected.length) {
			event.preventDefault();
			form.querySelector('.contactpanda-modal-message').textContent = tr('chooseOutputScope', 'Choose whether selected people or all filtered people should be used.');
			return;
		}
		form.querySelector('input[name="selected_ids"]').value = selected.join(',');
		window.setTimeout(closeContactOutput, 250);
	});

	document.addEventListener('input', function (event) {
		if (event.target.classList.contains('contactpanda-phone-national')) detectPhonePrefix(event.target);
		if (event.target.classList.contains('contactpanda-token-input')) updateTokenSuggestions(event.target);
		if (event.target.classList.contains('contactpanda-new-company-name')) {
			var modal = event.target.closest('.contactpanda-new-person-modal');
			var hidden = modal ? modal.querySelector('.contactpanda-new-company-id') : null;
			var option = Array.prototype.slice.call(document.querySelectorAll('#contactpanda-new-company-options option')).find(function (item) {
				return item.value.toLocaleLowerCase() === event.target.value.trim().toLocaleLowerCase();
			});
			if (hidden) hidden.value = option ? option.dataset.companyId : '';
		}
	});

	document.addEventListener('blur', function (event) {
		if (!event.target.classList.contains('contactpanda-phone-national')) return;
		formatPhoneNational(event.target);
	}, true);

	document.addEventListener('focusin', function (event) {
		if (event.target.classList.contains('contactpanda-token-input')) updateTokenSuggestions(event.target);
	});

	function activateTab(tab) {
		var container = tab.closest('[data-tabs]');
		if (!container) return;
		container.querySelectorAll('.contactpanda-tab').forEach(function (item) {
			var selected = item === tab;
			item.classList.toggle('is-active', selected);
			item.setAttribute('aria-selected', selected ? 'true' : 'false');
			item.tabIndex = selected ? 0 : -1;
		});
		container.querySelectorAll('.contactpanda-tab-panel').forEach(function (panel) {
			var selected = panel.id === tab.getAttribute('aria-controls');
			panel.hidden = !selected;
			panel.classList.toggle('is-active', selected);
		});
	}

	function startEditing(field) {
		if (!field || field.classList.contains('is-editing')) return;
		var valueNode = field.querySelector('.contactpanda-field-value');
		var multiline = field.dataset.multiline === '1';
		var isPhone = field.dataset.type === 'phone';
		var input = document.createElement(multiline ? 'textarea' : 'input');
		input.className = 'contactpanda-input';
		input.value = valueNode.dataset.value || '';
		field.dataset.verifiedOriginal = field.dataset.verified || '0';
		if (!multiline) input.type = field.dataset.field === 'domain' ? 'url' : (field.dataset.field === 'email' ? 'email' : 'text');
		field.classList.add('is-editing');
		field.querySelector('.contactpanda-field-row').hidden = true;
		var editor = document.createElement('div');
		editor.className = 'contactpanda-editor';
		if (isPhone) editor.appendChild(buildPhoneGroup(valueNode.dataset.value || ''));
		else editor.appendChild(input);
		var validateButton = field.dataset.field === 'uid' ? '<button type="button" class="button contactpanda-validate-vat">' + tr('validateVat', 'Validate VAT ID') + '</button>' : '';
		var compactActions = field.classList.contains('contactpanda-list-editable');
		var saveLabel = tr('save', 'Save');
		var cancelLabel = tr('cancel', 'Cancel');
		var saveContent = compactActions ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">' + saveLabel + '</span>' : saveLabel;
		var cancelContent = compactActions ? '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text">' + cancelLabel + '</span>' : cancelLabel;
		editor.insertAdjacentHTML('beforeend', '<div class="contactpanda-editor-actions"><button type="button" class="button button-primary contactpanda-save" aria-label="' + saveLabel + '">' + saveContent + '</button>' + validateButton + '<button type="button" class="button contactpanda-cancel" aria-label="' + cancelLabel + '">' + cancelContent + '</button></div>');
		field.querySelector('.contactpanda-field-message').before(editor);
		(isPhone ? editor.querySelector('.contactpanda-phone-national') : input).focus();
	}

	function validateVat(field) {
		var input = field.querySelector('.contactpanda-input');
		var button = field.querySelector('.contactpanda-validate-vat');
		var message = field.querySelector('.contactpanda-field-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_validate_vat');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('value', field.dataset.type === 'phone' ? composePhone(field.querySelector('.contactpanda-phone-input-group')) : input.value);
		if (field.dataset.entity === 'contact') data.set('contact_id', field.dataset.contact);
		else data.set('company_id', field.dataset.company);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('checkingVat', 'Checking EU VIES…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				message.textContent = result.data && result.data.message ? result.data.message : tr('noUsableResponse', 'No usable response was received.');
				message.classList.toggle('is-success', !!result.success);
				button.disabled = false;
				setVatCheck(field, !!result.success);
			})
			.catch(function (error) {
				message.textContent = error.message || tr('vatValidationFailed', 'VAT ID validation could not be completed.');
				button.disabled = false;
			});
	}

	function stopEditing(field, keepVerification) {
		var editor = field.querySelector('.contactpanda-editor');
		if (editor) editor.remove();
		if (!keepVerification && field.dataset.field === 'uid') setVatCheck(field, field.dataset.verifiedOriginal === '1');
		field.classList.remove('is-editing', 'is-saving');
		field.querySelector('.contactpanda-field-row').hidden = false;
		field.querySelector('.contactpanda-field-message').textContent = '';
	}

	function saveField(field) {
		var input = field.querySelector('.contactpanda-input');
		var saveButton = field.querySelector('.contactpanda-save');
		var message = field.querySelector('.contactpanda-field-message');
		var isContact = field.dataset.entity === 'contact';
		var data = new URLSearchParams();
		data.set('action', isContact ? 'contactpanda_update_contact_field' : 'contactpanda_update_company_field');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set(isContact ? 'contact_id' : 'company_id', isContact ? field.dataset.contact : field.dataset.company);
		data.set('field', field.dataset.field);
		data.set('value', field.dataset.type === 'phone' ? composePhone(field.querySelector('.contactpanda-phone-input-group')) : input.value);
		field.classList.add('is-saving');
		saveButton.disabled = true;
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				var valueNode = field.querySelector('.contactpanda-field-value');
				valueNode.dataset.value = result.data.value;
				valueNode.textContent = result.data.display;
				if (field.dataset.field === 'email') {
					var mailLink = field.querySelector('.contactpanda-mail-link');
					if (mailLink) mailLink.href = 'mailto:' + result.data.value;
				}
				if (field.dataset.field === 'uid') setVatCheck(field, !!result.data.verified);
				if (field.dataset.field === 'first_name' && result.data.salutation) {
					var salutation = document.querySelector('.contactpanda-salutation-field[data-contact="' + field.dataset.contact + '"] .contactpanda-salutation-select');
					if (salutation && !salutation.value) salutation.value = result.data.salutation;
				}
				stopEditing(field, true);
				message.textContent = tr('saved', 'Saved.');
				message.classList.add('is-success');
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2500);
			})
			.catch(function (error) {
				field.classList.remove('is-saving');
				saveButton.disabled = false;
				message.textContent = error.message;
				message.classList.remove('is-success');
			});
	}

	function setVatCheck(field, verified) {
		var check = field.querySelector('.contactpanda-vat-check');
		if (!check) return;
		check.hidden = !verified;
		field.dataset.verified = verified ? '1' : '0';
	}

	function openConsent(card) {
		card.querySelector('.contactpanda-consent-modal').hidden = false;
		document.body.classList.add('contactpanda-modal-open');
		card.querySelector('.contactpanda-consent-confirmed').checked = false;
		card.querySelector('.contactpanda-modal-message').textContent = '';
		updateConsentConfirmation(card);
		card.querySelector('.contactpanda-consent-decision').focus();
	}

	function closeConsent(card) {
		card.querySelector('.contactpanda-consent-modal').hidden = true;
		document.body.classList.remove('contactpanda-modal-open');
	}

	function updateConsentConfirmation(card) {
		var decision = card.querySelector('.contactpanda-consent-decision').value;
		var warning = card.querySelector('.contactpanda-consent-override-warning');
		if (warning) warning.hidden = decision !== 'block' && decision !== 'unblock';
		if (decision === 'revoke') card.querySelector('.contactpanda-confirm span').textContent = tr('confirmWithdrawal', 'I confirm that the withdrawal or unsubscribe has actually been documented.');
		else if (decision === 'block') card.querySelector('.contactpanda-confirm span').textContent = tr('confirmBlock', 'I confirm this permanent administrator block and have documented the reason.');
		else if (decision === 'unblock') card.querySelector('.contactpanda-confirm span').textContent = tr('confirmUnblock', 'I confirm this administrator override and understand that the address remains unconfirmed.');
		else card.querySelector('.contactpanda-confirm span').textContent = tr('confirmConsent', 'I confirm that explicit consent has been given.');
	}

	function saveConsent(card) {
		var button = card.querySelector('.contactpanda-save-consent');
		var message = card.querySelector('.contactpanda-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_update_marketing_consent');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', card.dataset.contact);
		data.set('decision', card.querySelector('.contactpanda-consent-decision').value);
		data.set('source', card.querySelector('.contactpanda-consent-source').value);
		data.set('date', card.querySelector('.contactpanda-consent-date').value);
		data.set('note', card.querySelector('.contactpanda-consent-note').value);
		data.set('confirmed', card.querySelector('.contactpanda-consent-confirmed').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('savingConfirmed', 'Saving with confirmation…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 900);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function selectedContactIds() {
		return Array.prototype.slice.call(document.querySelectorAll('.contactpanda-person-select:checked')).map(function (checkbox) { return checkbox.value; });
	}

	var allMatchingPeopleSelected = false;

	function selectAllMatchingPeople() {
		var toolbar = document.querySelector('.contactpanda-bulk-toolbar');
		if (!toolbar) return;
		allMatchingPeopleSelected = true;
		var label = toolbar.querySelector('.contactpanda-bulk-selection');
		var link = toolbar.querySelector('.contactpanda-select-all-matching');
		if (label) label.textContent = format(tr('peopleSelected', '%d people selected'), Number(toolbar.dataset.total || 0));
		if (link) link.hidden = true;
		updateBulkSelection();
	}

	function updateBulkSelection() {
		var selected = selectedContactIds();
		var checkboxes = document.querySelectorAll('.contactpanda-person-select');
		var selectAll = document.querySelector('.contactpanda-select-all');
		var action = document.getElementById('contactpanda-bulk-action');
		var button = document.querySelector('.contactpanda-open-bulk');
		var label = document.querySelector('.contactpanda-bulk-selection');
		var toolbar = document.querySelector('.contactpanda-bulk-toolbar');
		var allMatching = toolbar ? toolbar.querySelector('.contactpanda-select-all-matching') : null;
		if (selectAll) {
			selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
		}
		if (label && !allMatchingPeopleSelected) label.textContent = selected.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), selected.length);
		if (allMatching) allMatching.hidden = allMatchingPeopleSelected || !selectAll || !selectAll.checked || !toolbar || Number(toolbar.dataset.total || 0) <= checkboxes.length;
		if (button) button.disabled = !(selected.length || allMatchingPeopleSelected) || !action || ['marketable', 'newsletter'].indexOf(action.value) === -1 || (action.value === 'marketable' && allMatchingPeopleSelected);
		var outputModal = document.querySelector('.contactpanda-output-modal');
		if (outputModal) {
			var selectedOption = outputModal.querySelector('input[name="scope"][value="selected"]');
			var selectedLabel = outputModal.querySelector('.contactpanda-output-selected-label');
			if (selectedOption) selectedOption.disabled = !selected.length;
			if (selectedLabel) selectedLabel.textContent = selected.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), selected.length);
		}
	}

	function openNewsletterSelection() {
		var modal = document.querySelector('.contactpanda-newsletter-selection-modal');
		var toolbar = document.querySelector('.contactpanda-bulk-toolbar');
		if (!modal || !toolbar || !CONTACTPANDA_CONFIG.campaignUrl) return;
		var ids = selectedContactIds();
		var url = new URL(CONTACTPANDA_CONFIG.campaignUrl, window.location.href);
		if (allMatchingPeopleSelected) url.searchParams.set('people_selection', toolbar.dataset.listToken || '');
		else url.searchParams.set('contact_ids', ids.join(','));
		modal.querySelector('.contactpanda-newsletter-selection-frame').src = url.toString();
		modal.hidden = false;
		document.body.classList.add('contactpanda-modal-open');
	}

	function closeNewsletterSelection() {
		var modal = document.querySelector('.contactpanda-newsletter-selection-modal');
		if (modal) {
			modal.hidden = true;
			modal.querySelector('.contactpanda-newsletter-selection-frame').src = 'about:blank';
		}
		document.body.classList.remove('contactpanda-modal-open');
	}

	function openContactOutput(mode) {
		var modal = document.querySelector('.contactpanda-output-modal');
		if (!modal) return;
		var form = modal.querySelector('.contactpanda-output-form');
		var isPrint = mode === 'print';
		var selected = selectedContactIds();
		modal.hidden = false;
		document.body.classList.add('contactpanda-modal-open');
		modal.querySelector('#contactpanda-output-title').textContent = isPrint ? tr('printContactList', 'Print contact list') : tr('exportContacts', 'Export contacts');
		modal.querySelector('.contactpanda-output-columns').hidden = false;
		modal.querySelector('.contactpanda-submit-output').textContent = isPrint ? tr('printContactList', 'Print contact list') : tr('exportContacts', 'Export contacts');
		form.querySelector('input[name="action"]').value = isPrint ? 'contactpanda_print_contacts' : 'contactpanda_export_contacts';
		form.target = isPrint ? '_blank' : '_self';
		form.querySelector('input[name="selected_ids"]').value = selected.join(',');
		var selectedOption = form.querySelector('input[name="scope"][value="selected"]');
		selectedOption.disabled = !selected.length;
		if (!selected.length && selectedOption.checked) form.querySelector('input[name="scope"][value="all"]').checked = true;
		updateBulkSelection();
	}

	function closeContactOutput() {
		var modal = document.querySelector('.contactpanda-output-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contactpanda-modal-open');
	}

	function openBulkMarketing() {
		var ids = selectedContactIds();
		var modal = document.querySelector('.contactpanda-bulk-modal');
		if (!ids.length || !modal) return;
		modal.hidden = false;
		document.body.classList.add('contactpanda-modal-open');
		modal.querySelector('.contactpanda-bulk-confirmed').checked = false;
		modal.querySelector('.contactpanda-modal-message').textContent = '';
		modal.querySelector('.contactpanda-bulk-modal-count strong').textContent = ids.length === 1 ? tr('onePersonSelected', '1 person selected') : format(tr('peopleSelected', '%d people selected'), ids.length);
		modal.querySelector('.contactpanda-bulk-source').focus();
	}

	function closeBulkMarketing() {
		var modal = document.querySelector('.contactpanda-bulk-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contactpanda-modal-open');
	}

	function saveBulkMarketing() {
		var modal = document.querySelector('.contactpanda-bulk-modal');
		var ids = selectedContactIds();
		if (!modal || !ids.length) return;
		var button = modal.querySelector('.contactpanda-save-bulk');
		var message = modal.querySelector('.contactpanda-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_bulk_marketing_consent');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		ids.forEach(function (id) { data.append('contact_ids[]', id); });
		data.set('source', modal.querySelector('.contactpanda-bulk-source').value);
		data.set('date', modal.querySelector('.contactpanda-bulk-date').value);
		data.set('note', modal.querySelector('.contactpanda-bulk-note').value);
		data.set('confirmed', modal.querySelector('.contactpanda-bulk-confirmed').checked ? '1' : '0');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('checkingContacts', 'Checking the selected contacts…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('bulkEditFailed', 'Bulk editing failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 1600);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openNewPerson() {
		var modal = document.querySelector('.contactpanda-new-person-modal');
		if (!modal) return;
		modal.hidden = false;
		document.body.classList.add('contactpanda-modal-open');
		modal.querySelector('.contactpanda-modal-message').textContent = '';
		modal.querySelector('.contactpanda-new-first-name').focus();
		updateNewPersonSalutationSuggestion(modal);
	}

	function updateNewPersonSalutationSuggestion(modal) {
		if (!modal) return;
		var input = modal.querySelector('.contactpanda-new-first-name');
		var select = modal.querySelector('.contactpanda-new-salutation');
		if (!select || !input) return;
		var first = String(input.value || '').trim().split(/[\s-]+/)[0].toLocaleLowerCase();
		var suggestions = CONTACTPANDA_CONFIG.salutationSuggestions || {};
		var value = suggestions[first] || '';
		if (!select.value || select.dataset.autoAssigned === '1') {
			select.value = value;
			select.dataset.autoAssigned = value ? '1' : '0';
		}
	}

	function closeNewPerson() {
		var modal = document.querySelector('.contactpanda-new-person-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contactpanda-modal-open');
	}

	function saveNewPerson() {
		var modal = document.querySelector('.contactpanda-new-person-modal');
		if (!modal) return;
		var button = modal.querySelector('.contactpanda-save-new-person');
		var message = modal.querySelector('.contactpanda-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_create_contact');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('first_name', modal.querySelector('.contactpanda-new-first-name').value);
		data.set('last_name', modal.querySelector('.contactpanda-new-last-name').value);
		data.set('email', modal.querySelector('.contactpanda-new-email').value);
		data.set('salutation', modal.querySelector('.contactpanda-new-salutation').value);
		var companyName = modal.querySelector('.contactpanda-new-company-name');
		var companyId = modal.querySelector('.contactpanda-new-company-id');
		var companyPosition = modal.querySelector('.contactpanda-new-company-position');
		if (companyName) data.set('company_name', companyName.value);
		if (companyId) data.set('company_id', companyId.value);
		if (companyPosition) data.set('company_position', companyPosition.value);
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('creatingPerson', 'Creating person…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('createFailed', 'Creation failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.location.href = result.data.redirect;
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openNewCompany() {
		var modal = document.querySelector('.contactpanda-new-company-modal');
		if (!modal) return;
		modal.hidden = false;
		document.body.classList.add('contactpanda-modal-open');
		modal.querySelector('.contactpanda-modal-message').textContent = '';
		modal.querySelector('.contactpanda-new-company-name-only').focus();
	}

	function closeNewCompany() {
		var modal = document.querySelector('.contactpanda-new-company-modal');
		if (modal) modal.hidden = true;
		document.body.classList.remove('contactpanda-modal-open');
	}

	function saveNewCompany() {
		var modal = document.querySelector('.contactpanda-new-company-modal');
		if (!modal) return;
		var button = modal.querySelector('.contactpanda-save-new-company');
		var message = modal.querySelector('.contactpanda-modal-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_create_company');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('name', modal.querySelector('.contactpanda-new-company-name-only').value);
		button.disabled = true;
		message.textContent = tr('creatingCompany', 'Creating company…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('createFailed', 'Creation failed.'));
			window.location.href = result.data.redirect;
		}).catch(function (error) {
			button.disabled = false;
			message.textContent = error.message;
		});
	}

	function manageTag(trigger, operation) {
		var card = trigger.closest('.contactpanda-tags-card');
		var manager = card ? card.querySelector('.contactpanda-tag-manager') : null;
		if (!card || !manager) return;
		var tagId = '';
		var tagName = '';
		var message = manager.querySelector('.contactpanda-tag-message');
		if (operation === 'commit') {
			var input = manager.querySelector('.contactpanda-tag-input');
			tagName = input ? input.value.trim() : '';
			if (!tagName) {
				message.textContent = tr('enterTagName', 'Please enter a tag name.');
				return;
			}
			var option = Array.prototype.slice.call(manager.querySelectorAll('.contactpanda-tag-suggestion')).find(function (item) {
				return item.dataset.name.toLocaleLowerCase() === tagName.toLocaleLowerCase();
			});
			if (option && option.dataset.tag) {
				tagId = option.dataset.tag;
				if (manager.querySelector('.contactpanda-tag[data-tag="' + tagId + '"]')) {
					message.textContent = tr('tagAlreadyAssigned', 'This tag is already assigned to the person.');
					input.value = '';
					return;
				}
				operation = 'add';
			} else {
				operation = 'create';
			}
		}
		if (operation === 'add') {
			tagId = tagId || trigger.dataset.tag;
			tagName = trigger.dataset.name || tagName;
			if (manager.querySelector('.contactpanda-tag[data-tag="' + tagId + '"]')) {
				message.textContent = tr('tagAlreadyAssigned', 'This tag is already assigned to the person.');
				return;
			}
		}
		if (operation === 'remove') tagId = trigger.dataset.tag;
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_manage_contact_tag');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', card.dataset.contact);
		data.set('operation', operation);
		data.set('tag_id', tagId);
		data.set('tag_name', tagName);
		trigger.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('runningTagAction', 'Running tag action…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('tagActionFailed', 'Tag action failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				var savedId = String(result.data.tag_id || tagId);
				var savedName = result.data.tag_name || tagName;
				var summary = card.querySelector('.contactpanda-tags-summary');
				if (result.data.operation === 'remove') {
					var managerToken = manager.querySelector('.contactpanda-tag[data-tag="' + savedId + '"]'); if (managerToken) managerToken.remove();
					var summaryToken = summary && summary.querySelector('.contactpanda-tag[data-tag="' + savedId + '"]'); if (summaryToken) summaryToken.remove();
				} else {
					var inputField = manager.querySelector('.contactpanda-token-field');
					var input = manager.querySelector('.contactpanda-tag-input');
					if (!manager.querySelector('.contactpanda-tag[data-tag="' + savedId + '"]')) {
						var token = document.createElement('span'); token.className = 'contactpanda-tag'; token.dataset.tag = savedId; token.appendChild(document.createTextNode(savedName));
						var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button-link contactpanda-remove-tag'; remove.dataset.tag = savedId; remove.setAttribute('aria-label', tr('removeTag', 'Remove tag from this person')); remove.textContent = '×'; token.appendChild(remove);
						inputField.insertBefore(token, input);
					}
					if (summary) {
						var muted = summary.querySelector('.contactpanda-muted'); if (muted) muted.remove();
						if (!summary.querySelector('.contactpanda-tag[data-tag="' + savedId + '"]')) { var summaryTag = document.createElement('span'); summaryTag.className = 'contactpanda-tag'; summaryTag.dataset.tag = savedId; summaryTag.textContent = savedName; summary.appendChild(summaryTag); }
					}
				}
				var tagInput = manager.querySelector('.contactpanda-tag-input'); if (tagInput) tagInput.value = '';
				trigger.disabled = false;
				if (summary && !summary.querySelector('.contactpanda-tag')) { var none = document.createElement('span'); none.className = 'contactpanda-muted'; none.textContent = tr('noTags', 'No tags assigned yet.'); summary.appendChild(none); }
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 2000);
			})
			.catch(function (error) {
				trigger.disabled = false;
				message.textContent = error.message;
			});
	}

	function openTags(card) {
		if (!card) return;
		var summary = card.querySelector('.contactpanda-tags-summary');
		var manager = card.querySelector('.contactpanda-tag-manager');
		if (summary) summary.hidden = true;
		if (manager) manager.hidden = false;
		var input = manager && manager.querySelector('.contactpanda-tag-input');
		if (input) input.focus();
	}

	function closeTags(card) {
		if (!card) return;
		var summary = card.querySelector('.contactpanda-tags-summary');
		var manager = card.querySelector('.contactpanda-tag-manager');
		if (summary) summary.hidden = false;
		if (manager) manager.hidden = true;
	}

	function previewBroadcast(button) {
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_preview_broadcast');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', button.dataset.contact);
		data.set('broadcast_id', button.dataset.broadcast);
		button.disabled = true;
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('broadcastLoadFailed', 'The broadcast could not be loaded.'));
				openPreviewFrame(result.data.title || tr('broadcast', 'Broadcast'), '', result.data.html || '');
				button.disabled = false;
			})
			.catch(function (error) {
				button.disabled = false;
				window.alert(error.message);
			});
	}

	function previewRecord(button, action, idKey, id, fallbackTitle) {
		var data = new URLSearchParams();
		data.set('action', action);
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set(idKey, id);
		button.disabled = true;
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('previewLoadFailed', 'The preview could not be loaded.'));
				openPreviewFrame(result.data.title || fallbackTitle, '', result.data.html || '', result.data.edit_url || '');
				button.disabled = false;
			})
			.catch(function (error) {
				button.disabled = false;
				window.alert(error.message);
			});
	}

	function toggleLocalModal(modal, open) {
		if (!modal) return;
		modal.hidden = !open;
		document.body.classList.toggle('contactpanda-modal-open', open);
		if (open) {
			var focusTarget = modal.querySelector('.contactpanda-role-input,input,button');
			if (focusTarget) focusTarget.focus();
		}
	}

	function selectedRoles(modal) {
		return Array.prototype.slice.call(modal.querySelectorAll('.contactpanda-role-token')).map(function (token) { return token.dataset.role; });
	}

	function updateTokenSuggestions(input) {
		var entry = input.closest('.contactpanda-token-entry');
		var suggestions = entry ? entry.querySelector('.contactpanda-token-suggestions') : null;
		if (!suggestions) return;
		var query = input.value.trim().toLocaleLowerCase();
		var matches = 0;
		suggestions.querySelectorAll('.contactpanda-token-suggestion').forEach(function (suggestion) {
			var assigned = suggestion.dataset.tag
				? !!entry.querySelector('.contactpanda-tag[data-tag="' + suggestion.dataset.tag + '"]')
				: !!entry.querySelector('.contactpanda-role-token[data-role="' + suggestion.dataset.role + '"]');
			var visible = !!query && !assigned && suggestion.dataset.name.toLocaleLowerCase().indexOf(query) !== -1 && matches < 10;
			suggestion.hidden = !visible;
			if (visible) matches += 1;
		});
		suggestions.hidden = matches === 0;
	}

	function closeTokenSuggestions(input) {
		var entry = input ? input.closest('.contactpanda-token-entry') : null;
		var suggestions = entry ? entry.querySelector('.contactpanda-token-suggestions') : null;
		if (suggestions) suggestions.hidden = true;
	}

	function addRole(trigger) {
		var picker = trigger.closest('.contactpanda-role-picker');
		if (!picker) return;
		var input = picker.querySelector('.contactpanda-role-input');
		var message = picker.querySelector('.contactpanda-role-picker-message');
		var value = trigger.dataset.name || input.value.trim();
		var option = trigger.classList.contains('contactpanda-role-suggestion') ? trigger : Array.prototype.slice.call(picker.querySelectorAll('.contactpanda-role-suggestion')).find(function (item) {
			return item.dataset.name.toLocaleLowerCase() === value.toLocaleLowerCase();
		});
		if (!option || !option.dataset.role) {
			message.textContent = tr('selectSuggestedRole', 'Please select a suggested WordPress role.');
			return;
		}
		if (picker.querySelector('.contactpanda-role-token[data-role="' + option.dataset.role + '"]')) {
			message.textContent = tr('roleAlreadyAssigned', 'This role is already assigned.');
			input.value = '';
			return;
		}
		var token = document.createElement('span');
		token.className = 'contactpanda-role-token';
		token.dataset.role = option.dataset.role;
		token.appendChild(document.createTextNode(option.dataset.name));
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link contactpanda-remove-role';
		remove.setAttribute('aria-label', format(tr('removeRole', 'Remove role %s'), option.dataset.name));
		remove.innerHTML = '&times;';
		token.appendChild(remove);
		picker.querySelector('.contactpanda-token-field').insertBefore(token, input);
		input.value = '';
		closeTokenSuggestions(input);
		message.textContent = '';
		input.focus();
	}

	function saveRoles(button) {
		var modal = button.closest('.contactpanda-roles-modal');
		var panel = modal.querySelector('.contactpanda-modal-panel');
		var roles = selectedRoles(modal);
		if (!roles.length || !window.confirm(tr('confirmSaveRoles', 'Save the selected roles and the resulting permissions for this account?'))) return;
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_update_user_roles');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', panel.dataset.contact);
		data.set('user_id', panel.dataset.user);
		roles.forEach(function (role) { data.append('roles[]', role); });
		saveAccountRequest(button, modal, data);
	}

	function saveUserAccount(button) {
		var modal = button.closest('.contactpanda-user-account-modal');
		var panel = modal.querySelector('.contactpanda-modal-panel');
		var mode = panel.dataset.mode || 'create';
		var question = mode === 'link' ? tr('confirmLinkAccount', 'Link the existing account to this person?') : tr('confirmCreateAccount', 'Create the new user account now?');
		if (!window.confirm(question)) return;
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_create_user_account');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', panel.dataset.contact);
		data.set('mode', mode);
		if (mode === 'create') {
			data.set('username', modal.querySelector('.contactpanda-account-username').value);
			data.set('send_invite', modal.querySelector('.contactpanda-account-invite').checked ? '1' : '0');
			selectedRoles(modal).forEach(function (role) { data.append('roles[]', role); });
		}
		saveAccountRequest(button, modal, data);
	}

	function saveAccountRequest(button, modal, data) {
		var message = modal.querySelector('.contactpanda-modal-message');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('saving', 'Saving…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				window.setTimeout(function () { window.location.reload(); }, 700);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function openPreviewFrame(title, url, html, fallbackUrl) {
		closePreviewFrame();
		var modal = document.createElement('div');
		modal.className = 'contactpanda-consent-modal contactpanda-preview-modal';
		modal.innerHTML = '<div class="contactpanda-preview-panel" role="dialog" aria-modal="true"><div class="contactpanda-preview-header"><h2></h2><div><a class="button contactpanda-preview-external" target="_blank" rel="noopener">' + tr('openNewWindow', 'Open in new window') + '</a> <button type="button" class="button contactpanda-close-preview">' + tr('close', 'Close') + '</button></div></div><iframe class="contactpanda-preview-frame" title="' + tr('preview', 'Preview') + '"></iframe></div>';
		modal.querySelector('h2').textContent = title;
		var external = modal.querySelector('.contactpanda-preview-external');
		if (fallbackUrl || url) external.href = fallbackUrl || url;
		else external.hidden = true;
		var frame = modal.querySelector('.contactpanda-preview-frame');
		if (html) {
			frame.setAttribute('sandbox', 'allow-downloads allow-popups');
			frame.srcdoc = html;
		}
		else frame.src = url;
		document.body.appendChild(modal);
		document.body.classList.add('contactpanda-modal-open');
		modal.querySelector('.contactpanda-close-preview').focus();
	}

	function closePreviewFrame() {
		var modal = document.querySelector('.contactpanda-preview-modal');
		if (modal) modal.remove();
		if (!document.querySelector('.contactpanda-consent-modal:not([hidden])')) document.body.classList.remove('contactpanda-modal-open');
	}

	function saveAddress(form) {
		var button = form.querySelector('.contactpanda-save-address');
		var message = form.querySelector('.contactpanda-address-message');
		var data = new URLSearchParams();
		data.set('action', 'contactpanda_update_customer_address');
		data.set('nonce', CONTACTPANDA_CONFIG.nonce);
		data.set('contact_id', form.dataset.contact);
		data.set('address_type', form.dataset.addressType);
		form.querySelectorAll('[data-key]').forEach(function (field) { data.set(field.dataset.key, field.value); });
		var phoneGroup = form.querySelector('.contactpanda-phone-input-group');
		if (phoneGroup) data.set('phone', composePhone(phoneGroup));
		var syncToWooCommerce = form.querySelector('.contactpanda-sync-to-woocommerce');
		if (syncToWooCommerce && syncToWooCommerce.checked) data.set('sync_to_woocommerce', '1');
		button.disabled = true;
		message.classList.remove('is-success');
		message.textContent = tr('savingAddress', 'Saving address…');
		fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data))
			.then(parseResponse)
			.then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('saveFailed', 'Saving failed.'));
				message.textContent = result.data.message;
				message.classList.add('is-success');
				if (form.dataset.addressType === 'contact' && result.data.display) {
					var summary = document.querySelector('.contactpanda-address-summary-value');
					if (summary) summary.textContent = result.data.display;
				}
				button.disabled = false;
				window.setTimeout(function () { message.textContent = ''; message.classList.remove('is-success'); }, 3000);
			})
			.catch(function (error) {
				button.disabled = false;
				message.textContent = error.message;
			});
	}

	function buildPhoneGroup(value) {
		var parsed = parsePhone(value);
		var group = document.createElement('span');
		group.className = 'contactpanda-phone-input-group';
		var select = document.createElement('select');
		select.className = 'contactpanda-phone-prefix';
		select.setAttribute('aria-label', tr('countryCallingCode', 'Country calling code'));
		(CONTACTPANDA_CONFIG.phoneCountries || []).forEach(function (country) {
			var option = document.createElement('option');
			option.value = country.code;
			option.textContent = country.label + ' ' + country.code;
			option.selected = country.code === parsed.code;
			select.appendChild(option);
		});
		var input = document.createElement('input');
		input.type = 'tel';
		input.className = 'contactpanda-input contactpanda-phone-national';
		input.value = parsed.national;
		input.placeholder = tr('phoneNumber', 'Phone number');
		group.appendChild(select);
		group.appendChild(input);
		return group;
	}

	function parsePhone(value) {
		var normalized = String(value || '').trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		var countries = (CONTACTPANDA_CONFIG.phoneCountries || []).slice().sort(function (a, b) { return b.code.length - a.code.length; });
		for (var i = 0; i < countries.length; i++) {
			if (normalized.indexOf(countries[i].code) === 0) return {code: countries[i].code, national: normalized.slice(countries[i].code.length)};
		}
		return {code: '+43', national: normalized.replace(/^\+/, '').replace(/^0/, '')};
	}

	function detectPhonePrefix(input) {
		var raw = input.value.trim().replace(/^00/, '+').replace(/[^0-9+]/g, '');
		if (raw.charAt(0) !== '+') return;
		var group = input.closest('.contactpanda-phone-input-group');
		var countries = (CONTACTPANDA_CONFIG.phoneCountries || []).slice().sort(function (a, b) { return b.code.length - a.code.length; });
		for (var i = 0; i < countries.length; i++) {
			if (raw.indexOf(countries[i].code) === 0) {
				group.querySelector('.contactpanda-phone-prefix').value = countries[i].code;
				var national = raw.slice(countries[i].code.length);
				if (countries[i].code !== '+39') national = national.replace(/^0/, '');
				input.value = national;
				return;
			}
		}
	}

	function formatPhoneNational(input) {
		var group = input.closest('.contactpanda-phone-input-group');
		if (!group) return;
		var code = group.querySelector('.contactpanda-phone-prefix').value || '+43';
		var national = input.value.replace(/\D/g, '');
		if (code !== '+39') national = national.replace(/^0/, '');
		if (code !== '+43' || !national) {
			input.value = national;
			return;
		}
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
	}

	function composePhone(group) {
		if (!group) return '';
		var code = group.querySelector('.contactpanda-phone-prefix').value || '+43';
		var national = group.querySelector('.contactpanda-phone-national').value.replace(/\D/g, '');
		if (code !== '+39') national = national.replace(/^0/, '');
		return national ? code + national : '';
	}

	function requestOptions(data) {
		return {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: data.toString()};
	}

	function parseResponse(response) {
		return response.json().catch(function () { throw new Error(tr('invalidServerResponse', 'The server returned an invalid response.')); });
	}

	document.addEventListener('change', function (event) {
		if (event.target.matches('.contactpanda-merge-record-toggle')) {
			var recordId = event.target.value;
			var enabled = event.target.checked;
			document.querySelectorAll('[data-contactpanda-primary-record="' + recordId + '"], [data-contactpanda-merge-source="' + recordId + '"] input').forEach(function (input) {
				input.disabled = !enabled;
				if (!enabled && input.checked) input.checked = false;
			});
			var selected = document.querySelectorAll('.contactpanda-merge-record-toggle:checked');
			var primary = document.querySelector('[data-contactpanda-primary-record]:checked');
			if (!primary && selected.length) {
				var nextPrimary = document.querySelector('[data-contactpanda-primary-record="' + selected[0].value + '"]');
				if (nextPrimary) nextPrimary.checked = true;
			}
			document.querySelectorAll('[data-contactpanda-merge-channel-sources]').forEach(function (label) {
				var sourceIds = (label.getAttribute('data-contactpanda-merge-channel-sources') || '').split(',');
				var available = sourceIds.some(function (sourceId) {
					var sourceToggle = document.querySelector('.contactpanda-merge-record-toggle[value="' + sourceId + '"]');
					return sourceToggle && sourceToggle.checked;
				});
				label.querySelectorAll('input').forEach(function (input) {
					input.disabled = !available;
					if (!available) input.checked = false;
				});
			});
			document.querySelectorAll('.contactpanda-merge-channels fieldset').forEach(function (fieldset) {
				var checkedPrimary = fieldset.querySelector('input[type="radio"]:checked:not(:disabled)');
				if (checkedPrimary) return;
				var firstAvailable = fieldset.querySelector('input[type="checkbox"]:checked:not(:disabled)');
				if (!firstAvailable) return;
				var firstLabel = firstAvailable.closest('label');
				var firstPrimary = firstLabel && firstLabel.querySelector('input[type="radio"]');
				if (firstPrimary) firstPrimary.checked = true;
			});
			return;
		}
		if (event.target.matches('[data-contactpanda-merge-field]')) {
			var mergeField = event.target.getAttribute('data-contactpanda-merge-field');
			var mergeTarget = document.querySelector('[data-contactpanda-merge-target="' + mergeField + '"]');
			if (mergeTarget) mergeTarget.value = event.target.getAttribute('data-contactpanda-merge-value') || '';
			return;
		}
		if (event.target.matches('.contactpanda-settings-color-field input[type="color"]')) {
			var colorValue = event.target.closest('.contactpanda-settings-color-field').querySelector('.contactpanda-settings-color-value');
			if (colorValue) colorValue.textContent = event.target.value;
			return;
		}
		if (event.target.matches('.contactpanda-select-all-tags')) {
			document.querySelectorAll('.contactpanda-tag-bulk-form input[name="tag_ids[]"]').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
			return;
		}
		if (!event.target.matches('[data-contactpanda-delete-on-uninstall]') || !event.target.checked) return;
		var message = tr('confirmDeleteOnUninstall', 'Do you really want ContactPanda-owned customer data to be permanently deleted when the plugin is uninstalled?');
		if (!window.confirm(message)) event.target.checked = false;
	});

	document.addEventListener('submit', function (event) {
		if (event.target.matches('.contactpanda-migration-progress-form:not(.contactpanda-live-migration-form):not(.contactpanda-groundhogg-migration-form)')) {
			var form = event.target, button = form.querySelector('button[type="submit"]');
			if (form.dataset.running === '1') { event.preventDefault(); return; }
			form.dataset.running = '1';
			if (button) { button.disabled = true; button.dataset.originalLabel = button.textContent; button.textContent = tr('migrationRunning', 'Migration running…'); }
			var status = document.createElement('span'); status.className = 'contactpanda-inline-migration-progress'; status.setAttribute('role', 'status'); status.innerHTML = '<span class="spinner is-active" aria-hidden="true"></span><span>' + tr('migrationProgressHint', 'Source data is being read, matched, and saved. Please keep this page open.') + '</span>';
			form.appendChild(status);
			return;
		}
		if (event.target.matches('.contactpanda-live-migration-form')) {
			event.preventDefault();
			var liveForm = event.target;
			if (liveForm.dataset.running === '1') return;
			liveForm.dataset.running = '1';
			var liveButton = liveForm.querySelector('button[type="submit"]');
			var liveProgress = liveForm.closest('.contactpanda-card').querySelector('.contactpanda-migration-progress');
			if (liveButton) liveButton.disabled = true;
			if (liveProgress) liveProgress.hidden = false;
			var pollLiveMigration = function () {
				var progressData = new URLSearchParams(); progressData.set('action', 'contactpanda_migration_progress'); progressData.set('nonce', CONTACTPANDA_CONFIG.nonce);
				fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(progressData)).then(parseResponse).then(function (result) {
					if (!result.success || !liveProgress) return;
					var value = Math.max(0, Math.min(100, parseInt(result.data.percent || 0, 10)));
					liveProgress.querySelector('strong').textContent = result.data.stage || tr('migrationPreparing', 'Preparing migration…');
					liveProgress.querySelector('progress').value = value;
					liveProgress.querySelector('span').textContent = value + '%';
					liveProgress.querySelector('small').textContent = result.data.total ? String(result.data.processed) + ' / ' + String(result.data.total) : String(result.data.processed || '');
				}).catch(function () {});
			};
			pollLiveMigration();
			var liveTimer = window.setInterval(pollLiveMigration, 1000);
			var liveData = new FormData(liveForm);
			fetch(CONTACTPANDA_CONFIG.ajaxUrl, {method: 'POST', body: liveData, credentials: 'same-origin'}).then(parseResponse).then(function (result) {
				window.clearInterval(liveTimer);
				if (!result.success || !result.data || !result.data.redirect) throw new Error(result.data && result.data.message ? result.data.message : tr('invalidServerResponse', 'The server returned an invalid response.'));
				window.location.href = result.data.redirect;
			}).catch(function (error) {
				window.clearInterval(liveTimer); liveForm.dataset.running = '0';
				if (liveButton) liveButton.disabled = false;
				if (liveProgress) { liveProgress.querySelector('strong').textContent = error.message; liveProgress.querySelector('small').textContent = ''; }
			});
			return;
		}
		if (event.target.matches('.contactpanda-groundhogg-migration-form')) {
			event.preventDefault();
			var migrationForm = event.target;
			if (migrationForm.dataset.running === '1') return;
			migrationForm.dataset.running = '1';
			var migrationButton = migrationForm.querySelector('button[type="submit"]');
			var migrationProgress = document.querySelector('.contactpanda-migration-progress');
			if (migrationButton) migrationButton.disabled = true;
			if (migrationProgress) migrationProgress.hidden = false;
			var pollMigration = function () {
				var data = new URLSearchParams(); data.set('action', 'contactpanda_migration_progress'); data.set('nonce', CONTACTPANDA_CONFIG.nonce);
				fetch(CONTACTPANDA_CONFIG.ajaxUrl, requestOptions(data)).then(parseResponse).then(function (result) {
					if (!result.success || !migrationProgress) return;
					var value = Math.max(0, Math.min(100, parseInt(result.data.percent || 0, 10)));
					migrationProgress.querySelector('strong').textContent = result.data.stage || tr('migrationPreparing', 'Preparing migration…');
					migrationProgress.querySelector('progress').value = value;
					migrationProgress.querySelector('span').textContent = value + '%';
					migrationProgress.querySelector('small').textContent = result.data.total ? String(result.data.processed) + ' / ' + String(result.data.total) : String(result.data.processed || '');
				}).catch(function () {});
			};
			pollMigration();
			var migrationTimer = window.setInterval(pollMigration, 1200);
			var migrationData = new FormData(migrationForm);
			migrationData.set('action', 'contactpanda_migrate_groundhogg');
			fetch(CONTACTPANDA_CONFIG.ajaxUrl, {method: 'POST', body: migrationData, credentials: 'same-origin'}).then(parseResponse).then(function (result) {
				window.clearInterval(migrationTimer);
				if (!result.success || !result.data || !result.data.redirect) {
					throw new Error(result.data && result.data.message ? result.data.message : tr('invalidServerResponse', 'The server returned an invalid response.'));
				}
				window.location.href = result.data.redirect;
			}).catch(function (error) {
				window.clearInterval(migrationTimer);
				migrationForm.dataset.running = '0';
				if (migrationButton) migrationButton.disabled = false;
				if (migrationProgress) {
					migrationProgress.querySelector('strong').textContent = error.message;
					migrationProgress.querySelector('small').textContent = '';
				}
			});
			return;
		}
		if (event.target.matches('.contactpanda-tag-bulk-form')) {
			var selected = event.target.querySelectorAll('input[name="tag_ids[]"]:checked');
			var action = event.target.querySelector('[name="bulk_action"]').value;
			if (action !== 'delete' || !selected.length || !window.confirm(tr('confirmBulkDeleteTags', 'Delete the selected tags and all their contact assignments?'))) {
				event.preventDefault();
				return;
			}
			event.target.querySelector('[name="delete_confirmed"]').value = '1';
			return;
		}
		if (event.target.matches('.contactpanda-delete-tag-form')) {
			var form = event.target;
			var template = tr('confirmDeleteTag', 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.');
			var question = template.replace('%1$s', form.dataset.name || '').replace('%2$d', form.dataset.count || '0');
			if (!window.confirm(question)) {
				event.preventDefault();
				return;
			}
			form.querySelector('[name="delete_confirmed"]').value = '1';
			return;
		}
		if (!event.target.matches('.contactpanda-inline-action-form') || !event.target.querySelector('.contactpanda-reset-test-data')) return;
	});

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.contactpanda-delete-tag-link');
		if (!link) return;
		var template = tr('confirmDeleteTag', 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.');
		var question = template.replace('%1$s', link.dataset.name || '').replace('%2$d', link.dataset.count || '0');
		if (!window.confirm(question)) event.preventDefault();
	});

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-contactpanda-legal-form]')) {
			var representative = event.target.closest('form').querySelector('[data-contactpanda-representative-field]');
			if (representative) {
				var labels = {};
				try { labels = JSON.parse(representative.dataset.labels || '{}'); } catch (error) {}
				var selected = event.target.value;
				representative.hidden = selected === 'individual';
				var label = representative.querySelector('[data-contactpanda-representative-label]');
				if (label) label.textContent = labels[selected] || labels.other || '';
			}
			return;
		}
		if (!event.target.matches('.contactpanda-file-drop input[type="file"]')) return;
		var drop = event.target.closest('.contactpanda-file-drop');
		var name = drop && drop.querySelector('.contactpanda-file-drop-name');
		if (name) name.textContent = event.target.files.length ? event.target.files[0].name : '';
		if (drop) drop.classList.toggle('has-file', !!event.target.files.length);
	});

	document.addEventListener('dragover', function (event) {
		var drop = event.target.closest('.contactpanda-file-drop');
		if (!drop) return;
		event.preventDefault();
		drop.classList.add('is-dragging');
	});

	document.addEventListener('dragleave', function (event) {
		var drop = event.target.closest('.contactpanda-file-drop');
		if (drop) drop.classList.remove('is-dragging');
	});

	document.addEventListener('drop', function (event) {
		var drop = event.target.closest('.contactpanda-file-drop');
		if (!drop) return;
		event.preventDefault();
		drop.classList.remove('is-dragging');
		var input = drop.querySelector('input[type="file"]');
		if (!input || !event.dataTransfer || !event.dataTransfer.files.length) return;
		input.files = event.dataTransfer.files;
		input.dispatchEvent(new Event('change', { bubbles: true }));
	});
}());
