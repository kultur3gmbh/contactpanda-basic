(function () {
	'use strict';
	var config = window.CONTACTPANDA_SEGMENTS || {};
	var builder = document.querySelector('.contactpanda-segment-builder');
	if (!builder) return;
	var rules = builder.querySelector('.contactpanda-segment-rules');
	var template = builder.querySelector('template.contactpanda-segment-rule-template');
	if (!rules || !template) return;

	function escapeHtml(value) {
		return String(value).replace(/[&<>"]/g, function (character) {
			return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[character];
		});
	}

	function valueControl(type, index, value) {
		var optionGroup = (config.typeOptions || {})[type];
		if (optionGroup) {
			var html = '<select required name="rules[' + index + '][value]"><option value="">' + escapeHtml(config.choose || 'Please select') + '</option>';
			(optionGroup || []).forEach(function (option) {
				html += '<option value="' + escapeHtml(option.value) + '"' + (String(option.value) === String(value) ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
			});
			return html + '</select>';
		}
		if (String(type).indexOf('company_') === 0) {
			return '<input required type="text" name="rules[' + index + '][value]" value="' + escapeHtml(value || '') + '" placeholder="' + escapeHtml((config.valueLabels || {})[type] || config.value || 'Value') + '">';
		}
		var step = type === 'order_total_min' ? '0.01' : '1';
		var placeholder = (config.valueLabels || {})[type] || (config.number || 'Value');
		return '<input required type="number" min="0" step="' + step + '" name="rules[' + index + '][value]" value="' + escapeHtml(value || '') + '" placeholder="' + escapeHtml(placeholder) + '">';
	}

	function reindex() {
		rules.querySelectorAll('.contactpanda-segment-rule').forEach(function (rule, index) {
			rule.dataset.index = index;
			rule.querySelectorAll('[name]').forEach(function (field) {
				field.name = field.name.replace(/rules\[\d+\]/, 'rules[' + index + ']');
			});
			rule.querySelector('.contactpanda-segment-rule-number').textContent = String(index + 1);
		});
	}

	function updateValue(rule) {
		var type = rule.querySelector('.contactpanda-segment-type').value;
		var index = Number(rule.dataset.index || 0);
		rule.querySelector('.contactpanda-segment-value').innerHTML = valueControl(type, index, '');
	}

	builder.addEventListener('change', function (event) {
		if (event.target.classList.contains('contactpanda-segment-type')) updateValue(event.target.closest('.contactpanda-segment-rule'));
	});

	builder.addEventListener('click', function (event) {
		var add = event.target.closest('.contactpanda-add-segment-rule');
		if (add) {
			var fragment = template.content.cloneNode(true);
			rules.appendChild(fragment);
			reindex();
			return;
		}
		var remove = event.target.closest('.contactpanda-remove-segment-rule');
		if (remove) {
			var current = rules.querySelectorAll('.contactpanda-segment-rule');
			if (current.length > 1) remove.closest('.contactpanda-segment-rule').remove();
			else {
				var select = current[0].querySelector('.contactpanda-segment-type');
				select.value = '';
				updateValue(current[0]);
			}
			reindex();
		}
	});

	reindex();
}());
