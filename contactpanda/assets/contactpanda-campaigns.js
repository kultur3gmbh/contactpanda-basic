(function () {
	'use strict';
	var config = window.CONTACTPANDA_CAMPAIGNS || {};
	setupStatusPolling();
	setupCampaignFilters();
	var form = document.getElementById('contactpanda-campaign-form');
	if (!form) return;
	var scheduled = form.querySelector('[name="scheduled_at"]');
	var deliverySubmit = form.querySelector('.contactpanda-delivery-submit');
	function formattedSchedule() {
		if (!scheduled || !scheduled.value) return '';
		var date = new Date(scheduled.value);
		if (Number.isNaN(date.getTime())) return '';
		return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {dateStyle:'short', timeStyle:'short'}).format(date);
	}
	function scheduledButtonLabel() {
		if (!scheduled || !scheduled.value) return (config.scheduleDeliveryEmpty || 'Schedule delivery');
		var date = new Date(scheduled.value);
		if (Number.isNaN(date.getTime())) return (config.scheduleDeliveryEmpty || 'Schedule delivery');
		var locale = document.documentElement.lang || undefined;
		var dateLabel = new Intl.DateTimeFormat(locale, {dateStyle:'short'}).format(date);
		var timeLabel = new Intl.DateTimeFormat(locale, {timeStyle:'short'}).format(date);
		return (config.scheduleDelivery || 'Schedule delivery for %1$s at %2$s').replace('%1$s', dateLabel).replace('%2$s', timeLabel);
	}
	function updateDeliveryAction() {
		var later = form.querySelector('[name="send_timing"]:checked'); later = later && later.value === 'later';
		if (!deliverySubmit) return;
		deliverySubmit.value = later ? 'schedule' : 'send_now';
		deliverySubmit.textContent = later
			? scheduledButtonLabel()
			: (config.sendNow || 'Send now');
	}
	form.querySelectorAll('.contactpanda-searchable-select').forEach(function (wrapper) {
		var search = wrapper.querySelector('.contactpanda-select-search'), select = wrapper.querySelector('select');
		if (!search || !select) return;
		wrapper.classList.add('is-enhanced');
		var toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'contactpanda-searchable-toggle'; toggle.setAttribute('aria-expanded', 'false');
		var toggleText = document.createElement('span'); var toggleIcon = document.createElement('span'); toggleIcon.className = 'dashicons dashicons-arrow-down-alt2'; toggle.appendChild(toggleText); toggle.appendChild(toggleIcon);
		var panel = document.createElement('div'); panel.className = 'contactpanda-searchable-panel'; panel.hidden = true;
		var options = document.createElement('div'); options.className = 'contactpanda-searchable-options'; panel.appendChild(search); panel.appendChild(options); select.before(toggle); select.before(panel);
		function updateToggle() { var selected = select.options[select.selectedIndex] || select.options[0]; toggleText.textContent = selected ? selected.text : '—'; }
		function renderOptions() {
			var query = search.value.trim().toLocaleLowerCase(), found = 0; options.replaceChildren();
			Array.prototype.forEach.call(select.options, function (option) {
				if (query && option.text.toLocaleLowerCase().indexOf(query) === -1 && option.value !== select.value) return;
				var button = document.createElement('button'); button.type = 'button'; button.className = 'contactpanda-searchable-option' + (option.value === select.value ? ' is-selected' : ''); button.textContent = option.text; button.dataset.value = option.value;
				button.addEventListener('click', function () { select.value = button.dataset.value; updateToggle(); select.dispatchEvent(new Event('change', {bubbles:true})); search.value = ''; panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }); options.appendChild(button); found += 1;
			});
			if (!found) { var empty = document.createElement('span'); empty.className = 'contactpanda-option-picker-empty'; empty.textContent = config.noOptions || 'No matching entries'; options.appendChild(empty); }
		}
		updateToggle();
		toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true'); if (!panel.hidden) { search.value = ''; renderOptions(); search.focus(); } });
		search.addEventListener('input', renderOptions);
		select.addEventListener('change', updateToggle);
		search.addEventListener('keydown', function (event) { if (event.key === 'Escape') { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); } });
	});
	var templateSelect = form.elements.template_id;
	function copyTemplateSubject() { var option = templateSelect && templateSelect.options[templateSelect.selectedIndex]; if (option && option.dataset.subject && form.elements.subject) form.elements.subject.value = option.dataset.subject; }
	if (templateSelect) templateSelect.addEventListener('change', copyTemplateSubject);
	document.addEventListener('click', function (event) { form.querySelectorAll('.contactpanda-searchable-select.is-enhanced').forEach(function (wrapper) { if (wrapper.contains(event.target)) return; var panel = wrapper.querySelector('.contactpanda-searchable-panel'), toggle = wrapper.querySelector('.contactpanda-searchable-toggle'); panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }); });
	form.querySelectorAll('[name="send_timing"]').forEach(function (radio) {
		radio.addEventListener('change', function () { var later = radio.value === 'later' && radio.checked; if (later) { scheduled.hidden = false; scheduled.disabled = false; scheduled.required = true; } else if (radio.checked) { scheduled.hidden = true; scheduled.disabled = true; scheduled.required = false; } updateDeliveryAction(); });
	});
	if (scheduled) scheduled.addEventListener('change', updateDeliveryAction);
	updateDeliveryAction();
	var customerPanel = form.querySelector('.contactpanda-customer-recipient-search');
	var consentOverride = form.querySelector('.contactpanda-consent-override');
	var customerSearch = customerPanel ? customerPanel.querySelector('[name="recipient_search"]') : null;
	var customerSearchField = customerPanel ? customerPanel.querySelector('.contactpanda-customer-search-field') : null;
	var customerResults = customerPanel ? customerPanel.querySelector('.contactpanda-customer-search-results') : null;
	var savedSearchSource = form.querySelector('.contactpanda-saved-search-source');
	var segmentSelect = form.elements.segment_id;
	var customerTimer = null;
	function updateRecipientSource() {
		var selected = form.querySelector('[name="audience_source"]:checked');
		if (!customerPanel) return;
		var source = selected ? selected.value : 'contacts';
		customerPanel.hidden = false;
		if (customerSearchField) customerSearchField.hidden = source !== 'registered_customers';
		if (savedSearchSource) savedSearchSource.hidden = source !== 'contacts';
		if (consentOverride) { consentOverride.hidden = false; consentOverride.querySelectorAll('input,select').forEach(function (field) { field.disabled = false; }); }
		searchCustomers();
	}
	function searchCustomers() {
		if (!customerPanel || !customerResults) return;
		var selected = form.querySelector('[name="audience_source"]:checked');
		var source = selected ? selected.value : 'contacts';
		if (source === 'manual') {
			var manualCount = Number(customerResults.dataset.manualCount || 0);
			customerResults.dataset.eligible = String(manualCount);
			customerResults.textContent = (config.manualRecipientSummary || '%d people selected. Protected opt-outs and invalid addresses will still be excluded before sending.').replace('%d', String(manualCount));
			return;
		}
		customerResults.textContent = config.recipientSearchLoading || config.customerSearchLoading || 'Loading…';
		var data = new FormData(); data.set('action', 'contactpanda_campaign_customer_search'); data.set('nonce', config.nonce || ''); data.set('source', source); data.set('segment_id', segmentSelect ? segmentSelect.value : '0'); data.set('query', customerSearch ? customerSearch.value : '');
		fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:data}).then(function (response) { return response.json(); }).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message || config.recipientSearchFailed || 'Search failed');
			customerResults.replaceChildren();
			customerResults.dataset.eligible = String(result.data.eligible || 0);
			var summary = document.createElement('p'); summary.className = 'contactpanda-customer-search-summary'; summary.textContent = (config.recipientSearchSummary || '%1$d contacts found; %2$d eligible; %3$d with consent; %4$d without consent; %5$d excluded.').replace('%1$d', result.data.total).replace('%2$d', result.data.eligible).replace('%3$d', result.data.withConsent).replace('%4$d', result.data.withoutConsent).replace('%5$d', result.data.excluded); customerResults.appendChild(summary);
			if (!result.data.items.length) { var empty = document.createElement('p'); empty.textContent = config.noRecipients || config.noCustomers || 'No eligible contacts found.'; customerResults.appendChild(empty); return; }
			var list = document.createElement('ul'); list.className = 'contactpanda-customer-recipient-list'; result.data.items.forEach(function (item) { var row = document.createElement('li'); var identity = document.createElement('span'); identity.textContent = item.name + ' · ' + item.email; var state = document.createElement('span'); state.className = item.marketable ? 'is-marketable' : 'is-without-consent'; state.textContent = item.marketable ? (config.marketingConsent || 'Marketing consent') : (config.withoutMarketingConsent || 'No documented marketing consent'); row.appendChild(identity); row.appendChild(state); list.appendChild(row); }); customerResults.appendChild(list);
			if (result.data.truncated) { var truncated = document.createElement('p'); truncated.className = 'description'; truncated.textContent = config.recipientSearchTruncated || config.customerSearchTruncated || 'Only the first 200 matches are shown.'; customerResults.appendChild(truncated); }
		}).catch(function (error) { customerResults.textContent = error.message; });
	}
	form.querySelectorAll('[name="audience_source"]').forEach(function (radio) { radio.addEventListener('change', updateRecipientSource); });
	if (segmentSelect) segmentSelect.addEventListener('change', searchCustomers);
	if (customerSearch) customerSearch.addEventListener('input', function () { window.clearTimeout(customerTimer); customerTimer = window.setTimeout(searchCustomers, 250); });
	updateRecipientSource();
	var overrideToggle = consentOverride ? consentOverride.querySelector('[name="ignore_marketing_consent"]') : null;
	var overrideReason = consentOverride ? consentOverride.querySelector('[name="consent_override_reason"]') : null;
	var overrideConfirm = consentOverride ? consentOverride.querySelector('[name="consent_override_confirm"]') : null;
	var overrideError = consentOverride ? consentOverride.querySelector('.contactpanda-consent-override-error') : null;
	function validateOverride() {
		if (!overrideToggle || !overrideToggle.checked) { if (overrideError) overrideError.hidden = true; return true; }
		if (overrideReason && overrideReason.value && overrideConfirm && overrideConfirm.checked) { if (overrideError) overrideError.hidden = true; return true; }
		if (overrideError) { overrideError.textContent = config.overrideRequired || 'Select a reason and confirm the administrative exception.'; overrideError.hidden = false; }
		if (overrideReason && !overrideReason.value) overrideReason.focus(); else if (overrideConfirm) overrideConfirm.focus();
		return false;
	}
	form.addEventListener('submit', function (event) {
		if (!validateOverride()) { event.preventDefault(); return; }
		var submitter = event.submitter;
		if (!submitter || submitter.value === 'save') return;
		var eligible = customerResults ? Number(customerResults.dataset.eligible || 0) : 0;
		var question = submitter.value === 'schedule'
			? (config.confirmSchedule || 'Schedule this mailing for %1$s? %2$d eligible recipients will be prepared.').replace('%1$s', formattedSchedule()).replace('%2$d', String(eligible))
			: (config.confirmSendNow || 'Send this mailing to %d eligible recipients now?').replace('%d', String(eligible));
		if (!window.confirm(question)) event.preventDefault();
	});
	setupCampaignCreation();
	function request(action, email) {
		var data = new FormData(); data.set('action', action); data.set('nonce', config.nonce || ''); data.set('template_id', form.elements.template_id.value); data.set('subject', form.elements.subject.value); data.set('from_name', form.elements.from_name.value); data.set('from_email', form.elements.from_email.value); data.set('reply_to', form.elements.reply_to.value); if (email) data.set('email', email);
		return fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:data}).then(function (response) { return response.json(); });
	}
	var modal = document.getElementById('contactpanda-campaign-preview-modal'); var frame = document.getElementById('contactpanda-campaign-preview-frame'); var message = document.getElementById('contactpanda-campaign-message');
	document.getElementById('contactpanda-campaign-preview').addEventListener('click', function () { request('contactpanda_campaign_preview').then(function (result) { if (!result.success) throw new Error(result.data && result.data.message || config.previewFailed || 'Preview failed'); frame.srcdoc = result.data.html; modal.hidden = false; }).catch(function (error) { message.textContent = error.message; }); });
	document.getElementById('contactpanda-close-campaign-preview').addEventListener('click', function () { modal.hidden = true; });
	document.querySelectorAll('[data-campaign-preview-width]').forEach(function (button) { button.addEventListener('click', function () { document.querySelectorAll('[data-campaign-preview-width]').forEach(function (item) { item.classList.remove('is-active'); }); button.classList.add('is-active'); frame.style.width = button.dataset.campaignPreviewWidth; }); });
	document.getElementById('contactpanda-campaign-test').addEventListener('click', function () { message.textContent = ''; request('contactpanda_campaign_test', document.getElementById('contactpanda-campaign-test-email').value).then(function (result) { if (!result.success) throw new Error(result.data && result.data.message || config.testFailed || 'Test failed'); message.textContent = result.data.message; }).catch(function (error) { message.textContent = error.message; }); });

	function setupStatusPolling() {
		var delay = Math.max(10000, Number(config.pollInterval) || 12000);
		function activeRows() {
			return Array.prototype.filter.call(document.querySelectorAll('[data-contactpanda-campaign-id]'), function (row) {
				return row.dataset.contactpandaCampaignStatus === 'queued' || row.dataset.contactpandaCampaignStatus === 'sending';
			});
		}
		function renderStatus(row, item) {
			row.dataset.contactpandaCampaignStatus = item.status;
			var sent = row.querySelector('[data-contactpanda-mailing-sent]');
			var failed = row.querySelector('[data-contactpanda-mailing-failed]');
			var total = row.querySelector('[data-contactpanda-mailing-total]');
			var delivered = row.querySelector('[data-contactpanda-mailing-delivered]');
			var progress = row.querySelector('.contactpanda-mailing-progress');
			if (total) total.textContent = item.totalText;
			if (sent) sent.textContent = item.sentText;
			if (failed) failed.textContent = item.failedText;
			if (delivered) delivered.textContent = item.delivered;
			if (progress) {
				progress.setAttribute('aria-label', item.progressLabel);
				var bar = progress.querySelector('i'); var label = progress.querySelector('small');
				if (bar) bar.style.width = item.progress + '%';
				if (label) label.textContent = item.progress + '%';
			}
			if (item.terminal) {
				var actions = row.querySelector('.contactpanda-mailing-actions');
				if (!actions) return;
				var badge = document.createElement('span');
				badge.className = 'button contactpanda-mailing-state ' + (item.status === 'completed' ? 'contactpanda-mailing-completed' : 'contactpanda-mailing-cancelled');
				badge.textContent = item.statusLabel;
				actions.replaceChildren(badge);
			}
		}
		function poll() {
			var rows = activeRows();
			if (!rows.length) return;
			var data = new FormData();
			data.set('action', 'contactpanda_campaign_statuses');
			data.set('nonce', config.nonce || '');
			rows.forEach(function (row) { data.append('ids[]', row.dataset.contactpandaCampaignId); });
			fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (response) { return response.json(); })
				.then(function (result) {
					if (!result.success || !result.data || !result.data.statuses) throw new Error('Status update failed');
					rows.forEach(function (row) { var item = result.data.statuses[row.dataset.contactpandaCampaignId]; if (item) renderStatus(row, item); });
					if (activeRows().length) window.setTimeout(poll, delay);
				})
				.catch(function () { window.setTimeout(poll, Math.max(delay * 2, 30000)); });
		}
		if (activeRows().length) window.setTimeout(poll, delay);
	}

	function setupCampaignFilters() {
		document.querySelectorAll('.contactpanda-campaign-filter').forEach(function (picker) {
			var toggle = picker.querySelector('.contactpanda-campaign-filter-toggle'); var panel = picker.querySelector('.contactpanda-campaign-filter-panel'); var search = picker.querySelector('.contactpanda-campaign-filter-search');
			if (!toggle || !panel) return;
			toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true'); if (!panel.hidden && search) search.focus(); });
			if (search) search.addEventListener('input', function () { var query = search.value.trim().toLocaleLowerCase(); panel.querySelectorAll('label').forEach(function (label) { label.hidden = !!query && label.textContent.toLocaleLowerCase().indexOf(query) === -1; }); });
			document.addEventListener('click', function (event) { if (!picker.contains(event.target)) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } });
		});
	}

	function setupCampaignCreation() {
		var open = form.querySelector('.contactpanda-create-campaign'); var modal = document.getElementById('contactpanda-campaign-create-modal'); var createForm = document.getElementById('contactpanda-campaign-create-form');
		if (!open || !modal || !createForm) return;
		var close = modal.querySelector('.contactpanda-close-campaign-create'); var message = modal.querySelector('.contactpanda-campaign-create-message'); var select = form.elements.campaign_group_id;
		function hide() { modal.hidden = true; document.body.classList.remove('contactpanda-campaign-create-open'); }
		open.addEventListener('click', function () { modal.hidden = false; document.body.classList.add('contactpanda-campaign-create-open'); createForm.elements.name.focus(); });
		if (close) close.addEventListener('click', hide);
		createForm.addEventListener('submit', function (event) {
			event.preventDefault(); message.textContent = config.campaignCreating || 'Creating…'; var data = new FormData(createForm); data.set('action', 'contactpanda_campaign_group_create'); data.set('nonce', config.nonce || '');
			fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:data}).then(function (response) { return response.json(); }).then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message || config.campaignCreateFailed || 'Creation failed');
				var option = Array.prototype.find.call(select.options, function (item) { return String(item.value) === String(result.data.id); });
				if (!option) { option = document.createElement('option'); option.value = result.data.id; option.textContent = result.data.name; select.appendChild(option); }
				select.value = String(result.data.id); select.dispatchEvent(new Event('change', {bubbles:true})); createForm.reset(); message.textContent = ''; hide();
			}).catch(function (error) { message.textContent = error.message; });
		});
	}
}());
