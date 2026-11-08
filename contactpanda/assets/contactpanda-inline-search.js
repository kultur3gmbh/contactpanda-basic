(function () {
	'use strict';
	var config = window.CONTACTPANDA_INLINE_SEARCH || {};
	var panel = document.querySelector('.contactpanda-inline-search');
	if (!panel) return;
	var rulesElement = panel.querySelector('.contactpanda-inline-rules');
	var resultButton = panel.querySelector('.contactpanda-show-filter-results');
	var statusElement = panel.querySelector('.contactpanda-inline-search-status');
	var state = config.activeState && Array.isArray(config.activeState.rules) ? config.activeState : {relation: 'and', rules: []};
	var fieldMap = {};
	var countTimer = 0;
	var requestNumber = 0;
	(config.fields || []).forEach(function (group) {
		(group.fields || []).forEach(function (field) { fieldMap[field.key] = field; });
	});

	function tr(key, fallback) { return config.i18n && config.i18n[key] ? config.i18n[key] : fallback; }
	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
			return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
		});
	}
	function fieldOptions(selected) {
		var html = '<option value="">' + escapeHtml(tr('chooseField', 'Choose a field')) + '</option>';
		(config.fields || []).forEach(function (group) {
			html += '<optgroup label="' + escapeHtml(group.label) + '">';
			(group.fields || []).forEach(function (field) {
				html += '<option value="' + escapeHtml(field.key) + '"' + (field.key === selected ? ' selected' : '') + '>' + escapeHtml(field.label) + '</option>';
			});
			html += '</optgroup>';
		});
		return html;
	}
	function operatorOptions(field, selected) {
		if (!field) return '<option value=""></option>';
		return (field.operators || []).map(function (operator) {
			return '<option value="' + escapeHtml(operator) + '"' + (operator === selected ? ' selected' : '') + '>' + escapeHtml((config.operators || {})[operator] || operator) + '</option>';
		}).join('');
	}
	function valueControl(field, rule) {
		if (!field || ['empty', 'not_empty'].indexOf(rule.operator) !== -1) return '<span class="contactpanda-inline-no-value">—</span>';
		if (field.type === 'select') {
			if (field.key === 'event' || field.key === 'product') {
				var chosen = (field.options || []).find(function (option) { return String(option.value) === String(rule.value); });
				return '<div class="contactpanda-option-picker"><input type="hidden" class="contactpanda-inline-value" value="' + escapeHtml(rule.value || '') + '">' +
					'<button type="button" class="button contactpanda-option-picker-toggle"><span>' + escapeHtml(chosen ? chosen.label : tr('chooseValue', 'Please select')) + '</span><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
					'<div class="contactpanda-option-picker-panel" hidden><input type="search" class="contactpanda-option-picker-search" placeholder="' + escapeHtml(field.key === 'event' ? tr('searchEvents', 'Search events') : tr('searchProducts', 'Search products')) + '"><div class="contactpanda-option-picker-results">' + pickerOptions(field, rule.value, '') + '</div></div></div>';
			}
			var html = '<select class="contactpanda-inline-value"><option value="">' + escapeHtml(tr('chooseValue', 'Please select')) + '</option>';
			(field.options || []).forEach(function (option) {
				html += '<option value="' + escapeHtml(option.value) + '"' + (String(option.value) === String(rule.value) ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
			});
			return html + '</select>';
		}
		return '<input class="contactpanda-inline-value" type="' + (field.type === 'number' ? 'number' : 'text') + '"' + (field.type === 'number' ? ' min="0" step="any"' : '') + ' value="' + escapeHtml(rule.value || '') + '" placeholder="' + escapeHtml(tr('value', 'Value')) + '">';
	}
	function pickerOptions(field, selected, query) {
		query = String(query || '').toLocaleLowerCase();
		var found = 0;
		var html = (field.options || []).map(function (option) {
			if (query && String(option.label).toLocaleLowerCase().indexOf(query) === -1) return '';
			found++;
			return '<button type="button" class="contactpanda-option-picker-result' + (String(option.value) === String(selected) ? ' is-selected' : '') + '" data-value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label) + '</button>';
		}).join('');
		return found ? html : '<span class="contactpanda-option-picker-empty">' + escapeHtml(tr('noOptions', 'No matching entries')) + '</span>';
	}
	function renderRules() {
		rulesElement.innerHTML = '';
		state.rules.forEach(function (rule, index) {
			var field = fieldMap[rule.field];
			var row = document.createElement('div');
			row.className = 'contactpanda-inline-rule';
			row.dataset.index = String(index);
			row.innerHTML = '<span class="contactpanda-inline-rule-number">' + (index + 1) + '</span>' +
				'<select class="contactpanda-inline-field">' + fieldOptions(rule.field) + '</select>' +
				'<select class="contactpanda-inline-operator"' + (field ? '' : ' disabled') + '>' + operatorOptions(field, rule.operator) + '</select>' +
				'<span class="contactpanda-inline-value-wrap">' + valueControl(field, rule) + '</span>' +
				'<button type="button" class="button-link contactpanda-remove-inline-rule" aria-label="' + escapeHtml(tr('remove', 'Remove filter')) + '"><span class="dashicons dashicons-no-alt"></span></button>';
			rulesElement.appendChild(row);
		});
		if (!state.rules.length) rulesElement.innerHTML = '<p class="contactpanda-inline-empty">' + escapeHtml(tr('addFirst', 'Add a filter to narrow the people list.')) + '</p>';
		scheduleCount();
	}
	function completeState() {
		var complete = state.rules.length > 0 && state.rules.every(function (rule) {
			return rule.field && rule.operator && (['empty', 'not_empty'].indexOf(rule.operator) !== -1 || String(rule.value || '') !== '');
		});
		return complete ? {relation: state.relation === 'or' ? 'or' : 'and', rules: state.rules} : null;
	}
	function scheduleCount() {
		window.clearTimeout(countTimer);
		var complete = completeState();
		resultButton.setAttribute('aria-disabled', 'true');
		resultButton.href = '#';
		if (!complete) {
			resultButton.textContent = tr('complete', 'Complete all filters');
			statusElement.textContent = '';
			return;
		}
		countTimer = window.setTimeout(function () { countMatches(complete); }, 350);
	}
	function countMatches(complete) {
		var currentRequest = ++requestNumber;
		var data = new FormData();
		data.set('action', 'contactpanda_filter_count'); data.set('nonce', config.nonce); data.set('state', JSON.stringify(complete));
		statusElement.textContent = tr('checking', 'Counting matching people…');
		fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data})
			.then(function (response) { return response.text().then(function (body) { try { return JSON.parse(body); } catch (error) { throw new Error(tr('countFailed', 'Counting failed.')); } }); })
			.then(function (result) {
				if (currentRequest !== requestNumber) return;
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('countFailed', 'Counting failed.'));
				var count = Number(result.data.count || 0);
				resultButton.textContent = config.campaignMode
					? (count === 1 ? tr('campaignOne', '1 matching person') : tr('campaignMany', '%d matching people').replace('%d', count))
					: (count === 1 ? tr('showOne', 'Search finds 1 person – show now') : tr('showMany', 'Search finds %d people – show now').replace('%d', count));
				if (config.campaignMode) {
					resultButton.removeAttribute('href'); resultButton.setAttribute('aria-disabled', 'true'); statusElement.textContent = '';
				} else {
					var url = new URL(config.peopleUrl || window.location.href, window.location.origin);
					url.searchParams.set('page', 'contactpanda-people'); url.searchParams.set('contactpanda_filter', result.data.filter);
					url.searchParams.delete('segment'); url.searchParams.delete('contactpanda_page');
					resultButton.href = url.toString(); resultButton.setAttribute('aria-disabled', 'false'); statusElement.textContent = '';
				}
			})
			.catch(function (error) { if (currentRequest === requestNumber) statusElement.textContent = error.message; });
	}

	document.addEventListener('click', function (event) {
		var showResults = event.target.closest('.contactpanda-show-filter-results');
		if (showResults && showResults.getAttribute('aria-disabled') === 'false' && showResults.href && showResults.href !== '#') {
			event.preventDefault();
			window.location.assign(showResults.href);
			return;
		}
		var open = event.target.closest('.contactpanda-open-inline-search');
		if (open) { var openModal = panel.closest('.contactpanda-search-modal'); if (openModal) openModal.hidden = false; panel.hidden = false; panel.classList.add('is-open'); if (!state.rules.length) { state.rules.push({field: '', operator: '', value: ''}); renderRules(); } return; }
		if (event.target.closest('.contactpanda-close-inline-search')) { var modal = panel.closest('.contactpanda-search-modal'); if (modal) modal.hidden = true; else { panel.hidden = true; panel.classList.remove('is-open'); } return; }
		if (event.target.closest('.contactpanda-add-inline-rule')) { state.rules.push({field: '', operator: '', value: ''}); renderRules(); return; }
		var remove = event.target.closest('.contactpanda-remove-inline-rule');
		if (remove) { state.rules.splice(Number(remove.closest('.contactpanda-inline-rule').dataset.index), 1); renderRules(); return; }
		if (event.target.closest('.contactpanda-clear-inline-search')) {
			var clearUrl = new URL(window.location.href); clearUrl.searchParams.delete('contactpanda_filter'); clearUrl.searchParams.delete('segment'); clearUrl.searchParams.delete('contactpanda_page'); window.location.href = clearUrl.toString(); return;
		}
		if (event.target.closest('.contactpanda-save-inline-search')) { panel.querySelector('.contactpanda-inline-save').hidden = false; panel.querySelector('.contactpanda-inline-save-name').focus(); return; }
		if (event.target.closest('.contactpanda-confirm-inline-save')) saveSearch();
		var pickerToggle = event.target.closest('.contactpanda-option-picker-toggle');
		if (pickerToggle) {
			var pickerPanel = pickerToggle.closest('.contactpanda-option-picker').querySelector('.contactpanda-option-picker-panel');
			pickerPanel.hidden = !pickerPanel.hidden;
			if (!pickerPanel.hidden) pickerPanel.querySelector('.contactpanda-option-picker-search').focus();
			return;
		}
		var pickerResult = event.target.closest('.contactpanda-option-picker-result');
		if (pickerResult) {
			var pickerRow = pickerResult.closest('.contactpanda-inline-rule');
			state.rules[Number(pickerRow.dataset.index)].value = pickerResult.dataset.value;
			renderRules();
			return;
		}
	});

	panel.addEventListener('change', function (event) {
		var row = event.target.closest('.contactpanda-inline-rule');
		if (row) {
			var index = Number(row.dataset.index); var rule = state.rules[index];
			if (event.target.classList.contains('contactpanda-inline-field')) {
				rule.field = event.target.value; var field = fieldMap[rule.field]; rule.operator = field && field.operators.length ? field.operators[0] : ''; rule.value = ''; renderRules(); return;
			}
			if (event.target.classList.contains('contactpanda-inline-operator')) { rule.operator = event.target.value; if (['empty', 'not_empty'].indexOf(rule.operator) !== -1) rule.value = ''; renderRules(); return; }
			if (event.target.classList.contains('contactpanda-inline-value')) { rule.value = event.target.value; scheduleCount(); }
		}
		if (event.target.name === 'contactpanda_inline_relation') { state.relation = event.target.value === 'or' ? 'or' : 'and'; scheduleCount(); }
	});
	panel.addEventListener('input', function (event) {
		if (event.target.classList.contains('contactpanda-option-picker-search')) {
			var searchRow = event.target.closest('.contactpanda-inline-rule');
			var searchRule = state.rules[Number(searchRow.dataset.index)];
			var searchField = fieldMap[searchRule.field];
			searchRow.querySelector('.contactpanda-option-picker-results').innerHTML = pickerOptions(searchField, searchRule.value, event.target.value);
			return;
		}
		if (!event.target.classList.contains('contactpanda-inline-value')) return;
		var row = event.target.closest('.contactpanda-inline-rule'); state.rules[Number(row.dataset.index)].value = event.target.value; scheduleCount();
	});

	var savedSelect = document.getElementById('contactpanda-segment-filter');
	if (savedSelect) savedSelect.addEventListener('change', function () {
		var url = new URL(window.location.href); url.searchParams.delete('contactpanda_filter'); url.searchParams.delete('contactpanda_page');
		if (savedSelect.value !== '0') url.searchParams.set('segment', savedSelect.value); else url.searchParams.delete('segment'); window.location.href = url.toString();
	});

	function saveSearch() {
		var complete = completeState(); var name = panel.querySelector('.contactpanda-inline-save-name').value.trim();
		if (!complete || !name) { statusElement.textContent = tr('enterName', 'Please enter a name for this search.'); return; }
		var data = new FormData(); data.set('action', 'contactpanda_save_inline_search'); data.set('nonce', config.nonce); data.set('name', name); data.set('state', JSON.stringify(complete));
		statusElement.textContent = tr('saving', 'Saving search…');
		fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data})
			.then(function (response) { return response.text().then(function (body) { try { return JSON.parse(body); } catch (error) { throw new Error(tr('countFailed', 'Saving failed.')); } }); }).then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : tr('countFailed', 'Saving failed.'));
				statusElement.textContent = result.data.message || tr('saved', 'The search was saved.');
				if (savedSelect) { var option = document.createElement('option'); option.value = result.data.id; option.textContent = result.data.name; savedSelect.appendChild(option); }
				var campaignSelect = document.querySelector('#contactpanda-campaign-form select[name="segment_id"]');
				if (config.campaignMode && campaignSelect) { var campaignOption = document.createElement('option'); campaignOption.value = result.data.id; campaignOption.textContent = result.data.name; campaignOption.selected = true; campaignSelect.appendChild(campaignOption); var modal = panel.closest('.contactpanda-search-modal'); if (modal) modal.hidden = true; }
				panel.querySelector('.contactpanda-inline-save').hidden = true;
			}).catch(function (error) { statusElement.textContent = error.message; });
	}

	if (state.rules.length) { panel.hidden = false; panel.classList.add('is-open'); }
	renderRules();
}());
