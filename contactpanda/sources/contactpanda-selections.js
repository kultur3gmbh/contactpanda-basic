(function () {
	'use strict';
	var c = window.CP_SELECTIONS;
	if (!c) return;
	// wp_localize_script serializes top-level scalars as strings: '0' is truthy.
	var current = Number(c.current);
	var labels = c.i18n,
		key = 'cp-selection:' + c.user + ':' + c.source,
		basket = [],
		names = {},
		pending = false,
		savedId = 0;
	function store() {
		try {
			sessionStorage.setItem(
				key,
				JSON.stringify({
					ids: basket,
					names: names,
					pending: pending,
					savedId: savedId,
					query: query(),
				}),
			);
		} catch (_) {}
	}
	function query() {
		var params = new URLSearchParams(location.search);
		[
			'contactpanda_page',
			'orderby',
			'order',
			'list_state',
			'cp_add_results',
			'cp_merge_complete',
		].forEach(function (key) {
			params.delete(key);
		});
		params.sort();
		return params.toString();
	}
	function ids(values) {
		return Array.from(
			new Set(
				values.map(String).filter(function (v) {
					return /^[1-9][0-9]*$/.test(v);
				}),
			),
		).slice(0, 10000);
	}
	function boxes() {
		return Array.from(document.querySelectorAll('.contactpanda-person-select'));
	}
	function collect() {
		var selected = new Set(basket);
		boxes().forEach(function (box) {
			if (box.checked) {
				selected.add(box.value);
				if (box.dataset.contactName) names[box.value] = box.dataset.contactName;
			} else {
				selected.delete(box.value);
				delete names[box.value];
			}
		});
		basket = ids(Array.from(selected));
		store();
	}
	function refresh() {
		var action = document.getElementById('contactpanda-bulk-action');
		if (action) action.dispatchEvent(new Event('change', { bubbles: true }));
	}
	function request(operation, values) {
		var data = new URLSearchParams({
			action: 'contactpanda_selection',
			nonce: c.nonce,
			cp_selection_operation: operation,
		});
		Object.keys(values || {}).forEach(function (k) {
			if (Array.isArray(values[k]))
				values[k].forEach(function (v) {
					data.append(k + '[]', v);
				});
			else data.set(k, values[k]);
		});
		return fetch(c.ajax, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) {
				return r.json();
			})
			.then(function (r) {
				if (!r.success) throw new Error((r.data && r.data.message) || labels.error);
				return r.data;
			});
	}
	function nameDialog(value, done) {
		var dialog = document.createElement('dialog');
		dialog.className = 'cp-selection-dialog';
		var form = document.createElement('form');
		form.method = 'dialog';
		var label = document.createElement('label');
		label.textContent = labels.name;
		var input = document.createElement('input');
		input.type = 'text';
		input.required = true;
		input.maxLength = 200;
		input.value = value || '';
		label.appendChild(input);
		form.appendChild(label);
		var error = document.createElement('p');
		error.setAttribute('role', 'alert');
		form.appendChild(error);
		var save = document.createElement('button');
		save.className = 'button button-primary';
		save.innerHTML = '<span class="dashicons dashicons-yes" aria-hidden="true"></span>';
		save.appendChild(document.createTextNode(labels.save));
		form.appendChild(save);
		var cancel = document.createElement('button');
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
		cancel.appendChild(document.createTextNode(labels.cancel));
		cancel.onclick = function () {
			dialog.close();
		};
		form.appendChild(cancel);
		form.onsubmit = function (e) {
			e.preventDefault();
			save.disabled = true;
			done(input.value)
				.then(function () {
					dialog.close();
				})
				.catch(function (e) {
					error.textContent = e.message;
					save.disabled = false;
				});
		};
		dialog.appendChild(form);
		document.body.appendChild(dialog);
		dialog.addEventListener('close', function () {
			dialog.remove();
		});
		dialog.showModal();
		input.focus();
	}
	window.ContactPandaSelections = {
		ids: function () {
			return basket.slice();
		},
		run: function (action) {
			collect();
			if (action === 'selection_remove') {
				var removed = boxes()
					.filter(function (b) {
						return b.checked;
					})
					.map(function (b) {
						return b.value;
					});
				basket = basket.filter(function (id) {
					return removed.indexOf(id) === -1;
				});
				boxes().forEach(function (b) {
					if (removed.indexOf(b.value) !== -1) b.checked = false;
				});
				store();
				refresh();
				return;
			}
			if (action === 'selection_save') {
				var saved = c.rows.find(function (row) {
					return Number(row.id) === savedId;
				});
				return nameDialog(saved ? saved.name : '', function (name) {
					return request('save', { id: savedId, name: name, ids: basket }).then(function (row) {
						var destination = new URL(location.href);
						destination.search = '';
						destination.searchParams.set('page', 'contactpanda-people');
						destination.searchParams.set('selection', row.id);
						location.href = destination.href;
					});
				});
			}
			if (action === 'merge') {
				var form = document.createElement('form');
				form.method = 'post';
				form.action = c.post;
				var toolbar = document.querySelector('.contactpanda-bulk-toolbar');
				var values = {
					action: 'contactpanda_merge_preview',
					_wpnonce: c.mergeNonce,
					entity: 'contacts',
					merge_origin: 'people',
					list_token: toolbar ? toolbar.dataset.listToken || '' : '',
				};
				Object.keys(values).forEach(function (k) {
					var f = document.createElement('input');
					f.type = 'hidden';
					f.name = k;
					f.value = values[k];
					form.appendChild(f);
				});
				basket.forEach(function (id) {
					var f = document.createElement('input');
					f.type = 'hidden';
					f.name = 'ids[]';
					f.value = id;
					form.appendChild(f);
				});
				document.body.appendChild(form);
				form.submit();
			}
		},
	};
	var add = document.querySelector('.cp-selection-add');
	if (document.querySelector('.contactpanda-bulk-toolbar') || add) {
		var url = new URL(location.href);
		var replay = false;
		try {
			var previous = JSON.parse(sessionStorage.getItem(key) || '{}');
			replay = !!previous.pending && url.searchParams.has('cp_add_results');
			if (
				!url.searchParams.has('cp_merge_complete') &&
				(replay || (previous.query || '').replace(/^\?/, '') === query())
			) {
				basket = ids(previous.ids || []);
				names = previous.names || {};
				savedId = Number(previous.savedId) || 0;
			}
		} catch (_) {}
		var retained = document.querySelector('.cp-selection-retained');
		var labelRequests = new Set();
		var retainedLimit = 25;

		function renderRetained() {
			if (!retained) return;
			var visible = new Set(
				boxes().map(function (box) {
					return box.value;
				}),
			);
			var outside = basket.filter(function (id) {
				return !visible.has(id);
			});
			var shown = outside.slice(0, retainedLimit);
			retained.hidden = !outside.length;
			retained.querySelector('[data-cp-retained-count]').textContent = '(' + outside.length + ')';
			var list = retained.querySelector('ul');
			list.replaceChildren();
			shown.forEach(function (id) {
				var item = document.createElement('li');
				item.textContent = names[id] || labels.unnamed || 'Unnamed person';
				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'button panda-button-compact';
				remove.setAttribute(
					'aria-label',
					(labels.remove || 'Remove from selection') + ' ' + item.textContent,
				);
				remove.title = labels.remove || 'Remove from selection';
				remove.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
				remove.onclick = function () {
					basket = basket.filter(function (itemId) {
						return itemId !== id;
					});
					delete names[id];
					applyBasket();
					var next = list.querySelector('button');
					if (next) next.focus();
					else if (add) add.focus();
				};
				item.appendChild(remove);
				list.appendChild(item);
			});
			retained.querySelector('[data-cp-retained-more]').hidden = shown.length >= outside.length;
			var missing = shown
				.filter(function (id) {
					return !names[id] && !labelRequests.has(id);
				})
				.slice(0, 50);
			if (missing.length) {
				missing.forEach(function (id) {
					labelRequests.add(id);
				});
				request('people_labels', { ids: missing })
					.then(function (labelsById) {
						missing.forEach(function (id) {
							if (basket.indexOf(id) !== -1 && labelsById[id]) names[id] = labelsById[id];
						});
						store();
						renderRetained();
					})
					.catch(function () {});
			}
		}

		function applyBasket() {
			boxes().forEach(function (box) {
				box.checked = basket.indexOf(box.value) !== -1;
				if (box.checked && box.dataset.contactName) names[box.value] = box.dataset.contactName;
			});
			store();
			renderRetained();
			refresh();
		}

		if (retained)
			retained.querySelector('[data-cp-retained-more]').onclick = function () {
				retainedLimit += 25;
				renderRetained();
			};
		var status = document.querySelector('.cp-selection-result');
		function appendResults() {
			add.disabled = true;
			request('search_results', { list_token: add.dataset.listToken })
				.then(function (row) {
					var before = basket.length;
					var membership = new Set(basket);
					var incoming = row.ids.map(String).filter(function (id) {
						return !membership.has(id);
					});
					basket = ids(basket.concat(incoming));
					status.textContent = labels.added.replace('%d', basket.length - before);
					if (basket.length - before < incoming.length || row.total > row.ids.length)
						status.textContent += ' ' + labels.capacity;
					applyBasket();
				})
				.catch(function (error) {
					status.textContent = error.message;
				})
				.finally(function () {
					add.disabled = false;
				});
		}
		if (
			current > 0 &&
			!replay &&
			current !== savedId &&
			!url.searchParams.has('cp_merge_complete')
		) {
			if (add) add.disabled = true;
			request('load', { id: current })
				.then(function (row) {
					basket = ids(row.ids);
					savedId = current;
					applyBasket();
				})
				.catch(function (error) {
					if (status) status.textContent = error.message;
				})
				.finally(function () {
					if (add) add.disabled = false;
				});
		}
		if (add) {
			add.onclick = function () {
				collect();
				var form = add.closest('form');
				var input = form.querySelector('[name="s"]');
				if (
					!form.querySelector('[name="selection"]') &&
					input.value.trim() === (url.searchParams.get('s') || '').trim()
				) {
					appendResults();
					return;
				}
				var reference = form.querySelector('[name="selection"]');
				if (reference && !input.value.trim()) {
					input.focus();
					return;
				}
				if (reference) reference.remove();
				var marker = document.createElement('input');
				marker.type = 'hidden';
				marker.name = 'cp_add_results';
				marker.value = '1';
				form.appendChild(marker);
				pending = true;
				store();
				form.requestSubmit();
			};
			if (replay) {
				url.searchParams.delete('cp_add_results');
				history.replaceState(history.state, '', url);
				appendResults();
			}
		}
		document.addEventListener('change', function (e) {
			if (e.target.matches('.contactpanda-person-select,.contactpanda-select-all')) {
				collect();
				renderRetained();
				refresh();
			}
		});
		document.querySelectorAll('form.contactpanda-search').forEach(function (form) {
			form.addEventListener('submit', function () {
				if (!pending) {
					basket = [];
					names = {};
					savedId = 0;
					store();
				}
			});
		});
		if (url.searchParams.has('cp_merge_complete')) {
			url.searchParams.delete('cp_merge_complete');
			history.replaceState(history.state, '', url);
		}
		applyBasket();
	}
	function chooser(target, newsletter) {
		if (!target || !c.rows.length) return;
		var select = document.createElement('select');
		select.setAttribute('aria-label', labels.load);
		var empty = document.createElement('option');
		empty.value = '';
		empty.textContent = labels.load;
		select.appendChild(empty);
		c.rows.forEach(function (row) {
			var o = document.createElement('option');
			o.value = row.id;
			o.textContent = row.name + ' (' + row.count + ')';
			select.appendChild(o);
		});
		select.onchange = function () {
			if (!select.value) return;
			if (newsletter) {
				request('load', { id: select.value })
					.then(function (row) {
						var form = document.getElementById('contactpanda-campaign-form'),
							radio = form.querySelector('[name="audience_source"][value="manual"]'),
							input = form.querySelector('[name="manual_contact_ids"]');
						input.value = row.ids.join(',');
						radio.closest('label').hidden = false;
						form.querySelector('.cp-manual-selection-label').textContent =
							row.name + ' (' + row.ids.length + ')';
						var results = form.querySelector('.contactpanda-customer-search-results');
						if (results) results.dataset.manualCount = String(row.ids.length);
						radio.checked = true;
						radio.dispatchEvent(new Event('change', { bubbles: true }));
					})
					.catch(function (e) {
						window.alert(e.message);
					});
				return;
			}
			var u = new URL(location.href);
			u.searchParams.set('selection', select.value);
			['s', 'segment', 'contactpanda_filter', 'contactpanda_page', 'campaign'].forEach(
				function (k) {
					u.searchParams.delete(k);
				},
			);
			if (newsletter) {
				u.searchParams.set('new', '1');
				u.searchParams.delete('contact_ids');
				u.searchParams.delete('people_selection');
			}
			location.href = u.toString();
		};
		target.appendChild(select);
	}
	chooser(document.querySelector('.contactpanda-people-search-row'), false);
	var audience = document.querySelector('.contactpanda-audience-source');
	if (audience) {
		var saved = document.createElement('div');
		saved.className = 'cp-saved-person-selection';
		audience.before(saved);
		chooser(saved, true);
	}
	document.addEventListener('click', function (e) {
		var button = e.target.closest('.cp-selection-rename,.cp-selection-delete');
		if (!button) return;
		var row = button.closest('[data-cp-selection]'),
			id = row.dataset.cpSelection;
		if (button.classList.contains('cp-selection-delete')) {
			if (window.confirm(labels.delete))
				request('delete', { id: id, confirmed: '1' })
					.then(function () {
						row.remove();
					})
					.catch(function (e) {
						window.alert(e.message);
					});
		} else
			nameDialog(row.querySelector('.cp-selection-name').textContent, function (name) {
				return request('rename', { id: id, name: name }).then(function () {
					row.querySelector('.cp-selection-name').textContent = name;
				});
			});
	});
})();
